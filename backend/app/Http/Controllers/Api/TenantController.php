<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDocument;
use App\Support\Storage\S3ClientFactory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $tenants = QueryBuilder::for(Tenant::class)
            ->allowedFilters([
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('status'),
            ])
            ->allowedSorts(['first_name', 'created_at', 'email'])
            ->allowedIncludes(['leases', 'leases.unit', 'user'])
            ->paginate($request->get('per_page', 15));

        return response()->json($tenants);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:30',
            'company_name' => 'nullable|string|max:255',
            'id_number' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,blacklisted,prospect,former',
            'notes' => 'nullable|string',
        ]);
        $data['company_name'] = $data['company_name'] ?? $data['company'] ?? null;
        $data['id_number'] = $data['id_number'] ?? $data['tax_id'] ?? null;
        $data['status'] = $data['status'] ?? 'active';
        if ($data['status'] === 'prospect' || $data['status'] === 'former') {
            $data['status'] = 'inactive';
        }
        $tenant = Tenant::create($data);

        return response()->json($tenant, 201);
    }

    public function show(Tenant $tenant)
    {
        return response()->json($tenant->load(['leases.unit.building', 'leases.invoices', 'user']));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $tenant->update($request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:tenants,email,'.$tenant->id,
            'phone' => 'nullable|string',
            'status' => 'sometimes|in:active,inactive,blacklisted',
            'notes' => 'nullable|string',
        ]));

        return response()->json($tenant->fresh());
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return response()->json(null, 204);
    }

    public function leases(Tenant $tenant)
    {
        $leases = $tenant->leases()->with(['unit.building.property', 'invoices'])->get();

        return response()->json($leases);
    }

    public function documents(Request $request, Tenant $tenant)
    {
        $query = $tenant->documents()->getQuery();

        // Newest first unless the caller asks for something else, so the
        // relation itself stays ordering-agnostic.
        if (! $request->has('sort')) {
            $query->orderByDesc('created_at');
        }

        $documents = QueryBuilder::for($query)
            ->allowedFilters([
                AllowedFilter::exact('category'),
                AllowedFilter::partial('name'),
            ])
            ->allowedSorts(['name', 'size_kb', 'created_at', 'category'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return response()->json($documents);
    }

    /**
     * Upload a tenant document to object storage (Floci in dev/CI, S3 in prod).
     */
    public function storeDocument(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,zip', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(TenantDocument::CATEGORIES)],
        ]);

        $file = $request->file('file');

        // Guarantee the bucket exists so a first upload never fails on a fresh
        // environment. Idempotent, and a no-op against real AWS.
        try {
            S3ClientFactory::ensureBucket();
        } catch (Throwable) {
            // Storage unreachable; the write below reports the real outcome.
        }

        $path = $file->store('tenants/'.$tenant->id, 'documents');

        if ($path === false) {
            return response()->json([
                'message' => 'Could not store the document; nothing was attached to the tenant.',
                'storage_endpoint' => S3ClientFactory::endpoint(),
            ], 500);
        }

        $document = $tenant->documents()->create([
            'name' => $data['name'] ?? $file->getClientOriginalName(),
            'category' => $data['category'] ?? 'other',
            'disk' => 'documents',
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_kb' => max(1, (int) round($file->getSize() / 1024)),
            'uploaded_by' => $request->user()?->id,
        ]);

        return response()->json($document, 201);
    }

    /**
     * Stream an object back through the API. Used when the disk cannot presign
     * a URL; see TenantDocument::getUrlAttribute().
     */
    public function downloadDocument(Tenant $tenant, TenantDocument $document)
    {
        abort_unless($document->tenant_id === $tenant->id, 404);

        if (! Storage::disk($document->disk)->exists($document->path)) {
            return response()->json([
                'message' => 'The stored file is no longer available in object storage.',
            ], 404);
        }

        return Storage::disk($document->disk)->download($document->path, $document->name);
    }

    public function destroyDocument(Tenant $tenant, TenantDocument $document)
    {
        abort_unless($document->tenant_id === $tenant->id, 404);

        // Remove the row first: a leaked object is recoverable, an orphaned row
        // pointing at a missing object is not.
        $document->delete();

        try {
            Storage::disk($document->disk)->delete($document->path);
        } catch (Throwable) {
            // Storage unreachable — the metadata row is already gone.
        }

        return response()->json(null, 204);
    }
}

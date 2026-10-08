<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LeaseResource;
use App\Http\Resources\TenantResource;
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
            ->withCount('leases')
            ->allowedFilters([
                AllowedFilter::partial('first_name'),
                AllowedFilter::partial('last_name'),
                AllowedFilter::partial('email'),
                AllowedFilter::exact('status'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->search($value);
                }),
            ])
            ->allowedSorts(['first_name', 'last_name', 'created_at', 'email'])
            ->allowedIncludes(['leases', 'leases.unit', 'user'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return TenantResource::collection($tenants);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'nullable|string|unique:tenants,code',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'required|email|unique:tenants,email',
            'phone' => 'nullable|string|max:30',
            'company' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'date_of_birth' => 'nullable|date',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'status' => 'sometimes|in:active,inactive,prospect,former',
            'notes' => 'nullable|string',
        ]);

        $tenant = Tenant::create([
            'code' => $data['code'] ?? null,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company'] ?? null,
            'tax_id' => $data['tax_id'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'address' => $data['address'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            'status' => $data['status'] ?? 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return new TenantResource($tenant->loadCount('leases'));
    }

    public function show(Tenant $tenant)
    {
        return new TenantResource(
            $tenant->load(['leases.unit.building', 'leases.invoices', 'user'])->loadCount('leases')
        );
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'code' => 'sometimes|string|unique:tenants,code,'.$tenant->id,
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'sometimes|email|unique:tenants,email,'.$tenant->id,
            'phone' => 'nullable|string',
            'company' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'emergency_contact_name' => 'nullable|string|max:150',
            'emergency_contact_phone' => 'nullable|string|max:30',
            'status' => 'sometimes|in:active,inactive,prospect,former',
            'notes' => 'nullable|string',
        ]);

        $columns = [
            'code' => 'code',
            'first_name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'company' => 'company_name',
            'tax_id' => 'tax_id',
            'address' => 'address',
            'emergency_contact_name' => 'emergency_contact_name',
            'emergency_contact_phone' => 'emergency_contact_phone',
            'status' => 'status',
            'notes' => 'notes',
        ];

        $tenant->fill(array_combine(
            array_map(fn ($key) => $columns[$key], array_keys($data)),
            array_values($data)
        ))->save();

        return new TenantResource($tenant->fresh()->loadCount('leases'));
    }

    public function destroy(Tenant $tenant)
    {
        $tenant->delete();

        return response()->json(null, 204);
    }

    public function leases(Tenant $tenant)
    {
        $leases = $tenant->leases()->with(['unit.building.property', 'invoices'])->get();

        return LeaseResource::collection($leases);
    }

    public function documents(Request $request, Tenant $tenant)
    {
        $query = $tenant->documents()->getQuery();



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


    public function storeDocument(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx,csv,txt,zip', 'max:10240'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', Rule::in(TenantDocument::CATEGORIES)],
        ]);

        $file = $request->file('file');



        try {
            S3ClientFactory::ensureBucket();
        } catch (Throwable) {

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



        $document->delete();

        try {
            Storage::disk($document->disk)->delete($document->path);
        } catch (Throwable) {

        }

        return response()->json(null, 204);
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

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

    public function documents(Tenant $tenant)
    {
        $documents = [
            ['id' => 1, 'type' => 'id_document', 'name' => 'ID Document', 'tenant_id' => $tenant->id],
            ['id' => 2, 'type' => 'lease_agreement', 'name' => 'Lease Agreement', 'tenant_id' => $tenant->id],
            ['id' => 3, 'type' => 'payment_receipt', 'name' => 'Payment Receipt', 'tenant_id' => $tenant->id],
        ];

        return response()->json($documents);
    }
}

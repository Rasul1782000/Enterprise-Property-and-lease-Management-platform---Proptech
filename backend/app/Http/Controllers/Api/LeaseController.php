<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Http\Resources\LeaseResource;
use App\Models\Lease;
use App\Support\Storage\S3ClientFactory;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Throwable;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $leases = QueryBuilder::for(Lease::class)
            ->with(['unit.building.property', 'tenant', 'invoices'])
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('tenant_id'),
                AllowedFilter::exact('unit_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::partial('lease_number'),
                AllowedFilter::partial('code', 'lease_number'),
                AllowedFilter::callback('expiring_within', function ($query, $value) {
                    $query->active()->expiringSoon((int) $value);
                }),
                AllowedFilter::callback('property_id', function ($query, $value) {
                    $query->whereHas('unit.building', fn ($qq) => $qq->where('property_id', $value));
                }),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(fn ($qq) => $qq->where('lease_number', 'like', "%{$value}%")
                        ->orWhereHas('tenant', fn ($tt) => $tt
                            ->where('first_name', 'like', "%{$value}%")
                            ->orWhere('last_name', 'like', "%{$value}%")));
                }),
            ])
            ->allowedSorts(['lease_number', 'start_date', 'end_date', 'rent_amount', 'created_at'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return LeaseResource::collection($leases);
    }

    public function store(StoreLeaseRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'active';
        $data['payment_frequency'] = str_replace(
            'annually',
            'yearly',
            $data['payment_frequency'] ?? 'monthly'
        );
        $lease = Lease::create($data);

        return new LeaseResource($lease->load(['unit.building.property', 'tenant']));
    }

    public function show(Lease $lease)
    {
        return new LeaseResource(
            $lease->load(['unit.building.property', 'tenant', 'invoices.payments'])
        );
    }

    public function update(Request $request, Lease $lease)
    {
        $lease->update($request->validate([
            'end_date' => 'sometimes|date|after:start_date',
            'rent_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,active,expired,terminated,renewed',
            'escalation_clause' => 'nullable|string',
            'renewal_options' => 'sometimes|integer|min:0',
            'notes' => 'nullable|string',
        ]));

        return new LeaseResource($lease->fresh(['unit.building.property', 'tenant']));
    }

    public function destroy(Lease $lease)
    {
        $lease->delete();

        return response()->json(null, 204);
    }

    public function pdf(Lease $lease)
    {
        $lease->load(['unit.building.property', 'tenant']);
        $pdf = Pdf::loadView('pdfs.lease-agreement', compact('lease'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Lease-'.$lease->lease_number.'.pdf');
    }

    public function terminate(Request $request, Lease $lease)
    {
        $lease->update([
            'status' => 'terminated',
            'terminated_at' => now(),
        ]);
        $lease->unit()->update(['status' => 'vacant']);

        return new LeaseResource($lease->fresh(['unit.building.property', 'tenant']));
    }

    public function renew(Request $request, Lease $lease)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'rent_amount' => 'required|numeric|min:0',
        ]);
        $newLease = Lease::create([
            'unit_id' => $lease->unit_id,
            'tenant_id' => $lease->tenant_id,
            'type' => $lease->type,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'rent_amount' => $data['rent_amount'],
            'deposit_amount' => $lease->deposit_amount,
            'status' => 'active',
            'created_by' => $request->user()?->id,
        ]);
        $lease->update(['status' => 'renewed']);

        return new LeaseResource($newLease->load(['unit.building.property', 'tenant']));
    }

    public function sign(Request $request, Lease $lease)
    {
        $lease->load(['unit.building.property', 'tenant']);

        $path = 'signed/lease_'.$lease->lease_number.'.pdf';

        try {
            S3ClientFactory::ensureBucket();
        } catch (Throwable) {
        }

        $pdf = Pdf::loadView('pdfs.lease-agreement', compact('lease'))
            ->setPaper('a4', 'portrait');

        try {
            $stored = Storage::disk('documents')->put($path, $pdf->output());
        } catch (Throwable) {
            $stored = false;
        }

        if (! $stored) {
            return response()->json([
                'message' => 'Could not store the signed document; the lease was left unchanged.',
                'storage_endpoint' => S3ClientFactory::endpoint(),
            ], 500);
        }

        $lease->update([
            'status' => 'active',
            'signed_at' => now(),
            'document_path' => $path,
        ]);

        return new LeaseResource($lease->fresh(['unit.building.property', 'tenant']));
    }

    public function document(Request $request, Lease $lease)
    {
        $lease->load(['unit.building.property', 'tenant']);
        $pdf = Pdf::loadView('pdfs.lease-agreement', compact('lease'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Lease-Document-'.$lease->lease_number.'.pdf');
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaseRequest;
use App\Models\Lease;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LeaseController extends Controller
{
    public function index(Request $request)
    {
        $leases = Lease::with(['unit.building.property', 'tenant', 'invoices'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->tenant_id, fn ($q, $id) => $q->where('tenant_id', $id))
            ->when($request->property_id, fn ($q, $pid) => $q->whereHas('unit.building', fn ($qq) => $qq->where('property_id', $pid)))
            ->when($request->search, fn ($q, $s) => $q->where('lease_number', 'like', "%{$s}%")
                ->orWhereHas('tenant', fn ($qq) => $qq->where('first_name', 'like', "%{$s}%")->orWhere('last_name', 'like', "%{$s}%")))
            ->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json($leases);
    }

    public function store(StoreLeaseRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'active';
        $data['payment_frequency'] = $data['payment_frequency'] ?? 'monthly';
        $data['payment_frequency'] = str_replace('annually', 'yearly', $data['payment_frequency']);
        $lease = Lease::create($data);

        return response()->json($lease->load(['unit', 'tenant']), 201);
    }

    public function show(Lease $lease)
    {
        return response()->json($lease->load(['unit.building.property', 'tenant', 'invoices.payments']));
    }

    public function update(Request $request, Lease $lease)
    {
        $lease->update($request->validate([
            'end_date' => 'sometimes|date|after:start_date',
            'rent_amount' => 'sometimes|numeric|min:0',
            'status' => 'sometimes|in:draft,active,expired,terminated,renewed',
            'notes' => 'nullable|string',
        ]));

        return response()->json($lease->fresh());
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
        $lease->update(['status' => 'terminated']);
        $lease->unit()->update(['status' => 'vacant']);

        return response()->json($lease->fresh());
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
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'rent_amount' => $data['rent_amount'],
            'deposit_amount' => $lease->deposit_amount,
            'status' => 'active',
            'created_by' => $request->user()->id,
        ]);
        $lease->update(['status' => 'renewed']);

        return response()->json($newLease, 201);
    }

    public function sign(Request $request, Lease $lease)
    {
        $lease->update([
            'status' => 'active',
            'document_path' => 'signed/lease_'.$lease->id.'_'.time().'.pdf',
        ]);

        return response()->json($lease->fresh());
    }

    public function document(Request $request, Lease $lease)
    {
        $template = $request->get('template', 'default');
        $lease->load(['unit.building.property', 'tenant']);
        $pdf = Pdf::loadView('pdfs.lease-agreement', compact('lease'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Lease-Document-'.$lease->lease_number.'.pdf');
    }
}

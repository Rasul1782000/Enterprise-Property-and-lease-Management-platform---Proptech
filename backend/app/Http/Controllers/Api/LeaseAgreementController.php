<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaseAgreement;
use Illuminate\Http\Request;

class LeaseAgreementController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaseAgreement::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('lease_code')) {
            $query->where('lease_code', $request->lease_code);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%")
                    ->orWhere('tenant_company', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('unit_code', 'like', "%{$search}%")
                    ->orWhere('ejari_number', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $agreements = $query->latest('generated_on')->paginate($perPage);

        return response()->json($agreements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:lease_agreements,code',
            'lease_code' => 'required|string',
            'property_name' => 'required|string',
            'unit_code' => 'required|string',
            'tenant_name' => 'required|string',
            'tenant_company' => 'required|string',
            'type' => 'required|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'rent_amount' => 'required|numeric',
            'security_deposit' => 'required|numeric',
            'payment_frequency' => 'required|string',
            'template' => 'required|string',
            'version' => 'nullable|integer',
            'generated_on' => 'required|date',
            'status' => 'nullable|in:draft,sent,partially_signed,executed,expired',
            'landlord_signed_on' => 'nullable|date',
            'tenant_signed_on' => 'nullable|date',
            'witness_name' => 'nullable|string',
            'ejari_number' => 'nullable|string|unique:lease_agreements,ejari_number',
            'document_size_kb' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $agreement = LeaseAgreement::create($validated);

        return response()->json($agreement, 201);
    }

    public function show(LeaseAgreement $leaseAgreement)
    {
        return response()->json($leaseAgreement);
    }

    public function update(Request $request, LeaseAgreement $leaseAgreement)
    {
        $validated = $request->validate([
            'lease_code' => 'sometimes|string',
            'property_name' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'tenant_name' => 'sometimes|string',
            'tenant_company' => 'sometimes|string',
            'type' => 'sometimes|string',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'rent_amount' => 'sometimes|numeric',
            'security_deposit' => 'sometimes|numeric',
            'payment_frequency' => 'sometimes|string',
            'template' => 'sometimes|string',
            'version' => 'sometimes|integer',
            'generated_on' => 'sometimes|date',
            'status' => 'sometimes|in:draft,sent,partially_signed,executed,expired',
            'landlord_signed_on' => 'nullable|date',
            'tenant_signed_on' => 'nullable|date',
            'witness_name' => 'nullable|string',
            'ejari_number' => 'nullable|string|unique:lease_agreements,ejari_number,' . $leaseAgreement->id,
            'document_size_kb' => 'sometimes|integer',
            'notes' => 'nullable|string',
        ]);

        $leaseAgreement->update($validated);

        return response()->json($leaseAgreement);
    }

    public function destroy(LeaseAgreement $leaseAgreement)
    {
        $leaseAgreement->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => LeaseAgreement::count(),
            'by_status' => LeaseAgreement::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'with_ejari' => LeaseAgreement::whereNotNull('ejari_number')->count(),
            'attention' => LeaseAgreement::whereIn('status', ['draft', 'sent', 'partially_signed'])->count(),
        ]);
    }

    public function send(LeaseAgreement $leaseAgreement)
    {
        $leaseAgreement->update(['status' => 'sent']);
        return response()->json($leaseAgreement);
    }

    public function signLandlord(Request $request, LeaseAgreement $leaseAgreement)
    {
        $validated = $request->validate([
            'signed_on' => 'required|date',
        ]);
        $leaseAgreement->update(['landlord_signed_on' => $validated['signed_on']]);
        $this->updateStatus($leaseAgreement);
        return response()->json($leaseAgreement);
    }

    public function signTenant(Request $request, LeaseAgreement $leaseAgreement)
    {
        $validated = $request->validate([
            'signed_on' => 'required|date',
        ]);
        $leaseAgreement->update(['tenant_signed_on' => $validated['signed_on']]);
        $this->updateStatus($leaseAgreement);
        return response()->json($leaseAgreement);
    }

    public function issueEjari(Request $request, LeaseAgreement $leaseAgreement)
    {
        $validated = $request->validate([
            'ejari_number' => 'required|string|unique:lease_agreements,ejari_number',
        ]);
        $leaseAgreement->update([
            'ejari_number' => $validated['ejari_number'],
            'status' => 'executed',
        ]);
        return response()->json($leaseAgreement);
    }

    private function updateStatus(LeaseAgreement $agreement)
    {
        if ($agreement->landlord_signed_on && $agreement->tenant_signed_on) {
            $agreement->update(['status' => 'executed']);
        } elseif ($agreement->landlord_signed_on || $agreement->tenant_signed_on) {
            $agreement->update(['status' => 'partially_signed']);
        }
    }
}
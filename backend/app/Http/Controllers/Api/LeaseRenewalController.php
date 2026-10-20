<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaseRenewal;
use Illuminate\Http\Request;

class LeaseRenewalController extends Controller
{
    public function index(Request $request)
    {
        $query = LeaseRenewal::query();

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
                    ->orWhere('unit_code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('expiring_soon')) {
            $query->where('status', '!=', 'accepted')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>=', now());
        }

        $perPage = $request->integer('per_page', 15);
        $renewals = $query->latest('expiry_date')->paginate($perPage);

        return response()->json($renewals);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:lease_renewals,code',
            'lease_code' => 'required|string',
            'property_name' => 'required|string',
            'unit_code' => 'required|string',
            'tenant_name' => 'required|string',
            'tenant_company' => 'required|string',
            'current_rent' => 'required|numeric',
            'proposed_rent' => 'required|numeric',
            'uplift_percent' => 'required|numeric',
            'expiry_date' => 'required|date',
            'days_to_expiry' => 'required|integer',
            'offer_sent_on' => 'nullable|date',
            'response_due_on' => 'required|date',
            'responded_on' => 'nullable|date',
            'status' => 'nullable|in:draft,sent,under_negotiation,accepted,declined,expired',
            'term_months' => 'required|integer',
            'new_start_date' => 'required|date',
            'new_end_date' => 'required|date',
            'discount_percent' => 'nullable|numeric',
            'owner_note' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $renewal = LeaseRenewal::create($validated);

        return response()->json($renewal, 201);
    }

    public function show(LeaseRenewal $leaseRenewal)
    {
        return response()->json($leaseRenewal);
    }

    public function update(Request $request, LeaseRenewal $leaseRenewal)
    {
        $validated = $request->validate([
            'lease_code' => 'sometimes|string',
            'property_name' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'tenant_name' => 'sometimes|string',
            'tenant_company' => 'sometimes|string',
            'current_rent' => 'sometimes|numeric',
            'proposed_rent' => 'sometimes|numeric',
            'uplift_percent' => 'sometimes|numeric',
            'expiry_date' => 'sometimes|date',
            'days_to_expiry' => 'sometimes|integer',
            'offer_sent_on' => 'nullable|date',
            'response_due_on' => 'sometimes|date',
            'responded_on' => 'nullable|date',
            'status' => 'sometimes|in:draft,sent,under_negotiation,accepted,declined,expired',
            'term_months' => 'sometimes|integer',
            'new_start_date' => 'sometimes|date',
            'new_end_date' => 'sometimes|date',
            'discount_percent' => 'sometimes|numeric',
            'owner_note' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $leaseRenewal->update($validated);

        return response()->json($leaseRenewal);
    }

    public function destroy(LeaseRenewal $leaseRenewal)
    {
        $leaseRenewal->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => LeaseRenewal::count(),
            'by_status' => LeaseRenewal::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'expiring_soon' => LeaseRenewal::where('status', '!=', 'accepted')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>=', now())
                ->count(),
            'attention' => LeaseRenewal::whereIn('status', ['draft', 'sent', 'under_negotiation'])->count(),
        ]);
    }

    public function sendOffer(Request $request, LeaseRenewal $leaseRenewal)
    {
        $validated = $request->validate([
            'offer_sent_on' => 'required|date',
            'response_due_on' => 'required|date',
        ]);
        $leaseRenewal->update([
            'status' => 'sent',
            'offer_sent_on' => $validated['offer_sent_on'],
            'response_due_on' => $validated['response_due_on'],
        ]);
        return response()->json($leaseRenewal);
    }

    public function negotiate(LeaseRenewal $leaseRenewal)
    {
        $leaseRenewal->update(['status' => 'under_negotiation']);
        return response()->json($leaseRenewal);
    }

    public function accept(Request $request, LeaseRenewal $leaseRenewal)
    {
        $validated = $request->validate([
            'responded_on' => 'required|date',
            'proposed_rent' => 'sometimes|numeric',
            'discount_percent' => 'sometimes|numeric',
        ]);
        $leaseRenewal->update([
            'status' => 'accepted',
            'responded_on' => $validated['responded_on'],
            'proposed_rent' => $validated['proposed_rent'] ?? $leaseRenewal->proposed_rent,
            'discount_percent' => $validated['discount_percent'] ?? $leaseRenewal->discount_percent,
        ]);
        return response()->json($leaseRenewal);
    }

    public function decline(Request $request, LeaseRenewal $leaseRenewal)
    {
        $validated = $request->validate([
            'responded_on' => 'required|date',
        ]);
        $leaseRenewal->update([
            'status' => 'declined',
            'responded_on' => $validated['responded_on'],
        ]);
        return response()->json($leaseRenewal);
    }
}
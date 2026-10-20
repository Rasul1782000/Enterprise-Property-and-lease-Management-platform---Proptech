<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EjariContract;
use Illuminate\Http\Request;

class EjariContractController extends Controller
{
    public function index(Request $request)
    {
        $query = EjariContract::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('ejari_type')) {
            $query->where('ejari_type', $request->ejari_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('ejari_number', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%")
                    ->orWhere('tenant_company', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('unit_code', 'like', "%{$search}%")
                    ->orWhere('lease_code', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $contracts = $query->latest()->paginate($perPage);

        return response()->json($contracts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:ejari_contracts,code',
            'ejari_number' => 'nullable|string|unique:ejari_contracts,ejari_number',
            'lease_code' => 'required|string',
            'property_name' => 'required|string',
            'unit_code' => 'required|string',
            'landlord_name' => 'required|string',
            'tenant_name' => 'required|string',
            'tenant_company' => 'required|string',
            'ejari_type' => 'required|string',
            'registration_date' => 'nullable|date',
            'certificate_issued_on' => 'nullable|date',
            'expiry_date' => 'required|date',
            'status' => 'nullable|in:draft,submitted,registered,rejected,cancelled',
            'registration_fee' => 'nullable|numeric',
            'penalty_amount' => 'nullable|numeric',
            'deed_reference' => 'nullable|string',
            'free_zone_property' => 'nullable|boolean',
            'attempts' => 'nullable|integer',
            'last_error' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $contract = EjariContract::create($validated);

        return response()->json($contract, 201);
    }

    public function show(EjariContract $ejariContract)
    {
        return response()->json($ejariContract);
    }

    public function update(Request $request, EjariContract $ejariContract)
    {
        $validated = $request->validate([
            'ejari_number' => 'nullable|string|unique:ejari_contracts,ejari_number,' . $ejariContract->id,
            'lease_code' => 'sometimes|string',
            'property_name' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'landlord_name' => 'sometimes|string',
            'tenant_name' => 'sometimes|string',
            'tenant_company' => 'sometimes|string',
            'ejari_type' => 'sometimes|string',
            'registration_date' => 'nullable|date',
            'certificate_issued_on' => 'nullable|date',
            'expiry_date' => 'sometimes|date',
            'status' => 'sometimes|in:draft,submitted,registered,rejected,cancelled',
            'registration_fee' => 'sometimes|numeric',
            'penalty_amount' => 'sometimes|numeric',
            'deed_reference' => 'nullable|string',
            'free_zone_property' => 'sometimes|boolean',
            'attempts' => 'sometimes|integer',
            'last_error' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $ejariContract->update($validated);

        return response()->json($ejariContract);
    }

    public function destroy(EjariContract $ejariContract)
    {
        $ejariContract->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => EjariContract::count(),
            'by_status' => EjariContract::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'by_type' => EjariContract::select('ejari_type', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('ejari_type')
                ->pluck('count', 'ejari_type')
                ->toArray(),
            'expiring_soon' => EjariContract::where('status', 'registered')
                ->where('expiry_date', '<=', now()->addDays(30))
                ->where('expiry_date', '>=', now())
                ->count(),
            'attention' => EjariContract::whereIn('status', ['draft', 'submitted', 'rejected'])->count(),
        ]);
    }

    public function submit(Request $request, EjariContract $ejariContract)
    {
        $ejariContract->update([
            'status' => 'submitted',
            'attempts' => $ejariContract->attempts + 1,
            'last_error' => null,
        ]);
        return response()->json($ejariContract);
    }

    public function register(Request $request, EjariContract $ejariContract)
    {
        $validated = $request->validate([
            'ejari_number' => 'required|string|unique:ejari_contracts,ejari_number',
            'certificate_issued_on' => 'required|date',
            'registration_date' => 'nullable|date',
            'registration_fee' => 'nullable|numeric',
        ]);

        $ejariContract->update([
            'status' => 'registered',
            'ejari_number' => $validated['ejari_number'],
            'certificate_issued_on' => $validated['certificate_issued_on'],
            'registration_date' => $validated['registration_date'] ?? now()->toDateString(),
            'registration_fee' => $validated['registration_fee'] ?? $ejariContract->registration_fee,
            'last_error' => null,
        ]);

        return response()->json($ejariContract);
    }

    public function reject(Request $request, EjariContract $ejariContract)
    {
        $validated = $request->validate([
            'last_error' => 'nullable|string',
        ]);

        $ejariContract->update([
            'status' => 'rejected',
            'last_error' => $validated['last_error'] ?? $ejariContract->last_error,
            'attempts' => $ejariContract->attempts + 1,
        ]);

        return response()->json($ejariContract);
    }

    public function resubmit(Request $request, EjariContract $ejariContract)
    {
        $ejariContract->update([
            'status' => 'submitted',
            'attempts' => $ejariContract->attempts + 1,
            'last_error' => null,
        ]);
        return response()->json($ejariContract);
    }

    public function cancel(EjariContract $ejariContract)
    {
        $ejariContract->update(['status' => 'cancelled']);
        return response()->json($ejariContract);
    }
}
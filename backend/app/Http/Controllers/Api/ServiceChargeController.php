<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCharge;
use Illuminate\Http\Request;

class ServiceChargeController extends Controller
{
    public function index(Request $request)
    {
        $query = ServiceCharge::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('period_year')) {
            $query->where('period_year', $request->period_year);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $charges = $query->with('property:id,name')->latest('period_year')->paginate($perPage);

        return response()->json($charges);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:service_charges,code',
            'property_id' => 'required|exists:properties,id',
            'property_name' => 'required|string',
            'period_year' => 'required|integer',
            'category' => 'required|string',
            'budgeted_amount' => 'required|numeric',
            'actual_amount' => 'required|numeric',
            'variance' => 'required|numeric',
            'total_area_sqft' => 'required|numeric',
            'rate_per_sqft' => 'required|numeric',
            'units_charged' => 'required|integer',
            'amount_collected' => 'nullable|numeric',
            'outstanding' => 'nullable|numeric',
            'issued_on' => 'required|date',
            'due_date' => 'required|date',
            'status' => 'nullable|in:draft,issued,partially_paid,paid,closed',
            'approved_by' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $charge = ServiceCharge::create($validated);

        return response()->json($charge->load('property:id,name'), 201);
    }

    public function show(ServiceCharge $serviceCharge)
    {
        return response()->json($serviceCharge->load('property:id,name'));
    }

    public function update(Request $request, ServiceCharge $serviceCharge)
    {
        $validated = $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'property_name' => 'sometimes|string',
            'period_year' => 'sometimes|integer',
            'category' => 'sometimes|string',
            'budgeted_amount' => 'sometimes|numeric',
            'actual_amount' => 'sometimes|numeric',
            'variance' => 'sometimes|numeric',
            'total_area_sqft' => 'sometimes|numeric',
            'rate_per_sqft' => 'sometimes|numeric',
            'units_charged' => 'sometimes|integer',
            'amount_collected' => 'sometimes|numeric',
            'outstanding' => 'sometimes|numeric',
            'issued_on' => 'sometimes|date',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|in:draft,issued,partially_paid,paid,closed',
            'approved_by' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $serviceCharge->update($validated);

        return response()->json($serviceCharge->load('property:id,name'));
    }

    public function destroy(ServiceCharge $serviceCharge)
    {
        $serviceCharge->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => ServiceCharge::count(),
            'by_status' => ServiceCharge::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'total_budgeted' => ServiceCharge::sum('budgeted_amount'),
            'total_collected' => ServiceCharge::sum('amount_collected'),
            'total_outstanding' => ServiceCharge::sum('outstanding'),
            'attention' => ServiceCharge::whereIn('status', ['issued', 'partially_paid'])->count(),
        ]);
    }

    public function issue(ServiceCharge $serviceCharge)
    {
        $serviceCharge->update(['status' => 'issued']);
        return response()->json($serviceCharge);
    }

    public function markPaid(ServiceCharge $serviceCharge)
    {
        $serviceCharge->update([
            'status' => 'paid',
            'amount_collected' => $serviceCharge->budgeted_amount,
            'outstanding' => 0,
        ]);
        return response()->json($serviceCharge);
    }
}
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FitOutRequest;
use Illuminate\Http\Request;

class FitOutRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = FitOutRequest::query();

        if ($request->filled('permit_status')) {
            $query->where('permit_status', $request->permit_status);
        }

        if ($request->filled('noc_status')) {
            $query->where('noc_status', $request->noc_status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%")
                    ->orWhere('tenant_company', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('unit_code', 'like', "%{$search}%")
                    ->orWhere('contractor_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $requests = $query->latest('requested_on')->paginate($perPage);

        return response()->json($requests);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:fit_out_requests,code',
            'property_name' => 'required|string',
            'unit_code' => 'required|string',
            'tenant_name' => 'required|string',
            'tenant_company' => 'required|string',
            'contractor_name' => 'required|string',
            'contractor_trn' => 'required|string',
            'scope' => 'required|string',
            'permit_status' => 'nullable|in:not_submitted,under_review,approved,rejected,expired',
            'noc_status' => 'nullable|in:pending,inspection_scheduled,noc_issued,noc_rejected',
            'deposit_amount' => 'required|numeric',
            'deposit_status' => 'required|string',
            'requested_on' => 'required|date',
            'expected_completion' => 'required|date',
            'inspection_date' => 'nullable|date',
            'progress_percent' => 'nullable|integer',
            'priority' => 'nullable|in:low,normal,high',
            'estimated_cost' => 'required|numeric',
            'owner_name' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $fitOutRequest = FitOutRequest::create($validated);

        return response()->json($fitOutRequest, 201);
    }

    public function show(FitOutRequest $fitOutRequest)
    {
        return response()->json($fitOutRequest);
    }

    public function update(Request $request, FitOutRequest $fitOutRequest)
    {
        $validated = $request->validate([
            'property_name' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'tenant_name' => 'sometimes|string',
            'tenant_company' => 'sometimes|string',
            'contractor_name' => 'sometimes|string',
            'contractor_trn' => 'sometimes|string',
            'scope' => 'sometimes|string',
            'permit_status' => 'sometimes|in:not_submitted,under_review,approved,rejected,expired',
            'noc_status' => 'sometimes|in:pending,inspection_scheduled,noc_issued,noc_rejected',
            'deposit_amount' => 'sometimes|numeric',
            'deposit_status' => 'sometimes|string',
            'requested_on' => 'sometimes|date',
            'expected_completion' => 'sometimes|date',
            'inspection_date' => 'nullable|date',
            'progress_percent' => 'sometimes|integer',
            'priority' => 'sometimes|in:low,normal,high',
            'estimated_cost' => 'sometimes|numeric',
            'owner_name' => 'sometimes|string',
            'notes' => 'nullable|string',
        ]);

        $fitOutRequest->update($validated);

        return response()->json($fitOutRequest);
    }

    public function destroy(FitOutRequest $fitOutRequest)
    {
        $fitOutRequest->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => FitOutRequest::count(),
            'by_permit_status' => FitOutRequest::select('permit_status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('permit_status')
                ->pluck('count', 'permit_status')
                ->toArray(),
            'by_noc_status' => FitOutRequest::select('noc_status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('noc_status')
                ->pluck('count', 'noc_status')
                ->toArray(),
            'by_priority' => FitOutRequest::select('priority', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'total_estimated_cost' => FitOutRequest::sum('estimated_cost'),
            'attention' => FitOutRequest::whereIn('permit_status', ['not_submitted', 'under_review'])
                ->orWhereIn('noc_status', ['pending', 'inspection_scheduled'])
                ->count(),
        ]);
    }

    public function submitPermit(FitOutRequest $fitOutRequest)
    {
        $fitOutRequest->update(['permit_status' => 'under_review']);
        return response()->json($fitOutRequest);
    }

    public function approvePermit(FitOutRequest $fitOutRequest)
    {
        $fitOutRequest->update(['permit_status' => 'approved']);
        return response()->json($fitOutRequest);
    }

    public function rejectPermit(Request $request, FitOutRequest $fitOutRequest)
    {
        $fitOutRequest->update(['permit_status' => 'rejected']);
        return response()->json($fitOutRequest);
    }

    public function scheduleInspection(Request $request, FitOutRequest $fitOutRequest)
    {
        $validated = $request->validate([
            'inspection_date' => 'required|date',
        ]);
        $fitOutRequest->update([
            'noc_status' => 'inspection_scheduled',
            'inspection_date' => $validated['inspection_date'],
        ]);
        return response()->json($fitOutRequest);
    }

    public function issueNoc(FitOutRequest $fitOutRequest)
    {
        $fitOutRequest->update(['noc_status' => 'noc_issued']);
        return response()->json($fitOutRequest);
    }

    public function updateProgress(Request $request, FitOutRequest $fitOutRequest)
    {
        $validated = $request->validate([
            'progress_percent' => 'required|integer|min:0|max:100',
        ]);
        $fitOutRequest->update(['progress_percent' => $validated['progress_percent']]);
        return response()->json($fitOutRequest);
    }
}
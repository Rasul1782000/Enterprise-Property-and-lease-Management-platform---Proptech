<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BouncedCheque;
use Illuminate\Http\Request;

class BouncedChequeController extends Controller
{
    public function index(Request $request)
    {
        $query = BouncedCheque::query();

        if ($request->filled('stage')) {
            $query->where('stage', $request->stage);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('cheque_no', 'like', "%{$search}%")
                    ->orWhere('tenant_name', 'like', "%{$search}%")
                    ->orWhere('tenant_company', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('lease_code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('overdue')) {
            $query->where('next_action_due', '<', now());
        }

        $perPage = $request->integer('per_page', 15);
        $cheques = $query->latest('bounced_on')->paginate($perPage);

        return response()->json($cheques);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:bounced_cheques,code',
            'cheque_no' => 'required|string',
            'bank' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'tenant_name' => 'required|string',
            'tenant_company' => 'required|string',
            'lease_code' => 'required|string',
            'unit_code' => 'required|string',
            'property_name' => 'required|string',
            'bounced_on' => 'required|date',
            'bounce_reason' => 'required|string',
            'stage' => 'nullable|in:new,notified,promise_to_pay,partially_recovered,recovered,escalated,legal_action,written_off',
            'recovered_amount' => 'nullable|numeric',
            'outstanding' => 'required|numeric',
            'recovery_percent' => 'nullable|numeric',
            'actions_taken' => 'nullable|integer',
            'next_action_due' => 'required|date',
            'aging_days' => 'required|integer',
            'case_manager' => 'required|string',
            'priority' => 'nullable|in:low,normal,high,critical',
            'legal_notice_sent' => 'nullable|boolean',
            'notes' => 'nullable|string',
        ]);

        $cheque = BouncedCheque::create($validated);

        return response()->json($cheque, 201);
    }

    public function show(BouncedCheque $bouncedCheque)
    {
        return response()->json($bouncedCheque);
    }

    public function update(Request $request, BouncedCheque $bouncedCheque)
    {
        $validated = $request->validate([
            'cheque_no' => 'sometimes|string',
            'bank' => 'sometimes|string',
            'amount' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'tenant_name' => 'sometimes|string',
            'tenant_company' => 'sometimes|string',
            'lease_code' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'property_name' => 'sometimes|string',
            'bounced_on' => 'sometimes|date',
            'bounce_reason' => 'sometimes|string',
            'stage' => 'sometimes|in:new,notified,promise_to_pay,partially_recovered,recovered,escalated,legal_action,written_off',
            'recovered_amount' => 'sometimes|numeric',
            'outstanding' => 'sometimes|numeric',
            'recovery_percent' => 'sometimes|numeric',
            'actions_taken' => 'sometimes|integer',
            'next_action_due' => 'sometimes|date',
            'aging_days' => 'sometimes|integer',
            'case_manager' => 'sometimes|string',
            'priority' => 'sometimes|in:low,normal,high,critical',
            'legal_notice_sent' => 'sometimes|boolean',
            'notes' => 'nullable|string',
        ]);

        $bouncedCheque->update($validated);

        return response()->json($bouncedCheque);
    }

    public function destroy(BouncedCheque $bouncedCheque)
    {
        $bouncedCheque->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => BouncedCheque::count(),
            'by_stage' => BouncedCheque::select('stage', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('stage')
                ->pluck('count', 'stage')
                ->toArray(),
            'by_priority' => BouncedCheque::select('priority', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray(),
            'total_amount' => BouncedCheque::sum('amount'),
            'total_recovered' => BouncedCheque::sum('recovered_amount'),
            'total_outstanding' => BouncedCheque::sum('outstanding'),
            'overdue_actions' => BouncedCheque::where('next_action_due', '<', now())->count(),
            'attention' => BouncedCheque::whereIn('stage', ['new', 'notified'])
                ->orWhere('priority', 'critical')
                ->count(),
        ]);
    }

    public function notify(BouncedCheque $bouncedCheque)
    {
        $bouncedCheque->update(['stage' => 'notified', 'actions_taken' => $bouncedCheque->actions_taken + 1]);
        return response()->json($bouncedCheque);
    }

    public function promiseToPay(Request $request, BouncedCheque $bouncedCheque)
    {
        $validated = $request->validate([
            'next_action_due' => 'required|date',
        ]);
        $bouncedCheque->update([
            'stage' => 'promise_to_pay',
            'next_action_due' => $validated['next_action_due'],
            'actions_taken' => $bouncedCheque->actions_taken + 1,
        ]);
        return response()->json($bouncedCheque);
    }

    public function partialRecovery(Request $request, BouncedCheque $bouncedCheque)
    {
        $validated = $request->validate([
            'recovered_amount' => 'required|numeric',
            'next_action_due' => 'required|date',
        ]);
        $newRecovered = $bouncedCheque->recovered_amount + $validated['recovered_amount'];
        $bouncedCheque->update([
            'stage' => 'partially_recovered',
            'recovered_amount' => $newRecovered,
            'outstanding' => $bouncedCheque->amount - $newRecovered,
            'recovery_percent' => $bouncedCheque->amount > 0 ? round(($newRecovered / $bouncedCheque->amount) * 100, 2) : 0,
            'next_action_due' => $validated['next_action_due'],
            'actions_taken' => $bouncedCheque->actions_taken + 1,
        ]);
        return response()->json($bouncedCheque);
    }

    public function recover(BouncedCheque $bouncedCheque)
    {
        $bouncedCheque->update([
            'stage' => 'recovered',
            'recovered_amount' => $bouncedCheque->amount,
            'outstanding' => 0,
            'recovery_percent' => 100,
        ]);
        return response()->json($bouncedCheque);
    }

    public function escalate(Request $request, BouncedCheque $bouncedCheque)
    {
        $validated = $request->validate([
            'next_action_due' => 'required|date',
        ]);
        $bouncedCheque->update([
            'stage' => 'escalated',
            'next_action_due' => $validated['next_action_due'],
            'actions_taken' => $bouncedCheque->actions_taken + 1,
        ]);
        return response()->json($bouncedCheque);
    }

    public function legalAction(Request $request, BouncedCheque $bouncedCheque)
    {
        $validated = $request->validate([
            'legal_notice_sent' => 'required|boolean',
            'next_action_due' => 'required|date',
        ]);
        $bouncedCheque->update([
            'stage' => 'legal_action',
            'legal_notice_sent' => $validated['legal_notice_sent'],
            'next_action_due' => $validated['next_action_due'],
            'actions_taken' => $bouncedCheque->actions_taken + 1,
        ]);
        return response()->json($bouncedCheque);
    }

    public function writeOff(BouncedCheque $bouncedCheque)
    {
        $bouncedCheque->update([
            'stage' => 'written_off',
            'outstanding' => $bouncedCheque->amount - $bouncedCheque->recovered_amount,
        ]);
        return response()->json($bouncedCheque);
    }
}
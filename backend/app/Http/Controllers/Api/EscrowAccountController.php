<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EscrowAccount;
use Illuminate\Http\Request;

class EscrowAccountController extends Controller
{
    public function index(Request $request)
    {
        $query = EscrowAccount::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('property_id')) {
            $query->where('property_id', $request->property_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%")
                    ->orWhere('bank', 'like', "%{$search}%")
                    ->orWhere('iban', 'like', "%{$search}%");
            });
        }

        $perPage = $request->integer('per_page', 15);
        $accounts = $query->with('property:id,name')->latest()->paginate($perPage);

        return response()->json($accounts);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:escrow_accounts,code',
            'property_id' => 'required|exists:properties,id',
            'property_name' => 'required|string',
            'bank' => 'required|string',
            'iban' => 'required|string',
            'account_number' => 'required|string',
            'opening_balance' => 'required|numeric',
            'deposits' => 'nullable|numeric',
            'withdrawals' => 'nullable|numeric',
            'closing_balance' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'authority_limit' => 'required|numeric',
            'status' => 'nullable|in:active,frozen,closed',
            'opened_on' => 'required|date',
            'last_reconciled_on' => 'nullable|date',
            'manager_name' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $account = EscrowAccount::create($validated);

        return response()->json($account->load('property:id,name'), 201);
    }

    public function show(EscrowAccount $escrowAccount)
    {
        return response()->json($escrowAccount->load('property:id,name'));
    }

    public function update(Request $request, EscrowAccount $escrowAccount)
    {
        $validated = $request->validate([
            'property_id' => 'sometimes|exists:properties,id',
            'property_name' => 'sometimes|string',
            'bank' => 'sometimes|string',
            'iban' => 'sometimes|string',
            'account_number' => 'sometimes|string',
            'opening_balance' => 'sometimes|numeric',
            'deposits' => 'sometimes|numeric',
            'withdrawals' => 'sometimes|numeric',
            'closing_balance' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'authority_limit' => 'sometimes|numeric',
            'status' => 'sometimes|in:active,frozen,closed',
            'opened_on' => 'sometimes|date',
            'last_reconciled_on' => 'nullable|date',
            'manager_name' => 'sometimes|string',
            'notes' => 'nullable|string',
        ]);

        $escrowAccount->update($validated);

        return response()->json($escrowAccount->load('property:id,name'));
    }

    public function destroy(EscrowAccount $escrowAccount)
    {
        $escrowAccount->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => EscrowAccount::count(),
            'by_status' => EscrowAccount::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'total_balance' => EscrowAccount::sum('closing_balance'),
            'total_authority_limit' => EscrowAccount::sum('authority_limit'),
            'attention' => EscrowAccount::where('status', 'frozen')->count(),
        ]);
    }

    public function freeze(EscrowAccount $escrowAccount)
    {
        $escrowAccount->update(['status' => 'frozen']);
        return response()->json($escrowAccount);
    }

    public function activate(EscrowAccount $escrowAccount)
    {
        $escrowAccount->update(['status' => 'active']);
        return response()->json($escrowAccount);
    }

    public function reconcile(Request $request, EscrowAccount $escrowAccount)
    {
        $validated = $request->validate([
            'last_reconciled_on' => 'required|date',
            'closing_balance' => 'required|numeric',
        ]);
        $escrowAccount->update([
            'last_reconciled_on' => $validated['last_reconciled_on'],
            'closing_balance' => $validated['closing_balance'],
        ]);
        return response()->json($escrowAccount);
    }
}
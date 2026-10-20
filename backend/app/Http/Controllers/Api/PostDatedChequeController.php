<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostDatedCheque;
use Illuminate\Http\Request;

class PostDatedChequeController extends Controller
{
    public function index(Request $request)
    {
        $query = PostDatedCheque::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('lease_code')) {
            $query->where('lease_code', $request->lease_code);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('cheque_no', 'like', "%{$search}%")
                    ->orWhere('payer', 'like', "%{$search}%")
                    ->orWhere('payer_company', 'like', "%{$search}%")
                    ->orWhere('property_name', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('due_soon')) {
            $query->where('status', 'pending')
                ->where('due_on', '<=', now()->addDays(30))
                ->where('due_on', '>=', now());
        }

        $perPage = $request->integer('per_page', 15);
        $cheques = $query->latest('due_on')->paginate($perPage);

        return response()->json($cheques);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cheque_no' => 'required|string',
            'bank' => 'required|string',
            'branch' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'nullable|string|size:3',
            'payer' => 'required|string',
            'payer_company' => 'required|string',
            'payee' => 'required|string',
            'lease_code' => 'required|string',
            'unit_code' => 'required|string',
            'property_name' => 'required|string',
            'issued_on' => 'required|date',
            'due_on' => 'required|date',
            'deposited_on' => 'nullable|date',
            'status' => 'nullable|in:pending,deposited,cleared,returned,replaced',
            'instalment_label' => 'required|string',
            'bounce_case_code' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $cheque = PostDatedCheque::create($validated);

        return response()->json($cheque, 201);
    }

    public function show(PostDatedCheque $postDatedCheque)
    {
        return response()->json($postDatedCheque);
    }

    public function update(Request $request, PostDatedCheque $postDatedCheque)
    {
        $validated = $request->validate([
            'bank' => 'sometimes|string',
            'branch' => 'sometimes|string',
            'amount' => 'sometimes|numeric',
            'currency' => 'sometimes|string|size:3',
            'payer' => 'sometimes|string',
            'payer_company' => 'sometimes|string',
            'payee' => 'sometimes|string',
            'lease_code' => 'sometimes|string',
            'unit_code' => 'sometimes|string',
            'property_name' => 'sometimes|string',
            'issued_on' => 'sometimes|date',
            'due_on' => 'sometimes|date',
            'deposited_on' => 'nullable|date',
            'status' => 'sometimes|in:pending,deposited,cleared,returned,replaced',
            'instalment_label' => 'sometimes|string',
            'bounce_case_code' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $postDatedCheque->update($validated);

        return response()->json($postDatedCheque);
    }

    public function destroy(PostDatedCheque $postDatedCheque)
    {
        $postDatedCheque->delete();
        return response()->json(null, 204);
    }

    public function stats()
    {
        return response()->json([
            'total' => PostDatedCheque::count(),
            'by_status' => PostDatedCheque::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray(),
            'total_amount' => PostDatedCheque::sum('amount'),
            'due_soon' => PostDatedCheque::where('status', 'pending')
                ->where('due_on', '<=', now()->addDays(30))
                ->where('due_on', '>=', now())
                ->count(),
            'attention' => PostDatedCheque::where('status', 'returned')->count(),
        ]);
    }

    public function deposit(Request $request, PostDatedCheque $postDatedCheque)
    {
        $validated = $request->validate([
            'deposited_on' => 'required|date',
            'notes' => 'nullable|string',
        ]);

        $postDatedCheque->update([
            'status' => 'deposited',
            'deposited_on' => $validated['deposited_on'],
            'notes' => $validated['notes'] ?? $postDatedCheque->notes,
        ]);

        return response()->json($postDatedCheque);
    }

    public function clear(PostDatedCheque $postDatedCheque)
    {
        $postDatedCheque->update(['status' => 'cleared']);
        return response()->json($postDatedCheque);
    }

    public function return(Request $request, PostDatedCheque $postDatedCheque)
    {
        $validated = $request->validate([
            'bounce_case_code' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $postDatedCheque->update([
            'status' => 'returned',
            'bounce_case_code' => $validated['bounce_case_code'] ?? $postDatedCheque->bounce_case_code,
            'notes' => $validated['notes'] ?? $postDatedCheque->notes,
        ]);

        return response()->json($postDatedCheque);
    }
}
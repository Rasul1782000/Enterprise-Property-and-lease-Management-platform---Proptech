<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Payment::with(['invoice', 'tenant'])
                ->when($request->invoice_id, fn ($q, $id) => $q->where('invoice_id', $id))
                ->latest()->paginate($request->get('per_page', 15))
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'method' => 'required|in:cash,bank_transfer,check,card,online',
            'reference' => 'nullable|string|max:255',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $data['paid_at'] = $data['paid_at'] ?? $data['payment_date'] ?? now();
        $data['method'] = $data['method'] ?? $data['payment_method'] ?? 'cash';
        $invoice = Invoice::findOrFail($data['invoice_id']);
        $data['tenant_id'] = $invoice->tenant_id;
        $data['recorded_by'] = $request->user()->id;
        $payment = Payment::create($data);

        return response()->json($payment->load('invoice'), 201);
    }

    public function show(Payment $payment)
    {
        return response()->json($payment->load(['invoice', 'tenant']));
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = QueryBuilder::for(Payment::class)
            ->with(['invoice', 'tenant'])
            ->allowedFilters([
                AllowedFilter::exact('invoice_id'),
                AllowedFilter::exact('tenant_id'),
                AllowedFilter::exact('method'),
            ])
            ->allowedSorts(['paid_at', 'amount', 'created_at'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return PaymentResource::collection($payments);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,check,card,online',
            'reference' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        $invoice = \App\Models\Invoice::findOrFail($data['invoice_id']);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'tenant_id' => $invoice->tenant_id,
            'amount' => $data['amount'],
            'method' => $data['payment_method'],
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => $data['payment_date'] ?? now(),
            'recorded_by' => $request->user()?->id,
        ]);

        return new PaymentResource($payment->load('invoice'));
    }

    public function show(Payment $payment)
    {
        return new PaymentResource($payment->load(['invoice', 'tenant']));
    }
}
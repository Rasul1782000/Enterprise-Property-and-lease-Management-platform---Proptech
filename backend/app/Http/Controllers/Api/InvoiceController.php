<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InvoiceResource;
use App\Mail\RentInvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\Lease;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = QueryBuilder::for(Invoice::class)
            ->with(['lease.building', 'tenant', 'unit.building', 'payments'])
            ->withSum('payments as payments_sum_amount', 'amount')
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('lease_id'),
                AllowedFilter::exact('tenant_id'),
                AllowedFilter::exact('unit_id'),
                AllowedFilter::exact('type'),
                AllowedFilter::partial('invoice_number'),
                AllowedFilter::partial('code', 'invoice_number'),
                AllowedFilter::callback('property_id', function ($query, $value) {
                    $query->whereHas('unit.building', fn ($qq) => $qq->where('property_id', $value));
                }),
            ])
            ->allowedSorts(['invoice_number', 'due_date', 'issue_date', 'amount', 'created_at'])
            ->paginate($request->get('per_page', 15))
            ->appends($request->query());

        return InvoiceResource::collection($invoices);
    }

    public function show(Invoice $invoice)
    {
        return new InvoiceResource(
            $invoice->load(['lease.building', 'tenant', 'unit.building', 'payments', 'lineItems'])
        );
    }

    public function update(Request $request, Invoice $invoice)
    {
        $invoice->update($request->validate([
            'status' => 'sometimes|in:draft,sent,paid,partial,overdue,cancelled',
            'due_date' => 'sometimes|date',
            'notes' => 'nullable|string',
        ]));

        return new InvoiceResource($invoice->fresh(['lease.building', 'tenant', 'payments']));
    }

    public function send(Invoice $invoice)
    {
        Mail::to($invoice->tenant->email)->send(new RentInvoiceMail($invoice));
        $invoice->update([
            'sent_at' => now(),
            'status' => 'sent',
        ]);

        return response()->json([
            'message' => 'Invoice sent',
            'invoice' => new InvoiceResource($invoice->fresh(['lease.building', 'tenant', 'payments'])),
        ]);
    }

    public function receiptPdf(Invoice $invoice)
    {
        $invoice->load(['tenant', 'lease.unit.building.property', 'payments']);
        $pdf = Pdf::loadView('pdfs.invoice-receipt', compact('invoice'))->setPaper('a4', 'portrait');

        return $pdf->stream('Receipt-'.$invoice->invoice_number.'.pdf');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lease_id' => 'required|exists:leases,id',
            'type' => 'required|in:rent,deposit,late_fee,utility,maintenance,other',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'description' => 'nullable|string',
            'status' => 'sometimes|in:draft,sent,paid,partial,overdue,cancelled',
            'line_items' => 'nullable|array',
            'line_items.*.description' => 'required|string',
            'line_items.*.quantity' => 'required|numeric|min:0',
            'line_items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $lease = Lease::findOrFail($data['lease_id']);

        $invoice = Invoice::create([
            'lease_id' => $lease->id,
            'tenant_id' => $lease->tenant_id,
            'unit_id' => $lease->unit_id,
            'type' => $data['type'],
            'issue_date' => $data['issue_date'],
            'period_start' => $data['issue_date'],
            'period_end' => $data['due_date'],
            'due_date' => $data['due_date'],
            'amount' => $data['amount'],
            'late_fee' => 0,
            'total_amount' => $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'notes' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'draft',
        ]);

        foreach ($data['line_items'] ?? [] as $line) {
            InvoiceLineItem::create([
                'invoice_id' => $invoice->id,
                'description' => $line['description'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'amount' => $line['quantity'] * $line['unit_price'],
            ]);
        }

        return new InvoiceResource(
            $invoice->load(['lease.building', 'tenant', 'unit.building', 'lineItems'])
        );
    }

    public function destroy(Invoice $invoice)
    {
        $invoice->delete();

        return response()->json(null, 204);
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load(['tenant', 'lease.unit.building.property', 'payments']);
        $pdf = Pdf::loadView('pdfs.invoice-receipt', compact('invoice'))->setPaper('a4', 'portrait');

        return $pdf->stream('Invoice-'.$invoice->invoice_number.'.pdf');
    }

    public function storePayment(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_method' => 'required|in:cash,bank_transfer,check,card,online',
            'reference' => 'nullable|string|max:255',
            'payment_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

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

        return (new PaymentResource($payment->load('invoice')))->response()->setStatusCode(201);
    }

    public function payments(Invoice $invoice)
    {
        $payments = $invoice->payments()->with(['tenant', 'recorder'])->get();

        return PaymentResource::collection($payments);
    }

    public function bulkGenerate(Request $request)
    {
        $data = $request->validate([
            'lease_ids' => 'required|array',
            'lease_ids.*' => 'exists:leases,id',
            'issue_date' => 'required|date',
            'due_date' => 'required|date',
            'type' => 'nullable|in:rent,deposit,late_fee,utility,maintenance,other',
        ]);

        $leases = Lease::whereIn('id', $data['lease_ids'])->get();
        $created = [];

        foreach ($leases as $lease) {
            $existing = Invoice::where('lease_id', $lease->id)
                ->where('period_start', $data['issue_date'])
                ->first();

            if (! $existing) {
                $invoice = Invoice::create([
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'type' => $data['type'] ?? 'rent',
                    'issue_date' => $data['issue_date'],
                    'period_start' => $data['issue_date'],
                    'period_end' => $data['due_date'],
                    'due_date' => $data['due_date'],
                    'amount' => $lease->rent_amount,
                    'late_fee' => 0,
                    'total_amount' => $lease->rent_amount,
                    'status' => 'draft',
                ]);
                $created[] = $invoice;
            }
        }

        return InvoiceResource::collection(collect($created)->load('lease.building'));
    }
}
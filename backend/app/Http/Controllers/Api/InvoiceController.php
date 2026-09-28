<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RentInvoiceMail;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['lease', 'tenant', 'unit.building'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->lease_id, fn ($q, $id) => $q->where('lease_id', $id))
            ->when($request->tenant_id, fn ($q, $id) => $q->where('tenant_id', $id))
            ->orderBy($request->get('sort', 'due_date'), $request->get('direction', 'desc'))
            ->paginate($request->get('per_page', 15));

        return response()->json($invoices);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['lease.unit', 'tenant', 'unit', 'payments']));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $invoice->update($request->validate([
            'status' => 'sometimes|in:pending,paid,overdue,cancelled,partial',
            'notes' => 'nullable|string',
        ]));

        return response()->json($invoice->fresh());
    }

    public function send(Invoice $invoice)
    {
        Mail::to($invoice->tenant->email)->send(new RentInvoiceMail($invoice));
        $invoice->update(['sent_at' => now()]);

        return response()->json(['message' => 'Invoice sent', 'invoice' => $invoice->fresh()]);
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
            'tenant_id' => 'required|exists:tenants,id',
            'unit_id' => 'required|exists:units,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'due_date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'status' => 'sometimes|in:pending,paid,overdue,cancelled,partial,draft,sent',
            'notes' => 'nullable|string',
        ]);
        $data['total_amount'] = $data['amount'] + ($data['late_fee'] ?? 0);
        $data['status'] = $data['status'] ?? 'pending';
        if ($data['status'] === 'draft' || $data['status'] === 'sent') {
            $data['status'] = 'pending';
        }
        $invoice = Invoice::create($data);

        return response()->json($invoice->load(['lease', 'tenant', 'unit']), 201);
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
            'method' => 'required|in:cash,bank_transfer,check,card,online',
            'reference' => 'nullable|string|max:255',
            'paid_at' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);
        $data['invoice_id'] = $invoice->id;
        $data['tenant_id'] = $invoice->tenant_id;
        $data['recorded_by'] = $request->user()->id;
        $data['paid_at'] = $data['paid_at'] ?? now();
        $payment = Payment::create($data);

        return response()->json($payment->load('invoice'), 201);
    }

    public function payments(Invoice $invoice)
    {
        $payments = $invoice->payments()->with(['tenant', 'recorder'])->get();

        return response()->json($payments);
    }

    public function bulkGenerate(Request $request)
    {
        $data = $request->validate([
            'lease_ids' => 'required|array',
            'lease_ids.*' => 'exists:leases,id',
            'period_start' => 'required|date',
            'period_end' => 'required|date|after:period_start',
            'due_date' => 'required|date',
        ]);

        $leases = Lease::whereIn('id', $data['lease_ids'])->get();
        $created = [];

        foreach ($leases as $lease) {
            $existing = Invoice::where('lease_id', $lease->id)
                ->where('period_start', $data['period_start'])
                ->where('period_end', $data['period_end'])
                ->first();

            if (! $existing) {
                $invoice = Invoice::create([
                    'lease_id' => $lease->id,
                    'tenant_id' => $lease->tenant_id,
                    'unit_id' => $lease->unit_id,
                    'period_start' => $data['period_start'],
                    'period_end' => $data['period_end'],
                    'due_date' => $data['due_date'],
                    'amount' => $lease->rent_amount,
                    'late_fee' => 0,
                    'total_amount' => $lease->rent_amount,
                    'status' => 'pending',
                ]);
                $created[] = $invoice;
            }
        }

        return response()->json([
            'message' => count($created).' invoices generated',
            'invoices' => $created,
        ], 201);
    }
}

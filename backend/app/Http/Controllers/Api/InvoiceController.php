<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\RentInvoiceMail;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $invoices = Invoice::with(['lease','tenant','unit.building'])
            ->when($request->status, fn($q,$s)=> $q->where('status',$s))
            ->when($request->lease_id, fn($q,$id)=> $q->where('lease_id',$id))
            ->when($request->tenant_id, fn($q,$id)=> $q->where('tenant_id',$id))
            ->orderBy($request->get('sort','due_date'), $request->get('direction','desc'))
            ->paginate($request->get('per_page',15));
        return response()->json($invoices);
    }

    public function show(Invoice $invoice)
    {
        return response()->json($invoice->load(['lease.unit','tenant','unit','payments']));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $invoice->update($request->validate([
            'status'=>'sometimes|in:pending,paid,overdue,cancelled,partial',
            'notes'=>'nullable|string',
        ]));
        return response()->json($invoice->fresh());
    }

    public function send(Invoice $invoice)
    {
        Mail::to($invoice->tenant->email)->send(new RentInvoiceMail($invoice));
        $invoice->update(['sent_at'=>now()]);
        return response()->json(['message'=>'Invoice sent','invoice'=>$invoice->fresh()]);
    }

    public function receiptPdf(Invoice $invoice)
    {
        $invoice->load(['tenant','lease.unit.building.property','payments']);
        $pdf = Pdf::loadView('pdfs.invoice-receipt', compact('invoice'))->setPaper('a4','portrait');
        return $pdf->stream('Receipt-'.$invoice->invoice_number.'.pdf');
    }
}

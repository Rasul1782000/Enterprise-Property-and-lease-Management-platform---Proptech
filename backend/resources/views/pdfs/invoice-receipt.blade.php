<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Receipt - {{ $invoice->invoice_number }}</title>
<style>
  body{font-family:'DejaVu Sans', sans-serif; font-size:11px; color:#111827;}
  .header{text-align:center; border-bottom:3px solid #059669; padding-bottom:10px; margin-bottom:14px;}
  .header h1{margin:0; color:#059669;}
  table{width:100%; border-collapse:collapse;}
  th,td{border:1px solid #d1d5db; padding:6px 8px; text-align:left;}
  th{background:#ecfdf5;}
  .total td{font-weight:bold; background:#f0fdf4;}
  .badge{padding:2px 8px; border-radius:999px; font-size:10px; color:#fff;}
  .paid{background:#10b981;} .pending{background:#f59e0b;} .overdue{background:#ef4444;}
  .footer{text-align:center; font-size:9px; color:#9ca3af; margin-top:20px; border-top:1px solid #e5e7eb; padding-top:8px;}
</style>
</head>
<body>
<div class="header">
  <h1>RENT RECEIPT</h1>
  <p>{{ $invoice->unit->building->property->name ?? 'PropertyLease' }} | Invoice {{ $invoice->invoice_number }} | <span class="badge {{ $invoice->status=='paid'?'paid':($invoice->status=='overdue'?'overdue':'pending') }}">{{ strtoupper($invoice->status) }}</span></p>
</div>

<table style="margin-bottom:12px;">
<tr><th width="25%">Billed To</th><td>{{ $invoice->tenant->full_name }} @if($invoice->tenant->company_name) ({{ $invoice->tenant->company_name }}) @endif<br>{{ $invoice->tenant->email }} | {{ $invoice->tenant->phone }}</td>
    <th width="20%">Invoice Details</th><td>Lease: {{ $invoice->lease->lease_number }}<br>Unit: {{ $invoice->unit->unit_number }} - {{ $invoice->unit->building->name }}<br>Period: {{ $invoice->period_start->format('M d') }} - {{ $invoice->period_end->format('M d, Y') }}<br>Due: {{ $invoice->due_date->format('M d, Y') }}</td></tr>
</table>

<table>
<tr><th>Description</th><th width="25%">Amount</th></tr>
<tr><td>Monthly Rent - {{ $invoice->period_start->format('M Y') }}</td><td>${{ number_format($invoice->amount,2) }}</td></tr>
@if($invoice->late_fee > 0)<tr><td>Late Fee ({{ $invoice->lease->late_fee_percent }}%)</td><td>${{ number_format($invoice->late_fee,2) }}</td></tr>@endif
<tr class="total"><td>Total Due</td><td>${{ number_format($invoice->total_amount,2) }}</td></tr>
</table>

<h3 style="margin:14px 0 6px 0; color:#059669;">Payments</h3>
@if($invoice->payments->count())
<table>
<tr><th>Date</th><th>Method</th><th>Reference</th><th>Amount</th></tr>
@foreach($invoice->payments as $p)
<tr><td>{{ $p->paid_at->format('M d, Y H:i') }}</td><td>{{ ucfirst(str_replace('_',' ',$p->method)) }}</td><td>{{ $p->reference ?? '-' }}</td><td>${{ number_format($p->amount,2) }}</td></tr>
@endforeach
<tr class="total"><td colspan="3">Total Paid</td><td>${{ number_format($invoice->payments->sum('amount'),2) }}</td></tr>
<tr class="total"><td colspan="3">Balance</td><td>${{ number_format($invoice->total_amount - $invoice->payments->sum('amount'),2) }}</td></tr>
</table>
@else
<p style="color:#6b7280; font-style:italic;">No payments recorded yet.</p>
@endif

<p style="margin-top:14px;"><strong>Notes:</strong> {{ $invoice->notes ?? 'Thank you for your payment. Please keep this receipt for your records.' }}</p>

<div class="footer">
  Generated {{ now()->format('Y-m-d H:i') }} via PropertyLease Portal (DomPDF) | PropertyLease Enterprise, {{ $invoice->unit->building->property->address ?? '' }} {{ $invoice->unit->building->property->city ?? '' }}
</div>
</body>
</html>

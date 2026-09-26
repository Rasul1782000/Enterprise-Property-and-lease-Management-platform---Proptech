<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><style>body{font-family:Arial, sans-serif; color:#1f2937;}</style></head>
<body>
<p>Dear {{ $invoice->tenant->full_name }},</p>
<p>Your rent invoice <strong>{{ $invoice->invoice_number }}</strong> for the period {{ $invoice->period_start->format('M d') }} - {{ $invoice->period_end->format('M d, Y') }} has been generated.</p>
<table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse; width:100%; max-width:600px;">
<tr><td>Property/Unit</td><td>{{ $invoice->unit->building->property->name ?? '' }} / Unit {{ $invoice->unit->unit_number }}</td></tr>
<tr><td>Amount</td><td><strong>${{ number_format($invoice->total_amount,2) }}</strong></td></tr>
<tr><td>Due Date</td><td>{{ $invoice->due_date->format('M d, Y') }}</td></tr>
<tr><td>Status</td><td>{{ ucfirst($invoice->status) }}</td></tr>
</table>
<p>Please pay before the due date to avoid late fees ({{ $invoice->lease->late_fee_percent ?? 2.5 }}%).</p>
<p><a href="{{ config('app.frontend_url', env('FRONTEND_URL')).'/invoices/'.$invoice->id }}">View Invoice</a></p>
<p>Thank you,<br>PropertyLease Management</p>
</body>
</html>

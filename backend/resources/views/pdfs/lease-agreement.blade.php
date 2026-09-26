<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Lease Agreement - {{ $lease->lease_number }}</title>
<style>
  @page { margin: 40px 40px; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color:#111827; line-height:1.5; }
  .header { text-align:center; border-bottom:3px solid #1e40af; padding-bottom:12px; margin-bottom:18px; }
  .header h1 { margin:0; color:#1e40af; font-size:20px; }
  .header p { margin:2px 0; color:#6b7280; }
  .section { margin-bottom:16px; }
  .section h3 { background:#eff6ff; padding:6px 10px; color:#1e40af; margin:0 0 8px 0; font-size:12px; border-left:4px solid #1e40af; }
  table { width:100%; border-collapse:collapse; }
  th, td { border:1px solid #d1d5db; padding:6px 8px; text-align:left; }
  th { background:#f9fafb; font-weight:600; }
  .grid { display:table; width:100%; }
  .col { display:table-cell; width:50%; vertical-align:top; padding-right:10px; }
  .sig { margin-top:40px; display:table; width:100%; }
  .sig-cell { display:table-cell; width:50%; text-align:center; }
  .sig-line { border-top:1px solid #111827; margin:40px 30px 6px 30px; padding-top:6px; }
  .badge { display:inline-block; padding:2px 8px; border-radius:9999px; background:#dbeafe; color:#1e40af; font-size:10px; }
  .footer { text-align:center; font-size:9px; color:#9ca3af; margin-top:30px; border-top:1px solid #e5e7eb; padding-top:10px; }
</style>
</head>
<body>
<div class="header">
  <h1>PROPERTYLEASE ENTERPRISE</h1>
  <p>Lease Agreement</p>
  <p><span class="badge">{{ $lease->status }}</span> &nbsp; Agreement No: <strong>{{ $lease->lease_number }}</strong> &nbsp;|&nbsp; Date: {{ now()->format('M d, Y') }}</p>
</div>

<div class="section">
  <h3>1. Parties</h3>
  <div class="grid">
    <div class="col">
      <strong>Lessor (Property Management)</strong><br>
      {{ $lease->unit->building->property->name ?? 'PropertyLease Portal' }}<br>
      {{ $lease->unit->building->property->address ?? '' }}, {{ $lease->unit->building->property->city ?? '' }}<br>
      Building: {{ $lease->unit->building->name }} | Unit: {{ $lease->unit->unit_number }} (Floor {{ $lease->unit->floor }})
    </div>
    <div class="col">
      <strong>Lessee (Tenant)</strong><br>
      {{ $lease->tenant->full_name }}<br>
      @if($lease->tenant->company_name) Company: {{ $lease->tenant->company_name }}<br> @endif
      Email: {{ $lease->tenant->email }}<br>
      Phone: {{ $lease->tenant->phone }}<br>
      ID: {{ $lease->tenant->id_number ?? 'N/A' }}
    </div>
  </div>
</div>

<div class="section">
  <h3>2. Premises & Term</h3>
  <table>
    <tr><th width="30%">Property</th><td>{{ $lease->unit->building->property->name }} ({{ $lease->unit->building->property->code }}) - {{ $lease->unit->building->property->type }}</td></tr>
    <tr><th>Unit Details</th><td>{{ $lease->unit->unit_type }} | {{ $lease->unit->sqft }} sqft | {{ $lease->unit->bedrooms ?? 0 }} bed / {{ $lease->unit->bathrooms ?? 0 }} bath | Amenities: {{ $lease->unit->amenities ?? 'Standard' }}</td></tr>
    <tr><th>Lease Term</th><td>{{ $lease->start_date->format('M d, Y') }} to {{ $lease->end_date->format('M d, Y') }} ({{ $lease->start_date->diffInMonths($lease->end_date) }} months) | Frequency: {{ ucfirst($lease->payment_frequency) }}</td></tr>
    <tr><th>Rent & Deposit</th><td>Monthly Rent: <strong>${{ number_format($lease->rent_amount,2) }}</strong> | Security Deposit: ${{ number_format($lease->deposit_amount,2) }} | Due Day: {{ $lease->due_day }} of each month | Late Fee: {{ $lease->late_fee_percent }}%</td></tr>
  </table>
</div>

<div class="section">
  <h3>3. Rent Schedule & Invoicing</h3>
  <p>Rent is due on day {{ $lease->due_day }} of each month. Invoices are auto-generated on the 1st via Laravel Scheduler and emailed to the tenant. Late payments beyond 3 days incur {{ $lease->late_fee_percent }}% fee.</p>
  <table>
    <tr><th>#</th><th>Period</th><th>Due Date</th><th>Amount</th></tr>
    @php $d = $lease->start_date->copy()->startOfMonth(); $i=1; @endphp
    @while($d->lte($lease->end_date) && $i <= 6)
      <tr><td>{{ $i }}</td><td>{{ $d->format('M Y') }}</td><td>{{ $d->copy()->day(min($lease->due_day, $d->daysInMonth))->format('M d, Y') }}</td><td>${{ number_format($lease->rent_amount,2) }}</td></tr>
      @php $d->addMonth(); $i++; @endphp
    @endwhile
    @if($lease->start_date->diffInMonths($lease->end_date) > 6)
      <tr><td colspan="4" style="text-align:center; color:#6b7280;">... and {{ $lease->start_date->diffInMonths($lease->end_date)-6 }} more months (see portal) ...</td></tr>
    @endif
  </table>
</div>

<div class="section">
  <h3>4. Terms & Conditions</h3>
  <ol style="margin:0; padding-left:18px;">
    <li>Tenant shall use premises solely for {{ $lease->unit->unit_type }} purposes and maintain it in good condition.</li>
    <li>No subletting without written consent of Lessor.</li>
    <li>Lessor shall provide maintenance for structural elements; Tenant responsible for interior upkeep.</li>
    <li>Security deposit refundable within 30 days post lease end minus damages/unpaid dues.</li>
    @if($lease->terms)
      @foreach($lease->terms as $term)
        <li>{{ is_string($term) ? $term : json_encode($term) }}</li>
      @endforeach
    @else
      <li>Early termination requires 60 days notice and forfeiture of deposit.</li>
      <li>Renewal notice must be given 30 days before expiry.</li>
    @endif
  </ol>
  @if($lease->notes)<p><strong>Notes:</strong> {{ $lease->notes }}</p>@endif
</div>

<div class="sig">
  <div class="sig-cell"><div class="sig-line">Tenant Signature<br><small>{{ $lease->tenant->full_name }} | {{ now()->format('M d, Y') }}</small></div></div>
  <div class="sig-cell"><div class="sig-line">Authorized Signatory<br><small>PropertyLease Management</small></div></div>
</div>

<div class="footer">
  Generated by PropertyLease Portal via DomPDF on {{ now()->format('Y-m-d H:i') }} | This is a system-generated agreement and valid without physical seal when digitally signed.
</div>
</body>
</html>

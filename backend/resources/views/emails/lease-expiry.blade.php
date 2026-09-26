<!DOCTYPE html>
<html><head><meta charset="utf-8"></head><body style="font-family:Arial,sans-serif">
<p>Dear {{ $lease->tenant->full_name }},</p>
<p>Your lease <strong>{{ $lease->lease_number }}</strong> for Unit {{ $lease->unit->unit_number }} at {{ $lease->unit->building->property->name ?? '' }} will expire on <strong>{{ $lease->end_date->format('M d, Y') }}</strong>.</p>
<p>Please contact management to discuss renewal options.</p>
<p>Thank you</p>
</body></html>

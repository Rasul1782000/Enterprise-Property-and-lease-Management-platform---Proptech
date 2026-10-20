<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaseRenewal extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'lease_code',
        'property_name',
        'unit_code',
        'tenant_name',
        'tenant_company',
        'current_rent',
        'proposed_rent',
        'uplift_percent',
        'expiry_date',
        'days_to_expiry',
        'offer_sent_on',
        'response_due_on',
        'responded_on',
        'status',
        'term_months',
        'new_start_date',
        'new_end_date',
        'discount_percent',
        'owner_note',
        'notes',
    ];

    protected $casts = [
        'expiry_date' => 'date',
        'offer_sent_on' => 'date',
        'response_due_on' => 'date',
        'responded_on' => 'date',
        'new_start_date' => 'date',
        'new_end_date' => 'date',
        'current_rent' => 'decimal:2',
        'proposed_rent' => 'decimal:2',
        'uplift_percent' => 'decimal:2',
        'discount_percent' => 'decimal:2',
    ];

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    public function scopeUnderNegotiation($query)
    {
        return $query->where('status', 'under_negotiation');
    }

    public function scopeAccepted($query)
    {
        return $query->where('status', 'accepted');
    }

    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', '!=', 'accepted')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }
}
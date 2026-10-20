<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EjariContract extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'ejari_number',
        'lease_code',
        'property_name',
        'unit_code',
        'landlord_name',
        'tenant_name',
        'tenant_company',
        'ejari_type',
        'registration_date',
        'certificate_issued_on',
        'expiry_date',
        'status',
        'registration_fee',
        'penalty_amount',
        'deed_reference',
        'free_zone_property',
        'attempts',
        'last_error',
        'notes',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'certificate_issued_on' => 'date',
        'expiry_date' => 'date',
        'registration_fee' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
        'free_zone_property' => 'boolean',
    ];

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeSubmitted($query)
    {
        return $query->where('status', 'submitted');
    }

    public function scopeRegistered($query)
    {
        return $query->where('status', 'registered');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'registered')
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('expiry_date', '>=', now());
    }
}
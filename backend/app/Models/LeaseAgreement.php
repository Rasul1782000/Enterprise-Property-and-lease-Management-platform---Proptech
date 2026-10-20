<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeaseAgreement extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'lease_code',
        'property_name',
        'unit_code',
        'tenant_name',
        'tenant_company',
        'type',
        'start_date',
        'end_date',
        'rent_amount',
        'security_deposit',
        'payment_frequency',
        'template',
        'version',
        'generated_on',
        'status',
        'landlord_signed_on',
        'tenant_signed_on',
        'witness_name',
        'ejari_number',
        'document_size_kb',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'generated_on' => 'date',
        'landlord_signed_on' => 'date',
        'tenant_signed_on' => 'date',
        'rent_amount' => 'decimal:2',
        'security_deposit' => 'decimal:2',
    ];

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeExecuted($query)
    {
        return $query->where('status', 'executed');
    }

    public function scopeWithEjari($query)
    {
        return $query->whereNotNull('ejari_number');
    }
}
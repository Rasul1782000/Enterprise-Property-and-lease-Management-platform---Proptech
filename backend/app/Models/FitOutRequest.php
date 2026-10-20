<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FitOutRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'property_name',
        'unit_code',
        'tenant_name',
        'tenant_company',
        'contractor_name',
        'contractor_trn',
        'scope',
        'permit_status',
        'noc_status',
        'deposit_amount',
        'deposit_status',
        'requested_on',
        'expected_completion',
        'inspection_date',
        'progress_percent',
        'priority',
        'estimated_cost',
        'owner_name',
        'notes',
    ];

    protected $casts = [
        'requested_on' => 'date',
        'expected_completion' => 'date',
        'inspection_date' => 'date',
        'deposit_amount' => 'decimal:2',
        'estimated_cost' => 'decimal:2',
    ];

    public function scopePendingPermit($query)
    {
        return $query->where('permit_status', 'not_submitted');
    }

    public function scopeUnderReview($query)
    {
        return $query->where('permit_status', 'under_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('permit_status', 'approved');
    }

    public function scopeHighPriority($query)
    {
        return $query->where('priority', 'high');
    }
}
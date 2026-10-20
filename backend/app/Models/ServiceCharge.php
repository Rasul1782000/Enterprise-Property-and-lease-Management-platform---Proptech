<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'property_id',
        'property_name',
        'period_year',
        'category',
        'budgeted_amount',
        'actual_amount',
        'variance',
        'total_area_sqft',
        'rate_per_sqft',
        'units_charged',
        'amount_collected',
        'outstanding',
        'issued_on',
        'due_date',
        'status',
        'approved_by',
        'notes',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'due_date' => 'date',
        'budgeted_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance' => 'decimal:2',
        'total_area_sqft' => 'decimal:2',
        'rate_per_sqft' => 'decimal:4',
        'amount_collected' => 'decimal:2',
        'outstanding' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeIssued($query)
    {
        return $query->where('status', 'issued');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    public function scopeOutstanding($query)
    {
        return $query->whereIn('status', ['issued', 'partially_paid']);
    }
}
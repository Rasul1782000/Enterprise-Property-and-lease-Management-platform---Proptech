<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lease extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'lease_number', 'unit_id', 'tenant_id', 'start_date', 'end_date',
        'rent_amount', 'deposit_amount', 'late_fee_percent', 'due_day',
        'status', 'payment_frequency', 'terms', 'notes', 'document_path', 'created_by',
        'type', 'escalation_clause', 'renewal_options', 'signed_at', 'terminated_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'rent_amount' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'late_fee_percent' => 'decimal:2',
        'terms' => 'array',
        'signed_at' => 'datetime',
        'terminated_at' => 'datetime',
    ];

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }


    public function building()
    {
        return $this->hasOneThrough(Building::class, Unit::class, 'id', 'id', 'unit_id', 'building_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeExpiringSoon($q, $days = 30)
    {
        return $q->where('status', 'active')->whereBetween('end_date', [now(), now()->addDays($days)]);
    }

    public function scopeOverdue($q)
    {
        return $q->whereHas('invoices', fn ($qq) => $qq->where('status', 'overdue'));
    }

    protected static function booted()
    {
        static::creating(function ($lease) {
            if (empty($lease->lease_number)) {
                $lease->lease_number = 'LEASE-'.now()->format('Y').'-'.str_pad((Lease::count() + 1), 5, '0', STR_PAD_LEFT);
            }
        });

        static::created(function ($lease) {

            $lease->unit()->update(['status' => 'occupied']);
        });
        static::updated(function ($lease) {
            if ($lease->isDirty('status') && in_array($lease->status, ['expired', 'terminated'])) {

                if (! $lease->unit->leases()->where('status', 'active')->exists()) {
                    $lease->unit()->update(['status' => 'vacant']);
                }
            }
        });
    }
}

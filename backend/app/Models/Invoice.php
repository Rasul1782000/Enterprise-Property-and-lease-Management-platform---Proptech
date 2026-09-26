<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number','lease_id','tenant_id','unit_id',
        'period_start','period_end','due_date','amount','late_fee','total_amount',
        'status','notes','sent_at'
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'due_date' => 'date',
        'amount' => 'decimal:2',
        'late_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'sent_at' => 'datetime',
    ];

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->status !== 'paid' && $this->due_date->isPast();
    }

    public function scopePending($q){ return $q->where('status','pending'); }
    public function scopeOverdue($q){ return $q->where('status','overdue'); }
    public function scopeDueThisMonth($q){ return $q->whereMonth('due_date', now()->month)->whereYear('due_date', now()->year); }

    protected static function booted()
    {
        static::creating(function ($inv) {
            if (empty($inv->invoice_number)) {
                $inv->invoice_number = 'INV-'.now()->format('Ym').'-'.str_pad((Invoice::whereYear('created_at', now()->year)->count()+1),5,'0',STR_PAD_LEFT);
            }
            if (empty($inv->total_amount)) {
                $inv->total_amount = $inv->amount + $inv->late_fee;
            }
        });
    }
}

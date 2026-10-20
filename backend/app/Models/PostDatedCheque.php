<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostDatedCheque extends Model
{
    use HasFactory;

    protected $fillable = [
        'cheque_no',
        'bank',
        'branch',
        'amount',
        'currency',
        'payer',
        'payer_company',
        'payee',
        'lease_code',
        'unit_code',
        'property_name',
        'issued_on',
        'due_on',
        'deposited_on',
        'status',
        'instalment_label',
        'bounce_case_code',
        'notes',
    ];

    protected $casts = [
        'issued_on' => 'date',
        'due_on' => 'date',
        'deposited_on' => 'date',
        'amount' => 'decimal:2',
    ];

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDeposited($query)
    {
        return $query->where('status', 'deposited');
    }

    public function scopeCleared($query)
    {
        return $query->where('status', 'cleared');
    }

    public function scopeReturned($query)
    {
        return $query->where('status', 'returned');
    }

    public function scopeDueSoon($query, int $days = 30)
    {
        return $query->where('status', 'pending')
            ->where('due_on', '<=', now()->addDays($days))
            ->where('due_on', '>=', now());
    }
}
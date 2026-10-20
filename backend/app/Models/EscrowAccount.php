<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EscrowAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'property_id',
        'property_name',
        'bank',
        'iban',
        'account_number',
        'opening_balance',
        'deposits',
        'withdrawals',
        'closing_balance',
        'currency',
        'authority_limit',
        'status',
        'opened_on',
        'last_reconciled_on',
        'manager_name',
        'notes',
    ];

    protected $casts = [
        'opened_on' => 'date',
        'last_reconciled_on' => 'date',
        'opening_balance' => 'decimal:2',
        'deposits' => 'decimal:2',
        'withdrawals' => 'decimal:2',
        'closing_balance' => 'decimal:2',
        'authority_limit' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeFrozen($query)
    {
        return $query->where('status', 'frozen');
    }
}
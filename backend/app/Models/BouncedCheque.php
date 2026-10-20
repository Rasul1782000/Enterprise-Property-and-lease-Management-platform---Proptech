<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BouncedCheque extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'cheque_no',
        'bank',
        'amount',
        'currency',
        'tenant_name',
        'tenant_company',
        'lease_code',
        'unit_code',
        'property_name',
        'bounced_on',
        'bounce_reason',
        'stage',
        'recovered_amount',
        'outstanding',
        'recovery_percent',
        'actions_taken',
        'next_action_due',
        'aging_days',
        'case_manager',
        'priority',
        'legal_notice_sent',
        'notes',
    ];

    protected $casts = [
        'bounced_on' => 'date',
        'next_action_due' => 'date',
        'amount' => 'decimal:2',
        'recovered_amount' => 'decimal:2',
        'outstanding' => 'decimal:2',
        'recovery_percent' => 'decimal:2',
        'legal_notice_sent' => 'boolean',
    ];

    public function scopeNew($query)
    {
        return $query->where('stage', 'new');
    }

    public function scopeInRecovery($query)
    {
        return $query->whereIn('stage', ['notified', 'promise_to_pay', 'partially_recovered']);
    }

    public function scopeRecovered($query)
    {
        return $query->where('stage', 'recovered');
    }

    public function scopeCritical($query)
    {
        return $query->where('priority', 'critical');
    }

    public function scopeOverdueAction($query)
    {
        return $query->where('next_action_due', '<', now());
    }
}
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number', 'invoice_id', 'tenant_id', 'amount', 'method', 'reference', 'paid_at', 'receipt_pdf_path', 'notes', 'recorded_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    protected static function booted()
    {
        static::creating(function ($pay) {
            if (empty($pay->payment_number)) {
                $pay->payment_number = 'PAY-'.now()->format('Ym').'-'.str_pad((Payment::count() + 1), 5, '0', STR_PAD_LEFT);
            }
        });
        static::created(function ($pay) {
            // auto update invoice status
            $invoice = $pay->invoice;
            $totalPaid = $invoice->payments()->sum('amount');
            if ($totalPaid >= $invoice->total_amount) {
                $invoice->update(['status' => 'paid']);
            } elseif ($totalPaid > 0) {
                $invoice->update(['status' => 'partial']);
            }
        });
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VaultAsset extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'asset_type',
        'title',
        'property_id',
        'property_name',
        'unit_code',
        'holder',
        'safe_id',
        'safe_location',
        'status',
        'deposited_on',
        'expires_on',
        'value',
        'document_count',
        'notes',
    ];

    protected $casts = [
        'deposited_on' => 'date',
        'expires_on' => 'date',
        'value' => 'decimal:2',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function scopeSealed($query)
    {
        return $query->where('status', 'sealed');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReleased($query)
    {
        return $query->where('status', 'released');
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'expired');
    }
}
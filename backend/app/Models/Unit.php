<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'building_id', 'unit_number', 'name', 'floor', 'sqft', 'bedrooms', 'bathrooms',
        'rent_amount', 'unit_type', 'status', 'amenities', 'description',
    ];

    protected $casts = [
        'sqft' => 'decimal:2',
        'rent_amount' => 'decimal:2',
    ];

    public function building()
    {
        return $this->belongsTo(Building::class);
    }

    public function property()
    {
        return $this->hasOneThrough(Property::class, Building::class, 'id', 'id', 'building_id', 'property_id');
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLease()
    {
        return $this->hasOne(Lease::class)->where('status', 'active')->latestOfMany();
    }

    public function scopeVacant($q)
    {
        return $q->where('status', 'vacant');
    }

    public function scopeOccupied($q)
    {
        return $q->where('status', 'occupied');
    }
}

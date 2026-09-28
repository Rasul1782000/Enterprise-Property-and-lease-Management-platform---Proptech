<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'type', 'address', 'city', 'state', 'zip', 'country',
        'total_area_sqft', 'year_built', 'manager_id', 'description', 'image_path', 'status',
    ];

    protected $casts = [
        'total_area_sqft' => 'decimal:2',
        'year_built' => 'integer',
    ];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function buildings()
    {
        return $this->hasMany(Building::class);
    }

    public function units()
    {
        return $this->hasManyThrough(Unit::class, Building::class);
    }

    // computed: occupancy rate
    public function getOccupancyRateAttribute(): float
    {
        $total = $this->units()->count();
        if ($total === 0) {
            return 0;
        }
        $occupied = $this->units()->where('status', 'occupied')->count();

        return round($occupied / $total * 100, 2);
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeSearch($q, $term)
    {
        return $q->where(fn ($qq) => $qq->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%"));
    }
}

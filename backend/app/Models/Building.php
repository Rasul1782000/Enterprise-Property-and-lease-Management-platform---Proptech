<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Building extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['property_id', 'name', 'code', 'floors', 'year_built', 'construction_type', 'description'];

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function units()
    {
        return $this->hasMany(Unit::class);
    }
}

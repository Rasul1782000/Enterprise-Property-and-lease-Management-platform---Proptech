<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'company_name', 'id_number',
        'date_of_birth', 'address', 'emergency_contact', 'status', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    public function user()
    {
        return $this->hasOne(User::class);
    }

    public function leases()
    {
        return $this->hasMany(Lease::class);
    }

    public function activeLeases()
    {
        return $this->leases()->where('status', 'active');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function documents()
    {
        // Ordering is left to the caller so QueryBuilder can apply its own.
        return $this->hasMany(TenantDocument::class);
    }

    public function scopeSearch($q, $term)
    {
        return $q->where(fn ($qq) => $qq->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
    }
}

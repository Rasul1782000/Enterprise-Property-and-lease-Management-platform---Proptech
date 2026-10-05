<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TenantDocument extends Model
{
    use HasFactory;

    /** Files the portal accepts for a tenant document, keyed by the stored category. */
    public const CATEGORIES = [
        'id_document',
        'lease_agreement',
        'payment_receipt',
        'insurance',
        'tax_form',
        'correspondence',
        'other',
    ];

    protected $fillable = [
        'tenant_id', 'name', 'category', 'disk', 'path', 'mime_type', 'size_kb', 'uploaded_by',
    ];

    protected $casts = [
        'size_kb' => 'integer',
    ];

    protected $appends = ['url', 'extension'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * A time-limited link straight to the object, so the browser never has to
     * proxy the bytes through the API. Falls back to a proxying API route when
     * the disk cannot presign (a local disk, or S3 without the SDK).
     */
    public function getUrlAttribute(): string
    {
        try {
            return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes(15));
        } catch (\Throwable) {
            return route('api.tenants.documents.download', [
                'tenant' => $this->tenant_id,
                'document' => $this->id,
            ]);
        }
    }

    /** Lower-cased extension without the dot, e.g. `pdf`. */
    public function getExtensionAttribute(): string
    {
        return strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
    }
}
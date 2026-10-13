<?php

declare(strict_types=1);

namespace Shardevcom\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Track product import sessions.
 */
final class ProductImport extends Model
{
    use HasFactory;

    protected $table = 'product_imports';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'file_path',
        'disk',
        'format',
        'total_rows',
        'processed_rows',
        'successful_rows',
        'failed_rows',
        'status',
        'started_at',
        'completed_at',
        'created_by',
        'granted_permissions',
        'error_summary',
    ];

    protected $casts = [
        'total_rows' => 'integer',
        'processed_rows' => 'integer',
        'successful_rows' => 'integer',
        'failed_rows' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'granted_permissions' => 'array',
        'error_summary' => 'array',
    ];

    public function rows(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductImportRow::class, 'import_id');
    }

    public function markProcessing(): void
    {
        $this->update(['status' => 'processing']);
    }

    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_summary' => ['message' => $error],
        ]);
    }
}
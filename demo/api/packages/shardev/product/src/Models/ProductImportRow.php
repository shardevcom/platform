<?php

declare(strict_types=1);

namespace Shardevcom\Product\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Track individual row results for product imports.
 */
final class ProductImportRow extends Model
{
    use HasFactory;

    protected $table = 'product_import_rows';

    protected $fillable = [
        'import_id',
        'row_number',
        'data',
        'status',
        'product_id',
        'error_code',
        'error_message',
        'error_details',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'data' => 'array',
        'error_details' => 'array',
        'product_id' => 'integer',
    ];

    public function import(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ProductImport::class, 'import_id', 'id');
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public static function createSuccess(string $importId, int $rowNumber, array $data, int $productId): self
    {
        return self::create([
            'import_id' => $importId,
            'row_number' => $rowNumber,
            'data' => $data,
            'status' => 'success',
            'product_id' => $productId,
        ]);
    }

    public static function createFailure(string $importId, int $rowNumber, array $data, string $errorCode, string $errorMessage, array $errorDetails = []): self
    {
        return self::create([
            'import_id' => $importId,
            'row_number' => $rowNumber,
            'data' => $data,
            'status' => 'failed',
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'error_details' => $errorDetails,
        ]);
    }
}
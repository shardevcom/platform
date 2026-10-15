<?php

declare(strict_types=1);

namespace Shardevcom\Persistence;

use Illuminate\Database\Eloquent\Model;
use Shardevcom\Core\Contracts\BlindIndex;

/**
 * Trait for adding blind index support to Eloquent models (T-CASA-014).
 *
 * Lives in the persistence package because it is an Eloquent concern: core
 * must not import Eloquent, so the model-facing trait belongs next to the
 * other Eloquent adapters.
 *
 * Usage:
 *
 * class User extends Model {
 *     use HasBlindIndexes;
 *
 *     protected $blindIndexes = [
 *         'email' => ['email', 'emails'],      // column => [contexts...]
 *         'phone' => ['phone', 'phones'],
 *         'document_number' => ['document'],
 *     ];
 * }
 *
 * The trait automatically creates blind indexes when the model is saved.
 * Add corresponding columns to your migration:
 * $table->string('email_blind_idx')->unique()->nullable();
 * $table->string('phone_blind_idx')->unique()->nullable();
 */
trait HasBlindIndexes
{
    /**
     * Map of column names to blind index contexts.
     *
     * @var array<string, array<int, string>>
     */
    protected array $blindIndexes = [];

    /**
     * The blind index service instance.
     */
    protected ?BlindIndex $blindIndexService = null;

    /**
     * Boot the trait.
     */
    protected static function bootHasBlindIndexes(): void
    {
        static::saving(function (Model $model) {
            if (! $model->hasBlindIndexesConfigured()) {
                return;
            }

            foreach ($model->getBlindIndexes() as $column => $contexts) {
                $value = $model->getAttribute($column);

                if ($value === null || $value === '') {
                    continue;
                }

                // Create blind index for each context
                foreach ($contexts as $context) {
                    $indexColumn = $model->getBlindIndexColumn($column, $context);
                    $indexValue = $model->getBlindIndexService()
                        ->create((string) $value, $context);

                    $model->setAttribute($indexColumn, $indexValue);
                }
            }
        });
    }

    /**
     * Get the configured blind index map (column => contexts).
     *
     * @return array<string, array<int, string>>
     */
    public function getBlindIndexes(): array
    {
        return $this->blindIndexes;
    }

    /**
     * Check if the model has blind indexes configured.
     */
    public function hasBlindIndexesConfigured(): bool
    {
        return ! empty($this->blindIndexes);
    }

    /**
     * Get the blind index column name for a given column and context.
     */
    public function getBlindIndexColumn(string $column, string $context): string
    {
        return "{$column}_blind_idx";
    }

    /**
     * Get the blind index service instance.
     */
    public function getBlindIndexService(): BlindIndex
    {
        return $this->blindIndexService ??= app(BlindIndex::class);
    }

    /**
     * Set a custom blind index service (useful for testing).
     */
    public function setBlindIndexService(BlindIndex $service): void
    {
        $this->blindIndexService = $service;
    }

    /**
     * Search by blind index.
     *
     * Usage: User::byBlindIndex('email', 'test@example.com', 'email')->first()
     */
    public static function byBlindIndex(string $column, string $value, string $context): self
    {
        $indexValue = app(BlindIndex::class)->create($value, $context);

        $indexColumn = (new static())->getBlindIndexColumn($column, $context);

        return static::where($indexColumn, $indexValue);
    }

    /**
     * Verify a value against a stored blind index.
     */
    public function verifyBlindIndex(string $column, string $value, string $context): bool
    {
        $indexColumn = $this->getBlindIndexColumn($column, $context);
        $storedIndex = $this->getAttribute($indexColumn);

        if ($storedIndex === null) {
            return false;
        }

        return app(BlindIndex::class)->verify($value, $storedIndex, $context);
    }
}

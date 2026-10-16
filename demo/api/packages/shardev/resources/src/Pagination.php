<?php

declare(strict_types=1);

namespace Shardevcom\Resources;

use Shardevcom\Core\Exceptions\ValidationFailed;

/**
 * Validated pagination (T-040). Page and per-page must be >= 1.
 * Upper caps (T-042) are applied by QueryOptions, not here.
 */
final class Pagination
{
    private function __construct(
        public readonly ?int $page,
        public readonly ?int $perPage,
    ) {
    }

    public static function make(int $page = 1, int $perPage = 15): self
    {
        $errors = [];

        if ($page < 1) {
            $errors['page'] = ['Page must be at least 1.'];
        }

        if ($perPage < 1) {
            $errors['per_page'] = ['Per page must be at least 1.'];
        }

        if ($errors !== []) {
            throw new ValidationFailed($errors);
        }

        return new self($page, $perPage);
    }

    /**
     * Bulk operations (export) read the whole result set, not a page.
     * The persistence layer skips the paginator when this is set.
     */
    public static function unlimited(): self
    {
        return new self(null, null);
    }

    public function isUnlimited(): bool
    {
        return $this->page === null;
    }

    public function capped(int $maxPerPage): self
    {
        if ($this->isUnlimited()) {
            return $this;
        }

        return new self($this->page, min($this->perPage, max(1, $maxPerPage)));
    }
}

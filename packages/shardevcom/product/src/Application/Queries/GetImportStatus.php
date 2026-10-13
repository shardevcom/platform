<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Repositories\ProductImportRepository;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Read the status card of an import session (T-CASA-015, T-CASA-029).
 *
 * Requires 'products.import' (the permission that governs the whole import
 * workflow), decided by the same lifecycle funnel as submission. The session
 * is additionally scoped to its creator: a different user with import rights
 * answers 404, never a peek at someone else's session.
 */
final class GetImportStatus
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImportRepository $imports,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function execute(string $importId, ApplicationContext $context): array
    {
        return $this->products->import(
            $context,
            fn (): array => $this->imports->status($importId, $context->userId()),
        );
    }
}
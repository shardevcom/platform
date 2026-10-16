<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Queries;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Repositories\ProductImportRepository;
use Shardevcom\Product\Repositories\ProductRepository;

/**
 * Page of failed rows of an import session (T-CASA-015, T-CASA-029).
 *
 * Requires 'products.import' via the same lifecycle funnel as submission,
 * so a reader without import rights cannot crawl another session's rows.
 * The session is additionally scoped to its creator: a different user with
 * import rights answers 404, not the failed rows.
 */
final class GetImportErrors
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImportRepository $imports,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function execute(string $importId, ApplicationContext $context): array
    {
        return $this->products->import(
            $context,
            fn (): array => $this->imports->errors($importId, 100, $context->userId()),
        );
    }
}
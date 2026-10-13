<?php

declare(strict_types=1);

namespace Shardevcom\Product\Application\Commands;

use Illuminate\Support\Str;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Product\Jobs\PrepareProductImport;
use Shardevcom\Product\Repositories\ProductImportRepository;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Product\Support\ImportFileValidator;

/**
 * Import products from an Excel/CSV file stored by an adapter (T-CASA-015,
 * T-CASA-016).
 *
 * Requires 'products.import' permission. The gate is the same lifecycle
 * funnel as every other resource action: the repository decides, this class
 * only runs after it. Input is transport-neutral (a stored file, not an
 * HTTP upload) so the same workflow can be driven from REST or GraphQL.
 *
 * The request validates the STORED file against T-CASA-016 rules (size,
 * real content, macros, formulas) and then persists the import session and
 * dispatches the async pre-flight (PrepareProductImport), which counts rows
 * and dispatches the chunk batch. No spreadsheet is ever parsed in the
 * request; only raw, read-only content checks.
 */
final class ImportProducts
{
    public function __construct(
        private readonly ProductRepository $products,
        private readonly ProductImportRepository $imports,
        private readonly ImportFileValidator $fileValidator,
    ) {
    }

    /**
     * @param array{source: array{disk: string, path: string}, format?: string} $data
     * @return array{import_id: string, status: string}
     */
    public function execute(array $data, ApplicationContext $context): array
    {
        return $this->products->import($context, fn (): array => $this->submit($data, $context));
    }

    /**
     * @param array{source: array{disk: string, path: string}, format?: string} $data
     * @return array{import_id: string, status: string}
     */
    private function submit(array $data, ApplicationContext $context): array
    {
        $path = $data['source']['path'];
        $disk = $data['source']['disk'];
        $format = $data['format'] ?? 'xlsx';

        $this->fileValidator->validate($disk, $path, $format);

        $importId = (string) Str::uuid();
        $this->imports->start(
            $importId,
            $path,
            $disk,
            $format,
            0,
            $this->marshalUserId($context->userId()),
            $context->permissions(),
        );

        PrepareProductImport::dispatch($importId);

        return [
            'import_id' => $importId,
            'status' => 'queued',
        ];
    }

    private function marshalUserId(?string $userId): ?int
    {
        return $userId !== null && ctype_digit($userId) ? (int) $userId : null;
    }
}
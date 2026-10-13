<?php

declare(strict_types=1);

namespace Shardevcom\Product\Rest\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Product\Application\Commands\CreateProduct;
use Shardevcom\Product\Application\Commands\DeleteProduct;
use Shardevcom\Product\Application\Commands\ImportProducts;
use Shardevcom\Product\Application\Commands\UpdateProduct;
use Shardevcom\Product\Application\Queries\ExportProducts;
use Shardevcom\Product\Application\Queries\GetImportErrors;
use Shardevcom\Product\Application\Queries\GetImportStatus;
use Shardevcom\Product\Application\Queries\GetProduct;
use Shardevcom\Product\Application\Queries\ListProducts;
use Shardevcom\Product\Application\Queries\SelectProducts;
use Shardevcom\Product\Rest\Http\Requests\CreateProductRequest;
use Shardevcom\Product\Rest\Http\Requests\UpdateProductRequest;

/**
 * REST controller for products.
 *
 * Thin adapter: HTTP -> FormRequest -> DTO -> UseCase -> Response.
 * No business logic, no Eloquent, no authorization (handled by lifecycle).
 * File uploads are stored HERE, in the adapter; the use case only sees a
 * transport-neutral disk+path descriptor.
 */
final class ProductController
{
    public function __construct(
        private readonly ListProducts $listProducts,
        private readonly SelectProducts $selectProducts,
        private readonly GetProduct $getProduct,
        private readonly CreateProduct $createProduct,
        private readonly UpdateProduct $updateProduct,
        private readonly DeleteProduct $deleteProduct,
        private readonly ImportProducts $importProducts,
        private readonly ExportProducts $exportProducts,
        private readonly GetImportStatus $getImportStatus,
        private readonly GetImportErrors $getImportErrors,
        private readonly \Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder $contextBuilder,
    ) {}

    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->listProducts->execute($this->queryFrom($request), $context);

        return $this->respond($this->paginated($result, $context));
    }

    /**
     * Dropdown/autocomplete projection (T-CASA-015).
     *
     * Authorizes with products.list and answers exactly the selectFields the
     * resource declares; ?fields can narrow that projection, never widen it
     * (QueryOptions rejects anything outside the allow-list with 422).
     */
    public function select(Request $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->selectProducts->execute($this->queryFrom($request), $context);

        return $this->respond($this->paginated($result, $context));
    }

    /**
     * Bulk ingestion (T-CASA-015). Requires products.import.
     *
     * The multipart file is stored by the adapter; the use case queues a
     * batch of chunk jobs over the stored copy. Answers 202 with the import
     * session id the caller can poll at importStatus.
     */
    public function import(Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $file = $request->file('file');

        if ($file === null || ! $file->isValid()) {
            return response()->json(['error' => ['file' => ['A file is required.']]], 422);
        }

        $path = $file->store('imports', 'local');

        $result = $this->importProducts->execute([
            'source' => ['disk' => 'local', 'path' => $path],
            'format' => (string) $request->input('format', 'xlsx'),
        ], $context);

        return $this->respond(ApiResponse::success($result, 'Import queued.'), 202);
    }

    public function importStatus(string $importId, Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->getImportStatus->execute($importId, $context);

        return $this->respond(ApiResponse::success($result, 'Import status loaded.'));
    }

    public function importErrors(string $importId, Request $request): JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->getImportErrors->execute($importId, $context);

        return $this->respond(ApiResponse::success($result, 'Import errors loaded.'));
    }

    /**
     * Bulk extract (T-CASA-015). Requires products.export, not products.view:
     * a reader role cannot mass-download through this path.
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $query = $this->queryFrom($request);
        $format = (string) $request->query('format', 'xlsx');
        $query['format'] = $format;

        $export = $this->exportProducts->execute($query, $context);

        $extension = $format === 'csv' ? 'csv' : 'xlsx';

        return Excel::download($export, 'products.'.$extension, $extension === 'csv'
            ? \Maatwebsite\Excel\Excel::CSV
            : \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @return array{filters: mixed, includes: mixed, sort: mixed, page: int, per_page: int, fields: mixed}
     */
    private function queryFrom(Request $request): array
    {
        return [
            'filters' => $request->query('filter', []),
            'includes' => $request->query('include', []),
            'sort' => $request->query('sort', []),
            'page' => (int) $request->query('page', 1),
            'per_page' => (int) $request->query('per_page', 15),
            'fields' => $this->stringList($request->query('fields')),
        ];
    }

    /**
     * REST clients may send ?fields=name as well as ?fields[]=name; QueryOptions
     * always speaks in lists.
     *
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return $value === null || $value === '' ? [] : [(string) $value];
        }

        return array_values(array_map(strval(...), $value));
    }

    private function paginated(\Shardevcom\Resources\QueryResult $result, ApplicationContext $context): ApiResponse
    {
        $response = $result->pagination() === null
            ? ApiResponse::success($result->items(), 'Products retrieved.')
            : ApiResponse::success($result->items(), 'Products retrieved.', ApiMeta::fromContext($context)->with(
                ['pagination' => $result->pagination()->toArray()],
            ));

        return $response;
    }

    public function store(\Shardevcom\Product\Rest\Http\Requests\CreateProductRequest $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->createProduct->execute($request->toDTO(), $context);

        return $this->respond(ApiResponse::success($result, 'Product created.'), 201);
    }

    public function show(string $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->getProduct->execute($id, $context);

        return $this->respond(ApiResponse::success($result, 'Product loaded.'));
    }

    public function update(string $id, \Shardevcom\Product\Rest\Http\Requests\UpdateProductRequest $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $result = $this->updateProduct->execute($id, $request->toDTO(), $context);

        return $this->respond(ApiResponse::success($result, 'Product updated.'));
    }

    public function destroy(string $id, Request $request): \Illuminate\Http\JsonResponse
    {
        $context = $this->contextBuilder->fromRequest($request);

        $this->deleteProduct->execute($id, $context);

        return $this->respond(ApiResponse::success(null, 'Product deleted.'));
    }

    /**
     * Serialize an ApiResponse. The status is the transport concern; the
     * envelope itself never decides it.
     */
    private function respond(ApiResponse $response, int $status = 200): \Illuminate\Http\JsonResponse
    {
        return response()->json($response->toArray(), $status);
    }
}
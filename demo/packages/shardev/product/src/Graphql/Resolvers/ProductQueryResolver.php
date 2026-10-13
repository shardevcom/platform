<?php

declare(strict_types=1);

namespace Shardevcom\Product\Graphql\Resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use Shardevcom\Application\ApplicationContext\HttpApplicationContextBuilder;

use Shardevcom\Core\Results\PaginationMeta;
use Shardevcom\Product\Application\Queries\GetProduct;
use Shardevcom\Product\Application\Queries\ListProducts;
use Shardevcom\Product\Application\Queries\SelectProducts;

/**
 * GraphQL query adapter for products (T-073).
 *
 * Translates GraphQL arguments into the transport-neutral query input and
 * hands off to the same use cases the REST controller calls. No Eloquent, no
 * authorization, no validation: those belong to the use case and repository.
 */
final class ProductQueryResolver
{
    /**
     * Relations this resource declares as available for eager loading.
     * Validation is done by the Use Case/Repository via IncludeSet (T-040).
     *
     * @var list<string>
     */
    private const DECLARED_RELATIONS = ['brand'];

    /**
     * @var list<string>
     */
    private const PRODUCT_FIELDS = ['id', 'sku', 'name', 'ean', 'brand_id', 'active', 'created_at', 'updated_at'];

    public function __construct(
        private readonly ListProducts $listProducts,
        private readonly SelectProducts $selectProducts,
        private readonly GetProduct $getProduct,
        private readonly HttpApplicationContextBuilder $contextBuilder,
    ) {
    }

    /**
     * Dropdown/autocomplete projection, the GraphQL twin of
     * GET /products/select (T-CASA-015). Same use case, same fixed
     * allow-list: ?fields can narrow selectFields, never widen it.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function productsSelect(mixed $root, array $args, mixed $context = null, ?ResolveInfo $info = null): array
    {
        $query = (new ArgumentTranslator())->listQuery($args);
        $query['includes'] = $this->requestedRelations($args, $info);

        $result = $this->selectProducts->execute($query, $this->contextBuilder->fromRequest(request()));

        return [
            'data' => $result->items(),
            'pagination' => $this->pagination($result->pagination(), count($result->items())),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function products(mixed $root, array $args, mixed $context = null, ?ResolveInfo $info = null): array
    {
        $query = (new ArgumentTranslator())->listQuery($args);
        $query['includes'] = $this->requestedRelations($args, $info);

        $result = $this->listProducts->execute($query, $this->contextBuilder->fromRequest(request()));

        return [
            'data' => $result->items(),
            'pagination' => $this->pagination($result->pagination(), count($result->items())),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function product(mixed $root, array $args, mixed $context = null, ?ResolveInfo $info = null): array
    {
        return $this->getProduct->execute(
            $args['id'],
            $this->contextBuilder->fromRequest(request()),
            $this->requestedRelations($args, $info),
        );
    }

    /**
     * Relations the client asked for, from the explicit `include` argument and
     * from the selection set.
     *
     * REST makes the client name the include explicitly; GraphQL already states
     * it by asking for the field. A GraphQL client that selects `brand` without
     * passing `include` expects the same data REST would return for
     * `?include=brand`, so both signals are honored here.
     *
     * NOTE: We do NOT filter against DECLARED_RELATIONS here. The Use Case
     * (via QueryOptions/IncludeSet) validates against the ResourceDefinition
     * allow-list. This ensures both transports get the same validation errors.
     *
     * @param array<string, mixed> $args
     * @return list<string>
     */
    private function requestedRelations(array $args, ?ResolveInfo $info): array
    {
        $declared = is_array($args['include'] ?? null)
            ? array_values(array_filter($args['include'], is_string(...)))
            : [];

        $relations = array_values(array_unique([...$declared, ...$this->selectedRelations($info)]));

        return $relations;
    }

    /**
     * Relation fields present anywhere in the selection set under `data`.
     *
     * @return list<string>
     */
    private function selectedRelations(?ResolveInfo $info): array
    {
        if ($info === null) {
            return [];
        }

        $relations = [];

        foreach ($this->walk($info, 0) as $name) {
            if (in_array($name, self::DECLARED_RELATIONS, true)) {
                $relations[] = $name;
            }
        }

        return array_values(array_unique($relations));
    }

    /**
     * Walk the selection set, collecting the names of the direct children of
     * every `Product` selection.
     *
     * GraphQL-php flattens nested selections into dotted keys keyed by depth
     * (for example `data.brand.name`), so the relation is simply the segment
     * right after the product scope.
     *
     * @return list<string>
     */
    private function walk(ResolveInfo $info, int $depth): array
    {
        if ($depth > 5) {
            return [];
        }

        $names = [];

        foreach ($this->scope($info, $depth) as $name) {
            if (in_array($name, self::PRODUCT_FIELDS, true) === false
                && in_array($name, self::DECLARED_RELATIONS, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * The direct children of the current selection level.
     *
     * @return list<string>
     */
    private function scope(ResolveInfo $info, int $depth): array
    {
        $names = [];

        foreach (array_keys($info->getFieldSelection($depth + 2)) as $key) {
            $names[] = explode('.', (string) $key)[$depth] ?? null;
        }

        return array_values(array_filter($names, is_string(...)));
    }

    /**
     * @return array<string, int>
     */
    private function pagination(?PaginationMeta $pagination, int $count): array
    {
        if ($pagination === null) {
            return [
                'total' => 0,
                'count' => 0,
                'per_page' => 0,
                'current_page' => 1,
                'last_page' => 1,
            ];
        }

        return [
            'total' => $pagination->total,
            'count' => $count,
            'per_page' => $pagination->perPage,
            'current_page' => $pagination->page,
            'last_page' => $pagination->lastPage(),
        ];
    }
}
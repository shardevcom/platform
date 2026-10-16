# Fase GraphQL — T-073…T-074

> Regla: NUNCA `Product::query()` en resolvers. NUNCA tipos GraphQL en Core. Siempre mismo Use Case que REST (T-060).

## Avance (2026-10-04)
- DECLARADO DONE (incorrecto): se marcaron T-073 y T-074 completas sin que existiera un solo test de GraphQL sobre HTTP. La capa estaba muerta de punta a punta.

## Avance (2026-10-05) — T-073/T-074 rehechas

**El schema usaba directivas de Lighthouse que habla Eloquent por debajo** (`@paginate`, `@find`, `@create`, `@update`, `@delete`). Eso viola la regla madre: los resolvers nunca tocan el modelo. `{ products { id } }` devolvía `Internal server error`.

- Reescrito `demo/graphql/schema.graphql` con `@field(resolver: ...)`. `ProductConnection` y `PaginationInfo` son tipos planos, ya no `@paginate`. Las mutaciones se resuelven por resolver.
- Nuevos `packages/shardevcom/product/src/Graphql/`:
  - `Resolvers/ProductQueryResolver.php` y `Resolvers/ProductMutationResolver.php`: args → DTO → **mismo Use Case que REST**. Sin `Model`, sin `::query()`.
  - `Resolvers/ArgumentTranslator.php`: GraphQL no tiene tipo map, así que `filter` llega como lista `{field, value}` y REST envía objeto. Solo cambia la FORMA; los allow-lists, los caps y el clamp siguen en `QueryOptions`, así que GraphQL no puede validar algo que REST no validaría.
  - `Scalars/MixedScalar.php`: el schema usaba `Mixed` sin declarar. La clase no puede llamarse `Mixed` porque PHP reserva la palabra.
- `GraphqlServiceProvider`: 
  -quitados los bindings de `ProductQueryResolver`/`ProductMutationResolver`, que **no existían** (`class_exists` = false). El contenedor solo tenía entradas que reventaban al resolverse.
  - `lighthouse.namespaces` ahora incluye `App\GraphQL\Queries` y `Shardevcom\Product\Models` (se pisaba con un solo namespace).
  - Las guardas de seguridad (T-074) escribían `false`/`15`/en claves que esperan **constantes de GraphQL-php**, no booleanos ni tamaños. `DisableIntrospection::__construct()` reventaba antes de resolver cualquier campo. Ahora usan `QueryDepth::DISABLED + 15`.
  - `GraphQLErrorHandler` estaba registrado con `$this->app->singleton(ErrorHandler::class, ...)`, que Lighthouse **no lee**. Va en `lighthouse.error_handlers`. Además implementaba la interfaz equivocada: Lighthouse llama `__invoke(?Error, Closure)`, no `handle(array)`. Reescrito como middleware que enriquece y sigue la cadena.

**Bug del lado app (no del framework):** `demo/api/app/Platform/Authorization/ContextPermissionResolver` hacía `in_array` exacto de `products.view`, así que un token con `products.*` recibía 403 en **todo**. `InMemoryPermissionResolver` sí honraba el wildcard. Corregido.

**Cache de schema:** Lighthouse cachea el schema compilado en `bootstrap/cache/lighthouse-schema.php`. Un cambio de schema no se veía sin limpiarlo, lo que hacía que los tests mintieran. `phpunit.xml` desactiva el cache en testing y `composer test` corre `lighthouse:clear-cache`.

**Tests nuevos:** `demo/api/tests/Feature/Platform/ProductGraphqlEndpointTest.php` (13 casos sobre HTTP real) — ciclo CRUD completo, wildcard, guest 403, falta de ability, `RESOURCE_NOT_FOUND` con el mismo code que REST, filtro no declarado rechazado por el use case, filtro declarado aplicado, clamp de `per_page`, include no declarado, sort, conflicto de sku duplicado, error de validación de input, y campo inexistente.

**Mutación:** reemplacé el cuerpo de `ProductQueryResolver::products()` por `Product::query()->get()` (el bug original) → **7 tests en rojo**, incluyendo autorización, allow-lists y clamp. Restaurado.

**Verificación:** `composer ci` → root `OK (218 tests, 598 assertions)`, demo `138 passed (1433 assertions)`, exit 0.

# Shardev Platform

> **Este repo es el framework** — como `laravel/framework` + installer — no una app generada.
> El CLI instala una versión de Laravel y acondiciona el sistema (`packages/`, providers,
> `config/platform.php`, `.env`, migraciones, seed demo) para iniciar cualquier proyecto nuevo.

## Layout

```text
./                          # framework: CLI + fuente de packages + backlog
├── bin/platform            # CLI (new/list/doctor) — cero dependencias
├── src/                    # instalador (Framework, matriz compatibilidad, ...)
├── packages/               # FUENTE oficial: core, resources, authorization,
│                           # configuration, persistence, rest, graphql
├── tests/                  # tests del framework (phpunit, sin Laravel)
├── backlog/ + BACKLOG.md   # backlog técnico por fases
├── AGENTS.md / CLAUDE.md   # instrucciones para IAs
└── demo/                   # app de REFERENCIA — ella misma un workspace
    ├── platform.json       #   manifiesto del workspace demo (fuente de verdad)
    ├── api/                #   Laravel 12 + copia de packages/ + config/platform.php
    ├── web/                #   monorepo Vite/React: apps/ por guard + packages/ + plugins/
    ├── mobile/             #   monorepo Flutter: un flavor por guard + packages/ + plugins/
    └── desktop/            #   monorepo Gradle/JavaFX: una app por guard + packages/ + plugins/
```

`demo/` es el output que `platform new demo --laravel=12` produce. Hoy está
scaffoldeada a mano y es **a la vez un workspace** (`demo/platform.json`, escrito
por `WorkspaceManifest`): `platform status` / `doctor` la tratan como cualquier
workspace. `demo/api/` (`laravel/laravel` 12.x + copia de `packages/` +
`config/platform.php`), `demo/web/` (cliente de referencia: `apps/{user,admin,employee}`,
una app por guard, sobre `packages/{ui,core,plugin-kit}` + `plugins/`), `demo/mobile/`
(cliente de referencia Flutter: un flavor por guard sobre `packages/{plugin_kit,core}` + `plugins/`)
y `demo/desktop/` (cliente de referencia JavaFX: una app Gradle por guard sobre
`packages/{plugin-kit,core}` + `plugins/`).
`demo/api/tests/` corre la suite de la app generada (Feature); `tests/` la del framework (Unit).

## 1. Qué es Shardev Platform

Plataforma API modular sobre Laravel. Proporciona:

- **Core reutilizable**: Contratos, contexto de aplicación, acciones de recurso, excepciones de dominio, respuestas API estandarizadas
- **Repository pattern con autorización integrada**: Frontera autorizada a recursos (auth + validación + query constraints + lifecycle) — no un wrapper de Model
- **REST y GraphQL como adapters delgados**: Comparten Use Cases + Repository. Cero duplicación de auth/validación/reglas
- **Sistema de configuración extensible**: Providers con visibilidad (public/authenticated/admin) y resultados parciales (207)
- **CLI cero-dependencias**: `platform new` (workspace), `platform add|remove|install|build|dev|status`, `platform guard add|list`, `platform package make`, `platform demo`, `platform plugin list|show`, `platform doctor`

## 2. Versiones soportadas

| Componente | Versión |
|------------|---------|
| PHP        | ^8.2 (visión ^8.4) |
| Laravel    | ^12.0 |
| SQLite     | Default local DB |

`platform doctor` verifica la matriz de compatibilidad.

## 3. Crear un proyecto nuevo (workspace)

`platform new` genera un **workspace**: la raíz no es Laravel, sino un
`platform.json` (fuente de verdad) con la API y los clientes como componentes
independientes (`api/`, `web/`, `mobile/`, `desktop/`).

```bash
# Instalar el framework
composer install

# Solo la API (por defecto)
bin/platform new mi-proyecto --laravel=12 --no-interaction

# API + web + móvil + desktop
bin/platform new mi-proyecto --with=api,web,mobile,desktop

# Fijar los guards que sirven los clientes
bin/platform new mi-proyecto --with=api,web --guards=employee

# Añadir un cliente después
cd mi-proyecto
bin/platform add web --guards=web,admin
```

Cada componente se opera desde la raíz del workspace con un único CLI:

```bash
bin/platform status                  # qué componentes hay y su estado
bin/platform install [component]     # composer / npm install / flutter pub get / gradlew
bin/platform build   [component]     # dump-autoload / npm run build / flutter build / gradlew build
bin/platform dev     api             # php artisan serve (o npm run dev / flutter run / gradlew run)
bin/platform doctor                  # incluye la suite Workspace
```

```bash
# Verificar la API
bin/platform dev api
# GET http://localhost:8000/admin/api/v1/products → 200
```

## 4. Packages

La carpeta `packages/` en el proyecto generado contiene los módulos de la plataforma. Cada package sigue la estructura:

```text
packages/<nombre>/
├── src/
│   ├── ServiceProvider.php
│   ├── Application/      # Use Cases, DTOs, Validación
│   ├── Contracts/        # Interfaces de frontera
│   ├── Infrastructure/   # Adapters (Eloquent, etc.)
│   ├── Repositories/     # Repository + Eloquent persistence
│   ├── Rest/             # Controllers, FormRequests
│   ├── Graphql/          # Resolvers, Types
│   ├── Resources/        # ResourceDefinition
│   └── ...
├── config/
├── routes/
├── database/migrations/
├── tests/
└── composer.json         # extra.platform.provider
```

### Crear un package

```bash
# Desde el proyecto generado
platform package make catalogo --dry-run  # muestra árbol esperado
platform package make catalogo            # crea scaffold + registra provider
platform package:list                     # lista oficiales + usuario
```

### Configurar directorios de packages y vendor

El CLI permite personalizar dónde viven los packages y el namespace del vendor:

```bash
# Ver configuración actual
platform config

# Cambiar directorio de packages (relativo al proyecto)
platform config packages src/platform

# Cambiar vendor namespace para nuevos packages
platform config vendor acme
```

**Importante:**
- Los packages **oficiales** del framework se instalan siempre en `packages/shardevcom/` (o el directorio configurado)
- Los packages **demo** y los generados con `platform package make` viven en el vendor configurado (`packages/<vendor>/`)
- El directorio `vendor/` real de Composer (dependencias externas) **no se altera** — solo es para packages de la plataforma

---

## 5. Documentación Automática — `shardevcom/documentation`

Genera documentación OpenAPI 3.1 y GraphQL automáticamente desde rutas, controladores y esquemas.

```bash
# Generar documentación (todos los formatos)
php artisan docs:generate

# Formatos específicos
php artisan docs:generate --format=openapi-json --format=html --format=markdown

# Con servidor de vista previa
php artisan docs:generate --serve

# Directorio de salida personalizado
php artisan docs:generate --output=storage/app/docs
```

**Formatos soportados:**
- `openapi-json` / `openapi-yaml` — Especificación OpenAPI 3.1
- `html` — Swagger UI + Redoc + GraphiQL
- `markdown` — Documentación legible en `.md`
- `graphql-sdl` — Schema Definition Language
- `graphql-introspection` — Introspection JSON
- `postman` — Colección Postman
- `insomnia` — Colección Insomnia

**UI Web integrada:**
```
/docs          # Swagger UI
/docs/redoc    # Redoc
/docs/graphql  # GraphiQL Explorer
/docs/openapi.json
```

**Configuración** (`config/documentation.php`):
```php
'generation' => [
    'formats' => ['openapi-json', 'html', 'graphql-sdl'],
    'output_dir' => 'public/docs',
    'info' => [
        'title' => 'Mi API',
        'version' => '1.0.0',
    ],
],
'parsing' => [
    'routes' => ['include' => ['api/*'], 'exclude' => ['_debugbar/*']],
    'controllers' => ['enabled' => true, 'paths' => ['app/Http/Controllers']],
    'graphql' => ['enabled' => true, 'schema_path' => 'graphql/schema.graphql'],
],
```

---

## 6. REST API — `/api/v1`

Versionado solo en capa REST (`/api/v1`), nunca en Core.

```bash
# Listar productos con filtros, includes, paginación, campos
GET /api/v1/products?filter[active]=true&include=brand&sort=-created_at&page=1&per_page=15&fields=id,sku,name

# Crear producto
POST /api/v1/products
Content-Type: application/json
Authorization: Bearer <token>
{
  "sku": "ABC-123",
  "name": "Producto Demo",
  "brand_id": 1,
  "active": true
}

# Obtener producto
GET /api/v1/products/{id}

# Actualizar producto (PATCH parcial)
PATCH /api/v1/products/{id}
{
  "name": "Nuevo nombre"
}

# Eliminar producto
DELETE /api/v1/products/{id}
```

**Respuesta envelope estándar:**
```json
{
  "status": "success",
  "code": 200,
  "message": "Products retrieved.",
  "data": [...],
  "meta": {
    "pagination": { "total": 100, "per_page": 15, "current_page": 1, "last_page": 7 }
  }
}
```

**Errores mapeados desde dominio:**
- `Unauthorized` → 401
- `Forbidden` → 403
- `ResourceNotFound` → 404
- `ValidationFailed` → 422
- `Conflict` → 409
- `BusinessRuleViolation` → 422
- `ResourceOperationNotSupported` → 405

Sin leak de SQL/trace con `APP_DEBUG=false`.

## 7. GraphQL API — `/graphql`

Mismos Use Cases + Repository que REST. Mapeo args → `QueryOptions`.

```graphql
# Query con filtros, paginación, selección de campos
query {
  products(filter: {active: true}, orderBy: "-created_at", page: 1, perPage: 15, fields: ["id", "sku", "name"]) {
    data {
      id
      sku
      name
      brand { id name }
    }
    pagination {
      total
      count
      per_page
      current_page
      last_page
    }
  }
}

# Mutation
mutation {
  createProduct(input: {sku: "ABC-123", name: "Producto", brandId: 1, active: true}) {
    id
    sku
    name
  }
}
```

**Límites obligatorios:** `maxDepth`, `maxComplexity`, `maxFirst`, `pagination caps`, DataLoader/batching para evitar N+1.

**Errores GraphQL spec:**
```json
{
  "errors": [{
    "message": "Action [update] on resource [products] is forbidden.",
    "extensions": { "code": "FORBIDDEN" }
  }]
}
```

## 8. Autorización — `AuthorizationManager`

Punto centralizado. Repository pregunta `authorize('products', UPDATE, $context)`; no sabe cómo se almacenan permisos. Permiso derivado `{resource}.{action}`.

```php
// En Repository (automático vía lifecycle)
$manager->authorize('products', ResourceAction::UPDATE, $context);

// Adapter por defecto: Laravel Gate + config
// Intercambiable: Spatie, servicio externo, RBAC, ABAC vía PermissionResolver
```

**Regla:** NUNCA `Gate::authorize` ni `$user->can` en controllers/resolvers. Solo en `AuthorizationManager`/adapter.

## 9. Configuración — `ConfigurationRegistry`

Providers registrados desde cualquier package con visibilidad:

```php
// Provider demo 'core'
ConfigurationProvider::make('core', fn () => [
    'locale' => 'es-CO',
    'timezone' => 'America/Bogota',
], ConfigurationVisibility::PUBLIC);

// Endpoint unificado con resultados parciales (207)
GET /api/configuration?include=core,preferences,inexistente
```

## 10. Extender la plataforma

### Nuevo recurso (ej. `orders`)

1. `platform package make orders`
2. Definir `OrderResourceDefinition` (filters, includes, sorts, fields, repositoryClass, policy)
3. Crear `OrderRepository` extendiendo `AbstractResourceRepository`
4. Implementar Use Cases (`ListOrders`, `CreateOrder`, etc.)
5. Registrar en `ResourceRegistry` vía ServiceProvider
6. Adapters REST/GraphQL delgados → Use Cases

### Nuevo adapter de persistencia

Implementar `QueryExecutor` + `EloquentResourcePersistence` (o otro ORM) en `Infrastructure/`.

## 11. Testing

### Suite del framework (root)
```bash
composer test
# phpunit + composer validate
```

### Suite de la app generada (demo/api/)
```bash
composer test --working-dir=demo/api
# 141 tests: Repository, Use Cases, REST, GraphQL, Config, Parity, Arch
```

### Parity REST ↔ GraphQL
Mismo seed/usuario: crear con dato inválido, leer sin permiso, filtrar no permitido, paginar → misma decisión/validación en ambos transports.

### Architecture tests (T-110)
```bash
cd demo/api && php artisan test --filter=LayeringRulesTest
# Core↛Eloquent/HTTP, Adapters↛Model/::query(), Auth solo en AuthorizationManager, etc.
```

## 12. Health Check — `platform doctor`

```bash
# Desde el proyecto generado
../bin/platform doctor
# o desde root del framework apuntando al proyecto
bin/platform doctor --project=../mi-proyecto
```

**46 checks en 8 suites (+2 suites cuando el proyecto es un workspace):**
- **Toolchain**: PHP, Composer, Laravel version, Framework version
- **Project files**: .env, autoloader, config/platform.php, writable dirs
- **Packages**: discovery, providers, bootstrapper, autoload wiring
- **Package Integration**: providers loadable, migrations, routes registered, autoload working, per-package verification
- **Database**: sqlite connection
- **Running application**: REST routes, GraphQL route
- **API Functionality**: REST Products, GraphQL, Configuration, Widgets endpoints
- **Authentication**: Passport installed, keys generated, guards/providers configured, password reset tables, personal access clients
- **Workspace** (solo workspace): cada componente de `platform.json` existe en disco
- **Client requirements** (solo workspace): según los clientes instalados, comprueba el toolchain que necesitan para compilar/ejecutarse (Node/npm, Flutter/Dart, JDK); si falta, sugiere cómo arreglarlo (y si está instalado pero fuera del PATH, da el `export PATH=...` exacto)

**Salida sana:**
```
+ Application is ready.
Exit: 0
```

**Con fallos:** lista accionable + exit != 0.

---

## 13. Smoke Test — Verificación rápida (< 10 min)

Objetivo: un dev nuevo llega a `GET /api/v1/products → 200` y `{ products { id name } }` con sqlite.

```bash
# 1. Crear proyecto (o usar demo/api existente)
cd /ruta/al/framework
bin/platform new mi-proyecto --laravel=12 --no-interaction
cd mi-proyecto

# 2. Levantar servidor
php artisan serve
# → http://127.0.0.1:8000

# 3. Obtener token (Passport) — crear usuario admin + personal access token
php artisan tinker --execute="
\$u = App\Models\AdminUser::factory()->create();
\$token = \$u->createToken('smoke-test', ['products.view', 'products.create'])->accessToken;
echo \$token . PHP_EOL;
"
# Copia el token que imprime (ej. eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...)

# 4. Probar REST — lista productos (debe devolver 200 con data vacía [])
curl -H "Authorization: Bearer <TOKEN>" http://127.0.0.1:8000/admin/api/v1/products
# → {"status":"success","code":"OK","message":"Products retrieved.","data":[],"meta":{"pagination":{...}}}

# 5. Crear un producto
curl -X POST -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"sku":"SMK-1","name":"Smoke Product"}' \
  http://127.0.0.1:8000/admin/api/v1/products
# → 201 con el producto creado

# 6. Probar GraphQL — query básica
curl -X POST -H "Authorization: Bearer <TOKEN>" -H "Content-Type: application/json" \
  -d '{"query":"{ products { data { id sku name } } }"}' \
  http://127.0.0.1:8000/admin/graphql
# → {"data":{"products":{"data":[{"id":"...","sku":"SMK-1","name":"Smoke Product"}]}}}

# 7. Health check
../bin/platform doctor
# → 17 checks ✓ + "Application is ready."
```

**Notas:**
- Las rutas usan prefijo multi-plataforma (`admin/`, `employee/`, `web/`). El guard `admin` es el default.
- El token necesita scope `products.view` (lectura) y `products.create` (escritura).
- SQLite se crea automático en `database/database.sqlite` (`.env` ya configurado).

---

## 14. Uso rápido

```bash
# Framework
composer install
composer test
bin/platform --version
bin/platform list
bin/platform new mi-app --laravel=12 --no-interaction
bin/platform new mi-app --with=api,web,mobile,desktop
cd mi-app && bin/platform status && bin/platform install && bin/platform build && cd ..
bin/platform config
bin/platform config packages src/platform
bin/platform config vendor acme
bin/platform demo list
bin/platform demo add product
bin/platform demo add product --with-client
bin/platform demo add product --with-mobile-client
bin/platform demo add product --with-desktop-client
bin/platform plugin list
bin/platform plugin show product
bin/platform doctor

# App generada (demo/api) y clientes de referencia (demo/web, demo/mobile, demo/desktop)
composer test --working-dir=demo/api
(cd demo/api && php artisan route:list | grep -E "products|configuration")
(cd demo/web && npm install && npm test && npm run dev:admin)   # o dev:employee / dev:user
(cd demo/mobile && flutter pub get && flutter test && flutter run --dart-define=GUARD=admin)
(cd demo/desktop && ./gradlew build && ./gradlew :apps:admin:run)
```

## Gate completo (CI)

```bash
composer ci
# Ejecuta: composer validate + phpunit (framework) + composer test (demo)
```

## Decisiones clave

- CLI sin dependencias (solo `composer` externo vía shell)
- `packages/` del root es fuente única: el instalador los copia al proyecto generado
- PHP floor `^8.2` (entorno actual 8.4; visión apunta a ^8.4)
- REST y GraphQL son adapters delgados — Core no sabe quién llama
- Repository = frontera autorizada (auth + validación + query + lifecycle), no wrapper de Model
- Validación en un solo lugar (DTO en Use Case). Auth en un solo lugar (AuthorizationManager)
- `config/platform.php` mínimo; `.env` sqlite por defecto

---

## 15. Observabilidad (T-121)

### Contratos sin vendor lock

La plataforma define interfaces para logging, métricas y tracing que puedes implementar con tu proveedor favorito (Monolog, Prometheus, OpenTelemetry, etc.):

```php
// packages/shardevcom/core/src/Contracts/Logger.php
interface Logger {
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(string $level, string $message, array $context = []): void;
}

// packages/shardevcom/core/src/Contracts/Meter.php
interface Meter {
    public function counter(string $name, array $tags = []): void;
    public function gauge(string $name, float $value, array $tags = []): void;
    public function histogram(string $name, float $value, array $tags = []): void;
    public function time(string $name, callable $callable, array $tags = []): mixed;
    public function withTags(array $defaultTags): self;
}

// packages/shardevcom/core/src/Contracts/Tracer.php
interface Tracer {
    public function startSpan(string $name, array $attributes = []): Span;
    public function currentSpan(): ?Span;
    public function inSpan(string $name, callable $callable, array $attributes = []): mixed;
}
```

### `request_id` / `correlation_id` en el lifecycle

El `ApplicationContext` genera y propaga automáticamente `request_id` (UUID v4) y `correlation_id` (heredado del header `X-Correlation-ID` o igual a `request_id`):

```php
$context = ApplicationContext::create([
    'requestId' => $request->header('X-Request-ID'),     // opcional
    'correlationId' => $request->header('X-Correlation-ID'), // opcional
]);
// $context->requestId() y $context->correlationId() disponibles en hooks
```

Estos IDs llegan a los hooks del lifecycle (`beforeAction`, `afterAction`, `onActionFailure`) vía `ActionInvocation::context()`:

```php
class AuditHook implements LifecycleHooks {
    public function beforeAction(ActionInvocation $invocation): void {
        $ctx = $invocation->context();
        Logger::info('action.started', [
            'resource' => $invocation->resource(),
            'action' => $invocation->action(),
            'request_id' => $ctx->requestId(),
            'correlation_id' => $ctx->correlationId(),
            'user_id' => $ctx->userId(),
            'tenant_id' => $ctx->tenantId(),
        ]);
    }
}
```

### Patrón Cache Decorator (ejemplo compilable)

En lugar de cache manual dentro del Repository, usa un decorador que lee a través de la cache e invalida en cada escritura. El puerto de cache es agnóstico de vendor (`Shardevcom\Core\Contracts\CacheStore`), así que el adaptador concreto (PSR-16, Laravel Cache, Redis…) vive en infraestructura.

El ejemplo es una clase real y compilable, **no cableada por defecto**: `packages/shardevcom/product/src/Repositories/CachedProductRepository.php`.

```php
// extracto de CachedProductRepository (Shardevcom\Product\Repositories)
final class CachedProductRepository extends AbstractResourceRepository
{
    private const TTL_SECONDS = 300;

    public function __construct(
        private readonly AbstractResourceRepository $inner,
        private readonly CacheStore $cache,      // Shardevcom\Core\Contracts\CacheStore
        ActionLifecycle $lifecycle,
        QueryExecutor $executor,
    ) {
        parent::__construct($lifecycle, $executor);
    }

    public function find(string|int $id, ApplicationContext $context): ?array
    {
        $key = $this->resourceName().':'.$id;

        if (is_array($cached = $this->cache->get($key))) {
            return $cached;
        }

        $row = $this->inner->find($id, $context);
        $row !== null && $this->cache->put($key, $row, self::TTL_SECONDS);

        return $row;
    }

    public function update(string|int $id, array $attributes, ApplicationContext $context): array
    {
        $row = $this->inner->update($id, $attributes, $context);
        $this->cache->forget($this->resourceName().':'.$id);

        return $row;
    }
}
```

Reglas del patrón:

- **Autorización en un solo lugar.** El decorador delega cada operación pública al repositorio interno, así que el funnel del lifecycle (auth + hooks) corre una sola vez, dentro del interno. La cache nunca es una segunda vía de autorización.
- **El interno no sabe que lo cachean.** No hay cache manual dentro de `ProductRepository`.
- **Invalidación explícita en escritura** (`create`/`update`/`delete`/`restore` olvidan la clave). Para invalidación cruzada entre recursos, escucha eventos del lifecycle (`afterAction`) en un hook, no dentro del repositorio.

**Diagrama:**
```
┌─────────────┐     ┌─────────────────────┐     ┌──────────────────┐
│  Use Case   │────▶│ CachedProduct       │────▶│ ProductRepository│
│             │     │ Repository          │     │ (Eloquent)       │
└─────────────┘     │  - read-through     │     └──────────────────┘
                    │  - forget on write  │              ▲
                    └─────────┬───────────┘              │
                              │ CacheStore (puerto)      │ lifecycle/auth
                              ▼                          │ (una sola vez)
                       ┌─────────────┐
                       │  Cache      │  ← adapter PSR-16 / Laravel / Redis
                       └─────────────┘
```

Se registra en el container **en lugar del** `ProductRepository` real (ver docblock de `CachedProductRepository`). El repositorio interno sigue sin saber nada de cache.

---

## 16. Hardening Checklist (T-122)

Evidencia completa (file:line + comandos) en `backlog/fase-docs.md`. Resumen:

| # | Control | Evidencia (test/grep) | Estado |
|---|---------|----------------------|--------|
| 1 | **Auth before persistence** | `ActionLifecycle.php:47` autoriza antes de la operación; `AbstractResourceRepositoryTest::test_denied_action_never_reaches_persistence`, `ProductRepositoryTest::test_a_denied_write_never_reaches_the_database` | ✅ |
| 2 | **Validación única (DTO en Use Case)** | `ProductUseCaseParityTest::test_invalid_input_produces_identical_errors_from_both_transports` — mismos errores REST/GraphQL | ✅ |
| 3 | **Allow-list Filters/Includes/Sorts** | `ProductResourceDefinition.php:27-31`; `QueryOptionsTest::test_undeclared_{filter,include,sort}_is_rejected` | ✅ |
| 4 | **Mass assignment** | `Product.php:32` `$fillable`; `EloquentResourcePersistenceTest::test_mass_assignment_goes_through_fillable` | ✅ |
| 5 | **Rate limiting** | `RateLimitMiddleware` + alias `rate.limit` (`SecurityServiceProvider.php:54`); `RateLimitTest::test_the_31st_anonymous_request_is_throttled_by_ip`, `...::test_the_121st_authenticated_request_is_throttled_by_user` | ✅ |
| 6 | **Token expiry/revocación** | Passport PAT TTL desde `config/passport.php`; `PassportAuthenticationTest::test_an_expired_token_is_rejected...`, `AuthLoginEndpointTest::test_issued_token_honours_the_personal_access_lifetime`, `...::test_logout_revokes_the_token` | ✅ |
| 7 | **Auditabilidad** | `LoggingHooks` (`correlation_id`/`user_id`/`guard`/`duration_ms`); `LoggingHooksTest::test_after_action_logs_dimensions_with_correlation_id`. Audit store append-only = pendiente (BACKLOG §CASA) | ✅ |
| 8 | **Errores seguros (sin leak SQL/trace)** | `RestExceptionHandler::renderUnexpected()` (`:154`); `ErrorMappingTest::test_an_unexpected_failure_is_a_generic_500_that_leaks_nothing` | ✅ |
| 9 | **Sin whereRaw/SQL arbitrario** | `grep -rn "whereRaw" packages/` → solo `PlatformUser.php:57` (`'1=0'` constante, multi-platform) | ✅ |
| 10 | **Sin eager arbitrario** | `EloquentQueryExecutor` usa `IncludeSet` validado contra la Definition; `QueryOptionsTest::test_undeclared_include_is_rejected` | ✅ |
| 11 | **N+1 controlado** | `EloquentQueryExecutorTest::test_includes_eager_load_without_n_plus_one` (`assertLessThanOrEqual(3, DB::getQueryLog())`) | ✅ |
| 12 | **Límites page/complexity/depth** | `QueryOptions::DEFAULT_MAX_PER_PAGE=100`, GraphQL `MAX_QUERY_DEPTH=15`/`MAX_QUERY_COMPLEXITY=1000`/`MAX_FIRST=100`; `QueryOptionsTest::test_per_page_clamps_to_max`, `GraphqlHardeningTest::test_a_query_over_1000_complexity_is_rejected` | ✅ |

**Verificación automática:**
```bash
# Framework
composer test
grep -rn "Eloquent" packages/shardevcom/core/src || echo "CORE LIMPIO OK"
grep -rn "whereRaw" packages/ || echo "SIN WHERERAW OK"

# App generada
composer test --working-dir=demo/api
```

---

## 17. Cliente móvil (Flutter)

`demo/mobile` es el cliente móvil de referencia: una app Flutter con **un flavor
por guard** (`web`/`admin`/`employee`) sobre paquetes compartidos y **plugins por
guard**, espejo de `demo/web`. Un token Passport sólo es válido para el guard que
lo emitió, así que el guard se fija en compilación y la app no ofrece selector de
plataforma.

```text
demo/mobile/
├── lib/{main.dart,app.dart}      # registra plugins + MaterialApp.router
├── packages/plugin_kit/          # Guard, PluginDefinition, definePlugin, createRegistry
├── packages/core/                # shell, ApiClient (dio), auth (Riverpod), router (go_router)
└── plugins/product/              # una vista por guard (web read-only / employee manage / admin full)
```

```bash
# Android emulator: usa 10.0.2.2 para alcanzar el host
flutter pub get
./tool/test.sh          # corre flutter test en cada package del monorepo
flutter run --dart-define=GUARD=admin    --dart-define=API_HOST=http://10.0.2.2:8000
flutter run --dart-define=GUARD=employee --dart-define=API_HOST=http://10.0.2.2:8000
flutter run --dart-define=GUARD=web      --dart-define=API_HOST=http://10.0.2.2:8000
```

Añadir un plugin = añadir un paquete (path dependency) + registrarlo en
`lib/main.dart` con `pluginsProvider.overrideWithValue([...])`. El `path` de cada
vista coincide por convención con `guards.<guard>.path` del `plugin.json` del
package API. Instalación desde el CLI:

```bash
platform demo add product --with-mobile-client             # copia ./mobile-client + flutter pub get
platform demo add product --with-mobile-client --no-install
```

---

## 18. Cliente desktop (JavaFX)

`demo/desktop` es el cliente de escritorio de referencia: un monorepo Gradle con
**una app por guard** (`user`/`admin`/`employee`) sobre paquetes compartidos y
**plugins por guard**, espejo de `demo/web` y `demo/mobile`. Un token Passport sólo
es válido para el guard que lo emitió, así que el guard se fija en el módulo de app
y no hay selector de plataforma.

```text
demo/desktop/
├── settings.gradle.kts / gradlew   # Gradle wrapper (no requiere Gradle instalado)
├── packages/plugin-kit/            # Guard, PluginDefinition, definePlugin, PluginRegistry
├── packages/core/                  # shell, ApiClient (java.net.http), auth, vistas JavaFX
└── plugins/product/                # una vista por guard (web read-only / employee manage / admin full)
```

```bash
./gradlew build                  # compila + tests de todos los módulos
./gradlew :apps:admin:run        # ejecuta la app admin (host por defecto localhost:8000)
./gradlew :apps:admin:run -Dshardev.api.host=http://192.168.1.10:8000
```

Añadir un plugin = añadir un módulo como dependencia del app + registrarlo en
`Main.java` con `new PlatformApp(Guard.ADMIN, List.of(ProductPlugin.productPlugin()))`.
El `path` de cada vista coincide por convención con `guards.<guard>.path` del
`plugin.json` del package API. Instalación desde el CLI:

```bash
platform demo add product --with-desktop-client             # copia ./desktop-client + gradlew build
platform demo add product --with-desktop-client --no-install
platform demo add product --with-client --with-mobile-client --with-desktop-client
```

Requiere **JDK 21**; el wrapper descarga Gradle y JavaFX se resuelve como dependencia.

---

## 19. Workspace (API + clientes)

`platform new` ya no produce "una app Laravel": produce un **workspace**. La
raíz es un manifiesto `platform.json` y cada pieza es un componente
independiente con su propio toolchain.

```text
mi-proyecto/
├── platform.json     # fuente de verdad (name, platform, components + guards)
├── platform.sh       # shell de control (levantar/parar/build/doctor)
├── api/              # Laravel 12 (componente)
├── web/              # Vite/React (componente)
├── mobile/           # Flutter (componente)
└── desktop/          # Gradle/JavaFX (componente)
```

```json
{
  "name": "mi-proyecto",
  "platform": "0.1.0",
  "components": {
    "api":     { "type": "api",     "path": "api",     "guards": [],          "enabled": true },
    "web":     { "type": "web",     "path": "web",     "guards": ["web"],     "enabled": true },
    "mobile":  { "type": "mobile",  "path": "mobile",  "guards": ["web"],     "enabled": false },
    "desktop": { "type": "desktop", "path": "desktop", "guards": ["web"],     "enabled": false }
  }
}
```

Cada tipo tiene un **driver** (`ComponentDriver`) que conoce su toolchain, así
ningún comando ramifica por tipo:

| tipo      | install               | build                  | dev                          |
|-----------|-----------------------|------------------------|------------------------------|
| `api`     | `composer update`     | `composer dump-autoload` | `php artisan serve`        |
| `web`     | `npm install`         | `npm run build`        | `npm run dev:<guard>`        |
| `mobile`  | `flutter pub get`     | `flutter build apk`    | `flutter run --dart-define=GUARD=<guard>` |
| `desktop` | `./gradlew build -x test` | `./gradlew build` | `./gradlew :apps:<app>:run` |

Comandos:

```bash
platform new <name> [--with=api,web,mobile,desktop] [--guards=web,admin] [--laravel=12] [--no-interaction] [--no-install]
platform add <component> [--guards=web,admin] [--path=web] [--no-install]
platform remove <component> [--delete]
platform install [component] [--only=web,mobile]
platform build   [component] [--only=web,mobile] [--guard=admin]
platform dev     <component> [--guard=admin]
platform status
platform guard add <guard> [--driver=passport] [--no-clients] [--no-migrate]
platform guard list
```

Cada componente sirve **N guards**: la API los publica en
`config/platforms.php` y `platform guard add` los da de alta de punta a punta
(entrada de plataforma + modelo + migraciones + una app por guard en cada
cliente). El manifiesto guarda la lista `guards` por componente. `--guards` en
`new` (y en `add`) fija esa lista para los clientes; sin él, los clientes
espejan los guards que publica la API.

### `platform.sh` — shell de control

`platform new` deja un script ejecutable en la raíz del workspace que lee
`platform.json` en tiempo de ejecución, así que nunca hay que regenerarlo al
añadir un componente o un guard:

```bash
./platform.sh up             # levanta la API en background
./platform.sh web admin      # levanta la app web del guard admin
./platform.sh mobile web     # flutter run --dart-define=GUARD=web (foreground)
./platform.sh stop|restart|status|logs
./platform.sh build [api|web|mobile|desktop]...
./platform.sh install [targets...]
./platform.sh doctor|fix     # toolchain + dependencias; fix intenta arreglarlas
```

El estado de los procesos vive en `.platform/` (PIDs y logs) y está en
`.gitignore`.

`Context::projectRoot()` resuelve al componente `api`, de modo que `config`,
`demo add`, `package make` y `doctor` siguen operando sobre Laravel. `doctor`
añade dos suites: **Workspace** (falla si un componente no está en
disco) y **Client requirements** (según los clientes instalados comprueba el
toolchain que necesitan —Node/npm, Flutter/Dart, JDK— y sugiere cómo arreglarlo;
si la herramienta está instalada fuera del PATH, devuelve el `export PATH=...`
exacto). Detalle de diseño: `backlog/fase-workspace.md`.

El propio `demo/` es un workspace: `demo/platform.json` declara `api`, `web`,
`mobile` y `desktop` con `guards: ["web","admin","employee"]`, así que
`platform status` y `platform doctor` funcionan igual desde `demo/` que desde un
proyecto recién generado.

---

*Documentación generada desde `BACKLOG.md` + `backlog/`. Ver `backlog/00-index.md` para reglas de ejecución. Secciones: 1-12 (visión + DX), 13 Smoke Test, 14 Uso rápido, 15 Observabilidad (T-121), 16 Hardening (T-122), 17 Cliente móvil, 18 Cliente desktop, 19 Workspace.*
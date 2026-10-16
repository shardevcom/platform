# Fase Application — T-060…T-061, T-090…T-091

## Avance (2026-10-01)
- DONE T-060: 5 use cases en `demo/api/packages/product/src/Application/` — `Commands/{CreateProduct,UpdateProduct,DeleteProduct}` + `Queries/{ListProducts,GetProduct}`. Son ORQUESTACIÓN pura: reciben input ya validado, llaman al repo y devuelven filas planas. Cero HTTP, cero Eloquent, cero query building (el test hace grep de imports). **No hay binding en el provider**: son `final` con una única dependencia ya registrada, así que el contenedor los resuelve solo (una lista en el provider sería un segundo lugar que olvidar).
- `Data/CreateProductData` + `Data/UpdateProductData` = punto ÚNICO de validación de producto. `fromArray()` acepta un DTO ya construido o el payload crudo; ambos caminos dan el mismo resultado (test explícito). Un campo no declarado se descarta en el DTO, así que ni llega al repo. `UpdateProductData` distingue "omitido" de "null explícito" (un PATCH puede limpiar `ean`), y rechaza el patch vacío: sería una escritura y una entrada de auditoría para nada.
- `Validation/Validator` mínimo (required, string:N, integer:N, boolean, regex, digits) sin dependencia de transporte ni esquema anidado. Reglas pipe-separated (`'required|string:64'`), un mensaje por campo, `null` explícito permitido en opcionales.
- `GetProduct` usa `findOrFail` a propósito: fila ausente y fila oculta por scope de tenant dan el mismo `ResourceNotFound`, así no se puede sondear qué ids existen en otro tenant.
- `DeleteProduct` NO abre transacción (una sentencia); el docblock apunta al ejemplo de workflow del adapter de T-052.
- Verificación: `demo/api/tests/Feature/Product/ProductUseCaseParityTest` (24 tests, 24 datasets: paridad REST-vs-GraphQL en create/defaults/10 payloads inválidos/get/delete/list/patch vacío/404/Forbidden, columnas no declaradas descartadas, DTO pre-construido == payload crudo, use cases resolubles sin binding, grep de imports). `grep -rn "Product::" demo/api/packages/*/src/Rest demo/api/packages/*/src/Graphql` = 0. `CreateProduct` 45 LOC, `ProductRepository` 52 LOC.
- Suites: framework 110 tests; demo 76 tests.
- DONE T-061: `demo/api/tests/Feature/Platform/LayeringRulesTest.php` — 8 reglas de capas (10 tests, 283 assertions). No es ceremonialismo: cada regla tapa un modo de fallo real, y **las 7 verifiqué que muerden** mutando una COPIA del paquete en `/tmp` (nunca el repo) con la violación exacta: import de Eloquent en Application, `::query()/where()` en use case, `Gate::authorize()/can()/authorize()` en Application, `products` en un hook genérico, `Validator` en repositorio, `if (` en ServiceProvider, y use case importando un `*Service` en vez del repo. Todas detectadas; con el código real, las 7 limpias. Un caso negativo que confirmé: un `whereRaw` en el REPOSITORIO NO dispara R2, porque consultar datos es exactamente su trabajo — la regla apunta a Application, no a los archivos.
- Lo que las reglas defienden, en una línea cada una: R1 imports entre capas (un use case que importe el modelo puede saltarse el repo, y con él la autorización que hace el lifecycle); R2 sin query building en Application (si no, el segundo transport no puede reutilizarlo); R3 auth en una sola capa (dos decisiones de autorización solo pueden discrepar); R4 hooks agnósticos de recurso (lógica en un hook corre para todos los recursos y no es reutilizable); R5 el repo no valida (dos conjuntos de reglas = uno de más; los rules viven en el DTO); R6 providers solo cablean (lógica en boot corre fuera de todo lifecycle, saltándose auth y hooks); R7 use cases→repo directo (una cadena Controller→Service→Repository→Model sería un reenvío sin responsabilidad propia). LOC guía (150 use case / 250 repo) queda como tripwire, no como dogma: un use case que orquesta 10 pasos no es God class, uno que reimplementa validación/consulta/formateo sí.
- DONE T-090: `Packages\Core\Contracts\AuthenticationProvider` + `Packages\Core\Authentication\BearerTokenAuthentication` (stateless Bearer, expiry/revocation checks, device context) + `demo/api/app/Platform/Authentication\SanctumTokenResolver` (Sanctum storage behind TokenResolver contract). Verificación: `SanctumAuthenticationTest` (10 tests, token lifecycle, stateless API, no CSRF).
- DONE T-090 (migración de almacenamiento): Sanctum se sustituyó por **Laravel Passport** (v13) manteniendo el contrato `TokenResolver` intacto — el storage es un detalle detrás de esa costura. `PlatformUser` implementa `Laravel\Passport\HasApiTokens`; `SanctumTokenResolver` → `demo/api/app/Platform/Authentication/PassportTokenResolver` (usa `ResourceServer::validateAuthenticatedRequest`, no JWT casero) y `StatelessSanctumGuard` → `StatelessPassportGuard` (limpia el cache de `user`/`client` en `setRequest()` para que la sesión no se filtre entre requests). Scopes derivados del `ResourceRegistry` con `TokenScopeCatalogue` + `Passport::tokensCan` (necesita **array** `id => descripción`; una lista de strings no registra scopes y `createToken()` lanza). Guard `api` añadido (`api_guard` en `config/platforms.php`) porque `HasApiTokens::getProviderName()` revienta sin un guard con `driver => passport`. Clientes personales creados por migración propia (3, una por plataforma: `ClientRepository::personalAccessClient()` exige `provider` coincidente). Verificación: `demo/api/tests/Feature/Platform/PassportAuthenticationTest` (10 tests) + suite demo 142 passed.
- DONE T-091: `packages/application/src/ApplicationContext/` — `BaseApplicationContextBuilder`, `HttpApplicationContextBuilder`, `CliApplicationContextBuilder`, `JobApplicationContextBuilder`, `GraphQLApplicationContextBuilder`, `ApplicationContextBuilderFactory`. Extract permissions from identity abilities, resolve tenant/device from headers/options/payload, propagate request_id/correlation_id. Verificación: `packages/application/tests/ApplicationContextBuilderTest` (10 tests, all transports, defaults, overrides).

## T-060 — Application — Use Cases Product (única verdad)
- **Desc:** `List/Get/Create/Update/DeleteProduct` + DTOs + validación única DTO-agnóstica. Consumen `ProductRepository` + `ApplicationContext`. Controllers delgados.
- **Dep:** T-053, T-040
- **Aceptación:** Mismo `CreateProduct` desde harness REST y GraphQL → mismas reglas/errores; `grep "Product::" */Rest */Graphql` = 0.
- **Est:** M
- **Prompt agente:** `Implementa T-060: DTOs + 5 use cases Product sobre ProductRepository con validación centralizada. Tests que invocan el mismo use case desde adapter REST fake y GraphQL fake y assertan paridad.`

## T-061 — Application — Anti God-Service/Repo
- **Desc:** Repo = acceso+auth+query+lifecycle; UseCase = orquestación. Sin cadena artificial. Sin negocio en hooks.
- **Dep:** T-060
- **Aceptación:** `CreateProduct`<150 LOC guía; `Application/` sin imports Eloquent (arch test).
- **Est:** S

## T-090 — Core — `AuthenticationProvider` + Bearer (sin CSRF API)
- **Desc:** Contrato + impl de almacenamiento de token (Passport: `oauth_access_tokens` + JWT) con expiry+revocación+device context. Web=cookie+CSRF; API=Bearer sin CSRF. Sin refresh hasta T-CASA-005/007.
- **Dep:** T-011
- **Aceptación:** Bearer expirado/revocado → `Unauthorized`; doc explica nonce/idempotency como futuro.
- **Est:** M

## T-091 — Application — Builders `ApplicationContext` REST/GraphQL/CLI/Jobs
- **Desc:** Misma factoría desde Request, args GQL, consola, Job. Propaga `request_id/correlation_id`, locale/timezone.
- **Dep:** T-090, T-011
- **Aceptación:** Contexto idéntico desde HTTP y CLI con mismo user/tenant.
- **Est:** S

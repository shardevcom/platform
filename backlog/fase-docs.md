# Fase Docs — T-120…T-122

## T-120 — Docs — README generado + smoke
- **Desc:** 11 secciones visión §20 + ejemplos ejecutables REST + GraphQL + `platform doctor` + `package make`.
- **Dep:** T-070, T-073, T-081
- **Aceptación:** Dev nuevo <10 min → `GET /api/v1/products → 200` y `{ products { id name } }` con sqlite.
- **Est:** M
- **Estado:** ✅ DONE (2026-10-16). `README.md` cubre los puntos de visión (§1-12, sin numeración duplicada) + ejemplos REST (§6) y GraphQL (§7) + smoke reproducible (§13) + `platform doctor` (§12). Smoke: `bin/platform new` → `php artisan serve` → token Passport → `GET .../products` 200 → mutation/query GraphQL → `platform doctor`.
- **Verificación:** `grep -n "^## " README.md` (secciones 1-16 consecutivas); smoke §13 ejecutado contra `demo/api`.

## T-121 — Docs — Observabilidad + cache decorator
- **Desc:** `request_id/correlation_id` en lifecycle; contratos Logger/Meter/Tracer sin vendor; patrón `CachedProductRepository` + invalidación eventos. Sin cache manual por repo.
- **Dep:** T-050
- **Aceptación:** `correlation_id` en logs `execute()`; doc diagrama `Repo→Cache→Persistence` + ejemplo compilable.
- **Est:** M
- **Estado:** ✅ DONE (2026-10-16).
  - Contratos sin vendor lock en `packages/shardevcom/core/src/Contracts/`: `Logger`, `Meter`, `Tracer` (+ `Span`, `SpanContext`) y **nuevo** `CacheStore` (puerto mínimo del decorador).
  - `correlation_id`/`request_id` propagados vía `ApplicationContext` → `LoggingHooks`. Test: `LoggingHooksTest::test_after_action_logs_dimensions_with_correlation_id`.
  - Decorador **real y compilable** (no cableado): `packages/shardevcom/product/src/Repositories/CachedProductRepository.php` (read-through + `forget` en escritura; auth en un solo funnel = el repo interno).
  - Diagrama `Use Case → CachedProductRepository → ProductRepository` + puerto `CacheStore` en `README.md` §15.
- **Verificación:** `vendor/bin/phpunit --filter=CachedProductRepositoryTest` (5 tests: cache hit, invalidación en update/delete, delegación de findMany, sustituibilidad); `grep -n "test_after_action_logs_dimensions_with_correlation_id" tests/Unit/Observability/LoggingHooksTest.php`.


## T-122 — Docs — Hardening seguridad & performance
- **Checklist:** auth-before-persistence, validación única, allow-list F/I/S, mass assignment, rate limit, token expiry/revocación, auditabilidad, errores seguros, sin whereRaw/SQL arbitrario, sin eager arbitrario, N+1, límites page/complexity/depth.
- **Dep:** T-042, T-074, T-071
- **Aceptación:** 100% marcado con evidencia (test o grep); `composer test` verde.
- **Est:** S
- **Estado:** ✅ DONE (2026-10-16). Verificado contra el código; cada control con evidencia ejecutable. `composer test` verde (root 260 / demo 212).

### Checklist (control → evidencia → estado)

| # | Control | Evidencia (test o grep) | Estado |
| --- | --- | --- | --- |
| 1 | auth-before-persistence | `ActionLifecycle.php:47` autoriza antes de `$operation`; tests `AbstractResourceRepositoryTest::test_denied_action_never_reaches_persistence`, `ProductRepositoryTest::test_a_denied_write_never_reaches_the_database` (DB::listen: sin SQL de write), `EloquentResourcePersistenceTest::test_a_denied_action_never_reaches_the_database` | ✅ |
| 2 | Validación única | DTO canónico: `CreateProductData::rules()/fromArray()`; REST lo reusa (`CreateProductRequest.php:28`), GraphQL construye el mismo DTO (`ProductMutationResolver.php:37-55`). Test `ProductUseCaseParityTest::test_invalid_input_produces_identical_errors_from_both_transports`. **Desviación:** el schema Lighthouse repite el texto de reglas (`graphql/schema.graphql:144-157`); la frontera de cumplimiento sigue siendo el DTO (el resolver re-valida). | ✅ |
| 3 | Allow-list filters/includes/sort | `ProductResourceDefinition.php:27-31` (`withFilters/withIncludes/withSorting`); `ResourceDefinition::allowsFilter/allowsInclude/allowsSort`; `QueryOptions.php:52-67`. Tests `QueryOptionsTest::test_undeclared_{filter,include,sort}_is_rejected`; over-HTTP `ProductRestEndpointTest::test_an_undeclared_filter_is_422...`, `ProductGraphqlEndpointTest::test_an_undeclared_include_is_refused` | ✅ |
| 4 | Mass assignment | `Product.php:32` `$fillable`; `EloquentResourcePersistence::rejectUndeclared()` (llamado en `persistNew`/`persistUpdate`). Tests `EloquentResourcePersistenceTest::test_mass_assignment_goes_through_fillable`, `...::test_undeclared_columns_are_rejected_before_the_write`; doctor rule `MassAssignmentRule` | ✅ |
| 5 | Rate limiting | `RateLimitMiddleware` (buckets anon/user/operación), alias `rate.limit` en `SecurityServiceProvider.php:54`, aplicado en `config/platforms.php:60,74,88`. Tests `RateLimitTest::test_the_31st_anonymous_request_is_throttled_by_ip`, `...::test_the_121st_authenticated_request_is_throttled_by_user`, `...::test_an_import_hits_its_own_bucket_before_a_plain_get`; GraphQL `GraphqlHardeningTest::test_mutations_are_rate_limited...` | ✅ |
| 6 | Token expiry / revocación | `Passport::personalAccessTokensExpireIn` desde `config/passport.php` (`AppServiceProvider::configurePassportLifetimes`); `AuthController::logout()` revoca (`:103`). Tests `PassportAuthenticationTest::test_an_expired_token_is_rejected...`, `...::test_a_revoked_token_is_unauthorized`, `AuthLoginEndpointTest::test_logout_revokes_the_token`, y **nuevo** `AuthLoginEndpointTest::test_issued_token_honours_the_personal_access_lifetime` (TTL configurado aplicado al PAT emitido) | ✅ |
| 7 | Auditabilidad | `LoggingHooks` emite `correlation_id`/`request_id`/`user_id`/`tenant_id`/`guard`/`duration_ms`; `CorrelationMiddleware` (UUIDv4-only). Tests `LoggingHooksTest::test_after_action_logs_dimensions_with_correlation_id`, `ObservabilityTest::test_a_full_request_keeps_one_correlation_id...`, `CorrelationIdsTest`. **Nota:** es logging/trazabilidad, no un audit store append-only; eventos `auth.login/token.revoked/...` (BACKLOG §CASA) siguen pendientes fuera de T-122. | ✅ |
| 8 | Errores seguros | `RestExceptionHandler::renderUnexpected()` solo expone mensaje/detalles con `app.debug` (`:154,160`); mapeo de dominio `ApiError::fromDomain`. Test `ErrorMappingTest::test_an_unexpected_failure_is_a_generic_500_that_leaks_nothing` (sin `SQLSTATE`/trace) | ✅ |
| 9 | Sin `whereRaw`/SQL arbitrario | Único `whereRaw` es literal constante `PlatformUser.php:57` (`'1=0'`), nunca desde request; executor parametrizado. Grep: `grep -rn "DB::select\|DB::raw\|whereRaw\|orderByRaw\|selectRaw" packages/shardevcom demo/api/packages demo/api/app src \| grep -v vendor` → solo `whereRaw('1=0')`. Regla `LayeringRulesTest` prohíbe `->whereRaw(`/`DB::` en Use Cases | ✅ |
| 10 | Sin eager arbitrario | Includes allow-listed (`IncludeSet`), executor hace `with($includes)` solo con la lista validada (`EloquentQueryExecutor.php:103`). Grep sin coincidencias: `grep -rnE 'with\(\$request\|with\(\$args\|with\(\$input' packages demo/api`. Tests `QueryOptionsTest::test_undeclared_include_is_rejected`, `EloquentQueryExecutorTest::test_undeclared_include_never_reaches_the_database` | ✅ |
| 11 | N+1 controlado | Eager solo por includes declarados; relación GraphQL con `batch: true` (`schema.graphql:64`). Test `EloquentQueryExecutorTest::test_includes_eager_load_without_n_plus_one` (`assertLessThanOrEqual(3, DB::getQueryLog())`) | ✅ |
| 12 | Límites page/complexity/depth | `QueryOptions::DEFAULT_MAX_PER_PAGE=100` + `Pagination::capped()`; GraphQL `MAX_QUERY_DEPTH=15`, `MAX_QUERY_COMPLEXITY=1000`, `MAX_FIRST=100`. Tests `QueryOptionsTest::test_per_page_clamps_to_max`, `GraphqlHardeningTest::test_a_query_over_1000_complexity_is_rejected`, `...::test_a_query_deeper_than_15_is_rejected`, `ProductRestEndpointTest::test_an_oversized_page_size_is_clamped...` | ✅ |

**Comandos de evidencia (reproducibles):**

```bash
# Arquitectura (T-110)
grep -rn "Illuminate\\Database\\Eloquent" packages/shardevcom/core/src || echo "CORE LIMPIO OK"
grep -rn "::query()" demo/api/packages/shardev/*/src/Rest demo/api/packages/shardev/*/src/Graphql || echo "ADAPTERS LIMPIOS OK"
grep -rn "whereRaw(\$request" packages/ || echo "SIN WHERERAW DESDE CLIENTE OK"
# Suites
composer test                       # root
composer test --working-dir=demo/api # app (incluye AuthLoginEndpointTest, RateLimitTest, GraphqlHardeningTest, ErrorMappingTest)
```

**Revisión manual (firmar en PR):** ☐ Revisado por __________ — fecha __________.

---

## Cliente móvil (Flutter) — extensión del cliente de referencia

- **Desc:** cliente móvil de referencia (`demo/mobile`) espejo de `demo/web`: una app Flutter con un flavor por guard (`web`/`admin`/`employee`) sobre paquetes compartidos y plugins por guard. Exportable vía CLI.
- **Fuera de T-120..T-122** (petición del usuario, 2026-10-16).
- **Estado:** ✅ DONE (2026-10-16). Dart verificado con Flutter 3.47.7 / Dart 3.13.5 (`flutter analyze` → "No issues found!"; `./tool/test.sh` → 6+5+5 = 16 tests OK).
- **Arquitectura:**
  - `demo/mobile/packages/plugin_kit/` — `Guard` (id + prefijo API), `NavItem`, `GuardView`, `PluginDefinition`, `definePlugin`, `createRegistry`.
  - `demo/mobile/packages/core/` — `ApiClient` (dio, envelope `{status,code,message,data,meta}`, `ApiError`), auth Riverpod con token en `shared_preferences` y `auth/me`, router `go_router` con redirect a login, `AppShell` (drawer desde el registry), páginas login/dashboard/profile, tema M3.
  - `demo/mobile/plugins/product/` — `ProductApi` + `productsProvider`, `ProductList` con `ProductCapabilities`, y una vista por guard (`web` read-only, `employee` manage, `admin` full).
  - `demo/mobile/lib/{main.dart,app.dart}` — guard vía `--dart-define=GUARD=...`, plugins registrados con `pluginsProvider.overrideWithValue([productPlugin])`.
- **CLI:** `src/Cli/Scaffold/MobileClientInstaller.php` (source `demo/mobile`, destino `./mobile-client`, excluye `.dart_tool`/`build`/…, corre `flutter pub get`) + flag `--with-mobile-client` en `platform demo add`.
- **Verificación PHP:** `vendor/bin/phpunit --filter=DemoCommandTest` (20 tests; incluye copia del cliente Flutter, exclusión de artefactos, `flutter pub get`, `--no-install`, no-overwrite y ambos clientes).
- **Verificación Dart:** `cd demo/mobile && flutter pub get && flutter analyze && ./tool/test.sh` (Flutter 3.47.7): analyze sin issues; 16 tests OK (plugin_kit 6, core 5, product 5).

## Cliente desktop (JavaFX) — extensión del cliente de referencia

- **Desc:** cliente de escritorio de referencia (`demo/desktop`) espejo de `demo/web`/`demo/mobile`: un monorepo Gradle con una app por guard (`user`/`admin`/`employee`) sobre paquetes compartidos y plugins por guard. Exportable vía CLI.
- **Fuera de T-120..T-122** (petición del usuario, 2026-10-16).
- **Estado:** ✅ DONE (2026-10-16). Java verificado con JDK 21: `./gradlew build` → BUILD SUCCESSFUL, 17 tests OK (plugin-kit 6, core 6, product 5).
- **Arquitectura:**
  - `demo/desktop/packages/plugin-kit/` — `Guard` (id + prefijo API), `NavItem`, `GuardView`, `PluginContext`, `PluginDefinition`, `definePlugin`, `PluginRegistry`.
  - `demo/desktop/packages/core/` — `ApiClient` (`java.net.http`, envelope `{status,code,message,data,meta}`, `ApiError`), auth con token en `Preferences`, `PlatformApp` (shell + navegación desde el registry), vistas login/dashboard/profile, hoja de estilos `app.css`.
  - `demo/desktop/plugins/product/` — `ProductApi` (Gson), `ProductList`/`ProductForm` con `ProductCapabilities`, y una vista por guard (`web` read-only, `employee` manage, `admin` full).
  - `demo/desktop/apps/{user,employee,admin}/` — entrypoint `Main` por guard (`Guard.WEB`/`EMPLOYEE`/`ADMIN`), plugins registrados con `new PlatformApp(guard, List.of(ProductPlugin.productPlugin()))`.
- **CLI:** `src/Cli/Scaffold/DesktopClientInstaller.php` (source `demo/desktop`, destino `./desktop-client`, excluye `.gradle`/`build`/`out`/…, corre `./gradlew build -x test`) + flag `--with-desktop-client` en `platform demo add`. `LocalFilesystem::copyDirectory` preserva permisos (para el bit exec de `gradlew`).
- **Verificación PHP:** `vendor/bin/phpunit --filter=DemoCommandTest` (25 tests; incluye copia del cliente JavaFX, exclusión de artefactos, `./gradlew build -x test`, `--no-install`, no-overwrite y los tres clientes).
- **Verificación Java:** `cd demo/desktop && ./gradlew build` (JDK 21): 17 tests OK. Requiere red la primera vez (Gradle wrapper + JavaFX/Gson).

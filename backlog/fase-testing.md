# Fase Testing — T-100…T-102, T-110…T-111

## T-100 — Testing — Suite `ResourceRepository`
- **Desc:** auth-first, allow-list, hooks, `supportedOperations`, tenant scope. Sqlite + fakes.
- **Dep:** T-053
- **Aceptación:** `ProductRepositoryTest` verde; `update` sin permiso → persistencia `never()`.
- **Est:** M

## T-101 — Testing — Parity REST == GraphQL
- **Desc:** Mismo seed/usuario, 6 casos × 2 transports: create inválido, read sin permiso, filtro no permitido, include no permitido, paginación, update/delete.
- **Dep:** T-070, T-073
- **Aceptación:** Tabla parity verde; falla si GQL bypasea validación REST.

| Caso | REST esperado | GraphQL esperado |
|---|---|---|
| create inválido | 422 ValidationFailed | errors VALIDATION_FAILED |
| read sin `view` | 403 Forbidden | errors FORBIDDEN |
| filter no permitido | 422 | errors VALIDATION_FAILED |
| include no permitido | 422 | errors VALIDATION_FAILED |
| paginación `per_page=9999` | clamp a `max_per_page` (100) | clamp a `max_per_page` (100) |
| delete sin `delete` | 403 | errors FORBIDDEN |

- **Est:** M

## T-102 — Testing — Config + error mapping
- **Desc:** 207 parcial, envelope REST, errores GQL, no-leak 500.
- **Dep:** T-072, T-074, T-081
- **Aceptación:** `ConfigurationTest,ErrorMappingTest` verdes; 207 byte-compatible visión.
- **Est:** S

## T-110 — Testing — Architecture tests
- **Desc:** Pest-arch/deptrac/custom. Guards:
  1. `Core ↛ GraphQL` (no `GraphQL\`, lighthouse, rebing en `packages/core`).
  2. `Core ↛ REST` (no `Illuminate\Http`, `FormRequest`, `JsonResponse` en core).
  3. `Domain ↛ HTTP`, `Domain ↛ Eloquent`.
  4. `GraphQL ↛ Models` (no `Models\`, `Eloquent\Model`, `::query()` en `*/Graphql`).
  5. `REST Controllers ↛ Models`.
  6. `Modules/packages ↛ internals otros` (solo vía Contracts/Application).
  7. `Core ↛ HttpException/abort()`.
  8. `Application ↛ Eloquent`.
  9. Sin `whereRaw($request` / relaciones arbitrarias cliente.
- **Dep:** T-051, T-070, T-073
- **Aceptación:** `ArchTest` verde; mutación (import Model en Core) lo rompe intencionalmente.
- **Est:** M

## T-111 — Testing — CI gate
- **Desc:** `composer test` = unit+integration+feature+arch + `composer validate`.
- **Dep:** T-110, T-100
- **Aceptación:** Reproduce CI en local; rojo si T-110 falla.
- **Est:** S

## Avance (2026-10-05)
- DONE T-102: `demo/tests/Feature/Platform/ConfigurationTest.php` (6 casos, T-072) + `ErrorMappingTest.php` (9 casos, T-071/T-102). El test de error mapping conduce cada excepción de dominio por el stack HTTP real (no por la excepción suelta) porque el mapeo vive en el renderable del handler: `Unauthorized→401`, `Forbidden→403`, `ResourceNotFound→404`, `Conflict→409`, `BusinessRuleViolation→422`, `ResourceOperationNotSupported→405`, `ValidationFailed→422` con errores por campo, y 500 genérico con `APP_DEBUG=false` que no filtra SQL, clase de excepción ni ruta del archivo.
- FIX T-014 (decisión del usuario): el envelope REST devolvía `"code":404`, una copia del status HTTP, y descartaba el código semántico que `ApiError::fromDomain()` ya calculaba. Un cliente no podía ramificar por el motivo. `ApiResponse` ahora separa `code` (string semántico: `OK`, `PARTIAL`, `RESOURCE_NOT_FOUND`, `VALIDATION_FAILED`) de `httpStatus` (int, para la línea HTTP). Verificación: `composer test` 218 verdes; `composer test --working-dir=demo` 112 verdes; sonda manual sobre `/api/v1/configuration` devuelve `{"status":"partial","code":"PARTIAL"}` en 207 y `{"status":"success","code":"OK"}` en 200.
- DONE T-100: la aceptación pedía "`update` sin permiso → persistencia `never()`" y solo existía un test análogo para el read path (query executor que explota). Un write podía seguir persistiéndose después de la autorización fallida sin que nada lo detectara. Añadido `test_a_denied_write_never_reaches_the_database` en `demo/tests/Feature/Product/ProductRepositoryTest.php`: registra el SQL real con `DB::listen` y falla si alguna sentencia empieza por `update` o `delete`, además de comprobar que la fila sigue existiendo con el nombre original.
  Nota de implementación: primero intenté un `new class(...) extends ProductRepository` que sobrescribiera `persistUpdate`/`persistDelete` para contar llamadas, pero `ProductRepository` es `final` y no se puede extender. El listener de SQL cubre la misma propiedad (y más) sin tocar la visibilidad de la clase de producción.
  Mutación: en `AbstractResourceRepository::update()` invoqué `persistUpdate` **antes** de `assertSupported(self::UPDATE)` (autorización movida después de la persistencia) → rojo. Restaurado y reverdeado.
  Verificación: demo `115 passed (1353 assertions)`, root `218 passed`, `composer ci` exit 0.
- DONE T-101: la tabla parity de 6 casos quedó cubierta en `demo/tests/Feature/Product/ProductUseCaseParityTest.php`, pero faltaban dos filas: include no permitido y el clamp de `per_page`. Añadidos `test_an_undeclared_include_is_rejected_from_both_transports` (VALIDATION_FAILED en `includes.relacionNoDeclarada` desde ambos transports) y `test_an_oversized_page_size_is_clamped_and_not_rejected_from_both_transports` (`per_page=9999` → `QueryOptions::DEFAULT_MAX_PER_PAGE`, igual en ambos). El clamp es 100, no 200 como decía la tabla del backlog: `QueryOptions::DEFAULT_MAX_PER_PAGE = 100` es lo que implementa T-042, así que la tabla del backlog está desactualizada y no el código.
  Mutación de ACEPTACIÓN ("falla si GQL bypasea validación REST"): en `ListProducts::execute()` descarté los filters antes de que llegaran a `QueryOptions` (equivalente a que un adapter se saltee la validación) → rojo en `test_an_undeclared_list_filter_is_rejected_by_the_use_case_not_the_adapter`. Restaurado y reverdeado.
  Verificación: demo `114 passed (1345 assertions)`, root `218 passed`, `composer ci` exit 0.
- DONE T-111: gate local. `composer test` (root) = `composer validate --no-check-publish` + PHPUnit; `composer test --working-dir=demo` = validate + `config:clear` + `artisan test`; `composer ci` corre ambos y propaga el exit code vía `scripts/ci-demo.php`. El gate cubre T-110 porque `artisan test` incluye `tests/Feature/Platform/LayeringRulesTest.php`.
  Nota: `@composer` anidado en un script falla con exit 127 en este repo (funciona en un proyecto limpio), por eso el paso de demo va en un script PHP aparte en vez de `"@composer test --working-dir=demo"` inline.
  Mutación verificada en las dos direcciones: importar `Shardevcom\Widgets\WidgetsRegistry` en `core/src/Results/ApiError.php` → rojo con "must not import ... core cannot depend on a package built on top of it"; importar `Illuminate\Database\Eloquent\Model` en `product/src/Application/Commands/CreateProduct.php` → rojo con "must not import Illuminate\Database — that is another layer's job".
  Verificación: `composer ci` → root `OK (218 tests, 598 assertions)`, demo `112 passed (1339 assertions)`, exit 0.

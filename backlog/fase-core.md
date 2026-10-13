# Fase Core — T-010…T-014 (+ T-090 auth contract)

## Avance (2026-09-29)
- DONE T-010: 7 contratos en `packages/core/src/Contracts/` (AuthorizationManager, ResourceRegistry, QueryExecutor, AuthenticationProvider, PermissionResolver, ConfigurationProvider, ResourceRepository), cada uno con docblock `Boundary:` que justifica la frontera. Tipos referenciados (ApplicationContext, ResourceAction, ResourceDefinition…) llegan en T-011/T-012/T-020 (PHP resuelve firmas lazily). Verificación: `php artisan test --filter=ContractsTest` (8 tests, 42 assertions) + `grep Illuminate packages/core/src/Contracts` = 0.
- DONE T-011: `Packages\Core\ApplicationContext` (inmutable, `create(array)`/`with()`/`toArray()`, user/tenant/application/device independientes, locale/timezone, permissions/capabilities + `has*()`, request_id UUIDv4 auto, correlation defaults a request_id) + `ApplicationIdentity` (id/kind/attributes, completa contrato T-010). Cero imports framework. Verificación: `ApplicationContextTest` (8 tests).
- DONE T-012: `enum ResourceAction` (view/create/update/delete/restore) + `segment()`/`permission()` que aceptan customs (`publish` → `products.publish`) con validación `^[a-z][a-z0-9_-]{0,63}$` (rechaza vacíos, mayúsculas, puntos). Contratos `AuthorizationManager`/`PermissionResolver` ampliados a `ResourceAction|string` (mismos métodos: ContractsTest intacto). Verificación: `ResourceActionTest` (8 tests).
- Suite framework: 33 tests, 93 assertions. `demo/packages` re-sincronizado; suite demo 7 tests verde.
- DONE T-013: `packages/core/src/Exceptions/` — base `DomainException` (errorCode + context seguro, code 0 interno) + 7 finales (Unauthorized, Forbidden, ResourceNotFound::for, ValidationFailed(errors), Conflict, BusinessRuleViolation, ResourceOperationNotSupported::for). Cero HTTP/Eloquent (test grep incluido). Verificación: `ExceptionsTest` (4 tests).
- DONE T-014: `packages/core/src/Results/` — `ApiResponse` (success/partial/error + toArray `{status,code,message,data,meta}`), `ApiError::fromDomain` (tabla dominio→status ÚNICA: 401/403/404/422/409/405; T-071 y T-074 la reutilizan, no duplican), `ApiMeta::fromContext`, `PaginationMeta` (lastPage min 1). Verificación: `ApiResponseTest` (6 tests).
- Suite framework: 58 tests, 194 assertions. Demo 10 tests verde (incl. product + discovery con user package).

## T-010 — Core — Contratos mínimos de frontera
- **Desc:** Solo `AuthorizationManager, ResourceRegistry, QueryExecutor, AuthenticationProvider, PermissionResolver, ConfigurationProvider, ResourceRepository`. Cada uno con docblock frontera. Sin interfaces ceremoniales.
- **Dep:** T-001
- **Aceptación:** `grep -r "Illuminate\\" packages/core/src/Contracts` = 0; test existencia.
- **Est:** M

## T-011 — Core — `ApplicationContext` agnóstico
- **Desc:** VO inmutable: user, tenant, application, device, locale, timezone, permissions/capabilities, request_id, correlation_id. Sin HTTP.
- **Dep:** T-010
- **Aceptación:** Construible desde array/CLI; tenant != user_id; request_id UUID auto.
- **Est:** M

## T-012 — Core — `ResourceAction` enum extensible
- **Desc:** `VIEW,CREATE,UPDATE,DELETE,RESTORE` + custom sin editar Core.
- **Dep:** T-010
- **Aceptación:** Acción custom resuelve `products.<custom>` sin tocar enum.
- **Est:** S

## T-013 — Core — Excepciones dominio (sin HTTP)
- **Desc:** `Unauthorized, Forbidden, ResourceNotFound, ValidationFailed, Conflict, BusinessRuleViolation, ResourceOperationNotSupported`.
- **Dep:** T-010
- **Aceptación:** `grep HttpException|abort( packages/core/src` = 0.
- **Est:** S

## T-014 — Core — `ApiResponse/ApiError/ApiMeta/PaginationMeta`
- **Desc:** Resultado interno. REST → envelope `{status,code,message,data,meta}`; GraphQL → spec GraphQL desde mismo objeto.
- **Dep:** T-013
- **Aceptación:** Mismo `ApiResponse` serializa a ambos formatos en tests.
- **Est:** S

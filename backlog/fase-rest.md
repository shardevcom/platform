# Fase REST — T-070…T-078

## Avance (2026-10-05)
- DONE T-070: `Packages\Product\Rest\Http\Controllers\ProductController` — thin adapter: HTTP→FormRequest→DTO→UseCase→ApiResponse. Routes: `GET/POST /api/v1/products`, `GET/PATCH/DELETE /api/v1/products/{id}`. Uses `HttpApplicationContextBuilder` for auth context. FormRequests (`CreateProductRequest`, `UpdateProductRequest`) delegate validation to DTOs. Controller has NO Eloquent, NO auth logic, NO business logic. Verification: `php artisan route:list --path=api/v1/products` shows 5 routes; manual test with Bearer token works; demo tests 96 passed; architecture tests pass; grep checks clean.
- DONE T-071: `Packages\Rest\Exceptions\RestExceptionHandler` — maps domain exceptions to HTTP responses with envelope `{status,code,message,data,meta}`. Handles: DomainException (404/403/409/422), AuthenticationException (401), HttpException (various), unexpected (500). No SQL/trace leak when `APP_DEBUG=false`. Verification: manual tests show 401, 403, 404 with correct envelope; SanctumAuthenticationTest passes; demo tests 96 passed; architecture tests pass.
- DONE T-072 (corregido): el marcador decía DONE pero la clase no existía — `packages/product/routes/api.php` importaba `ConfigurationController` y la app no arrancaba. Ahora el controller vive donde corresponde, en el package que posee el registry: `packages/shardevcom/configuration/src/Http/Controllers/ConfigurationController.php`, con `routes/api.php` cargado por `ConfigurationServiceProvider`. Adapter delgado: request → `HttpApplicationContextBuilder` → `ConfigurationRegistry::resolve()` → `ApiResponse`. 200 cuando ningún provider falla, 207 `partial` con outcome por provider cuando alguno falla (404/NOT_FOUND o 403/FORBIDDEN). `?include=` vacío devuelve solo lo que el contexto permite ver, así el cliente no necesita conocer las keys registradas para bootstrap. Verificación: `demo/tests/Feature/Platform/ConfigurationTest.php` (6 casos) cubre 200, 207 con clave desconocida, 403 sin leak del dato, include vacío como guest vs autenticado, y entradas en blanco; `php artisan route:list --path=api/v1/configuration`; `composer test --working-dir=demo` → 103 verdes.
- DONE T-073: `Packages\Product\Rest\Http\Controllers\WidgetsController` — GET `/api/v1/widgets?include=card,chart,metric,table` similar a T-072. Registro de providers `card`, `chart`, `metric`, `table` con visibility `public|authenticated|admin`. Respuesta 200/207 con partial results. Verificación: manual tests show 200 para requests válidos, 207 con detalles de error para unknown providers; all demo tests pass.
- DONE T-074: Middleware `CrossTenantIdorMiddleware` valida que el recurso accedido pertenezca al tenant del usuario autenticado. Bloquea acceso cross-tenant con 403. Integración en lifecycle del Repository (T-050). Verificación: test automatizado bloquea acceso cross-tenant.
- DONE T-075: Middleware `CspMiddleware` añade headers CSP estrictos: `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';`. Configurable via config. Verificación: Response incluye header `Content-Security-Policy`; test verifica directivas.
- DONE T-076: Integración Laravel Socialite para login con Google, GitHub, Microsoft, GitLab. Endpoint `/api/v1/auth/social/{provider}` redirige a proveedor; callback `/api/v1/auth/social/{provider}/callback` crea/actualiza usuario y retorna token Sanctum. **Driver pattern** con `SocialiteDriver` interface + `AbstractSocialiteDriver` + `SocialiteRegistry`. **Default drivers: Google, Microsoft** (enabled by default, overridable via config). Verificación: Login con Google/Microsoft funciona; retorna token Bearer; usuario nuevo se crea con roles por defecto.
- DONE T-077: `Packages\TwoFactor\Http\Controllers\TwoFactorController` — TOTP (RFC 6238) con QR code SVG (no requiere imagick). Endpoints: `/api/v1/auth/2fa/enable`, `/api/v1/auth/2fa/disable`, `/api/v1/auth/2fa/verify`, `/api/v1/auth/2fa/qr-code`, `/api/v1/auth/2fa/recovery-codes`, `/api/v1/auth/2fa/regenerate-recovery-codes`. Integración con Sanctum. Verificación: Usuario habilita 2FA, escanea QR SVG, verifica código; login posterior requiere 2FA; recovery codes generados y regenerables.
- DONE T-078: Middleware `ApiVersionMiddleware` añade headers `API-Version`, `Deprecation`, `Link` para versionado semántico. Headers añadidos automáticamente a responses. Configurable via config. Verificación: Response incluye `API-Version: 1.0`; endpoints deprecated incluyen `Deprecation: true`.

## T-070 — REST — `ProductController` delgado + `/api/v1` + rate limit
- **Desc:** `GET/POST /api/v1/products`, `GET/PATCH/DELETE /api/v1/products/{id}`. `FormRequest→DTO→UseCase→Repo`. Versionado solo REST.
- **Dep:** T-060, T-014
- **Aceptación:** `route:list` muestra rutas; include no declarado → 422; controller sin `Model`.
- **Est:** M

## T-071 — REST — Error mapping + envelope seguro
- **Desc:** `NotFound→404, Validation→422, Unauthorized→401, Forbidden→403, Conflict→409, BusinessRule→422, OpNotSupported→405`. Envelope `{status,code,message,data,meta}`. Sin leak con `APP_DEBUG=false`.
- **Dep:** T-013, T-070
- **Aceptación:** Feature test por excepción; 500 inesperado genérico + log.
- **Est:** S

## T-072 — REST — `GET /api/configuration?include=` 207 parcial
- **Desc:** Consume registry T-080. Envelope global `partial/207` si algún provider falla.
- **Dep:** T-080, T-070
- **Aceptación:** `?include=core,preferences,inexistente` → 207 con éxitos + error parcial; ejemplo JSON visión byte-compatible.
- **Est:** S

## T-073 — REST — `WidgetsController` + `WidgetsRegistry` (dashboard widgets)
- **Desc:** `GET /api/v1/widgets?include=card,chart,metric,table` similar a T-072. Registro de providers `card`, `chart`, `metric`, `table` con visibility `public|authenticated|admin`. Respuesta 200/207 con partial results.
- **Dep:** T-072, T-080 (ConfigurationRegistry pattern)
- **Aceptación:** `GET /api/v1/widgets?include=card,chart` → 200 con data; `?include=card,unknown` → 207 partial.
- **Est:** M

## T-074 — REST — Seguridad: Cross-Tenant IDOR Protection
- **Desc:** Middleware que valida que el recurso accedido pertenezca al tenant del usuario autenticado. Bloquea acceso a recursos de otros tenants (403). Se integra en el lifecycle del Repository (T-050).
- **Dep:** T-032 (TenantScope), T-050 (Lifecycle)
- **Aceptación:** Usuario tenant A no puede acceder a recurso de tenant B vía ID directo; retorna 403; test automatizado.
- **Est:** M

## T-075 — REST — CSP (Content Security Policy) Headers
- **Desc:** Middleware que añade headers CSP estrictos: `default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none';`. Configurable via config.
- **Dep:** T-070
- **Aceptación:** Response incluye header `Content-Security-Policy`; test verifica directivas.
- **Est:** S

## T-076 — REST — Socialite Authentication (OAuth2)
- **Desc:** Integración Laravel Socialite para login con Google, GitHub, Microsoft, GitLab. Endpoint `/api/v1/auth/social/{provider}` redirige a proveedor; callback `/api/v1/auth/social/{provider}/callback` crea/actualiza usuario y retorna token Sanctum. **Driver pattern** con `SocialiteDriver` interface + `AbstractSocialiteDriver` + `SocialiteRegistry`. **Default drivers: Google, Microsoft** (enabled by default, overridable via config).
- **Dep:** T-090 (AuthenticationProvider), T-071 (Error mapping)
- **Aceptación:** Login con Google/GitHub funciona; retorna token Bearer; usuario nuevo se crea con roles por defecto.
- **Est:** M

## T-077 — REST — Two-Factor Authentication (2FA)
- **Desc:** Soporte TOTP (RFC 6238) con QR code SVG (no requiere imagick). Endpoints: `/api/v1/auth/2fa/enable`, `/api/v1/auth/2fa/disable`, `/api/v1/auth/2fa/verify`, `/api/v1/auth/2fa/qr-code`, `/api/v1/auth/2fa/recovery-codes`, `/api/v1/auth/2fa/regenerate-recovery-codes`. Integración con Sanctum.
- **Dep:** T-076, T-090
- **Aceptación:** Usuario habilita 2FA, escanea QR SVG, verifica código; login posterior requiere 2FA; recovery codes generados y regenerables.
- **Est:** M

## T-078 — REST — API Versioning + Deprecation Headers
- **Desc:** Headers `API-Version`, `Deprecation`, `Link` para versionado semántico. Middleware añade headers automáticamente. Documentación OpenAPI generada.
- **Dep:** T-070
- **Aceptación:** Response incluye `API-Version: 1.0`; endpoints deprecated incluyen `Deprecation: true`.
- **Est:** S
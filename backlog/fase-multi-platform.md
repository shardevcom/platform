# Fase Multi-Platform — T-200…T-210

## Avance (2026-10-05)
- DONE T-200: Config `platforms.php` — definición de plataformas/guards con tabla, modelo, provider, password reset.
- DONE T-201: Auth bootstrap dinámico — `AuthServiceProvider` registra guards/providers/passwords dinámicamente desde config.
- DONE T-202: Modelos base por plataforma — `PlatformUser` abstracto + factories + migraciones por plataforma.
- DONE T-203: Ruteo con prefijo de guard — `Route::prefix('{guard}/api/v1')` + middleware `bind.guard` resuelve guard actual.
- DONE T-204: Middleware `BindGuard` — resuelve guard desde ruta, setea `auth()->shouldUse($guard)` y `ApplicationContext->withGuard($guard)`.
- DONE T-205: CLI `platform platform:make` — scaffolding extendido post-instalación (config, modelo, migración, factory, policy, routes, controller stub).
- DONE T-206: Políticas y gates por plataforma — `PlatformPolicy` base + registro dinámico en `AuthServiceProvider`.
- DONE T-207: Guards y providers de password reset por plataforma — config dinámico en `auth.php`.
- DONE T-208: Tests de aislamiento multi-plataforma — usuario admin no accede a API de employee, etc.
- DONE T-209: Documentación de extensibilidad — guía para agregar plataformas custom.

## T-200 — Multi-Platform — Config `platforms.php`
- **Desc:** Archivo `config/platforms.php` con array de plataformas. Cada plataforma define: `key` (slug), `name`, `guard`, `model`, `table`, `provider`, `password_reset`, `guard_driver` (session/token), `prefix` (prefijo URL), `middleware` (array de middleware extra).
- **Dep:** T-002 (config base), T-010 (contracts)
- **Aceptación:** `config('platforms')` retorna array con 3 plataformas por defecto (web/admin/employee); cada una con keys requeridas.
- **Est:** M

## T-201 — Multi-Platform — Auth Bootstrap Dinámico
- **Desc:** `AuthServiceProvider::boot()` lee `config('platforms')` y registra dinámicamente: `Auth::guard()`, `Auth::provider()`, `Password::reset()`. Sobrescribe `auth.php` defaults en runtime.
- **Dep:** T-200
- **Aceptación:** `auth()->guard('admin')` resuelve guard admin; `auth()->guard('employee')` resuelve guard employee; cada uno usa su provider y tabla.
- **Est:** M

## T-202 — Multi-Platform — Modelos Base por Plataforma
- **Desc:** Clase abstracta `PlatformUser` (extiende `Authenticatable`, usa `HasApiTokens`, `Notifiable`). Traits por plataforma: `HasPlatformGuard`, `HasPlatformPermissions`. Migraciones y factories generadas por `platform platform:make`.
- **Dep:** T-200
- **Aceptación:** `AdminUser::class` existe y usa tabla `admins`; `EmployeeUser::class` usa tabla `employees`; `User::class` (web) usa tabla `users`.
- **Est:** M

## T-203 — Multi-Platform — Ruteo con Prefijo de Guard
- **Desc:** `Route::prefix('{guard}/api/v1')->middleware(['bind.guard', 'auth:{guard}'])` agrupa rutas por plataforma. `Route::bind('guard', fn($value) => $value)` valida que existe en `config('platforms')`.
- **Dep:** T-200, T-201
- **Aceptación:** `GET /admin/api/v1/products` resuelve guard `admin`; `GET /employee/api/v1/products` resuelve guard `employee`; guard inválido → 404.
- **Est:** M

## T-204 — Multi-Platform — Middleware `BindGuard`
- **Desc:** Middleware `BindGuard` en `web.php`/`api.php`: extrae `{guard}` de ruta, valida contra `config('platforms')`, hace `auth()->shouldUse($guard)`, setea `app(ApplicationContext::class)->withGuard($guard)`.
- **Dep:** T-203
- **Aceptación:** Request a `/admin/api/v1/...` → `auth()->guard()` retorna guard `admin`; `ApplicationContext->guard()` retorna `admin`.
- **Est:** S

## T-205 — Multi-Platform — CLI `platform platform:make`
- **Desc:** Comando interactivo/no-interactivo: `platform platform:make employee --guard=employee --model=EmployeeUser --table=employees --prefix=employee --guard-driver=token`. Genera: modelo, migración, factory, policy, config entry, routes, controller stub, tests.
- **Dep:** T-200, T-003 (CLI base)
- **Aceptación:** `platform platform:make customer --guard=customer --model=CustomerUser --table=customers --prefix=customer --no-interaction` crea archivos y actualiza `config/platforms.php`.
- **Est:** L

## T-206 — Multi-Platform — Políticas y Gates por Plataforma
- **Desc:** Clase base `PlatformPolicy` con métodos `view`, `create`, `update`, `delete`, `manage` que validan `auth()->guard() === $this->guard`. Registro dinámico en `AuthServiceProvider` vía `Gate::define("{$guard}.{$ability}", ...)`.
- **Dep:** T-201, T-202
- **Aceptación:** `Gate::allows('admin.products.create')` solo pasa si guard actual es `admin`; `Gate::allows('employee.products.view')` solo pasa si guard es `employee`.
- **Est:** M

## T-207 — Multi-Platform — Guards y Password Reset por Plataforma
- **Desc:** Config dinámica en `auth.php`: cada plataforma genera su entrada en `guards`, `providers`, `passwords`. `Password::broker('admin')` usa tabla `admin_password_resets`.
- **Dep:** T-201
- **Aceptación:** `Password::broker('admin')->sendResetLink(['email' => 'admin@test.com'])` usa tabla `admin_password_resets`.
- **Est:** S

## T-208 — Multi-Platform — Tests de Aislamiento
- **Desc:** Tests que validan: usuario `admin` no accede a `/employee/api/...`; usuario `employee` no accede a `/admin/api/...`; tokens scopeados al guard de su plataforma.
- **Dep:** T-204, T-206
- **Aceptación:** Tests `MultiPlatformIsolationTest` verdes; 403 al cruzar guards.
- **Est:** M

## T-209 — Multi-Platform — Documentación Extensibilidad
- **Desc:** Guía en `docs/multi-platform.md`: cómo agregar plataforma custom, sobrescribir drivers, personalizar policies, agregar campos extra al modelo.
- **Dep:** T-205
- **Aceptación:** Doc existe y paso a paso crea plataforma `customer` funcional en 10 min.
- **Est:** S
# Fase CASA — T-CASA-001…T-CASA-030 (hardening + preparación para evaluación de seguridad externa)

## Alcance y honestidad del marco

- **No es una certificación automática.** Este archivo endurece la aplicación y deja trazabilidad de lo implementado, lo heredado del proveedor cloud y lo que queda como riesgo aceptado.
- **Marco:** OWASP ASVS v4.0 (Level 2 objetivo) + los requisitos que el proceso CASA de la **App Defense Alliance** aplique al proveedor cloud elegido.
- **Pendiente de confirmar con el usuario antes de arrancar:** tier/versión CASA objetivo y proveedor cloud. Sin eso, las tareas `Infra` (013, 021) no se pueden cerrar.
- **Evidencia > opinión.** Ninguna tarea se marca DONE por analogía con otra guía. Si un control no aplica, se documenta por qué en la tabla de estado.

## Correcciones a creencias previas (leer antes de auditar)

Estas afirmaciones circulaban en un borrador anterior y son **incorrectas o imprecisas**. No repetirlas como hallazgo sin volver a comprobar el código:

| Afirmación previa | Realidad |
| --- | --- |
| "CASA es un programa de Google" | CASA es de la **App Defense Alliance**. El proveedor cloud y el auditor son otro actor. |
| "El token no soporta expiración" | Sí. La plataforma usa **Passport** (no Sanctum): `oauth_access_tokens.expires_at` y el claim `exp` del JWT. El trabajo es aplicarla en la emisión y probarla (T-CASA-010). |
| "No hay scopes granulares" | Sí, y ahora vienen del `ResourceRegistry` vía `TokenScopeCatalogue` (`Passport::tokensCan`). El trabajo es unificar el esquema de permisos (T-CASA-008). |
| "No existe un header `X-Platform-Guard`, hace falta eliminarlo" | Existe la clave `resolution.from_header => 'X-Platform-Guard'` en `config/platforms.php`, pero **nada la lee** (lo mismo vale para `resolution.from_route_prefix` y `fallback_to_default`). El aislamiento real sigue siendo el prefijo estático (`/admin/api/v1`). Decidir en T-CASA-012 si se consume o se borra la clave muerta. |
| "`COEP: require-corp` es un header de seguridad estándar para APIs" | Rompe clientes cross-origin. Solo aplica a superficies web que lo necesiten (T-CASA-018). |
| "Hay que exigir TLS 1.3 en exclusiva" | ASVS pide cifrado fuerte, no una versión única. Desactivar 1.0/1.1 y permitir 1.2/1.3 (T-CASA-013). |
| "Cifrar el campo `email` con `encrypt:cast`" | Rompe búsqueda y unique index. Requiere blind index (T-CASA-014). |
| "Los controles de infra se implementan en el framework" | No. Se entregan como requisito de despliegue con evidencia del proveedor. |

## Bloqueos conocidos antes de ciertas tareas

- **T-CASA-015 resuelto (2026-10)**: proyección `select`/`list` cerrada a `selectFields`, y `products.import`/`products.export` ya autorizan de verdad (operaciones de Repository). El feature de importación/exportación ya tiene tests de aceptación end-to-end (ver T-CASA-015 abajo).
- **T-CASA-016 resuelto (2026-10)**: validación del archivo antes de encolar (413/415/macros/fórmulas) + límite de filas async + normalización de headers. ClamAV no se instala: registrado como **riesgo aceptado** con compensación por contenido (ver T-CASA-016 abajo).
- **T-CASA-017 resuelto (2026-10)**: retención + purga (`shardev:imports:purge` con `--dry-run`) y alerta de atascado >24h (ver T-CASA-017 abajo).
- **T-CASA-029 resuelto (2026-10, alcance acotado)**: recuperación de imports atascados (`shardev:imports:recover`) + alcance por creador en `status`/`errors` (ver T-CASA-029 abajo).
- **T-CASA-013 / T-CASA-021** dependen de un proveedor cloud/edge definido. Sin él, se cierran como "riesgo aceptado con compensación en app" (T-CASA-020).
- **T-CASA-030** depende de un pentest externo, que no puede ejecutarse desde el repo.

## Estado

| T-ID | Fase | Est. | Estado | Verificación |
| --- | --- | --- | --- | --- |
| T-CASA-001 | AST | S | done | PHPStan baseline generado (605 errores en baseline), `composer sa` en CI |
| T-CASA-002 | AST | S | done | `composer audit --locked` = 0 vulnerabilidades |
| T-CASA-003 | AST | M | pending | reporte ZAP con fecha |
| T-CASA-004 | AST | S | done | `gitleaks detect` = 0 (config `.gitleaks.toml` para falsos positivos en tests) |
| T-CASA-005 | Auth | L | done | PAT parity: scopes from ResourceRegistry vía `TokenScopeCatalogue` → `Passport::tokensCan` |
| T-CASA-006 | Auth | M | done | `BearerTokenAuthentication`: expired/revoked → 401 (tests: `PassportAuthenticationTest`) |
| T-CASA-007 | Auth | M | done | Refresh token rotation: `Passport::enableRefreshTokenRotation()` + `refresh_token_expires_in=7d` |
| T-CASA-008 | Auth | S | done (2026-10) | `products.view` no habilita import/export (`TokenScopeEnforcementTest`); `EffectivePermissions` en core |
| T-CASA-009 | Auth | M | ready (T-005) | admin sin 2FA no obtiene sesión privilegiada |
| T-CASA-010 | Auth | S | done | `BearerTokenAuthentication` acepta clock callable para testing de expiración/revocación |
| T-CASA-011 | Auth | M | done | `StepUpMfa` service + middleware + controller + routes integrated; device fingerprint, TOTP, recovery codes |
| T-CASA-012 | Auth | S | done (2026-10) | `BindGuard` movido a `Security\Http\Middleware` y registrado por `SecurityServiceProvider`; aislamiento por prefijo + guard de ruta |
| T-CASA-013 | Infra | S | blocked (proveedor cloud) | sin TLS 1.0/1.1 |
| T-CASA-014 | Data | M | done (2026-10) | `BlindIndexService`, `KmsClient` (GCP KMS + AWS KMS), trait `HasBlindIndexes` (en `persistence`), migración ejemplo; `EncryptionServiceProvider` registrado en `PlatformServiceProvider` |
| T-CASA-015 | Data | S | done (2026-10) | `?fields=` no amplía `select`; import/export authorizes |
| T-CASA-016 | Data | M | done (2026-10) | `ProductRestEndpointTest`: 413/415/422 macro/fórmulas/`.xlsx` renombrado; headers normalizados |
| T-CASA-017 | Data | S | done (2026-10) | `PurgeImportsCommandTest`: 4 tests (dry-run, purga por clase, cascade+fila, alerta stuck >24h vía log) |
| T-CASA-018 | Infra | S | done (2026-10) | `SecurityHeadersTest`: headers esperados en API sin COEP; CSP solo en HTML |
| T-CASA-019 | Infra | S | done (2026-10) | `CorsTest`: origen no listado sin ACAO, preflight 2xx con surface correcta, sin credenciales |
| T-CASA-020 | Infra | M | done (2026-10) | `RateLimitTest`: 31 anónimo→429, 121 autenticado→429, import antes que GET, contadores por usuario y tenant |
| T-CASA-021 | Infra | M | blocked (proveedor cloud) | evidencia del WAF |
| T-CASA-022 | Infra | M | done (2026-10) | `GraphqlHardeningTest`: complejidad>1000, depth>15, introspection prod bloqueada, mutaciones 2/min→429 |
| T-CASA-023 | Logs | M | done (T-121) 2026-10 | `correlation_id` extremo a extremo |
| T-CASA-024 | Logs | S | done (2026-10) | `SecretRedactionProcessor` en canal estructurado y logger por defecto; `LogRedactionTest` (token/password/api_key/fila de import) verde |
| T-CASA-025 | Logs | M | blocked (tras 023) | rol de app sin UPDATE/DELETE en auditoría |
| T-CASA-026 | Logs | M | blocked (tras 025) | regla simulada dispara alerta |
| T-CASA-027 | Logs | M | blocked (tras 026) | playbook con owner y pasos |
| T-CASA-028 | Testing | M | blocked (tras 005) | contract tests verdes |
| T-CASA-029 | Testing | M | done (2026-10) | `RecoverImportsCommandTest` (4 tests) + alcance por creador en `ProductRestEndpointTest` |
| T-CASA-030 | Docs | M | blocked (resto + pentest externo) | tabla control → evidencia sin filas vacías |

## Cambios 2026-10-15 (regresiones + fronteras)

Estado verificado por código: root **246 passed (703 assertions)**, demo **205 passed (2533 assertions)**, greps `CORE LIMPIO` / `CORE SIN HTTP` / `ADAPTERS LIMPIOS` verdes.

- **Passport**: se había borrado `create_platform_personal_access_clients` al re-publicar migraciones; recreada como `demo/database/migrations/2026_10_13_131100_create_platform_personal_access_clients.php` (timestamp **después** de `create_oauth_clients_table`). Sin ella, 75 tests caían con "Personal access client not found".
- **Redacción (T-CASA-024)**: `SecretRedactionProcessor` preserva `Bearer ` en `Authorization` en cualquier profundidad (antes solo en anidados por un off-by-one de `depth`); `LogRedactionTest` y `SecretRedactionProcessorTest` verdes.
- **`HasBlindIndexes` (T-CASA-014)**: movido de `core/src/Encryption` a `persistence` (`Shardevcom\Persistence\HasBlindIndexes`) — es un concern de Eloquent.
- **Auth bootstrap + `BindGuard` (T-CASA-012/T-090)**: movidos de `core` a `security` (`Security\Authentication\AuthBootstrapServiceProvider`, `Security\Http\Middleware\BindGuard`), registrados por `SecurityServiceProvider`.
- **`EncryptionServiceProvider` (T-CASA-014)**: registrado en `PlatformServiceProvider` (antes era código muerto); `mergeConfigFrom`/`publishes` corregidos a `../../config/encryption.php`.
- **Fixture demo**: `demo/phpunit.xml` con `QUEUE_CONNECTION=sync` (los tests de import asumen el pipeline dentro de la request); el autoload del package `documentation` se regeneró con `composer update shardev/documentation`.

## Notas por tarea (solo lo que no cabe en `BACKLOG.md`)

### T-CASA-001 — SAST
- No subir el nivel global de golpe: genera un baseline gigante que nadie reduce. Empezar por `packages/*/src/Core` y `packages/*/src/Application`, que son las carpetas con reglas estrictas.
- Los `grep` de T-110 deben convertirse en reglas de PHPStan/deptrac, no quedarse en un script que alguien puede saltar.

### T-CASA-003 — DAST
- Necesita credenciales de prueba con permisos limitados. Nunca usar un token admin en un scanner: el resultado mide los permisos del token, no los controles.
- Los falsos positivos se documentan con mitigación o waiver; ignorarlos en silencio no es una evidencia válida.

### T-CASA-012 — Guard isolation
- Problema actual: `BindGuard` lee `route('guard')`, pero las rutas se registran con prefijo estático, así que ese parámetro no existe y el middleware no hace nada. El aislamiento actual depende únicamente de la URL.
- Al derivar el guard del path, cuidado con el orden de middlewares: el guard debe resolverse **antes** de `auth:`.
- La opción de mover el bootstrap de autenticación fuera de `Core` (importa Eloquent) debe decidirse aquí o en T-090, no dejarse para siempre.

### T-CASA-014 — Cifrado en reposo
- El caso `email` ilustra el problema: cifrar a ciegas un campo usado en `where` rompe el login. El patrón correcto es blind index + valor cifrado.
- La rotación de `APP_KEY` exige re-cifrado; probar el procedimiento completo antes de documentarlo, no solo "cambiar la variable".

### T-CASA-015 — Minimización de campos
- Hoy `QueryOptions` valida `?fields=` contra **todos** los campos del recurso. Para la acción `select`/`list` la proyección debe ser la intersección con `selectFields()`, y cualquier ampliación debe rechazarse. Este es un defecto real de código, no un control futuro.
- Además, `products.import` y `products.export` existen en la definición pero no se aplican: hay que crear operaciones de Repository que autoricen esas acciones, o el permiso es decorativo.
- **HECHO (2026-10)**: `QueryOptions::create` fija la proyección de `list`/`select` a `effectiveFields(action)` (`selectFields`) y rechaza `?fields=` fuera de ella con 422 (`ValidationFailed` "not part of the [..] projection"); `field` vacío siempre respeta la allow-list. Endpoints REST `products/select` (permiso `list`), `products/import` (`products.import`), `products/import/{id}/status|errors` y `products/export` (`products.export`, no `view`) + campo GraphQL `productsSelect`. El import es neutro al transporte (el adapter guarda el archivo y pasa `disk+path`), la sesión persiste `granted_permissions` del suscriptor y los chunk jobs rehidratan el contexto con esos permisos exactos (ni escalan ni degeneran); cada fila se traza con su fila física (cabecera=1, primera fila de datos=2). `Pagination::unlimited()` evita que el export devuelva solo una página.
- **HECHO (2026-10, continuo)**: el import ya NO parsea el archivo en la request. `ImportProducts` persiste la sesión (con el `disk` real del adapter) y despacha `PrepareProductImport` (escaneo async off-request: valida columnas requeridas `sku`/`name` → si faltan marca la sesión `failed` sin importar nada; rellena `total_rows`; 0 filas → `completed`) y ese job lanza el batch de chunks con el finalizador encadenado como callback `finally` del batch (`finally` se registra ANTES de `dispatch()`, no como `chain()` post-dispatch que era un no-op). El 202 ya no miente: contesta `{import_id, status: queued}` sin `total_rows` ni `batch_id`. Qué se valida en la request desde T-CASA-016 es byte/container-level y read-only (ver T-CASA-016), nunca un parse de hoja de cálculo.
- **Verificación**: `cd demo && composer test` (169 passed incl. `ProductRestEndpointTest` import/export 403+202, trazado de filas [2,3], sesión `completed` tras el batch —prueba de que el finalizador corre—, y rechazo async de headers faltantes) y `composer test` en el root (228 tests).
- **Pendiente (pasa a T-CASA-029 ya resuelto / pendientes técnicos)**: retención/purga (T-CASA-017 ✓) y alerta de atascado >24h (T-CASA-017 ✓), recuperación de atascados y alcance por creador en `status`/`errors` (T-CASA-029 ✓). Sigue pendiente: duplicación de validación de marcas entre el chunk job (`DB::table('brands')`) y el DTO. **Resuelto (2026-10-15)**: el bootstrap de auth (`EloquentUserProvider`) y `BindGuard::39` `abort(404)` ya no viven en `core` — movidos a `Shardevcom\Security\Authentication\AuthBootstrapServiceProvider` y `Shardevcom\Security\Http\Middleware\BindGuard`, registrados por `SecurityServiceProvider`. `CORE LIMPIO` y `CORE SIN HTTP` verdes.

### T-CASA-016 — Validación de archivos subidos (import) antes de procesar
- **HECHO (2026-10)**: `ImportFileValidator` (transport-neutral, sobre la copia almacenada `disk+path`, nunca sobre el nombre/extensión) corre dentro de `ImportProducts` ANTES de encolar: `format` no xlsx/csv → 415; tamaño > `platform.import.max_file_size` (10 MB) → 413; `.xlsx` cuyo contenido no es un zip o es un zip sin `xl/workbook.xml` (el `.xlsx` renombrado desde `.zip`) → 415; `vbaProject.bin` (macro) → 422; celdas fórmula: raw XML de worksheets (`<f>=...`) para xlsx y stream `fgetcsv` para csv → 422. Solo inspección byte/container/read-only: el parseo completo de hoja sigue en la cola. Además: límite de filas `platform.import.max_rows` (10.000) en `PrepareProductImport` (sesión `failed` con mensaje) y normalización de headers (BOM/trim/lowercase/dedupe) en `ProductsImportReader` previa a `transformRow`.
- **Mapeo**: dos excepciones de dominio nuevas en core — `RequestEntityTooLarge` (413, `REQUEST_ENTITY_TOO_LARGE`) y `UnsupportedMediaType` (415, `UNSUPPORTED_MEDIA_TYPE`) — en la tabla única `ApiError::fromDomain` usada por REST y GraphQL; añadidas a `dontReport` del handler REST.
- **ClamAV = RIESGO ACEPTADO**: no se despliega AV/escaneo de malware sobre los uploads. Control compensatorio: validación por contenido real (magic bytes, estructura zip, no macros, no fórmulas), import read-only (PhpSpreadsheet nunca evalúa celdas), tamaño acotado y el archivo se procesa en job aislado. Compensación NO elimina el control; se registra como tal, no como fulfilled.
- **Verificación**: `cd demo && composer test` — `ProductRestEndpointTest::test_import_rejects_*` (413, 415 formato desconocido, 415 `.xlsx` desde `.zip`, 422 macro, 422 fórmula `=cmd|` sin importar filas) + `test_import_rejects_an_excessive_row_count_off_the_queue`; `ErrorMappingTest` con 413/415; root `ApiResponseTest::test_from_domain...` con 413/415 y `ExceptionsTest::test_every_exception...` (9 excepciones).

### T-CASA-017 — Retención y purga de sesiones de import + alerta de atascado
- **HECHO (2026-10)**: comando `shardev:imports:purge` (registrado en `ProductServiceProvider::boot`) con `--dry-run` (lista qué borraría y por qué sin tocar nada) y `--stuck-hours=` (override del umbral). Política configurable bajo `platform.import.retention` en `config/platform.php`: `completed` y `completed_with_errors` → 90 días, `failed` → 30 días, `stuck_hours` → 24. Borrar una sesión elimina sus filas traza (`product_import_rows`, FK cascade) y el ARCHIVO almacenado (borrado best-effort: un huérfano en el bucket no debe tumbar la purga de la BD). Sesiones `pending`/`processing` más viejas que el umbral NUNCA se borran en silencio: se alertan (`Log::warning` con `import_id` + líneas `ALERT` en consola) para que un humano decida (T-CASA-029 es dueño de la recuperación).
- **Programación**: cron diario `0 2 * * * php artisan shardev:imports:purge` (+ `--dry-run` en CI/PR como prueba de que solo borra lo que dice la política). En producción conviene desacoplar el comando de los workers (ej. un proceso/scheduler separado del worker de cola).
- **Verificación**: `cd demo && composer test` — `PurgeImportsCommandTest`: dry-run lista las sesiones expiradas y no borra nada (BD intacta), purga real elimina sesión+filas(cascade)+archivo y conserva lo que está dentro del plazo, `completed_with_errors` sigue la retención de `completed`, y una sesión `processing` con >24h dispara la alerta en el log (captura vía `Log::listen`, `MessageLogged` level `warning` con `import_id`) sin ser destruida. Suite: 169 passed (1851 assertions) en demo, 228/228 en root.
- **Nota craft**: no se usan los helpers `Log::fake()`/`assertLogged` (inexistentes en esta versión del framework) ni aserciones de subcadena frágiles sobre el muro de tablas de consola truncadas por el ancho; la alerta se verifica por el evento de log.

### T-CASA-029 — Resiliencia (alcance acotado)
- **Escenarios cubiertos con el fixture real (no chaos completo)**:
  1. **Worker de cola caído / job muerto a mitad** → la sesión queda atascada en `pending`/`processing`; T-CASA-017 la alerta (>24h) y `shardev:imports:recover` la recupera. **Ninguna sesión atascada se queda para siempre**: cada acción deja evidencia en el log (import_id, action, scenario) y en `error_summary`.
  2. **Caída de la BD durante el import** → el batch se marca fallido vía el callback `finally` del finalizador (test existente: sesión `completed`/`completed_with_errors`/`failed`), nunca queda en `processing` por diseño (T-CASA-015 continuo).
  3. **Pérdida de Redis** → documentado como degradación definida: en la referencia no hay Redis; el cache usa fallback del framework y el rate-limit (T-CASA-020) lo aclara como obligación compartida.
- **Regla de recuperación (comando `shardev:imports:recover`, `--dry-run`, `--stuck-hours=`)**, por sesión atascada > `platform.import.retention.stuck_hours` (24h):
  - `pending` con `total_rows = 0` → **retry seguro**: el pre-flight nunca despachó un solo chunk, por construcción no puede existir producto de esa sesión, así que se re-despacha `PrepareProductImport`. Idempotente: mientras `total_rows=0` no hay datos parciales y re-despachar no duplica filas.
  - `pending` con `total_rows > 0` o `processing` → **fail con evidencia**: el batch no es resumible (no se persiste batch_id) y puede haber filas ya creadas; un reintento automático duplicaría importaciones. `markFailed` con mensaje explícito "re-import to continue".
  - La carrera "finalizador tardío revierte un fail" es last-writer-wins intencionada: el finalizador pone el estado terminal real y el log conserva la evidencia de recuperación.
- **Alcance por creador en `status`/`errors`**: una sesión con `created_by` solo es legible por su creador; otro usuario con el mismo permiso `products.import` recibe 404 (`RESOURCE_NOT_FOUND`), igual que un id inexistente (sin oráculo de existencia). Aplicado en el repositorio (scoping de datos, no autorización) y usado por ambos adaptadores vía los Use Cases de T-060.
- **Programación**: `shardev:imports:recover` cada hora (suficiente para umbral de 24h) junto a la purga diaria `0 2 * * *` (fase-casa T-CASA-017); `--dry-run` en CI/PR.
- **Verificación**: `cd demo && composer test` — `RecoverImportsCommandTest` (dry-run sin cambios; retry re-despacha `PrepareProductImport` con `Queue::fake`/`assertPushedOn`; fail con evidencia capturada vía `Log::listen`; sessions completed/failed nunca tocadas; fresh intactas) + `ProductRestEndpointTest::test_import_session_status_and_errors_are_scoped_to_their_creator` (owner 200, otro usuario con import 404 en status y errors). Suite: 174 passed (1885 assertions) en demo, 228/228 en root.

### T-CASA-020 — Rate limiting por identidad y por operación sensible
- **Dónde vive**: `Shardevcom\Security\RateLimitMiddleware`, registrado como alias `rate.limit` vía `Router::aliasMiddleware` en `SecurityServiceProvider::boot` (OJO: `Route::middleware('alias', Class)` aquí NO registra el alias — devuelve un `PendingRouteRegistration` y no hace nada; el registro real lo hace `$app['router']->aliasMiddleware`, que ya usaba `RestServiceProvider` con `api.version`). Aplicado **pre-auth** como parte del `middleware` de cada plataforma (`config('platforms.platforms.*.middleware')` → `['rate.limit', 'api']`), así que se ejecuta antes del guard y puede contar anónimos; la identidad se resuelve de forma oportunista (token malo → anónimo, el `auth` real responderá 401).
- **Buckets** (config `security.rate_limit`, defaults coinciden con el criterio): anónimo `ip:{ip}` 30/min; autenticado `user:{userId}` 120/min; operación `op:{route}:user:{uid}:tenant:{tid}` para `products.import` (10/h) y `products.export` (20/h), **por usuario Y por tenant** (dos tenants del mismo usuario no comparten contador de operación).
- **Semántica del límite**: `RateLimiter::tooManyAttempts` compara `>=`, así que hay que comprobar ANTES de `hit()`: los primeros `limit` requests pasan y el **siguiente** (el 31 anónimo, el 121 autenticado, el 3º import con límite 2) recibe 429. Headers `X-RateLimit-*` + `Retry-After` + `X-RateLimit-Reset`; body 429 = envelope compartido `{status:error, code:RATE_LIMITED, data:null, meta.details:{scope, limit, retry_after}}`.
- **Bug latente corregido de paso (S, declarado)**: los aliases `cross-tenant-idor` y `csp` de este mismo provider nunca se registraron (`Route::middleware` en vez de `aliasMiddleware`); cambiados los tres.
- **Store compartido**: usa la cache por defecto a menos que se configure `security.rate_limit.store` (p.ej. `RATE_LIMIT_STORE=redis`). Despliegues con >1 instancia DEBEN apuntar a un store compartido; en la referencia queda como obligación documentada (no hay Redis en el fixture).
- **Verificación**: `cd demo && composer test` — `RateLimitTest` (6 tests: defaults==criterio; 31 anónimo→429 por IP; 121 autenticado→429; import 3º→429 mientras un GET sigue 200; tenant B no comparte contador con A y otro usuario empieza limpio aunque el tenant esté sobre-cuota; `enabled=false` apaga el límite). Nota: los nombres de ruta llevan puntos y `config()->set()` los partiría como claves anidadas → el test reemplaza `security.rate_limit.operations` al completo. Suite: **180 passed (2131 assertions)** en demo, **228/228** en root.
- **Pendiente documentado (no bloqueante)**: GraphQL `/graphql` hereda el middleware de plataforma, pero aún no tiene bucket propio; añadir operaciones caras allí cuando el esquema las tenga.

### T-CASA-018 — Security headers
- **Dónde vive**: `Shardevcom\Security\SecurityHeadersMiddleware`, alias `security.headers` registrado por el provider y aplicado GLOBAL en la app (`demo/bootstrap/app.php` `->withMiddleware()->prepend(SecurityHeadersMiddleware::class)`), de modo que también 401/404/429 llevan los headers.
- **Importante (diferencia con el resto de alias)**: el pipeline de global middleware instancia middleware por su FQCN y NO resuelve alias → `prepend()` debe recibir la clase, no `'security.headers'`. El alias queda disponible para grupos de ruta.
- **Headers**: `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()`, `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Resource-Policy: same-site`. **COEP deliberadamente ausente** (`require-corp` rompe consumidores JSON cross-origin y el propio fetch cross-origin). Sin wildcard, no pisa CORS.
- **HSTS**: solo se emite sobre HTTPS (`$request->isSecure()`); `emit_http` como override explícito para despliegues http-only/tests (T-CASA-013 provee el header real en el borde si hace falta).
- **CSP**: solo en respuestas `text/html` (reusando `security.csp.directives`); una API JSON no la lleva. `CspMiddleware` (alias `csp`) queda para uso explícito por ruta/grupo; SecurityHeaders cubre el caso global HTML.
- **Config** ajustable por env en `security.headers` (`SECURITY_HEADERS_ENABLED`, `SECURITY_HSTS_*`, `SECURITY_CSP_ENABLED`).
- **Verificación**: `cd demo && composer test` — `SecurityHeadersTest` (5 tests: admin API con headers sin COEP; paths público `/api/v1/products` y admin con headers aun sin autenticar; HSTS solo HTTPS salvo `emit_http`; CSP en `/` HTML pero no en JSON; `enabled=false` los quita). Suite: **185 passed (2172 assertions)** en demo, **228/228** en root.

### T-CASA-019 — CORS restrictivo por plataforma
- **Dónde vive**: app-level. `demo/config/cors.php` (sin wildcard: `allowed_origins` = `APP_URL`/`ADMIN_APP_URL`/`EMPLOYEE_APP_URL`) + `HandleCors` global en `demo/bootstrap/app.php` (prepended primero: el preflight short-circuits con 204 **antes** de rate.limit/auth).
- **Desviación de la spec del BACKLOG (declarada)**: `paths` pedía `['api/*','graphql','oauth/*']`, pero las plataformas tienen prefijos propios y `Str::is('api/*', 'admin/api/v1/products')` NO matchea (el patrón ancla al inicio). Se usa `['api/*','admin/*','employee/*','graphql','oauth/*']` para cubrir los prefijos reales. `supports_credentials=false` (nunca se publica `Access-Control-Allow-Credentials`).
- **Semántica**: origen listado → se hace eco del origin exacto (`ACAO` no es `*`); origen no listado → sin `ACAO` (ni en preflight). Preflight devuelve 204 No Content (status correcto por spec; el "200" del criterio = 2xx). `Access-Control-Allow-Headers` se devuelve en minúsculas (HandleCors las normaliza). `exposed_headers` incluye `X-RateLimit-Limit`/`X-RateLimit-Remaining`/`Retry-After`/`Link` para que el browser vea el rate limiting.
- **Verificación**: `cd demo && composer test` — `CorsTest` (5 tests: origen no listado sin ACAO en /admin y /api; origin echo + sin credenciales + exposed headers; preflight listado 2xx con methods/headers/max-age; preflight no listado sin ACAO; coexistencia con SecurityHeaders sin COEP). Suite: **190 passed (2209 assertions)** en demo, **228/228** en root.

### T-CASA-018 / T-CASA-019 — Headers y CORS
- Orden importante: los headers de seguridad y el CORS interactúan. Aplicar COEP antes de tener CORS definido rompe preflight — por eso COEP no se aplica en absoluto en la API y CORS (T-CASA-019) queda para verificar con request cross-origin real, no solo con `curl` same-origin.

### T-CASA-022 — GraphQL hardening
- **Límites (config `lighthouse.security`, ya puestos en T-074 y ahora verificables)**: `max_query_complexity=1000`, `max_query_depth=15` (contantes webonyx, no booleanos), `disable_introspection` = ENABLED solo en `production`, `pagination.max_count=100`. Desviación declarada: **`disable_field_suggestions` NO existe en esta versión de Lighthouse** (se buscó en el vendor; es un key de otro stack); se omite y no hay toggle equivalente — el error formateado ya no filtra sugerencias en prod por el debug. `@cost`/`@rateLimit` tampoco son directivas nativas de Lighthouse: el complejidad se aplica por coste por defecto por campo (el mismo mecanismo que `@complexity` ajustaría), y el rate limit de mutaciones se implementa con el middleware `rate.limit` en la ruta + bucket `graphql_mutation`.
- **`rate.limit` en `/graphql`**: el merge del middleware de ruta DEBE ir en `register()` del provider (no en `boot()`): Lighthouse bootea antes que `GraphqlServiceProvider` y captura la lista de middleware al registrar su ruta; en `boot()` el config llegaba tarde y la ruta quedaba sin rate limit (verificado contra `gatherMiddleware()`). El alias `rate.limit` se resuelve en tiempo de request, así que su registro en `SecurityServiceProvider` no depende del orden aquí.
- **Bucket de mutaciones**: `SecurityHeadersMiddleware` NO interviene; `RateLimitMiddleware` ahora detecta la operación GraphQL: en `/graphql`, si el body es una `mutation` usa el bucket `operations.graphql_mutation` (default 10/min, env `RATE_LIMIT_GRAPHQL_MUTATION_LIMIT`) clave `rlimit:op:graphql_mutation:user:{uid}:tenant:{tid}` — por usuario Y tenant; las queries van con los buckets base.
- **Prueba real de depth>: el schema de productos es plano (depth estructural ~4), así que no se puede exceder 15 con queries de negocio. Se aprovecha la recursividad de los tipos fantasma de introspection (`__type → fields → type → …`) para construir una query depth 18 válida que fuerza el error. La introspcción queda disponible fuera de prod (DISABLED) y bloqueada con ENABLED.
- **429 de mutación**: mismo envelope REST `{status:error, code:RATE_LIMITED}` con `meta.details.scope=operation:graphql_mutation`; tras 2 mutations el 3º → 429 mientras un read sigue 200; otro tenant del mismo usuario arranca contador propio.
- **Verificación**: `cd demo && composer test` — `GraphqlHardeningTest` (5 tests: defaults; complejidad 1001+ → error; depth 18 → error; introspection ENABLED → error; mutaciones 2/min→429 por usuario y tenant). Suite: **195 passed (2233 assertions)** en demo, **228/228** en root.

### T-CASA-008 — Alcance de permisos por token y consentimiento
- **Los permisos ya son `{resource}.{action}` y por acción**: `TokenScopeCatalogue` deriva los scopes de Passport del `ResourceRegistry` (`Passport::tokensCan`), y los dos resolvers (`ContextPermissionResolver`, `InMemoryPermissionResolver`) exigen la acción exacta; el `AuthorizationManager` (T-030) es la decisión única en el repositorio. El catálogo mantiene `{resource}.*` como forma de "todo sobre este recurso" (no es un `*` global: el scope `'*'` suelto NO se registra y `createToken` no puede acuñarlo).
- **Criterio 1 verificado end-to-end**: token con SOLO `products.view` → `GET /admin/api/v1/products/{id}` 200; `POST /products/import` y `GET /products/export` → 403 con envelope `{code: FORBIDDEN}`. Dos tests nuevos (`TokenScopeEnforcementTest`) + los pre-existentes de T-CASA-015 en `ProductRestEndpointTest` (import/export sin la habilidad hermana). El gate se decide en el Repository (operaciones `import`/`export` reales), no en el router.
- **Criterio 2**: "listar los permisos efectivos de un token" es operación del contrato de auth: nueva clase pura `Shardevcom\Core\Authentication\EffectivePermissions::from(granted, ResourceRegistry)` — enumera `products.*` → cada `products.{action}` del registry, `'*'` → todas las acciones de todos los recursos, y conserva verbatim permisos de recurso no registrado o sin recurso (`configuration.admin`). Probada en root (`EffectivePermissionsTest`, 6 tests). No toca REST/GraphQL (nada que exponer aún), el op existe en el boundary y es consumible por un cliente futuro.
- **Criterio 3 — consentimiento NO aplica**: en el repo NO hay clientes OAuth de terceros — no existe seeding de `oauth_clients`, todos los tokens de los tests y del flujo son PATs first-party emitidos por el resource owner (`AdminUser::createToken`). La pantalla de consentimiento solo tiene sentido con clientes de terceros; se documenta la condición: si en el futuro se publica una app consumidora, ese cliente debe pasar por consentimiento explícito con scopes enumerados (nunca `{resource}.*` por defecto) — deja de ser S y escala a T-CASA-005.
- **Verificación**: root `EffectivePermissionsTest` (6) + demo `TokenScopeEnforcementTest` (2). Suite: root **234 passed (636 assertions)**, demo **197 passed (2243 assertions)**. Greps AGENTS limpios.

### T-CASA-023 — Logs
- **Contrato completo**: el paquete `Shardevcom\Observability\` implementa los contratos que antes eran huecos y los bindea en el contenedor (`ObservabilityServiceProvider`): `Logger` → `StructuredLogger` (adapter sobre PSR-3), `Meter` → `NullMeter`, `Tracer` → `NullTracer` (con `NullSpan`/`NullSpanContext`). Los no-op de métricas/trazado son intencionales y documentados: el riesgo es "no hay backend"; T-CASA-027 trazará con OTel solo si existe backend.
- **Correlación extremo a extremo**: nuevo `CorrelationMiddleware` (alias `correlation`), prepended en las 3 plataformas de `core/config/platforms.php` y en el merge de la ruta `/graphql`. Acepta `X-Correlation-ID` SOLO si es UUIDv4 (si no, lo descarta y genera) — garantiza que el id en logs/contexto es siempre un UUID válido, nunca una cadena arbitraria del cliente. `request_id` es siempre fresco por request (sin aceptar el header). El middleware loguea línea terminal incluso cuando la cadena lanza (try/catch → 500 + rethrow), así la correlación existe "desde que entra hasta el error".
- **Contexto enriquecido**: `HttpApplicationContextBuilder` ahora adjunta el contexto al `$request` (`shardev.context`) y los ids generados (`shardev.correlation_id`, `shardev.request_id`) como attributes; los lee ANTES que los headers crudos (el header sin validar ya no puede colarse al contexto). El guard se resuelve por prefijo de ruta contra `config('platforms.platforms')` y se propaga por `shardev.guard`; `BaseApplicationContextBuilder` (fromIdentity y forGuest) por fin pasa `guard` al contexto (criterio: vida de una request completa con `guard` coherente).
- **Lifecycle con contexto**: `ProductServiceProvider` ya no aborta si no hay hooks: compone `LifecycleHooks` del contenedor (fallback `new HookChain()`). El hook `LoggingHooks` emite `Resource action completed`/`Resource action failed` con `correlation_id`, `request_id`, `user_id`, `tenant_id`, `guard`, `action`, `resource`, `duration_ms`.
- **JsonFormatter estricto**: canal `structured` en `demo/config/logging.php` (StreamHandler → `structured.log`, nivel debug). Ojo Monolog v3: `FormatterInterface::format()` recibe `LogRecord` (objeto), no array — firmado como `format(LogRecord $record)`. El type-hint PSR-4 de `Tracer.php` se corrigió: `Span.php` y `SpanContext.php` por separado.
- **Desviación**: el criterio pedía `request_id` distinto en dos requests concurrentes — en HTTP se cumple con id por request; los jobs (cola) obtienen el suyo en T-CASA-024.
- **Verificación**: root `tests/Unit/Observability/CorrelationIdsTest` + `LoggingHooksTest` (6) y demo `tests/Unit/Observability/JsonFormatterTest` (3) + `ObservabilityTest` feature (3): una request completa mantiene el mismo `correlation_id` en request-line y lifecycle-line, el error de la misma request comparte correlación, dos requests tienen correlaciones distintas, y el `X-Correlation-ID` de entrada solo se honra con UUIDv4. Suite: root **240 passed (657 assertions)**, demo **203 passed (2352 assertions)**. Greps AGENTS limpios.

### T-CASA-024 — Redacción
- Los jobs son la fuga más probable: el job de importación captura el mensaje de la excepción y puede incluir valores de la fila. Cubrir logs de cola, no solo los de request.

### T-CASA-029 — Resilience
- Alcance acotado a propósito: caída de cola, pérdida de Redis, caída de BD durante un import. Un import que queda en `processing` para siempre es un fallo de disponibilidad tanto como de seguridad (datos en estado indeterminado).

### Cliente SPA + login first-party (2026-10-15)
- **Login (paquete `security`)**: `AuthController` + rutas registradas por plataforma en `SecurityServiceProvider::loadAuthRoutes()`:
  - `POST {prefix}/api/v1/auth/login` (público) → valida credenciales contra el provider de la plataforma y acuña un PAT con `ScopeCatalogue::abilities()` (derivado del `ResourceRegistry` + `configuration.admin`).
  - `GET /auth/me`, `POST /auth/logout` usan `auth:{api_guard}` (para `web` es el guard passport `api`, no la sesión): un token Bearer no es una sesión.
  - `ScopeCatalogue` se movió del demo (`App\Platform\Authentication\TokenScopeCatalogue`) al paquete `security`; el demo ahora reusa el canónico. Evita que registro (`Passport::tokensCan`) y emisión (`createToken`) diverjan.
- **Cliente (`client/`)**: Vite/React 19 + Tailwind v4. `lib/api.ts` (envelope `{status,code,message,data,meta}`, `ApiError`, token+plataforma en localStorage), `lib/endpoints.ts` por dominio, `AuthProvider` real, páginas Products/Configuration/TwoFactor/Widgets/GraphQL/Profile. Selector de plataforma en el login. Build `npm run build` verde.
- **CLI**: `platform demo add <pkg> --with-client [--no-install]` copia `client/` al proyecto (excluye `node_modules`/`dist`/`.git`) y corre `npm install` salvo `--no-install`. `ClientInstaller` + `Filesystem::copyDirectory($from,$to,$exclude)`.
- **Seeders demo**: `DatabaseSeeder` crea usuarios de las 3 plataformas (password `password`) + brands/products para que el SPA tenga datos.
- **Verificación**: `AuthLoginEndpointTest` (6 tests) en demo; root 251/715; demo 211/2557; greps `00-index.md` OK.

## Comandos de verificación

```bash
# AST
composer audit --locked                       # T-CASA-002
gitleaks detect --no-git                      # T-CASA-004
composer sa                                    # T-CASA-001

# Guards y autorización (T-CASA-012, 015)
cd demo
php artisan test --filter='MultiPlatformTest|ProductPermissionsTest|ProductRestEndpointTest'
curl -s -o /dev/null -w '%{http_code}\n' -H "Accept: application/json" \
  http://127.0.0.1:8000/admin/api/v1/products     # sin token → 401

# Headers y CORS (T-CASA-018, 019)
curl -sI http://127.0.0.1:8000/api/v1/configuration | grep -Ei 'x-content-type|x-frame|referrer-policy|permissions-policy|cross-origin'
curl -sI -X OPTIONS http://127.0.0.1:8000/api/v1/products \
  -H 'Origin: https://evil.example' -H 'Access-Control-Request-Method: GET' | grep -i 'access-control' || echo "SIN ACAO OK"

# Rate limit (T-CASA-020)
for i in $(seq 1 40); do curl -s -o /dev/null -w '%{http_code}\n' \
  http://127.0.0.1:8000/api/v1/configuration; done | sort | uniq -c

# Logs (T-CASA-023, 024)
grep -c correlation_id demo/storage/logs/*.log
grep -riE 'bearer |authorization.*:.*[a-z0-9]{20}|"password"' demo/storage/logs/ || echo "SIN SECRETOS OK"

# Estado del backlog
grep -c '^### T-CASA-' BACKLOG.md   # debe ser 30
```

## Definition of Done de esta fase

- [ ] Cada T-ID con estado real en la tabla (no hay `pending` falsos).
- [ ] Todo control `Infra` tiene referencia a evidencia del proveedor o está marcado como riesgo aceptado.
- [ ] `composer test` verde en root y en `demo/`, y los `grep` de `00-index.md` sin regresiones.
- [ ] Los riesgos aceptados están escritos con su motivo y su control compensatorio (o "no existe").
- [ ] T-CASA-030 tiene una tabla `control → tarea → evidencia` sin filas vacías.

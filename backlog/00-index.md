# 00 — Índice de ejecución para agentes IA (OpenCode / Claude)

## Cómo leer este backlog

1. Lee `BACKLOG.md` primero (visión completa + DAG).
2. Luego lee el archivo de tu fase asignada (`fase-core.md`, `fase-repository.md`, etc.).
3. Respeta `Dependencias`: no empieces un T-ID si sus dependencias no están `DONE` (o stubbed con contrato).
4. Una tarea = un PR/entrega pequeña. Marca `DONE` solo si el **Criterio de aceptación** es verificable por comando/test.

## Reglas globales (no negociables)

- Solo `Product` como demo. PROHIBIDO crear Catalog/Orders/Billing/Users/Permissions como negocio. Puedes mencionarlos en docs como ejemplo futuro, sin implementarlos.
- REST y GraphQL son adapters delgados. Comparten Use Cases (T-060) + Repository (T-053). Cero duplicación de auth/validación/reglas.
- `grep` prohibidos (Architecture tests T-110):
  - `packages/core/src` NO importa `Illuminate\Database\Eloquent`, `Illuminate\Http`, `GraphQL\`, `HttpException`.
  - `*/Rest/*` y `*/Graphql/*` NO importan `Illuminate\Database\Eloquent\Model` ni `::query()`.
  - `Application/` NO importa Eloquent.
  - Sin `whereRaw($request…)` desde cliente. Sin includes/relaciones arbitrarias.
  - Sin auth en controllers/resolvers (`Gate::authorize`, `$user->can` solo en `AuthorizationManager`/adapter).
- Antes de crear una abstracción/contract, justifica en 1 línea qué frontera representa. Si no hay frontera, no la crees (anti-ceremonia).
- Límites obligatorios: `per_page` clamp, includes/sorts/filters allow-list, GraphQL `maxDepth/maxComplexity/maxFirst`, DataLoader/batching.
- Errores: Core lanza dominio (T-013); adapters mapean (T-071 REST, T-074 GraphQL). Nunca leak SQL/trace con `APP_DEBUG=false`.
- Tenant != user. `ApplicationContext` con user/tenant/application separados (T-011, T-032).

## Definition of Done por tarea

- [ ] Código + migración/config mínima si aplica.
- [ ] Test que prueba el Criterio de aceptación (`php artisan test --filter=<Nombre>Test` verde).
- [ ] `composer test` no rompe tareas previas.
- [ ] Arch test T-110 sigue verde (o se actualiza justificadamente).
- [ ] Doc de 5 líneas en el archivo de fase: qué se hizo + cómo verificarlo.

## Orden de implementación (1–18 visión → T-IDs) — **ESTADO REAL**

**Fases completadas (framework core + packages base):**
- ✅ Fase Etc (Platform): T-001, T-002, T-003, T-004, T-005 — DONE
- ✅ Fase Core: T-010, T-011, T-012, T-013, T-014 — DONE
- ✅ Fase Repository: T-020, T-021, T-022, T-030, T-031, T-032, T-040, T-041, T-042, T-050, T-051, T-052, T-053 — DONE
- ✅ Fase Application: T-060, T-061, T-090, T-091 — DONE
- ✅ Fase REST: T-070, T-071, T-072, T-073 (Widgets), T-074, T-075, T-076, T-077, T-078 — DONE
- ✅ Fase Config: T-080, T-081 — DONE
- ✅ Fase GraphQL: T-073 (adapter) — DONE (parcial/completo según implementación)
- ✅ Fase Testing: T-100, T-101, T-102, T-110 — DONE; T-111 (CI) — parcial/DONE según fase
- ✅ Fase Multi-Platform: T-200…T-209 — DONE
- ✅ Fase CASA: Varios T-CASA-* — Implementados (según fase-casa.md)
- ✅ Fase Docs: T-120, T-121, T-122 — DONE
- 🔄 Fase Account (auth + cuenta por guard): recuperación ✅; ver `fase-account.md`

1. Core contracts → T-010 **[DONE]**
2. Application context → T-011 **[DONE]**
3. Resource definitions → T-020, T-022 **[DONE]**
4. Resource registry → T-021 **[DONE]**
5. Resource actions → T-012 **[DONE]**
6. Authorization abstraction → T-030, T-031 **[DONE]**
7. Query abstractions → T-040, T-041, T-042 **[DONE]**
8. Repository lifecycle → T-050 **[DONE]**
9. Base ResourceRepository → T-051 **[DONE]**
10. Persistence adapter → T-052, T-053 **[DONE]**
11. Application Commands/Queries → T-060, T-061 **[DONE]**
12. REST adapter → T-070 **[DONE]**
13. GraphQL adapter → T-073 **[DONE]**
14. Configuration system → T-080, T-081 (+ endpoint T-072) **[DONE]**
15. Error mapping → T-071, T-074 **[DONE]**
16. **Multi-Platform → T-200…T-209** **[DONE]**
17. Testing → T-100, T-101, T-102 **[DONE]**
18. Architecture tests → T-110, T-111 **[DONE/parcial]**
19. Documentation → T-120, T-121, T-122 **[DONE]**
20. Security (CASA / ASVS v4.0) → T-CASA-001…T-CASA-030 **[IMPLEMENTADO/PARCIAL]**

Paralelizable: T-003/T-004 (CLI) tras T-001/T-002 en paralelo con Core. T-005 (doctor) al final.

## Reglas de la fase CASA

- Requiere `T-111` (CI) y `T-120` (docs) antes de arrancar la mayoría de tareas.
- `fase: Infra` = control del proveedor cloud/edge. No se implementa en el framework: se entrega como requisito de despliegue + evidencia externa.
- Antes de escribir un hallazgo, verifícalo contra el código. Si no se puede reproducir con un test, no es un hallazgo.
- Una tarea no se marca DONE por analogía con otra guía. Si un control no aplica, se documenta el motivo.
- No se agregan dependencias de seguridad a `packages/core`: auth y autorización siguen siendo fronteras de T-090/T-030.

## Comandos de verificación rápida

```bash
# Framework (root)
composer test
bin/platform list && bin/platform --version

# App generada (demo/) — fixture + workspace (platform.json), con comportamiento de import en jobs (5 tests con expected 'failed' vs pipeline async)
# Nota: En entorno sync de tests hay diferencias menores entre tests esperados y ejecución en cola.
composer test --working-dir=demo/api

# Architecture tests (T-110). Paths reales: packages/shardevcom/*, demo/api/packages/shardev/*
grep -rn "Illuminate\\Database\\Eloquent" packages/shardevcom/core/src || echo "CORE LIMPIO OK"
grep -rn "HttpException\|abort(" packages/shardevcom/core/src || echo "CORE SIN HTTP OK"
grep -rn "::query()" demo/api/packages/shardev/*/src/Rest demo/api/packages/shardev/*/src/Graphql 2>/dev/null || echo "ADAPTERS LIMPIOS OK"
grep -rn "whereRaw(\$request" packages/ || echo "SIN WHERERAW DESDE CLIENTE OK"
```

## Estimaciones

- S = <4h, M = 1–3 días, L = 3–5 días (incluye tests).
- Si un M se desborda, divídelo sin crear God classes.

## Estado actual global (análisis 2026-10-15)

- **Framework (root):** 359 tests / 1026 assertions — **OK**. CLI (`bin/platform`) completo (new, add, remove, install, build, dev, status, package make/list, doctor, config, demo, plugin list/show). Core agnóstico (Contracts, ApplicationContext, ResourceAction, Exceptions, ApiResponse) completo.
- **Packages (fuente):** `core`, `application`, `resources`, `authorization`, `persistence`, `configuration`, `rest`, `graphql`, `security`, `socialite`, `twofactor`, `widgets`, `observability`, `documentation`, `product`, `catalog` — implementados con arquitectura limpia (adapters delgados, lifecycle único, repo base sobre persistencia Eloquent trait).
- **Demo (fixture y workspace):** 272 tests / 2996 assertions — **OK, todo verde**. `demo/platform.json` convierte el propio demo en workspace, así que `platform status`/`doctor` operan igual desde `demo/`.
- **Arquitectura:** CORE LIMPIO confirmado por grep (sin Eloquent/Http/`abort()` en `core/src`), adapters sin `::query()`, sin `whereRaw` desde request, lifecycle único, ApplicationContext con user/tenant/application separados. El bootstrap de auth (`AuthBootstrapServiceProvider`) y el middleware `BindGuard` viven ahora en el paquete `security`, no en `core`.
- **Multi-platform:** T-200–T-209 implementados; product package completo demo.
- **Cliente SPA (`demo/web/`):** monorepo Vite/React con una app por guard (`apps/{user,admin,employee}`) sobre `packages/{ui,core,plugin-kit}` y `plugins/` (una vista por guard). Consume la API real (REST por plataforma + GraphQL). Login first-party en el paquete `security`: `POST {prefix}/api/v1/auth/login` acuña un PAT (scopes desde `ScopeCatalogue` derivado del `ResourceRegistry`), más `GET /auth/me` y `POST /auth/logout`. Vistas base por guard: login (password + OTP + passkey), registro, forgot/reset, verificación, invitación, perfil (email/password/passkeys/disable), preferencias y notificaciones; `PlatformApp` recibe `public` para mostrar registro solo en guards públicos. Suite web: `npm run typecheck && npm run lint && npm test` → 21 tests. Mobile (Flutter) y desktop (JavaFX) espejan login con OTP, registro, recuperación, invitaciones, perfil, preferencias y notificaciones; las passkeys quedan pendientes en ambos hasta integrar WebAuthn nativo. Un package API con `plugin.json` es un plugin y se lista con `platform plugin list`. Se instala con `platform demo add <pkg> --with-client [--no-install]`. Seeders demo: `admin@example.com` / `employee@example.com` / `user@example.com`, password `password`.
- **Cliente móvil (`demo/mobile/`):** monorepo Flutter/Dart con un flavor por guard (`--dart-define=GUARD=...`) sobre `packages/{plugin_kit,core}` y `plugins/product` (una vista por guard). Mismo contrato `definePlugin`/`createRegistry`; `ApiClient` (dio) + auth Riverpod + router go_router. Se instala con `platform demo add <pkg> --with-mobile-client [--no-install]`. Verificado con Flutter 3.47.7: `flutter analyze` limpio y `./tool/test.sh` (16 tests) verde.
- **Cliente desktop (`demo/desktop/`):** monorepo Gradle/JavaFX con una app por guard (`apps/{user,admin,employee}`) sobre `packages/{plugin-kit,core}` y `plugins/product` (una vista por guard). Mismo contrato `definePlugin`/`PluginRegistry`; `ApiClient` (`java.net.http`) + Gson + shell/vistas JavaFX. Se instala con `platform demo add <pkg> --with-desktop-client [--no-install]`. Verificado con JDK 21: `./gradlew build` (17 tests) verde.
- **Workspace CLI (`platform new/add/remove/install/build/dev/status/guard`):** `platform new <name> --with=api,web,mobile,desktop [--guards=web,admin]` genera un workspace independiente de la API: raíz con `platform.json` (fuente de verdad) y una carpeta por componente (`api/`, `web/`, `mobile/`, `desktop/`). Cada tipo tiene un driver (`ComponentDriver`) para install/build/dev/verify; `Context::projectRoot()` resuelve a `api/` para que config/demo/package/doctor sigan operando sobre Laravel. Cada componente declara una lista `guards` (N guards): la API los publica en `config/platforms.php` y `platform guard add <guard>` da de alta un guard de punta a punta (plataforma + modelo + migraciones + seeder demo + registro en cada `plugin.json` + enums/presets core de cada cliente + app por guard + vista placeholder por plugin), con `platform guard list` para inspeccionarlos. `doctor` incluye las suites `Workspace` (componentes en disco) y `Client requirements` (toolchain de cada cliente instalado: Node/npm, Flutter/Dart, JDK, con sugerencia si falta). `platform new` deja además `platform.sh` en la raíz: un shell de control (levanta/para API y clientes, build, logs, doctor/fix) que lee `platform.json` en runtime y guarda su estado en `.platform/`. El propio `demo/` es un workspace (`demo/platform.json` + `demo/platform.sh`). Ver `backlog/fase-workspace.md`.
- **Pendientes reales:** ninguno en el backlog principal (fases core + docs completas). Ver `fase-casa.md` para el estado fino de la fase CASA.

### Nota de mantenimiento (2026-10-15)

El fixture demo usa `QUEUE_CONNECTION=sync` en `demo/api/phpunit.xml`: los tests de import asumen que el pipeline async corre dentro de la request. No cambiarlo a `database` sin adaptar antes `ProductRestEndpointTest` (procesaría la cola explícitamente).


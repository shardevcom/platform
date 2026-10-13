# Fase Etc (Platform) — T-001…T-005

## Avance (2026-10-04)
- Corrección arquitectónica: el repo es el FRAMEWORK (como laravel/framework + installer), no la app. Root = CLI (`bin/platform`, cero dependencias) + `src/` (instalador, ` Shardevcom\Framework` con matriz compat 1.x → Laravel ^12) + `packages/` (FUENTE oficial) + `tests/` (unit framework). `demo/` = app generada de referencia (fixture manual hasta T-003): Laravel 12.69.2 + copia física de `packages/` (visión §2/§10: el proyecto generado lleva su propio `packages/`) + `config/platform.php` + `.env` sqlite. `bin/platform list/--version` funcionando; `new --laravel=9` rechazado por matriz; `new` completo = T-003 (hará el copiado real). `demo/packages/` es snapshot: re-copiar desde root tras cambios (`cp -a packages demo/packages` + dump-autoload en demo).
- DONE T-001: ver BACKLOG.md (implementado; ahora vive mitad en root — manifest/discovery — y mitad en demo — autoload app + registro provider).
- DONE T-002: `demo/config/platform.php` + `.env(.example)` Shardev + sqlite.
- DONE T-003: `bin/platform new` implementa flujo 15 pasos: valida nombre/matriz, `composer create-project laravel/laravel`, copia packages oficiales, actualiza composer.json (autoload PSR-4 + path repositories + requires), `composer update` instala dependencias, registra providers en `bootstrap/app.php`, crea `config/platform.php`, actualiza `.env` (sqlite + vars platform), migra, seed opcional, dump-autoload, verifica rutas GraphQL/config/DB/providers. Flags: `--laravel=12` + `--no-interaction`. Verificación: `platform new test-project --laravel=12 --no-interaction` crea proyecto funcional con GraphQL route, config platform, DB sqlite, providers registrados.
- DONE T-004: `bin/platform package make <name> [--dry-run]` crea scaffold mínimo (src/, config/, routes/, database/migrations/, tests/, composer.json, ServiceProvider). `bin/platform package list` lista oficiales + usuario. El manifest lee `extra.platform.provider` del composer.json de cada paquete. NO se implementa lógica de negocio en catálogo (solo scaffold de prueba efímera). Verificación: `package make catalog --dry-run` muestra árbol; tras crear, `package:list` lo incluye con provider auto-registrado.
- DONE T-005: `bin/platform doctor` ejecuta 15 checks: PHP, Laravel, Composer, FW version, packages, .env, DB, cache, storage, autoload, providers, REST routes, GraphQL route, config platform, queue. En proyecto sano (demo) → 15× `✓` + `Application is ready.` exit 0; con fallos → lista accionable + exit !=0. Verificación: `../bin/platform doctor` desde demo → 15/15 pass.
- Nota entorno: PHP 8.3.6 en máquina vs PHP ^8.4 de la visión (sin sudo para instalar 8.4). Se avanza con PHP 8.3 + Laravel 12 (requisito real: ^8.2). Paquetes platform ajustados a PHP ^8.3.

> Fuente de verdad resumida: `BACKLOG.md`. Aquí detalle ejecutable por agente.

## T-001 — Etc — Estructura `packages/` + autoload + discovery
- **Desc:** Sin alterar Laravel estándar. Namespaces `Packages\<Nombre>\`, PSR-4 en `composer.json`, manifest `extra.platform.provider`, auto-registro ServiceProviders, `composer dump-autoload` automatizable.
- **Dep:** —
- **Aceptación:** `platform new demo` → Laravel estándar + `packages/` stubs; `composer dump-autoload` ok; `php artisan` funciona; `App\` vs `Packages\` sin colisión.
- **Est:** M
- **Prompt agente:** `Implementa T-001: crea stub packages/ (core,support,configuration,authorization,resources,persistence,query,rest,graphql), composer autoload PSR-4, PackageManifest reader + ServiceProvider auto-discovery con test. No lógica de negocio.`

## T-002 — Etc — `config/platform.php` + `.env.example` sqlite-first
- **Desc:** Solo `config/platform.php` (`version`, `api.rest/graphql`). `.env.example` con `PLATFORM_VERSION`, `API_REST_ENABLED`, `API_GRAPHQL_ENABLED`, `DB_CONNECTION=sqlite`.
- **Dep:** T-001
- **Aceptación:** `config('platform.api.rest')` lee env; boot sqlite sin externos.
- **Est:** S

## T-003 — Etc — CLI `platform new --laravel=` + compatibilidad
- **Desc:** Valida matriz (FW 1.x → Laravel ^12, PHP ^8.4). Flujo 15 pasos visión. Flags no-interactivos + selector interactivo.
- **Dep:** T-001, T-002
- **Aceptación:** `platform new demo --laravel=12 --no-interaction` ok; `--laravel=9` falla con mensaje.
- **Est:** L

## T-004 — Etc — CLI `platform package make/list` + manifest
- **Desc:** Scaffold mínimo ampliable (Provider, config, routes, migrations, tests). `package:list`. Sin lógica en providers.
- **Dep:** T-001
- **Aceptación:** `package make catalog` + `package:list` lo muestra; provider auto-registrado. NO implementar negocio catalog.
- **Est:** M

## T-005 — Etc — `platform doctor`
- **Desc:** Checks PHP/Laravel/Composer/FW/packages/env/DB/cache/queue/REST/GraphQL/autoload/providers.
- **Dep:** T-003, T-070, T-073
- **Aceptación:** Sano → 10× `✓` + `Application is ready.` exit 0; DB caída → fail accionable exit !=0.
- **Est:** M

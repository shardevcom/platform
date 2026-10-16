# AGENTS.md — Instrucciones para agentes (OpenCode / IA)

> Lee esto antes de tocar código. Fuente de verdad: `BACKLOG.md` + `backlog/`.
> ESTE REPO ES EL FRAMEWORK (installer + fuente de packages), NO la app. La app generada vive en `demo/` (`demo/api/` + `demo/web/` + `demo/mobile/`, fixture manual hasta T-003).

## Layout
- Root framework: `bin/platform` (CLI cero-dep), `src/` (` Shardevcom\`), `packages/` (FUENTE oficial), `tests/` (unit, `composer test`).
- `demo/`: app de referencia dividida en `demo/api/` (Laravel 12 con SU copia de `packages/` + `config/platform.php`), `demo/web/` (monorepo Vite/React: `apps/{user,admin,employee}` = una app por guard, `packages/` compartidos, `plugins/` con vistas por guard) y `demo/mobile/` (monorepo Flutter/Dart: un flavor por guard, `packages/{plugin_kit,core}`, `plugins/` con vistas por guard). Suite app: `composer test` dentro de `demo/api/`; suite web: `npm test` dentro de `demo/web/` (Vitest + jsdom); suite mobile: `./tool/test.sh` dentro de `demo/mobile/` (Flutter SDK; no instalado en todos los entornos).
- Plugins: un package API puede declarar `plugin.json` (resource, actions, guards) y convertirse en plugin; `platform plugin list` los lista desde los manifiestos (nunca de una lista hardcodeada). El lado web usa `definePlugin` de `packages/plugin-kit`; el mobile, `definePlugin` de `packages/plugin_kit`.
- Tras cambiar `packages/` del root, sincronizar SOLO oficiales (nunca borrar `demo/api/packages/<usuario>`):
  `python3 scripts/sync-demo-packages.py && composer dump-autoload --working-dir=demo/api`.
  El script copia `packages/shardevcom/*` a `demo/api/packages/shardev/*` reescribiendo `name` y
  `require` del manifest (el demo usa vendor `shardev`, el framework `shardevcom`). Verifica con
  `composer test` en ambos. Un `cp -a` a secas rompe el autoload de composer en el demo.

## Stack
- Laravel ^12, PHP ^8.4, sqlite-first local, `packages/` sobre Laravel estándar.
- REST `/api/v1` + GraphQL `/graphql` como adapters delgados del mismo Use Case.

## Flujo de trabajo por tarea
1. Lee `backlog/00-index.md` + tu `backlog/fase-*.md`.
2. Verifica dependencias en `backlog/dependencias.md`. Si falta una dep, implementa contra el contrato (mock/fake), no la inventes como negocio.
3. Implementa SOLO el T-ID asignado. No avances otros T-IDs en el mismo cambio salvo que sean S triviales y lo declares.
4. Ejecuta: `composer test` y los `grep` de `00-index.md` (CORE LIMPIO / ADAPTERS LIMPIOS).
5. Actualiza el archivo de fase con: qué hiciste + comando de verificación.

## Prohibiciones (fallan T-110)
- Crear módulos `catalog/orders/billing/users/notifications`. Solo `Product` demo.
- `Model::query()` / importar `Model` en `*/Rest/*` o `*/Graphql/*`.
- `HttpException` / `abort()` / `Illuminate\Http` / `Eloquent` en `packages/core`.
- Auth (`can`, `Gate::authorize`) fuera de `AuthorizationManager`/adapter.
- `whereRaw($request…)`, filtros/includes/sorts no allow-listed, eager arbitrario.
- Interfaces sin frontera justificada. Repos CRUD vacíos. God Service/Repo. Lógica negocio en ServiceProvider o en hooks lifecycle.
- Duplicar validación/autorización entre REST y GraphQL. Si lo necesitas en ambos, muévelo a T-060 (Use Case/DTO).

## Estructura esperada de un package usuario
```text
packages/<nombre>/src/{ServiceProvider,Application,Resources,Repositories,Contracts,Infrastructure,Rest,Graphql}
packages/<nombre>/{config,routes,migrations,tests,composer.json}
```
Un package puede además incluir `plugin.json` (resource, actions, guards) para exponerse como plugin.

## Criterio para dar DONE
Solo con Criterio de aceptación del `BACKLOG.md` verificado por test/comando. Sin excepciones.

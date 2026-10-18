# Fase Workspace — CLI de control del proyecto (API + clientes)

> Extensión fuera de T-120..T-122 (petición del usuario, 2026-10-16). El repo
> sigue siendo el FRAMEWORK; lo que cambia es que `platform new` genera un
> **workspace** cuyo miembro es la API, no la API como raíz.

## Objetivo

Que el proyecto generado sea **independiente de la API y de cada cliente**, y
que un único CLI (`platform`) lo administre de forma monolítica: instalar,
configurar, compilar, ejecutar y diagnosticar cada componente desde una fuente
de verdad única.

## Modelo

```text
my-project/                 # workspace (NO Laravel)
├── platform.json           # manifiesto: fuente de verdad
├── api/                    # Laravel 12 (componente)
├── web/                    # Vite/React (componente)
├── mobile/                 # Flutter (componente)
└── desktop/                # Gradle/JavaFX (componente)
```

`platform.json`:

```json
{
  "name": "my-project",
  "platform": "0.1.0",
  "components": {
    "api":     { "type": "api",     "path": "api",     "guards": ["web","admin"], "enabled": true },
    "web":     { "type": "web",     "path": "web",     "guards": ["web","admin"], "enabled": true },
    "mobile":  { "type": "mobile",  "path": "mobile",  "guards": ["web","admin"], "enabled": false },
    "desktop": { "type": "desktop", "path": "desktop", "guards": ["web","admin"], "enabled": false }
  }
}
```

- **N guards.** Un componente sirve una **lista** de guards. La API la declara
  (`config/platforms.php`); los clientes la espejan (`WorkspaceScaffolder::syncGuards`
  lee las plataformas vía `PlatformsReader` y las aplica a todo componente). Un
  guard suelto es una lista de uno, así que nada especial-casa un único guard.
  `platform new --guards=...` (y `platform add --guards=...`) fija la lista de los
  clientes: en ese caso `syncGuards` solo refresca la API con sus plataformas y
  deja intactos los guards pedidos para los clientes.
- `ComponentType` (`api`/`web`/`mobile`/`desktop`) conoce su plantilla de
  referencia (`demo/<tipo>`), sus guards por defecto (`defaultGuards()`: `[]` para
  la API, `['web']` para los clientes), el directorio que prueba que
  sus dependencias están instaladas (`vendor`/`node_modules`/`.dart_tool`/
  `.gradle`) y qué artefactos excluir al copiar.
- `Workspace::locate` sube desde el cwd hasta `platform.json`, así los comandos
  funcionan desde la raíz o desde dentro de cualquier componente.
- `Context::projectRoot()` devuelve el componente `api` cuando hay workspace, de
  modo que `config`, `demo add`, `package make` y `doctor` siguen operando sobre
  Laravel sin cambios.

## Comandos

```bash
platform new <name> [--with=api,web,mobile,desktop] [--guards=web,admin] [--laravel=12] [--no-interaction] [--no-install]
platform add <component> [--guards=web,admin] [--path=web] [--no-install]
platform remove <component> [--delete]
platform install [component] [--only=web,mobile]
platform build   [component] [--only=web,mobile] [--guard=admin]
platform dev     <component> [--guard=admin]
platform status
platform guard add <guard> [--name=...] [--model=...] [--table=...] [--driver=passport] [--prefix=...] [--no-clients] [--no-migrate]
platform guard list
platform doctor            # incluye la suite Workspace (componentes en disco)
```

`build`/`dev` aceptan `--guard` para construir/levantar una sola app del cliente;
sin él recorren todos los guards del componente.

## Añadir un guard (`platform guard add`)

`guard add` da de alta un guard de punta a punta, partiendo de una única
descripción (`GuardDefinition`) y sin listas hardcodeadas:

- **API** (`GuardScaffolder`): inserta la entrada en `config/platforms.php`
  (`PlatformsEditor`, con bracket-matching y awareness de strings/comentarios,
  porque el config usa `env()` y `::class` y no se puede `require`), genera el
  modelo (`App\Models\<Guard>User extends PlatformUser` con
  `$platformGuard`/`$table`) y las migraciones `<table>` +
  `<table>_password_resets`, y corre `php artisan migrate --force` (salvo
  `--no-migrate`).
- **Manifiesto**: añade el guard a todos los componentes de `platform.json`.
- **Clientes** (`GuardClientScaffolder`): extiende el enum `Guard` de cada cliente
  (union TS de `plugin-kit`, enum Dart de `plugin_kit`, enum Java de `plugin-kit`)
  y clona la app existente para el nuevo guard — `web/apps/<guard>` (+ scripts
  `dev|build|preview:<guard>`), `desktop/apps/<guard>` (+ include en
  `settings.gradle.kts`). En mobile no hay app por guard (el guard es un
  `--dart-define=GUARD=` de build), solo se extiende el enum. `--no-clients`
  limita el cambio a la API + manifiesto.
- El mapeo guard→app es `web→user` y el resto 1:1 (`GuardClientScaffolder::app`).

`platform guard list` lista los guards del manifiesto y qué componentes los sirve.

Cada tipo de componente tiene un **driver** (`ComponentDriver`) que sabe su
toolchain: `composer`+`artisan` (api), `npm` (web), `flutter` (mobile),
`gradlew` (desktop). `DriverRegistry` mapea tipo→driver, así ningún comando
ramifica por tipo. Añadir un cliente = añadir un driver + una plantilla.

## Reutilización

- `WorkspaceScaffolder` delega la API en `ProjectScaffolder` (composer
  create-project + packages + migrate + seed + verify) y copia cada cliente
  desde `demo/<tipo>` reutilizando los instaladores de referencia existentes.
- `doctor` añade `WorkspaceSuite`: falla si un componente del manifiesto no está
  en disco.
- Los instaladores `ClientInstaller`/`MobileClientInstaller`/
  `DesktopClientInstaller` siguen disponibles para `platform demo add`.

## Doctor: requisitos por cliente

`platform doctor` detecta el workspace y añade dos suites:

- **Workspace** (`WorkspaceSuite`): cada componente habilitado del manifiesto
  existe en disco.
- **Client requirements** (`RequirementsSuite`): para cada componente
  habilitado pregunta a su driver `requirements()` y comprueba, en el
  directorio del componente, que el toolchain necesario está disponible. Si
  falta una herramienta, el detalle es una **sugerencia para solucionarlo**, no
  solo el fallo.

`ComponentDriver::requirements(): list<Requirement>` declara
`(label, command, expect, hint, locations)`; `expect` vacío = basta con exit 0.
La API devuelve `[]` porque su toolchain (PHP/Composer/Laravel) ya lo cubre la
suite `Toolchain`. Requisitos por tipo:

| tipo      | herramientas                                  |
|-----------|-----------------------------------------------|
| `web`     | Node.js (`node --version`), npm (`npm --version`) |
| `mobile`  | Flutter (`flutter --version`), Dart (`dart --version`) |
| `desktop` | JDK 21+ (`java -version`)                     |

Cuando el sondeo falla con "no está en PATH" (exit 127 / `command not found`),
el doctor busca el ejecutable en `locations` (rutas típicas, `~` = home) y, si
lo encuentra, devuelve el comando exacto para arreglarlo; si no, cae al `hint`
de instalación. Ejemplo real (Flutter instalado fuera del PATH):

```text
[FAIL] mobile: Flutter SDK   not on PATH — found at ~/flutter/bin/flutter; run: export PATH="$HOME/flutter/bin:$PATH"
```

Verificado contra el entorno real: `node`, `npm` y `java` pasan con su versión
detectada. `flutter` (instalado en `~/flutter/bin`, fuera del PATH por defecto)
sugiere `export PATH="$HOME/flutter/bin:$PATH"`; tras exportarlo pasa con
`Flutter 3.47.7` y `Dart 3.13.5`.

## demo/ como workspace

El propio `demo/` es un workspace: `demo/platform.json` (escrito por
`WorkspaceManifest`, no a mano) declara los cuatro componentes con
`guards: ["web","admin","employee"]` (los tres guards que publica la API). Así
`platform status` y `platform doctor` funcionan igual desde `demo/` que desde un
proyecto recién generado, y el demo deja de ser un fixture "invisible" para el
CLI.

```bash
cd demo && php ../bin/platform status
cd demo/api && php ../../bin/platform doctor   # incluye Workspace + Client requirements
```

## Correcciones del doctor (falsos positivos sobre el demo)

Al pasar `doctor` sobre `demo/api` aparecieron fallos que no eran del demo sino
de las comprobaciones. Corregidos:

- `ApiSuite` (endpoint de configuración): las rutas se registran por guard
  (`configuration.web.index`, `configuration.admin.index`), no como
  `configuration.index`. Ahora se busca el prefijo `configuration.`.
- `SessionCookieSecureRule`: `http_only` y `same_site` leen de `env(...)` (con
  defaults seguros), pero la regla solo aceptaba el literal; ahora acepta
  `env(...)` igual que la comprobación de `secure`.
- `DebugModeEnabledRule`: marcaba `APP_DEBUG=true` como fallo aunque
  `APP_ENV=local`. Ahora solo falla si el entorno no es local/development/testing
  (y sigue marcando `APP_ENV=production` + `APP_DEBUG=true` como crítico).
- `ApiSecurityRule`: trataba `routes/web.php` como API (la ruta `/` de Laravel
  disparaba "sin auth") y no veía el versionado porque el prefijo `v1` lo aplica
  el registro de rutas del framework, no el archivo. Ahora solo inspecciona
  `routes/api.php` para auth y detecta el versionado desde `route:list`.

También se corrigió un drift real del demo: `demo/api/composer.json` no mapeaba
`Shardevcom\Documentation\` en `autoload.psr-4` (el paquete sí estaba en
`require`/`repositories`), así que `PackagesSuite` lo reportaba como *Unmapped*.
Añadido el mapeo + `composer dump-autoload --working-dir=demo/api`.

Con esto, `platform doctor` sobre `demo/api` queda limpio (**64 checks, 0
fallos**; `Application is ready.`) en un login shell con el toolchain en PATH.

## Shell de control (`platform.sh`)

`platform new` deja en la raíz del workspace un script bash ejecutable
(`WorkspaceScript::render()`, escrito por `WorkspaceScaffolder::create` vía
`Filesystem::makeExecutable`). No es un artefacto estático: lee `platform.json`
en tiempo de ejecución, así que sobrevive a `platform add` / `platform guard add`
sin regenerarse.

| verbo | efecto |
|-------|--------|
| `up` | levanta la API en background (puerto `API_PORT`, 8000) |
| `api` | alias de `up` |
| `web [guards...]` | `npm run dev:<app>` de cada guard (o todas) |
| `desktop [guards...]` | `./gradlew :apps:<app>:run` |
| `mobile [guard]` | `flutter run --dart-define=GUARD=...` (foreground) |
| `stop` \| `restart` | detiene (kill del árbol) / reinicia |
| `status` \| `ps` | componentes, guards, procesos y puertos |
| `logs [target]` | sigue un log de `.platform/logs/` |
| `build` \| `install` | build/instala cada componente (o los indicados) |
| `doctor` \| `check` | toolchain + dependencias; exit 1 si hay issues |
| `fix` \| `repair` | intenta arreglar (install de clientes + `composer install`) |

El estado en marcha vive en `.platform/run/*.pid` y `.platform/logs/*.log`
(`.platform/` está en `.gitignore`). Puertos: API `API_PORT` (8000); web user
5175 / admin 5173 / employee 5174 (leídos del `createAppConfig(...)` del
`vite.config.ts`). `MOBILE_API_HOST` por defecto `http://10.0.2.2:$API_PORT`
(emulador Android).

## Verificación

- `composer test` (root): **320 tests / 934 assertions** — OK.
  - `WorkspaceManifestTest` (round-trip de `platform.json`, listas de guards,
    clave legacy `guard`, `locate`).
  - `NewCommandTest` (reescrito): el workspace se crea con manifiesto y la API
    vive en `api/`; `--with` selecciona componentes; los clientes espejan los
    platforms de la API y `--guards` los fija (api incluida aparte); deja
    `platform.sh` ejecutable en la raíz.
  - `WorkspaceCommandTest`: status/install/build/dev/add/remove por manifiesto,
    incluyendo "no es un workspace".
  - `GuardCommandTest`: `guard add` escribe `config/platforms.php`, modelo y
    migraciones de la API, espeja el guard en `platform.json` y clona la app por
    guard en web/desktop + extiende los enums (web/desktop/mobile); `--no-clients`,
    `--no-migrate`, slug inválido, guard duplicado y `guard list`.
  - `RequirementsSuiteTest`: requisitos por cliente (tool presente/ausente,
    componentes deshabilitados, solo-API, probe en el directorio del componente).
  - `DoctorCommandTest`: incluye la suite `Client requirements` cuando hay
    workspace.
  - `WorkspaceScriptTest`: contrato del shell (`#!/usr/bin/env bash`, lee
    `platform.json`, verbos del router presentes) + `bash -n` sobre el template.
- `php bin/platform` lista `new/add/remove/install/build/dev/status` y `guard`.
- `platform guard add <guard> --no-migrate` sobre una copia del demo genera
  artefactos válidos (`platforms.php`/`package.json`/`platform.json` parsean) y
  `guard list` muestra el guard nuevo.
- `composer test` (demo/api): **212 tests / 2657 assertions** — OK.
- `platform status` desde `demo/` lista los 4 componentes (`scaffolded`/`installed`
  = yes).
- `platform doctor` desde `demo/api` (login shell): **64 checks, 0 fallos** →
  `Application is ready.`
- `platform.sh`: `bash -n` OK; en `demo/` (`./platform.sh help|status|doctor`)
  `status` lista los 4 componentes con sus guards y `doctor` sale con exit 1
  cuando falta toolchain/dependencias mostrando el `fix` sugerido.

## Pendiente / siguiente iteración

- `platform config` a nivel workspace (editar `platform.json` desde CLI).
- `platform doctor --only=web` para diagnosticar un componente concreto.
- `platform guard add` no siembra un usuario de ejemplo ni registra el guard en
  `plugin.json` (los plugins declaran sus guards por vista; un guard nuevo queda
  sin vista hasta que un plugin lo añada).
- `platform dev`/`build` del demo: los clientes son monorepos con una app por
  guard; `--guard` permite apuntar a una sola.

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
    "api":     { "type": "api",     "path": "api",     "guard": null,       "enabled": true },
    "web":     { "type": "web",     "path": "web",     "guard": "admin",    "enabled": true },
    "mobile":  { "type": "mobile",  "path": "mobile",  "guard": "web",      "enabled": false },
    "desktop": { "type": "desktop", "path": "desktop", "guard": "admin",    "enabled": false }
  }
}
```

- `ComponentType` (`api`/`web`/`mobile`/`desktop`) conoce su plantilla de
  referencia (`demo/<tipo>`), su guard por defecto, el directorio que prueba que
  sus dependencias están instaladas (`vendor`/`node_modules`/`.dart_tool`/
  `.gradle`) y qué artefactos excluir al copiar.
- `Workspace::locate` sube desde el cwd hasta `platform.json`, así los comandos
  funcionan desde la raíz o desde dentro de cualquier componente.
- `Context::projectRoot()` devuelve el componente `api` cuando hay workspace, de
  modo que `config`, `demo add`, `package make` y `doctor` siguen operando sobre
  Laravel sin cambios.

## Comandos

```bash
platform new <name> [--with=api,web,mobile,desktop] [--laravel=12] [--no-interaction] [--no-install]
platform add <component> [--guard=admin] [--path=web] [--no-install]
platform remove <component> [--delete]
platform install [component] [--only=web,mobile]
platform build   [component] [--only=web,mobile]
platform dev     <component>
platform status
platform doctor            # incluye la suite Workspace (componentes en disco)
```

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

## Verificación

- `composer test` (root): **292 tests / 837 assertions** — OK.
  - `WorkspaceManifestTest` (round-trip de `platform.json`, guards por defecto,
    `locate`).
  - `NewCommandTest` (reescrito): el workspace se crea con manifiesto y la API
    vive en `api/`; `--with` selecciona componentes.
  - `WorkspaceCommandTest`: status/install/build/dev/add/remove por manifiesto,
    incluyendo "no es un workspace".
- `php bin/platform` lista `new/add/remove/install/build/dev/status`.

## Pendiente / siguiente iteración

- `platform config` a nivel workspace (editar `platform.json` desde CLI).
- `--guard` por cliente al crear (`platform new --with=web --guard=employee`).
- `platform doctor --only=web` para diagnosticar un componente concreto.
- Migrar `demo/` a un `platform.json` para que el propio repo sea un workspace.

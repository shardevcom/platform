# Shardev Web (demo SPA)

Monorepo de referencia (Vite + React 19 + TypeScript + Tailwind v4) que consume
la API real: REST versionada por plataforma (`/api/v1`, `/admin/api/v1`,
`/employee/api/v1`) y GraphQL (`/graphql`).

No es un mock: hace login contra el endpoint first-party del paquete `security`,
recibe un personal access token de Passport y usa ese Bearer en cada llamada.

## Una app por guard

Cada guard tiene su propia app Vite; comparten todo el código vía `packages/`:

| App | Guard | Puerto dev | Plataforma (prefijo API) |
| --- | --- | --- | --- |
| `apps/user` | `web` | 5175 | `/api/v1` |
| `apps/admin` | `admin` | 5173 | `/admin/api/v1` |
| `apps/employee` | `employee` | 5174 | `/employee/api/v1` |

Un token solo es válido para el guard que lo emitió, así que el login vive en
cada app (sin selector de plataforma).

## Estructura

```
apps/                 # una entrada por guard (index.html + main.tsx + App.tsx)
packages/
  ui/                 # primitivas shadcn-style + styles.css (Tailwind v4)
  plugin-kit/         # contrato de plugin: definePlugin, Guard, createRegistry
  core/               # shell compartido: api, AuthProvider, PlatformApp, páginas base
plugins/
  product/            # plugin de referencia: endpoints + una vista por guard
```

`packages/core` es agnóstico de la app: recibe `guard`, `appName` y la lista de
plugins, y monta providers, rutas base y las vistas/nav que aporta cada plugin
según el guard.

## Plugins

Un plugin = package API con `plugin.json` (resource, actions, guards) + módulo
web con una vista por guard. El contrato web se declara con `definePlugin`:

```tsx
export const productPlugin = definePlugin({
  name: "product",
  title: "Products",
  resource: "products",
  guards: {
    web:      { path: "products", component: ProductCatalogView, nav: {...} },
    employee: { path: "products", component: ProductManageView,  nav: {...} },
    admin:    { path: "products", component: ProductAdminView,   nav: {...} },
  },
})
```

La misma diferencia se refleja en la API: `web` solo lectura, `employee`
lectura/escritura, `admin` CRUD + import/export (según las abilities del token).
Lista de plugins instalados:

```bash
platform plugin list
```

## Requisitos

- El proyecto Laravel (demo) corriendo en `http://localhost:8000`.
- Credenciales demo sembradas con `php artisan migrate --seed`:
  - `admin@example.com` / `password` (plataforma `admin`)
  - `employee@example.com` / `password` (plataforma `employee`)
  - `user@example.com` / `password` (plataforma `web`)

## Arranque

```bash
npm install
npm run dev:admin      # o dev:employee / dev:user
```

Vite proxya `/api`, `/admin`, `/employee`, `/graphql` y `/oauth` a
`http://localhost:8000` (ver `vite.shared.ts`), así que no hay que configurar
CORS en desarrollo.

## Qué consume

| Sección | Endpoints |
| --- | --- |
| Auth | `POST /{platform}/api/v1/auth/login`, `GET /auth/me`, `POST /auth/logout` |
| Products | CRUD `/products`, `/products/select`, `/products/import`, `/products/export` |
| Configuration | `GET /configuration` (visibilidad por token) |
| Two-Factor | `/enable`, `/verify`, `/disable`, `/qr-code`, `/recovery-codes` |
| Widgets | `GET /api/v1/widgets` |
| GraphQL | `POST /graphql` (queries y mutaciones) |

## Build

```bash
npm run build   # typecheck + build de las 3 apps
npm run lint    # oxlint
```

## Instalación vía CLI

El instalador copia este monorepo a un proyecto con:

```bash
platform demo add product --with-client            # copia ./client y corre npm install
platform demo add product --with-client --no-install
```

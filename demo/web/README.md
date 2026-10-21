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
El package API declara ese contrato en `plugin.json` (`resource`, `actions` y
`guards` con su `mode` y `path`), que es la fuente del CLI:

```json
{
  "name": "product",
  "resource": "products",
  "actions": ["view", "list", "create", "update", "delete", "import", "export"],
  "guards": {
    "web": { "mode": "read", "path": "products" },
    "employee": { "mode": "write", "path": "products" },
    "admin": { "mode": "full", "path": "products" }
  }
}
```

Lista e inspección de plugins instalados:

```bash
platform plugin list            # tabla (o --json para tooling)
platform plugin show product    # actions, abilities y ruta por guard (o --json)
```

## Passkeys en escritorio

La app JavaFX no puede ejecutar WebAuthn, así que delega la ceremonia en este
SPA. La ruta pública `/passkey` recibe `mode` (`login`/`register`), `state`,
`email`, `name` y `return`; solo acepta callbacks loopback HTTP
(`127.0.0.1`, `localhost` o `[::1]`). En modo login devuelve el token por
`return?state=...&token=...`; en modo register toma el token de la app desde el
fragmento, registra la credencial y responde `?state=...&status=registered`.
Los errores vuelven como `error`. El origen del SPA debe estar en
`WEBAUTHN_ALLOWED_ORIGINS` de la API.

## Login social nativo

La API también acepta `GET {prefix}/auth/social/{provider}?return=<callback>`
para clientes nativos. El destino se cifra en el `state` OAuth y el callback
redirige el token ahí. Solo se permiten callbacks loopback HTTP y los esquemas de
`SOCIALITE_NATIVE_SCHEMES` (default `shardev`); la web sigue usando
`/auth/callback` con el token en el fragmento.

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
| Auth | `POST /{platform}/api/v1/auth/login`, `GET /auth/me`, `POST /auth/logout`, `/auth/webauthn/*`, `/auth/social/*` |
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

## Tests

```bash
npm test        # vitest (jsdom) — plugin-kit, PlatformApp y el contrato del plugin product
```

Cubren `definePlugin`/`createRegistry` (resolución por guard, orden de nav),
`PlatformApp` (login sin token, shell autenticado, aislamiento por guard) y que
el plugin `product` monta una vista por guard.

## Instalación vía CLI

El instalador copia este monorepo a un proyecto con:

```bash
platform demo add product --with-client            # copia ./client y corre npm install
platform demo add product --with-client --no-install
```

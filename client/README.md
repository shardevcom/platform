# Shardev Client (demo SPA)

SPA de referencia (Vite + React 19 + TypeScript + Tailwind v4) que consume la
API real del proyecto: REST versionada por plataforma (`/api/v1`, `/admin/api/v1`,
`/employee/api/v1`) y GraphQL (`/graphql`).

No es un mock: hace login contra el endpoint first-party del paquete `security`,
recibe un personal access token de Passport y usa ese Bearer en cada llamada.

## Requisitos

- El proyecto Laravel (demo) corriendo en `http://localhost:8000`.
- Credenciales demo sembradas con `php artisan migrate --seed`:
  - `admin@example.com` / `password` (plataforma `admin`)
  - `employee@example.com` / `password` (plataforma `employee`)
  - `user@example.com` / `password` (plataforma `web`)

## Arranque

```bash
npm install
npm run dev
```

Vite proxya `/api`, `/admin`, `/employee`, `/graphql` y `/oauth` a
`http://localhost:8000` (ver `vite.config.ts`), así que no hay que configurar CORS
en desarrollo.

## Qué consume

| Sección | Endpoints |
| --- | --- |
| Auth | `POST /{platform}/api/v1/auth/login`, `GET /auth/me`, `POST /auth/logout` |
| Products | CRUD `/products`, `/products/select`, `/products/import`, `/products/export` |
| Configuration | `GET /configuration` (visibilidad por token) |
| Two-Factor | `/enable`, `/verify`, `/disable`, `/qr-code`, `/recovery-codes` |
| Widgets | `GET /api/v1/widgets` |
| GraphQL | `POST /graphql` (queries y mutaciones) |

El selector de plataforma (`web` / `admin` / `employee`) forma parte del login,
porque un token solo es válido para el guard que lo emitió.

## Estructura

```
src/
  lib/api.ts          # fetch tipado: envelope {status,code,message,data,meta}, ApiError, token/plataforma
  lib/endpoints.ts    # funciones por dominio (auth, products, configuration, twoFactor, widgets, graphql)
  providers/          # AuthProvider (login/me/logout real)
  pages/              # Login, Dashboard, Products, Configuration, TwoFactor, Widgets, GraphQL, Profile
  components/ui/      # primitivas (button, card, input, tabs, ...)
  types.ts            # envelope y tipos de dominio
```

## Build

```bash
npm run build   # tsc -b && vite build
npm run lint    # oxlint
```

## Instalación vía CLI

El instalador copia este cliente a un proyecto con:

```bash
platform demo add product --with-client            # copia ./client y corre npm install
platform demo add product --with-client --no-install
```

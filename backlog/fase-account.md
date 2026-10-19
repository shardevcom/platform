# Fase Account — vistas base por guard (auth + cuenta)

## Objetivo

Cada guard debe tener una base completa de pantallas de autenticación y cuenta
en los clientes (login, social, 2FA, passkeys, OTP, recuperación, registro,
invitaciones, verificación, bloqueo, dashboard, preferencias, perfil,
notificaciones), consumiendo la API real. `platform guard add`/`new` decide si
la vista del guard es **pública** o **privada** (privada ⇒ la página principal
es el login).

## Decisiones

- **API primero**: no se generan vistas contra endpoints inexistentes; se
  implementan y testean en `packages/shardevcom/security` (y `configuration`
  para preferencias).
- **Web es la referencia**; mobile/desktop se espejan después.
- **Público/privado por guard** como metadato en `platform.json` (la lista
  `guards` pasa de `list<string>` a objeto con `public: bool`, con compat
  legacy).
- No se crean módulos `users`/`notifications` (prohibido T-110): el surface de
  cuenta vive en `security`; la preferencia en `configuration`.

## Estado por capacidad

| # | Capacidad | API | Tests | Cliente web |
|---|-----------|-----|-------|-------------|
| 1 | Login email/password | ✅ existía | ✅ | ✅ existía |
| 2 | `auth/me` (lectura) / logout | ✅ existía | ✅ | ✅ existía |
| 3 | Recuperación (forgot/reset) | ✅ nuevo | ✅ | ⏳ |
| 4 | Registro | ⏳ | — | ⏳ |
| 5 | Verificación email/cuenta | ⏳ | — | ⏳ |
| 6 | Update perfil + password | ⏳ | — | ⏳ |
| 7 | Preferencias (update) | ⏳ | — | ⏳ |
| 8 | Notificaciones | ⏳ | — | ⏳ |
| 9 | Bloqueo/disable individual | ⏳ | — | ⏳ |
| 10 | Invitaciones + verificación | ⏳ | — | ⏳ |
| 11 | OTP login | ⏳ | — | ⏳ |
| 12 | Passkeys/WebAuthn | ⏳ | — | ⏳ |
| 13 | Login social | ✅ Google/Microsoft (global) | ✅ | ⏳ |
| 14 | 2FA TOTP | ✅ existía | ✅ | ✅ existía |
| 15 | Dashboard/widgets | ✅ existía | ✅ | ✅ existía |

## Endpoints nuevos (paquete `security`)

Registrados por plataforma en `SecurityServiceProvider::loadAuthRoutes()`, con
`bind.guard` + middleware de la plataforma:

- `POST {prefix}/api/v1/auth/forgot-password` → `PasswordController@sendResetLink`
- `POST {prefix}/api/v1/auth/reset-password` → `PasswordController@reset`

La recuperación delega en el broker Laravel de la plataforma
(`AuthBootstrapServiceProvider` ya registra `auth.passwords.{reset}`), y el
email apunta al SPA vía `security.password_reset.frontend_url`
(`PASSWORD_RESET_FRONTEND_URL`/`FRONTEND_URL`):
`{frontend}/reset-password?token=&email=&guard=`.

## Verificación

- `demo/api`: `tests/Feature/Platform/PasswordResetEndpointTest.php` (7 tests) —
  notificación enviada, sin enumeración de emails, token válido cambia la
  contraseña y el login de la plataforma la acepta, broker por plataforma
  (admin), token inválido y confirmación.
- `composer test --working-dir=demo/api` → **219 passed (2680 assertions)**.
- `composer test` (root) → pendiente de re-ejecutar en cada entrega.

## Orden de implementación

1. Recuperación ✅
2. Registro + verificación email
3. Perfil (update) + cambio de contraseña
4. Preferencias (update)
5. Notificaciones
6. Bloqueo/disable
7. Invitaciones
8. OTP
9. Passkeys
10. Flag público/privado en `platform.json` + `guard add`
11. Vistas base web

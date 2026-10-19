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
| 4 | Registro (plataforma pública) | ✅ nuevo | ✅ | ⏳ |
| 5 | Verificación email/cuenta | ✅ nuevo | ✅ | ⏳ |
| 6 | Update perfil + password | ✅ nuevo | ✅ | ⏳ |
| 7 | Preferencias (update) | ✅ nuevo | ✅ | ⏳ |
| 8 | Notificaciones | ✅ nuevo | ✅ | ⏳ |
| 9 | Bloqueo/disable individual | ✅ nuevo | ✅ | ⏳ |
| 10 | Invitaciones + verificación | ✅ nuevo | ✅ | ⏳ |
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
- `POST {prefix}/api/v1/auth/register` → `RegistrationController@register` (solo `public: true`)
- `GET {prefix}/api/v1/auth/email/verify/{id}/{hash}` → `EmailVerificationController@verify` (`signed`)
- `POST {prefix}/api/v1/auth/email/verify/resend` → `EmailVerificationController@resend` (`auth`)
- `PATCH {prefix}/api/v1/auth/profile` → `AuthController@updateProfile` (`auth`)
- `PUT {prefix}/api/v1/auth/password` → `AuthController@updatePassword` (`auth`)
- `GET {prefix}/api/v1/auth/preferences` → `PreferencesController@show` (`auth`)
- `PUT {prefix}/api/v1/auth/preferences` → `PreferencesController@update` (`auth`)
- `GET {prefix}/api/v1/auth/notifications` → `NotificationsController@index` (`auth`)
- `POST {prefix}/api/v1/auth/notifications/read-all` → `NotificationsController@readAll` (`auth`)
- `POST {prefix}/api/v1/auth/notifications/{id}/read` → `NotificationsController@read` (`auth`)
- `DELETE {prefix}/api/v1/auth/notifications/{id}` → `NotificationsController@destroy` (`auth`)
- `POST {prefix}/api/v1/auth/account/disable` → `AccountController@disable` (`auth`, `current_password`)
- `POST {prefix}/api/v1/auth/account/reactivate` → `AccountController@reactivate` (público, credenciales)
- `POST {prefix}/api/v1/auth/invitations` → `InvitationController@store` (`auth`)
- `GET {prefix}/api/v1/auth/invitations/{token}` → `InvitationController@show` (público)
- `POST {prefix}/api/v1/auth/invitations/accept` → `InvitationController@accept` (público)

El registro crea la cuenta por el modelo de la plataforma (mismo que su provider),
emite el token igual que el login (shared `AccessTokenIssuer`/`UserPresenter`) y,
si `features.verification`, envía el correo de verificación. El enlace de
verificación es un signed URL de Laravel; los correos apuntan al SPA vía
`security.frontend.url` (`FRONTEND_URL`), con `password_reset_path` y
`email_verification_path`. `PlatformUser` implementa `MustVerifyEmail`.

Cambiar el email en `auth/profile` lo deja sin verificar y reenvía el enlace;
`auth/password` exige `current_password`. Las preferencias (tema, idioma,
canales de notificación) se guardan por usuario y guard en
`platform_user_preferences` (migración **del paquete**, `loadMigrationsFrom`),
fusionadas sobre los defaults para que el cliente siempre reciba un objeto
completo. Las notificaciones usan la tabla estándar `notifications` (migración
del paquete, solo si falta) y el `Notifiable` del modelo; el adapter se apoya en
`$user->notifications()` y nunca deja tocar filas de otra cuenta. El bloqueo de
cuenta vive en `platform_user_locks` (migración del paquete, `AccountLock`):
deshabilitar exige `current_password` y revoca los tokens; el login devuelve
`ACCOUNT_DISABLED`, y el middleware `account.active` rechaza cualquier token vivo
de una cuenta deshabilitada. Reactivar exige credenciales. Las invitaciones viven
en `platform_invitations` (migración del paquete; token guardado con SHA-256,
`security.invitations.ttl_hours`): un miembro autenticado invita un email, el
invitado inspecciona el token y lo acepta con nombre + contraseña, creando la
cuenta ya verificada. `UserRegistrar` centraliza la creación de cuentas para que
registro e invitación no diverjan.

## Verificación

- `demo/api`: `PasswordResetEndpointTest` (7), `RegistrationEndpointTest` (5),
  `EmailVerificationEndpointTest` (5), `AccountEndpointTest` (8),
  `NotificationsEndpointTest` (6), `AccountLockEndpointTest` (7) y
  `InvitationEndpointTest` (7).
- `composer test --working-dir=demo/api` → **257 passed (2888 assertions)**.
- `composer test` (root) → **351 passed (1010 assertions)**.
- Flake preexistente (no de esta fase): `RateLimitTest` a veces falla con
  `Lcobucci\JWT ... The token was issued in the future` (clock skew de Passport);
  pasa la mayoría de las corridas.

## Orden de implementación

1. Recuperación ✅
2. Registro + verificación email ✅
3. Perfil (update) + cambio de contraseña ✅
4. Preferencias (update) ✅
5. Notificaciones ✅
6. Bloqueo/disable ✅
7. Invitaciones ✅
8. OTP
9. Passkeys
10. Flag público/privado en `platform.json` + `guard add`
11. Vistas base web

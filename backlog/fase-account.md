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
  `guards` pasa a objeto `{ "guard": { "public": bool } }`, con compat legacy:
  las listas se leen como privadas salvo `web`). Un guard público abre su app
  directamente; uno privado arranca en el login.
- No se crean módulos `users`/`notifications` (prohibido T-110): el surface de
  cuenta vive en `security`; la preferencia en `configuration`.

## Estado por capacidad

| # | Capacidad | API | Tests | Cliente web |
|---|-----------|-----|-------|-------------|
| 1 | Login email/password | ✅ existía | ✅ | ✅ existía |
| 2 | `auth/me` (lectura) / logout | ✅ existía | ✅ | ✅ existía |
| 3 | Recuperación (forgot/reset) | ✅ nuevo | ✅ | ✅ |
| 4 | Registro (plataforma pública) | ✅ nuevo | ✅ | ✅ |
| 5 | Verificación email/cuenta | ✅ nuevo | ✅ | ✅ |
| 6 | Update perfil + password | ✅ nuevo | ✅ | ✅ |
| 7 | Preferencias (update) | ✅ nuevo | ✅ | ✅ |
| 8 | Notificaciones | ✅ nuevo | ✅ | ✅ |
| 9 | Bloqueo/disable individual | ✅ nuevo | ✅ | ✅ |
| 10 | Invitaciones + verificación | ✅ nuevo | ✅ | ✅ |
| 11 | OTP login | ✅ nuevo | ✅ | ✅ |
| 12 | Passkeys/WebAuthn | ✅ nuevo | ✅ | ✅ |
| 13 | Login social | ✅ Google/Microsoft (global) | ✅ | ✅ |
| 14 | 2FA TOTP (obligatorio admin/employee; challenge/enrolamiento en login) | ✅ T-CASA-009 | ✅ | ✅ panel de enrolamiento/challenge |
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
- `POST {prefix}/api/v1/auth/otp/request` → `OtpController@request` (público, opaco)
- `POST {prefix}/api/v1/auth/otp/verify` → `OtpController@verify` (público)
- `POST {prefix}/api/v1/auth/webauthn/login/options` → `WebAuthnController@loginOptions` (público, opaco)
- `POST {prefix}/api/v1/auth/webauthn/login` → `WebAuthnController@login` (público)
- `GET {prefix}/api/v1/auth/webauthn/credentials` → `WebAuthnController@index` (`auth`)
- `POST {prefix}/api/v1/auth/webauthn/register/options` → `WebAuthnController@registerOptions` (`auth`)
- `POST {prefix}/api/v1/auth/webauthn/register` → `WebAuthnController@register` (`auth`)
- `DELETE {prefix}/api/v1/auth/webauthn/credentials/{id}` → `WebAuthnController@destroy` (`auth`)

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
registro e invitación no diverjan. El OTP (`platform_otp_codes`, migración del
paquete; `security.otp.*`) guarda el hash del código, lo ata a la plataforma, lo
expira, limita intentos y es de un solo uso; `otp/request` responde igual exista
o no la cuenta. Las passkeys usan `web-auth/webauthn-lib` (`WebAuthnBridge`,
singleton): `platform_webauthn_credentials` (migración del paquete) guarda el
`CredentialRecord` serializado por guard y usuario; el registro está autenticado
y el login es passwordless. Los challenges se cachean por su valor codificado,
son de un solo uso y expiran (`security.webauthn.challenge_ttl_seconds`).

## Vistas base mobile

`demo/mobile/packages/core` espeja la web: `api/account_api.dart` (modelos
`Preferences`, `AccountNotification`, `NotificationsFeed`, `InvitationSummary`
y endpoints de perfil, password, preferencias, notificaciones, invitaciones,
OTP y recuperación); `AuthNotifier` añade `loginWithOtp`, `loginWithPasskey`,
`register`, `acceptInvitation` y `refreshUser` (persistiendo el token igual que
el login).
El router monta las rutas públicas (`/login`, `/register`, `/forgot-password`,
`/reset-password`, `/verify-email`, `/accept-invitation`) y protegidas
(`/preferences`, `/notifications`); `/register` solo es accesible con
`--dart-define=PUBLIC=true` (default `web`). Login con email/password o código
OTP, perfil editable con verificación, password y disable, preferencias y
notificaciones. Las passkeys usan el plugin `passkeys` (Credential Manager /
ASAuthorization) mediante un seam testeable (`PasskeyClient`/`PasskeyService`);
login passwordless desde el email y alta/baja en perfil. El login social usa
`flutter_web_auth_2` (Custom Tabs / ASWebAuthenticationSession) y el deep link
`shardev://auth/callback`; `SocialService` resuelve providers, pide el redirect
con `return` y canjea el token. Verificación: `cd demo/mobile && flutter analyze
&& ./tool/test.sh` (core 18 tests, plugin_kit 6, product 5).

## Vistas base desktop

`demo/desktop/packages/core` añade `account/` (`Preferences`, `AccountNotification`,
`NotificationsFeed`, `InvitationSummary`, `AccountApi`) y `AuthService` gana
`loginWithOtp`, `register`, `acceptInvitation` y `refreshUser`. `LoginFlowView`
encadena login (password/OTP), registro, recuperación/reset, verificación e
invitación sin tocar la shell; `ShellView` incorpora Preferencias y
Notificaciones; `ProfileView` permite editar perfil, reenviar verificación,
cambiar contraseña y deshabilitar la cuenta. La visibilidad se lee de
`shardev.guard.public`/`SHARDEV_GUARD_PUBLIC` (default `web` público).

Las passkeys de escritorio se delegan al navegador del sistema: la app abre
`{frontend}/passkey` con `mode`, `state` y `return` (callback loopback efímero
`http://127.0.0.1:<puerto>/callback`), el SPA ejecuta la ceremonia WebAuthn y
devuelve el token o el estado por ese callback. El registro entrega el token
actual en el fragmento. `PlatformConfig.frontendUrl(guard)` se configura con
`-Dshardev.frontend.url`/`SHARDEV_FRONTEND_URL` (default demo: admin 5173,
employee 5174, web 5175).

El login social de escritorio usa el mismo patrón loopback: `GET
auth/social/{provider}?return=http://127.0.0.1:...` devuelve la URL del
proveedor, el navegador completa el OAuth y el token llega al callback local.
Verificado con `cd demo/desktop && ./gradlew build` (compila las 3 apps + tests).

## Verificación

- `demo/api`: `PasswordResetEndpointTest` (7), `RegistrationEndpointTest` (5),
  `EmailVerificationEndpointTest` (5), `AccountEndpointTest` (8),
  `NotificationsEndpointTest` (6), `AccountLockEndpointTest` (7),
  `InvitationEndpointTest` (7), `OtpEndpointTest` (8), `WebAuthnEndpointTest`
  (7, con `Tests\Support\WebAuthnAuthenticator`) y `SocialiteEndpointTest` (10).
- `composer test --working-dir=demo/api` → **282 passed (3031 assertions)**.
- `composer test` (root) → **359 passed (1026 assertions)** (incluye visibilidad
  de guards en `new`/`add`/`config guard` y `guard list`).
- `platform guard list` muestra `public`/`private` por guard; `platform new
  --guards=web:private,admin:public` y `platform guard add <g> --public|--private`
  persisten la visibilidad en `platform.json` y en `config/platforms.php`.
- `demo/web`: `npm run typecheck`, `npm run lint`, `npm test`
  → **30 passed** (7 archivos; incluye `webauthn`, auth, cuenta, social y el
  puente de passkeys del desktop) y `npm run build` (user/admin/employee).
- `demo/mobile`: `flutter analyze` (sin issues) y `./tool/test.sh`
  → core 18 + plugin_kit 6 + product 5 tests.
- `demo/desktop`: `./gradlew build` → **34 tests** (core 23 + plugin_kit 6 +
  product 5; incluye loopback, passkeys, OAuth nativo y `authenticateWithToken`).
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
8. OTP ✅
9. Passkeys ✅
10. Flag público/privado en `platform.json` + `guard add` ✅
11. Vistas base web ✅
12. Vistas base mobile ✅
13. Vistas base desktop ✅
14. Login social web ✅
15. Passkeys nativas mobile ✅ (plugin `passkeys`; asociación de dominio pendiente del host)
16. Passkeys desktop ✅ (ceremonia en navegador del sistema + callback loopback)
17. Login social mobile ✅ (deep link + navegador del sistema)
18. Login social desktop ✅ (navegador del sistema + callback loopback)

## Vistas base web

`demo/web/packages/core/src` es la referencia: `lib/endpoints.ts` expone los
grupos `auth` (login, register, forgot/reset, verify/resend, OTP, passkeys) y
`account` (perfil, password, preferencias, notificaciones, disable/reactivate,
invitaciones); `lib/webauthn.ts` convierte las options base64url y las respuestas
del navegador (`navigator.credentials.create/get`) al payload que espera la API;
`AuthProvider` añade `loginWithOtp`, `loginWithPasskey`, `register`,
`acceptInvitation` y `refreshUser`. `PlatformApp` monta las rutas públicas
(`/login`, `/register`, `/forgot-password`, `/reset-password`, `/verify-email`,
`/accept-invitation`) y protegidas (`/preferences`, `/notifications`) con la prop
`public`: los guards públicos muestran el enlace de registro, los privados solo
invitación. `GuardClientScaffolder::web` inyecta `public={true|false}` en el
`App.tsx` clonado según `platform.json`.

### Login social (web)

El paquete `socialite` expone `GET {prefix}/api/v1/auth/social/providers`
(catálogo de drivers habilitados), `GET .../{provider}` (`redirect_url` al
proveedor) y `GET .../{provider}/callback`. El callback sigue devolviendo JSON a
clientes API (`Accept: application/json`) y, para navegaciones del navegador,
redirige al SPA con el token en el fragmento
(`{FRONTEND_URL}{SOCIALITE_FRONTEND_REDIRECT}#token=...`); el flag
`SOCIALITE_FRONTEND_REDIRECT` vacío conserva el JSON. El modelo se resuelve con
`socialite.user_model`, y `SocialiteServiceProvider` espeja
`socialite.providers.*` en `services.*` para que baste un bloque de config.
Web monta `/auth/callback` (`SocialCallbackPage`): lee el token, lo canjea por
`auth/me`, limpia el fragmento y entra al shell. Los botones solo aparecen en el
guard `web`, que es el único con rutas sociales (`api/v1`). Ejemplo de `.env`:
`FRONTEND_URL=http://localhost:5175` y
`SOCIALITE_FRONTEND_REDIRECT=/auth/callback`.

Los clientes nativos no pasan por el SPA: `GET .../{provider}?return=<callback>`
guarda el callback en el `state` OAuth (cifrado) y el callback final redirige el
token a ese destino. Solo se aceptan `http(s)://127.0.0.1|localhost|[::1]` y los
esquemas de `socialite.native_schemes` (`SOCIALITE_NATIVE_SCHEMES`, default
`shardev`). En loopback el token va en la query (el navegador nunca envía el
fragmento al servidor local); en un esquema nativo va en el fragmento. Mobile
usa `flutter_web_auth_2` con `shardev://auth/callback`; desktop reutiliza el
callback loopback. Las rutas sociales siguen viviendo solo en el guard `web`:
los botones se muestran únicamente ahí. Mobile integra passkeys con el plugin
`passkeys`; desktop delega la ceremonia al navegador del sistema con callback
loopback (`/passkey` en el SPA).

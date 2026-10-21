# Shardev Mobile — cliente de referencia (Flutter)

Cliente móvil de referencia del framework. Una **app Flutter con un flavor por
guard** (`web`, `admin`, `employee`) sobre paquetes compartidos y **plugins por
guard**, espejo del cliente web (`demo/web`).

Un token Passport sólo es válido para el guard que lo emitió, así que la app se
compila una vez por guard y **nunca** ofrece un selector de plataforma en el
login.

## Estructura (monorepo Dart)

```text
demo/mobile/
├── pubspec.yaml                 # app shardev_mobile (path deps a packages/ y plugins/)
├── lib/
│   ├── main.dart                # registra plugins + arranca
│   └── app.dart                 # MaterialApp.router
├── packages/
│   ├── plugin_kit/              # contrato: Guard, PluginDefinition, definePlugin, createRegistry
│   └── core/                    # shell, ApiClient (dio), auth (Riverpod), router (go_router), páginas
├── plugins/
│   └── product/                 # una vista por guard (web read-only, employee manage, admin full)
└── test/                        # (dentro de cada paquete)
```

`shardev_core` es agnóstico de la app: recibe el `Guard` y la lista de plugins,
y no conoce ningún recurso concreto. Un plugin (p. ej. `shardev_product_plugin`)
depende de `shardev_core` para llamar a la API.

## Una plataforma por guard (flavors)

El guard se fija en tiempo de compilación con `--dart-define=GUARD=...`
(por defecto `admin`). El prefijo de API sale de `Guard.apiBase`:

| Guard | `--dart-define` | Prefijo API | Registro |
| --- | --- | --- | --- |
| user/web | `GUARD=web` | `/api/v1` | público (default) |
| admin | `GUARD=admin` | `/admin/api/v1` | privado (default) |
| employee | `GUARD=employee` | `/employee/api/v1` | privado (default) |

`--dart-define=PUBLIC=true|false` refleja la visibilidad de `platform.json`: un
guard público muestra el enlace de registro y acepta auto-registro; uno privado
solo permite entrar por invitación. Sin el define, `web` es público y el resto
privado.

```bash
# Android emulator: el host de la API es 10.0.2.2 (no localhost)
flutter run --dart-define=GUARD=admin    --dart-define=API_HOST=http://10.0.2.2:8000
flutter run --dart-define=GUARD=employee --dart-define=API_HOST=http://10.0.2.2:8000
flutter run --dart-define=GUARD=web      --dart-define=API_HOST=http://10.0.2.2:8000
```

Puedes envolverlos en flavors reales (`--flavor admin`) añadiendo la config en
`android/`/`ios/`; el guard sigue viniendo de `--dart-define`.

## Plugins por guard

Un plugin declara, por guard, una vista (ruta + entrada de navegación opcional).
El registro resuelve sólo las vistas del guard activo:

```dart
final productPlugin = definePlugin(
  PluginDefinition(
    name: 'product',
    title: 'Products',
    resource: 'products',
    guards: {
      Guard.web:      GuardView(path: 'products', builder: (_) => const ProductCatalogView(),
                                nav: const NavItem(label: 'Catálogo', to: '/products', order: 10)),
      Guard.employee: GuardView(path: 'products', builder: (_) => const ProductManageView(),
                                nav: const NavItem(label: 'Productos', to: '/products', order: 10)),
      Guard.admin:    GuardView(path: 'products', builder: (_) => const ProductAdminView(),
                                nav: const NavItem(label: 'Products', to: '/products', order: 10)),
    },
  ),
);
```

Añadir un plugin = añadir un paquete como path dependency + registrarlo en
`lib/main.dart`:

```dart
ProviderScope(
  overrides: [pluginsProvider.overrideWithValue([productPlugin])],
  child: const ShardevApp(),
)
```

El `path` de cada vista debe coincidir por convención con
`guards.<guard>.path` del `plugin.json` del package API.

```bash
platform plugin list          # descubre plugins desde los manifiestos
platform plugin show product  # guards, modos y abilities
```

## Credenciales demo

`admin@example.com`, `employee@example.com`, `user@example.com` — password
`password`. El login rellena el email según el guard del build.

## Instalación desde el CLI

```bash
platform demo add product --with-mobile-client             # copia ./mobile-client y corre flutter pub get
platform demo add product --with-mobile-client --no-install
platform demo add product --with-client --with-mobile-client   # web + mobile
```

### Passkeys (WebAuthn)

El login sin contraseña usa el plugin [`passkeys`](https://pub.dev/packages/passkeys)
(Android Credential Manager / iOS Authentication Services). `shardev_core`
expone `PasskeyClient` (seam inyectable), `PasskeyService` (options de la API →
ceremonia del dispositivo → verificación en la API) y
`passkeyServiceProvider`; `AuthNotifier.loginWithPasskey` obtiene la assertion y
la canjea por el token. El perfil lista, añade y elimina passkeys.

Las carpetas `android/`/`ios/` no se versionan: tras `flutter create .` hay que
asociar la app con el dominio del relying party antes de usar passkeys reales:

- Android: publicar `https://{rp_id}/.well-known/assetlinks.json` con el package
  y el SHA-256 del certificado; el origen nativo
  (`android:apk-key-hash:...`) debe estar en `WEBAUTHN_ALLOWED_ORIGINS`.
- iOS: entitlement `com.apple.developer.associated-domains` con
  `webcredentials:{rp_id}`, y el origen nativo correspondiente en
  `WEBAUTHN_ALLOWED_ORIGINS`.
- API: `WEBAUTHN_RP_ID` debe ser el dominio registrable (no `localhost`) y
  `APP_URL`/`WEBAUTHN_ALLOWED_ORIGINS` deben coincidir con ese dominio.

Los tests cubren el contrato (options → credential → API) con un
`PasskeyClient` falso; la ceremonia biométrica real requiere un emulador/
dispositivo con cuenta y esas asociaciones.

### Login social

El guard `web` muestra los proveedores habilitados por la API. El flujo usa
[`flutter_web_auth_2`](https://pub.dev/packages/flutter_web_auth_2) para abrir
el navegador del sistema: `SocialService` pide
`GET {base}/auth/social/{provider}?return=shardev://auth/callback`, abre la URL
del proveedor y recibe el token en el deep link. `AuthNotifier.loginWithSocial`
lo valida con `auth/me` y lo persiste. Configura el callback con
`--dart-define=SOCIAL_CALLBACK_URL=esquema://ruta` (default
`shardev://auth/callback`).

`android/`/`ios/` no se versionan: tras `flutter create .` declara el esquema
(`android:scheme="shardev"` en un intent-filter de la actividad y
`CFBundleURLTypes` en iOS) y añade el esquema a `SOCIALITE_NATIVE_SCHEMES` en la
API. Las rutas sociales viven solo en `/api/v1`; en admin/employee no se muestra
el bloque.

## Comandos

```bash
# Las carpetas de plataforma (android/, ios/, ...) no se versionan:
# genéralas una vez con:
flutter create .

flutter pub get
flutter analyze          # analiza app + packages + plugins desde la raíz
./tool/test.sh           # pub get + `flutter test` en cada package (plugin_kit, core, product)
flutter run --dart-define=GUARD=admin --dart-define=API_HOST=http://10.0.2.2:8000
```

## Endpoints consumidos

| Acción | REST |
| --- | --- |
| Login | `POST {base}/auth/login` |
| Login OTP | `POST {base}/auth/otp/request` · `POST {base}/auth/otp/verify` |
| Registro | `POST {base}/auth/register` |
| Recuperación | `POST {base}/auth/forgot-password` · `POST {base}/auth/reset-password` |
| Verificación | `GET {base}/auth/email/verify/{id}/{hash}` · `POST .../resend` |
| Invitaciones | `POST {base}/auth/invitations` · `GET .../{token}` · `POST .../accept` |
| Perfil | `PATCH {base}/auth/profile` · `PUT {base}/auth/password` |
| Preferencias | `GET/PUT {base}/auth/preferences` |
| Notificaciones | `GET {base}/auth/notifications` · `POST .../read-all` · `POST .../{id}/read` · `DELETE .../{id}` |
| Deshabilitar | `POST {base}/auth/account/disable` · `POST {base}/auth/account/reactivate` |
| Passkeys | `POST .../webauthn/login/options` · `POST .../webauthn/login` · `GET/POST .../webauthn/register*` · `DELETE .../webauthn/credentials/{id}` |
| Login social | `GET /api/v1/auth/social/providers` · `GET .../{provider}?return=...` · callback al deep link |
| Sesión | `GET {base}/auth/me` |
| Logout | `POST {base}/auth/logout` |
| Listar | `GET {base}/products` |
| Crear | `POST {base}/products` |
| Actualizar | `PUT {base}/products/{id}` |
| Eliminar | `DELETE {base}/products/{id}` |

`{base}` = `/api/v1`, `/admin/api/v1` o `/employee/api/v1` según el guard.

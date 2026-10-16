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

| Guard | `--dart-define` | Prefijo API |
| --- | --- | --- |
| user/web | `GUARD=web` | `/api/v1` |
| admin | `GUARD=admin` | `/admin/api/v1` |
| employee | `GUARD=employee` | `/employee/api/v1` |

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

## Comandos

```bash
# Las carpetas de plataforma (android/, ios/, ...) no se versionan:
# genéralas una vez con:
flutter create .

flutter pub get
flutter analyze
flutter test
flutter run --dart-define=GUARD=admin --dart-define=API_HOST=http://10.0.2.2:8000
```

## Endpoints consumidos

| Acción | REST |
| --- | --- |
| Login | `POST {base}/auth/login` |
| Sesión | `GET {base}/auth/me` |
| Logout | `POST {base}/auth/logout` |
| Listar | `GET {base}/products` |
| Crear | `POST {base}/products` |
| Actualizar | `PUT {base}/products/{id}` |
| Eliminar | `DELETE {base}/products/{id}` |

`{base}` = `/api/v1`, `/admin/api/v1` o `/employee/api/v1` según el guard.

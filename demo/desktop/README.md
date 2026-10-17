# Shardev Desktop — cliente de referencia (JavaFX)

Cliente de escritorio de referencia del framework. Un **proyecto Gradle multi-módulo
con una app por guard** (`user`, `admin`, `employee`) sobre paquetes compartidos y
**plugins por guard**, espejo de los clientes web (`demo/web`) y móvil (`demo/mobile`).

Un token Passport sólo es válido para el guard que lo emitió, así que cada guard es
un módulo de app independiente y **nunca** hay un selector de plataforma en el login.

## Estructura (monorepo Gradle)

```text
demo/desktop/
├── settings.gradle.kts          # incluye packages/, plugins/ y apps/
├── build.gradle.kts             # toolchain JDK 21 + JUnit + openjfx (apply false)
├── gradlew / gradle/wrapper/    # Gradle wrapper (no requiere Gradle instalado)
├── packages/
│   ├── plugin-kit/              # Guard, PluginDefinition, definePlugin, PluginRegistry
│   └── core/                    # shell, ApiClient (java.net.http), auth, vistas JavaFX
├── plugins/
│   └── product/                 # una vista por guard (web read-only, employee manage, admin full)
└── apps/
    ├── user/                    # Guard.WEB      → /api/v1
    ├── employee/                # Guard.EMPLOYEE → /employee/api/v1
    └── admin/                   # Guard.ADMIN    → /admin/api/v1
```

`shardev_core` es agnóstico de la app: recibe el `Guard` y la lista de plugins, y no
conoce ningún recurso concreto. Un plugin (p. ej. `shardev_product`) depende de
`shardev_core` para llamar a la API.

## Una app por guard

El guard se fija en el módulo de app (`Main.java`), no en runtime:

```java
// apps/admin/src/main/java/dev/shardev/apps/admin/Main.java
new PlatformApp(Guard.ADMIN, List.of(ProductPlugin.productPlugin())).start(stage);
```

| Módulo | Guard | Prefijo API |
| --- | --- | --- |
| `apps/user` | `Guard.WEB` | `/api/v1` |
| `apps/employee` | `Guard.EMPLOYEE` | `/employee/api/v1` |
| `apps/admin` | `Guard.ADMIN` | `/admin/api/v1` |

```bash
# Admin (por defecto apunta a http://localhost:8000)
./gradlew :apps:admin:run

# Otro host (emulador, staging...)
./gradlew :apps:admin:run -Dshardev.api.host=http://192.168.1.10:8000
```

`main` vive en una clase que no extiende `Application` (`Main` lanza `AdminApp`), de
modo que JavaFX arranca desde el classpath sin el chequeo del launcher modular.

## Plugins por guard

Un plugin declara, por guard, una vista (ruta + entrada de navegación opcional). El
registro resuelve sólo las vistas del guard activo:

```java
public static PluginDefinition productPlugin() {
    return definePlugin(new PluginDefinition("product", "Products", "products", Map.of(
        Guard.WEB, new GuardView("products",
            ctx -> new ProductList(ctx, ProductCapabilities.READ_ONLY, "Catálogo"),
            new NavItem("Catálogo", "/products", 10)),
        Guard.EMPLOYEE, new GuardView("products",
            ctx -> new ProductList(ctx, ProductCapabilities.MANAGE, "Productos"),
            new NavItem("Productos", "/products", 10)),
        Guard.ADMIN, new GuardView("products",
            ctx -> new ProductList(ctx, ProductCapabilities.FULL, "Products"),
            new NavItem("Products", "/products", 10)))));
}
```

Añadir un plugin = añadir un módulo como dependencia del app + registrarlo en
`Main.java`:

```java
new PlatformApp(Guard.ADMIN, List.of(ProductPlugin.productPlugin())).start(stage);
```

El `path` de cada vista debe coincidir por convención con
`guards.<guard>.path` del `plugin.json` del package API.

```bash
platform plugin list          # descubre plugins desde los manifiestos
platform plugin show product  # guards, modos y abilities
```

## Credenciales demo

`admin@example.com`, `employee@example.com`, `user@example.com` — password
`password`. El login rellena el email según el guard del módulo.

## Instalación desde el CLI

```bash
platform demo add product --with-desktop-client                # copia ./desktop-client y corre gradlew
platform demo add product --with-desktop-client --no-install
platform demo add product --with-client --with-mobile-client --with-desktop-client
```

## Comandos

```bash
./gradlew build            # compila + tests de todos los módulos
./gradlew test             # solo tests
./gradlew :apps:admin:run  # ejecuta la app admin
```

Requiere **JDK 21** (el wrapper descarga Gradle; JavaFX se resuelve como dependencia).

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

<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: writes the development Docker assets for a workspace.
 *
 * The API and web components are the only ones that run inside containers.
 * Everything is derived from the manifest, so adding or removing a component
 * (or a guard, which becomes one web app) refreshes the compose file instead
 * of leaving a stale service behind.
 */
final class DockerScaffolder
{
    /**
     * Conventional Vite ports used when an app's config cannot be read.
     *
     * @var array<string, int>
     */
    private const DEFAULT_APP_PORTS = [
        'user' => 5175,
        'admin' => 5173,
        'employee' => 5174,
    ];

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @return list<string> paths written, relative to the workspace root
     */
    public function write(string $root, WorkspaceManifest $manifest): array
    {
        $root = rtrim($root, '/');
        $api = $this->enabledComponent($manifest, ComponentType::Api);
        $web = $this->enabledComponent($manifest, ComponentType::Web);
        $written = [];

        if ($api !== null) {
            $written[] = $this->writeFile($root, $api->path.'/Dockerfile', $this->apiDockerfile());
            $written[] = $this->writeFile($root, $api->path.'/.dockerignore', $this->apiDockerignore());
            $written[] = $this->writeFile($root, $api->path.'/docker-entrypoint.sh', $this->apiEntrypoint(), true);
        }

        $apps = [];

        if ($web !== null) {
            $apps = $this->webApps($web, $root.'/'.$web->path);
            $written[] = $this->writeFile(
                $root,
                $web->path.'/Dockerfile',
                $this->webDockerfile(array_values(array_unique(array_values($apps))))
            );
            $written[] = $this->writeFile($root, $web->path.'/.dockerignore', $this->webDockerignore());
            $written[] = $this->writeFile(
                $root,
                $web->path.'/docker-entrypoint.sh',
                $this->webEntrypoint(),
                true
            );
        }

        if ($api !== null) {
            $written[] = $this->writeFile($root, 'docker-compose.yml', $this->compose($api, $web, $apps));
            $written[] = $this->writeFile($root, 'DOCKER.md', $this->readme($api, $web, $apps));
        } else {
            $this->remove($root.'/docker-compose.yml');
            $this->remove($root.'/DOCKER.md');
        }

        return $written;
    }

    private function enabledComponent(WorkspaceManifest $manifest, ComponentType $type): ?Component
    {
        if (! $manifest->has($type->value)) {
            return null;
        }

        $component = $manifest->component($type->value);

        return $component->enabled ? $component : null;
    }

    /**
     * @return array<string, int> app directory => Vite port
     */
    private function webApps(Component $web, string $path): array
    {
        $apps = [];
        $used = [];

        foreach ($web->guardNames() as $guard) {
            $app = $guard === 'web' ? 'user' : $guard;
            $port = $this->configuredPort($path.'/apps/'.$app.'/vite.config.ts');
            $fallback = self::DEFAULT_APP_PORTS[$app] ?? null;

            if ($port === null || in_array($port, $used, true)) {
                $port = $fallback !== null && ! in_array($fallback, $used, true)
                    ? $fallback
                    : $this->nextPort($used);
            }

            $used[] = $port;
            $apps[$app] = $port;
        }

        return $apps;
    }

    private function configuredPort(string $file): ?int
    {
        if (! $this->filesystem->isFile($file)) {
            return null;
        }

        if (preg_match('/createAppConfig\([^,]+,\s*(\d+)\)/', $this->filesystem->read($file), $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    /**
     * @param list<int> $used
     */
    private function nextPort(array $used): int
    {
        $port = 5175;

        while (in_array($port, $used, true)) {
            $port++;
        }

        return $port;
    }

    /**
     * @param list<int> $ports
     */
    private function webDockerfile(array $ports): string
    {
        $expose = implode(' ', array_map(static fn (int $port): string => (string) $port, $ports));

        $template = <<<'DOCKERFILE'
            FROM node:22-bookworm-slim

            WORKDIR /app

            COPY . .
            COPY docker-entrypoint.sh /usr/local/bin/shardev-web
            RUN chmod +x /usr/local/bin/shardev-web

            EXPOSE __PORTS__

            ENTRYPOINT ["shardev-web"]
            DOCKERFILE;

        return str_replace('__PORTS__', $expose, $template);
    }

    /**
     * @param array<string, int> $apps
     */
    private function compose(Component $api, ?Component $web, array $apps): string
    {
        $services = [$this->apiService($api->path)];
        $volumes = ['  api-vendor:'];

        if ($web !== null) {
            $services[] = $this->webService($web->path, $apps);
            $volumes[] = '  web-node_modules:';
        }

        return "services:\n".implode("\n", $services)."\n\nvolumes:\n".implode("\n", $volumes)."\n";
    }

    private function apiService(string $path): string
    {
        $service = <<<'YAML'
              api:
                build:
                  context: ./__PATH__
                environment:
                  APP_ENV: local
                  APP_DEBUG: "true"
                  APP_URL: http://localhost:${API_PORT:-8000}
                  DB_CONNECTION: sqlite
                  DB_DATABASE: /var/www/html/database/database.sqlite
                ports:
                  - "${API_PORT:-8000}:8000"
                volumes:
                  - ./__PATH__:/var/www/html
                  - api-vendor:/var/www/html/vendor
                healthcheck:
                  test: ["CMD", "php", "artisan", "--version"]
                  interval: 10s
                  timeout: 5s
                  retries: 30
                  start_period: 30s
            YAML;

        return str_replace('__PATH__', $path, rtrim($service));
    }

    /**
     * @param array<string, int> $apps
     */
    private function webService(string $path, array $apps): string
    {
        $ports = [];

        foreach ($apps as $app => $port) {
            $variable = 'WEB_'.strtoupper((string) preg_replace('/[^A-Za-z0-9]+/', '_', $app)).'_PORT';
            $ports[] = '      - "${'.$variable.':-'.$port.'}:'.$port.'"';
        }

        $service = <<<'YAML'
              web:
                build:
                  context: ./__PATH__
                environment:
                  PLATFORM_BACKEND: http://api:8000
                  WEB_APPS: "__APPS__"
                ports:
            __PORTS__
                volumes:
                  - ./__PATH__:/app
                  - web-node_modules:/app/node_modules
                depends_on:
                  api:
                    condition: service_healthy
            YAML;

        return str_replace(
            ['__PATH__', '__APPS__', '__PORTS__'],
            [$path, implode(' ', array_keys($apps)), implode("\n", $ports)],
            rtrim($service)
        );
    }

    /**
     * @param array<string, int> $apps
     */
    private function readme(Component $api, ?Component $web, array $apps): string
    {
        $lines = [
            '# Docker (desarrollo)',
            '',
            'Este workspace incluye un entorno de desarrollo con Docker para el API'.($web !== null ? ' y los clientes web' : '').'.',
            '',
            '## Servicios',
            '',
            '- `api`: Laravel en `http://localhost:8000`. El primer arranque instala Composer, genera la clave, migra SQLite y arranca `php artisan serve`.',
        ];

        if ($web !== null) {
            $lines[] = '- `web`: Vite sirviendo cada app del workspace con hot reload. El proxy usa `http://api:8000` dentro de la red de Compose.';
        }

        $lines[] = '';
        $lines[] = '## Arranque';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'docker compose up --build';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = 'La primera ejecución puede tardar mientras se descargan las imágenes y las dependencias.';
        $lines[] = '';
        $lines[] = '## URLs';
        $lines[] = '';
        $lines[] = '- API: `http://localhost:8000`';

        foreach ($apps as $app => $port) {
            $lines[] = '- Web '.$app.': `http://localhost:'.$port.'`';
        }

        $lines[] = '';
        $lines[] = 'Los puertos del API y de cada app se pueden cambiar en `docker-compose.yml` o con las variables `API_PORT` y `WEB_<APP>_PORT`.';
        $lines[] = '';
        $lines[] = '## Hot reload';
        $lines[] = '';
        $lines[] = 'El código del API y de la web está montado desde el host. Los cambios en PHP se aplican al recargar; Vite recarga el navegador al cambiar TypeScript/React.';
        $lines[] = 'Las dependencias viven en volúmenes (`api-vendor`, `web-node_modules`) para no mezclarlas con las del host.';
        $lines[] = '';
        $lines[] = '## Dependencias y migraciones';
        $lines[] = '';
        $lines[] = 'Tras cambiar `composer.json` o `package.json`, reinstala dentro de los contenedores:';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'docker compose run --rm api composer update';
        $lines[] = 'docker compose run --rm web npm install';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = 'Las migraciones se ejecutan en cada arranque del API. Para ejecutar otros comandos de Artisan:';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'docker compose exec api php artisan <comando>';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '## Parar y limpiar';
        $lines[] = '';
        $lines[] = '```bash';
        $lines[] = 'docker compose down';
        $lines[] = 'docker compose down -v';
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '`down -v` también borra los volúmenes de dependencias, así que el siguiente arranque las reinstala.';
        $lines[] = '';
        $lines[] = $web !== null
            ? 'El componente `'.$api->path.'` y el componente `web` se pueden levantar por separado, pero el compose solo se genera cuando el workspace tiene un API.'
            : 'Este workspace no tiene componente web, por eso el compose solo incluye el API.';

        return implode("\n", $lines)."\n";
    }

    private function apiDockerfile(): string
    {
        return <<<'DOCKERFILE'
            FROM php:8.4-cli

            ENV COMPOSER_ALLOW_SUPERUSER=1

            RUN apt-get update \
                && apt-get install -y --no-install-recommends git unzip libfreetype6-dev libicu-dev libjpeg62-turbo-dev libpng-dev libzip-dev \
                && docker-php-ext-configure gd --with-freetype --with-jpeg \
                && docker-php-ext-install bcmath gd intl zip \
                && apt-get clean \
                && rm -rf /var/lib/apt/lists/*

            COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

            WORKDIR /var/www/html

            COPY . .
            COPY docker-entrypoint.sh /usr/local/bin/shardev-api
            RUN chmod +x /usr/local/bin/shardev-api

            EXPOSE 8000

            ENTRYPOINT ["shardev-api"]
            DOCKERFILE;
    }

    private function apiDockerignore(): string
    {
        return <<<'IGNORE'
            .env
            .git
            .idea
            vendor
            node_modules
            bootstrap/cache
            database/*.sqlite
            storage/framework/cache
            storage/framework/sessions
            storage/framework/views
            storage/logs
            IGNORE;
    }

    private function apiEntrypoint(): string
    {
        return <<<'SH'
            #!/bin/sh
            set -eu

            cd /var/www/html

            if [ "$#" -gt 0 ]; then
                exec "$@"
            fi

            if [ ! -f .env ]; then
                if [ -f .env.example ]; then
                    cp .env.example .env
                else
                    : > .env
                fi
            fi

            mkdir -p \
                bootstrap/cache \
                database \
                storage/framework/cache/data \
                storage/framework/sessions \
                storage/framework/views \
                storage/logs

            if [ ! -f database/database.sqlite ]; then
                touch database/database.sqlite
            fi

            installed=0

            if [ ! -f vendor/autoload.php ]; then
                composer install --no-interaction --prefer-dist --no-progress --no-scripts
                installed=1
            fi

            if ! grep -q '^APP_KEY=base64:' .env; then
                php artisan key:generate --force
            fi

            if [ "$installed" -eq 1 ]; then
                php artisan package:discover --ansi
            fi

            php artisan migrate --force

            exec php artisan serve --host=0.0.0.0 --port=8000
            SH;
    }

    private function webDockerignore(): string
    {
        return <<<'IGNORE'
            .env
            .git
            .idea
            node_modules
            dist
            coverage
            apps/*/dist
            IGNORE;
    }

    private function webEntrypoint(): string
    {
        return <<<'SH'
            #!/bin/sh
            set -eu

            cd /app

            if [ "$#" -gt 0 ]; then
                exec "$@"
            fi

            install=0

            if [ ! -x node_modules/.bin/vite ]; then
                install=1
            fi

            if [ ! -f node_modules/.package-lock.json ]; then
                install=1
            fi

            if [ -f package.json ] && [ package.json -nt node_modules/.package-lock.json ]; then
                install=1
            fi

            if [ -f package-lock.json ] && [ package-lock.json -nt node_modules/.package-lock.json ]; then
                install=1
            fi

            if [ "$install" -eq 1 ]; then
                if [ -f package-lock.json ]; then
                    npm ci --no-audit --no-fund
                else
                    npm install --no-audit --no-fund
                fi
            fi

            apps="${WEB_APPS:-}"

            if [ -z "$apps" ]; then
                apps="$(find apps -mindepth 1 -maxdepth 1 -type d -exec basename {} \; | sort | tr '\n' ' ')"
            fi

            started=0
            pids=""

            for app in $apps; do
                if [ ! -f "apps/$app/vite.config.ts" ]; then
                    continue
                fi

                echo "[web] starting $app"
                ./node_modules/.bin/vite --config "apps/$app/vite.config.ts" --host 0.0.0.0 --clearScreen false &
                pids="$pids $!"
                started=1
            done

            if [ "$started" -eq 0 ]; then
                echo "[web] no apps found under /app/apps" >&2
                exit 1
            fi

            trap 'kill $pids 2>/dev/null || true' INT TERM
            wait
            SH;
    }

    private function writeFile(string $root, string $relative, string $contents, bool $executable = false): string
    {
        $path = $root.'/'.ltrim($relative, '/');

        $this->filesystem->write($path, rtrim($contents, "\n")."\n");

        if ($executable) {
            $this->filesystem->makeExecutable($path);
        }

        return $relative;
    }

    private function remove(string $path): void
    {
        if ($this->filesystem->exists($path)) {
            $this->filesystem->delete($path);
        }
    }
}

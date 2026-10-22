<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Workspace\Component;
use Shardevcom\Cli\Workspace\ComponentType;
use Shardevcom\Cli\Workspace\DockerScaffolder;
use Shardevcom\Cli\Workspace\WorkspaceManifest;
use Tests\Unit\Cli\Support\FakeFilesystem;

/**
 * Acceptance for the Docker assets generated from the workspace manifest.
 *
 * The compose file must reflect exactly the components and guards present, and
 * the Vite ports must come from the generated app configs rather than a
 * duplicated hardcoded list.
 */
final class DockerScaffolderTest extends TestCase
{
    private const ROOT = '/ws';

    public function test_writes_the_api_and_web_services_with_the_manifest_guards(): void
    {
        $filesystem = new FakeFilesystem([
            self::ROOT.'/web/apps/user/vite.config.ts' => 'createAppConfig(import.meta.dirname, 5175)',
            self::ROOT.'/web/apps/admin/vite.config.ts' => 'createAppConfig(import.meta.dirname, 6001)',
        ]);

        $written = (new DockerScaffolder($filesystem))->write(self::ROOT, $this->manifest());

        $this->assertContains('docker-compose.yml', $written);
        $this->assertContains('DOCKER.md', $written);
        $this->assertContains('api/Dockerfile', $written);
        $this->assertContains('web/Dockerfile', $written);
        $this->assertTrue($filesystem->isExecutable(self::ROOT.'/api/docker-entrypoint.sh'));
        $this->assertTrue($filesystem->isExecutable(self::ROOT.'/web/docker-entrypoint.sh'));

        $compose = $filesystem->read(self::ROOT.'/docker-compose.yml');

        $this->assertStringContainsString('  api:', $compose);
        $this->assertStringContainsString('context: ./api', $compose);
        $this->assertStringContainsString('APP_URL: http://localhost:${API_PORT:-8000}', $compose);
        $this->assertStringContainsString('- "${API_PORT:-8000}:8000"', $compose);
        $this->assertStringContainsString('api-vendor:/var/www/html/vendor', $compose);
        $this->assertStringContainsString('  web:', $compose);
        $this->assertStringContainsString('context: ./web', $compose);
        $this->assertStringContainsString('PLATFORM_BACKEND: http://api:8000', $compose);
        $this->assertStringContainsString('WEB_APPS: "user admin employee"', $compose);
        $this->assertStringContainsString('- "${WEB_USER_PORT:-5175}:5175"', $compose);
        $this->assertStringContainsString('- "${WEB_ADMIN_PORT:-6001}:6001"', $compose);
        $this->assertStringContainsString('- "${WEB_EMPLOYEE_PORT:-5174}:5174"', $compose);
        $this->assertStringContainsString('web-node_modules:/app/node_modules', $compose);
        $this->assertStringContainsString('condition: service_healthy', $compose);

        $apiDockerfile = $filesystem->read(self::ROOT.'/api/Dockerfile');
        $this->assertStringContainsString('FROM php:8.4-cli', $apiDockerfile);
        $this->assertStringContainsString('COPY --from=composer:2', $apiDockerfile);
        $this->assertStringContainsString('docker-php-ext-install bcmath gd intl zip', $apiDockerfile);
        $this->assertStringContainsString('EXPOSE 8000', $apiDockerfile);

        $apiEntrypoint = $filesystem->read(self::ROOT.'/api/docker-entrypoint.sh');
        $this->assertStringContainsString('composer install --no-interaction --prefer-dist --no-progress --no-scripts', $apiEntrypoint);
        $this->assertStringContainsString('php artisan package:discover --ansi', $apiEntrypoint);
        $this->assertStringContainsString('exec "$@"', $apiEntrypoint);

        $webDockerfile = $filesystem->read(self::ROOT.'/web/Dockerfile');
        $this->assertStringContainsString('FROM node:22-bookworm-slim', $webDockerfile);
        $this->assertStringContainsString('EXPOSE 5175 6001 5174', $webDockerfile);

        $entrypoint = $filesystem->read(self::ROOT.'/web/docker-entrypoint.sh');
        $this->assertStringContainsString('apps/$app/vite.config.ts', $entrypoint);
        $this->assertStringContainsString('--host 0.0.0.0', $entrypoint);
        $this->assertStringContainsString('exec "$@"', $entrypoint);

        $readme = $filesystem->read(self::ROOT.'/DOCKER.md');
        $this->assertStringContainsString('docker compose up --build', $readme);
        $this->assertStringContainsString('http://localhost:6001', $readme);
    }

    public function test_api_only_workspaces_get_no_web_assets_or_service(): void
    {
        $filesystem = new FakeFilesystem();

        $manifest = WorkspaceManifest::make('demo', '1.0', [
            new Component('api', ComponentType::Api, 'api', ['web' => true]),
        ]);

        $written = (new DockerScaffolder($filesystem))->write(self::ROOT, $manifest);

        $this->assertContains('docker-compose.yml', $written);
        $this->assertNotContains('web/Dockerfile', $written);
        $this->assertFalse($filesystem->isFile(self::ROOT.'/web/Dockerfile'));
        $this->assertStringNotContainsString('  web:', $filesystem->read(self::ROOT.'/docker-compose.yml'));
        $this->assertStringNotContainsString('clientes web', $filesystem->read(self::ROOT.'/DOCKER.md'));
    }

    public function test_without_an_api_it_removes_the_stale_compose_and_docs(): void
    {
        $filesystem = new FakeFilesystem([
            self::ROOT.'/docker-compose.yml' => 'services: {}',
            self::ROOT.'/DOCKER.md' => 'stale',
        ]);

        $manifest = WorkspaceManifest::make('demo', '1.0', [
            new Component('web', ComponentType::Web, 'web', ['web' => true]),
        ]);

        $written = (new DockerScaffolder($filesystem))->write(self::ROOT, $manifest);

        $this->assertNotContains('docker-compose.yml', $written);
        $this->assertFalse($filesystem->isFile(self::ROOT.'/docker-compose.yml'));
        $this->assertFalse($filesystem->isFile(self::ROOT.'/DOCKER.md'));
        $this->assertTrue($filesystem->isFile(self::ROOT.'/web/Dockerfile'));
    }

    public function test_rewriting_after_removing_web_drops_its_service(): void
    {
        $filesystem = new FakeFilesystem();
        $scaffolder = new DockerScaffolder($filesystem);

        $scaffolder->write(self::ROOT, $this->manifest());

        $withoutWeb = WorkspaceManifest::make('demo', '1.0', [
            new Component('api', ComponentType::Api, 'api', ['web' => true, 'admin' => false]),
        ]);

        $scaffolder->write(self::ROOT, $withoutWeb);

        $compose = $filesystem->read(self::ROOT.'/docker-compose.yml');
        $this->assertStringContainsString('  api:', $compose);
        $this->assertStringNotContainsString('  web:', $compose);
        $this->assertStringNotContainsString('web-node_modules', $compose);
    }

    private function manifest(): WorkspaceManifest
    {
        return WorkspaceManifest::make('demo', '1.0', [
            new Component('api', ComponentType::Api, 'api', [
                'web' => true,
                'admin' => false,
                'employee' => false,
            ]),
            new Component('web', ComponentType::Web, 'web', [
                'web' => true,
                'admin' => false,
                'employee' => false,
            ]),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Cli;

use PHPUnit\Framework\TestCase;
use Shardevcom\Cli\Application;
use Shardevcom\Cli\Context;
use Shardevcom\Cli\Packages\PackageCatalog;
use Tests\Unit\Cli\Support\FakeFilesystem;
use Tests\Unit\Cli\Support\FakeProcessRunner;
use Tests\Unit\Cli\Support\RecordingPrinter;

/**
 * Acceptance for `platform guard add|list`: a new guard touches every artefact
 * that must know about it (API config, model, migrations, platform.json and one
 * app/flavor per client) and nothing is derived from a hardcoded guard list.
 */
final class GuardCommandTest extends TestCase
{
    private const ROOT = '/ws';

    public function test_add_registers_the_platform_and_generates_the_api_artefacts(): void
    {
        $filesystem = $this->workspaceFiles();

        $exit = $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $this->assertSame(0, $exit);

        $config = $filesystem->read(self::ROOT.'/api/config/platforms.php');
        $this->assertStringContainsString("'partner' => [", $config);
        $this->assertStringContainsString('\App\Models\PartnerUser::class', $config);

        $this->assertTrue($filesystem->isFile(self::ROOT.'/api/app/Models/PartnerUser.php'));
        $this->assertStringContainsString(
            "protected static string \$platformGuard = 'partner';",
            $filesystem->read(self::ROOT.'/api/app/Models/PartnerUser.php')
        );

        $this->assertNotNull($this->migrationFor($filesystem, 'create_partners_table'));
        $this->assertNotNull($this->migrationFor($filesystem, 'create_partners_password_resets_table'));
    }

    public function test_add_scaffolds_one_web_app_per_guard(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $this->assertTrue($filesystem->isFile(self::ROOT.'/web/apps/partner/src/main.tsx'));
        $this->assertStringContainsString('setPlatform("partner")', $filesystem->read(self::ROOT.'/web/apps/partner/src/main.tsx'));
        $this->assertStringContainsString('guard="partner"', $filesystem->read(self::ROOT.'/web/apps/partner/src/App.tsx'));
        $this->assertStringContainsString('public={false}', $filesystem->read(self::ROOT.'/web/apps/partner/src/App.tsx'));
        $this->assertStringContainsString('5176', $filesystem->read(self::ROOT.'/web/apps/partner/vite.config.ts'));
        $this->assertStringContainsString('Shardev · Partner', $filesystem->read(self::ROOT.'/web/apps/partner/index.html'));

        $this->assertStringContainsString(
            '| "partner"',
            $filesystem->read(self::ROOT.'/web/packages/plugin-kit/src/types.ts')
        );

        $scripts = $filesystem->readJson(self::ROOT.'/web/package.json')['scripts'];
        $this->assertArrayHasKey('dev:partner', $scripts);
        $this->assertArrayHasKey('build:partner', $scripts);
        $this->assertStringContainsString('build:partner', $scripts['build']);
    }

    public function test_add_scaffolds_a_public_web_app_when_the_guard_is_public(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--public', '--no-migrate'], $filesystem);

        $this->assertStringContainsString(
            'public',
            $filesystem->read(self::ROOT.'/web/apps/partner/src/App.tsx')
        );
        $this->assertStringNotContainsString(
            'public={false}',
            $filesystem->read(self::ROOT.'/web/apps/partner/src/App.tsx')
        );
    }

    public function test_add_scaffolds_one_desktop_app_per_guard(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $main = self::ROOT.'/desktop/apps/partner/src/main/java/dev/shardev/apps/partner/Main.java';
        $this->assertTrue($filesystem->isFile($main));
        $this->assertStringContainsString('package dev.shardev.apps.partner;', $filesystem->read($main));
        $this->assertStringContainsString('Guard.PARTNER', $filesystem->read($main));
        $this->assertStringContainsString(
            'dev.shardev.apps.partner',
            $filesystem->read(self::ROOT.'/desktop/apps/partner/build.gradle.kts')
        );

        $this->assertStringContainsString(
            'PARTNER("partner", "partner/")',
            $filesystem->read(self::ROOT.'/desktop/packages/plugin-kit/src/main/java/dev/shardev/pluginkit/Guard.java')
        );
        $this->assertStringContainsString(
            '":apps:partner",',
            $filesystem->read(self::ROOT.'/desktop/settings.gradle.kts')
        );
    }

    public function test_add_extends_the_mobile_guard_enum(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $guard = $filesystem->read(self::ROOT.'/mobile/packages/plugin_kit/lib/src/guard.dart');
        $this->assertStringContainsString("partner('partner', 'partner/');", $guard);
        $this->assertStringContainsString('case Guard.partner:', $guard);
    }

    public function test_add_mirrors_the_guard_into_every_component(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        foreach (['api', 'web', 'mobile', 'desktop'] as $component) {
            $this->assertArrayHasKey(
                'partner',
                $filesystem->readJson(self::ROOT.'/platform.json')['components'][$component]['guards']
            );
        }
    }

    public function test_add_defaults_to_a_private_guard(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $manifest = $filesystem->readJson(self::ROOT.'/platform.json');
        $this->assertSame(['public' => false], $manifest['components']['web']['guards']['partner']);
        $this->assertStringContainsString("'public' => false,", $filesystem->read(self::ROOT.'/api/config/platforms.php'));
    }

    public function test_add_public_flag_marks_the_guard_public(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--public', '--no-migrate'], $filesystem);

        $manifest = $filesystem->readJson(self::ROOT.'/platform.json');
        $this->assertSame(['public' => true], $manifest['components']['web']['guards']['partner']);
        $this->assertStringContainsString("'public' => true,", $filesystem->read(self::ROOT.'/api/config/platforms.php'));
    }

    public function test_web_is_public_by_default(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'web', '--no-migrate'], $filesystem);

        $manifest = $filesystem->readJson(self::ROOT.'/platform.json');
        $this->assertSame(['public' => true], $manifest['components']['web']['guards']['web']);
    }

    public function test_no_clients_limits_the_change_to_the_api_and_manifest(): void
    {
        $filesystem = $this->workspaceFiles();

        $exit = $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate', '--no-clients'], $filesystem);

        $this->assertSame(0, $exit);
        $this->assertFalse($filesystem->isFile(self::ROOT.'/web/apps/partner/src/main.tsx'));
        $this->assertArrayHasKey('partner', $filesystem->readJson(self::ROOT.'/platform.json')['components']['web']['guards']);
    }

    public function test_add_runs_the_migration_in_the_api(): void
    {
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'guard', 'add', 'partner'], $this->workspaceFiles(), $process);

        $this->assertTrue($process->ranIn('php artisan migrate --force', self::ROOT.'/api'));
    }

    public function test_add_seeds_a_demo_user_for_the_new_guard(): void
    {
        $filesystem = $this->workspaceFiles();
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'guard', 'add', 'partner'], $filesystem, $process);

        $seeder = self::ROOT.'/api/database/seeders/PartnerUserSeeder.php';
        $this->assertTrue($filesystem->isFile($seeder));
        $this->assertStringContainsString('class PartnerUserSeeder extends Seeder', $filesystem->read($seeder));
        $this->assertStringContainsString("'partner@example.com'", $filesystem->read($seeder));
        $this->assertTrue(
            $process->ranIn("php artisan db:seed --class='Database\\Seeders\\PartnerUserSeeder' --force", self::ROOT.'/api')
        );
    }

    public function test_no_seed_only_scaffolds_the_seeder_and_skips_running_it(): void
    {
        $filesystem = $this->workspaceFiles();
        $process = new FakeProcessRunner();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-seed'], $filesystem, $process);

        $this->assertTrue($filesystem->isFile(self::ROOT.'/api/database/seeders/PartnerUserSeeder.php'));
        $this->assertFalse($process->ran('db:seed'));
    }

    public function test_add_registers_the_guard_in_every_plugin_manifest(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $plugin = $filesystem->readJson(self::ROOT.'/api/packages/shardev/product/plugin.json');
        $this->assertArrayHasKey('partner', $plugin['guards']);
        $this->assertSame('read', $plugin['guards']['partner']['mode']);
        $this->assertSame('products', $plugin['guards']['partner']['path']);
        $this->assertSame('full', $plugin['guards']['admin']['mode']);
    }

    public function test_no_plugins_leaves_the_manifests_untouched(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate', '--no-plugins'], $filesystem);

        $plugin = $filesystem->readJson(self::ROOT.'/api/packages/shardev/product/plugin.json');
        $this->assertArrayNotHasKey('partner', $plugin['guards']);
    }

    public function test_add_refreshes_the_docker_web_apps(): void
    {
        $filesystem = $this->workspaceFiles();

        $this->execute(['platform', 'guard', 'add', 'partner', '--no-migrate'], $filesystem);

        $compose = $filesystem->read(self::ROOT.'/docker-compose.yml');
        $this->assertStringContainsString('WEB_APPS: "admin partner"', $compose);
        $this->assertStringContainsString('- "${WEB_PARTNER_PORT:-5176}:5176"', $compose);
    }

    public function test_add_refuses_a_guard_the_workspace_already_has(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'guard', 'add', 'admin', '--no-migrate'], $this->workspaceFiles(), null, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('already part', $recorder->stderr());
    }

    public function test_add_rejects_an_invalid_slug(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'guard', 'add', 'Partner', '--no-migrate'], $this->workspaceFiles(), null, $recorder);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid guard', $recorder->stderr());
    }

    public function test_list_reports_the_guards_and_who_serves_them(): void
    {
        $recorder = RecordingPrinter::create();

        $exit = $this->execute(['platform', 'guard', 'list'], $this->workspaceFiles(), null, $recorder);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('admin', $recorder->stdout());
        $this->assertStringContainsString('api, web', $recorder->stdout());
    }

    private function migrationFor(FakeFilesystem $filesystem, string $suffix): ?string
    {
        foreach ($filesystem->files(self::ROOT.'/api/database/migrations') as $file) {
            if (str_ends_with($file, $suffix.'.php')) {
                return $file;
            }
        }

        return null;
    }

    private function workspaceFiles(): FakeFilesystem
    {
        return new FakeFilesystem([
            self::ROOT.'/platform.json' => json_encode([
                'name' => 'demo',
                'platform' => '0.1.0',
                'components' => [
                    'api' => ['type' => 'api', 'path' => 'api', 'guards' => ['admin'], 'enabled' => true],
                    'web' => ['type' => 'web', 'path' => 'web', 'guards' => ['admin'], 'enabled' => true],
                    'mobile' => ['type' => 'mobile', 'path' => 'mobile', 'guards' => ['admin'], 'enabled' => true],
                    'desktop' => ['type' => 'desktop', 'path' => 'desktop', 'guards' => ['admin'], 'enabled' => true],
                ],
            ]),
            self::ROOT.'/api/composer.json' => '{"require":{"laravel/framework":"^12.0"}}',
            self::ROOT.'/api/packages/shardev/product/composer.json' => '{"name":"shardev/product"}',
            self::ROOT.'/api/packages/shardev/product/plugin.json' => <<<'JSON'
                {
                  "name": "product",
                  "title": "Products",
                  "resource": "products",
                  "actions": ["view", "list"],
                  "guards": {
                    "admin": { "mode": "full", "path": "products" }
                  }
                }
                JSON,
            self::ROOT.'/api/config/platforms.php' => <<<'PHP'
                <?php

                return [
                    'platforms' => [
                        'admin' => [
                            'key' => 'admin',
                            'model' => \App\Models\AdminUser::class,
                        ],
                    ],
                    'default' => 'admin',
                ];
                PHP,
            self::ROOT.'/web/apps/admin/vite.config.ts' => "import { createAppConfig } from \"../../vite.shared.ts\"\n\nexport default createAppConfig(import.meta.dirname, 5175)\n",
            self::ROOT.'/web/apps/admin/index.html' => "<title>Shardev · Admin</title>\n",
            self::ROOT.'/web/apps/admin/src/main.tsx' => "setPlatform(\"admin\")\n",
            self::ROOT.'/web/apps/admin/src/App.tsx' => "<PlatformApp guard=\"admin\" appName=\"Shardev Admin\" plugins={[productPlugin]} />\n",
            self::ROOT.'/web/packages/plugin-kit/src/types.ts' => "export type Guard = \"web\" | \"admin\" | \"employee\"\n",
            self::ROOT.'/web/package.json' => <<<'JSON'
                {
                  "name": "shardev-web",
                  "scripts": {
                    "dev:admin": "vite --config apps/admin/vite.config.ts",
                    "typecheck": "tsc --noEmit",
                    "build": "npm run typecheck && npm run build:admin",
                    "build:admin": "vite build --config apps/admin/vite.config.ts"
                  }
                }
                JSON,
            self::ROOT.'/desktop/apps/admin/build.gradle.kts' => "application {\n    mainClass = \"dev.shardev.apps.admin.Main\"\n}\n",
            self::ROOT.'/desktop/apps/admin/src/main/java/dev/shardev/apps/admin/Main.java' => <<<'JAVA'
                package dev.shardev.apps.admin;

                import dev.shardev.pluginkit.Guard;

                /** Admin desktop app: the {@code admin} guard, full product CRUD. */
                public final class Main {
                    public static void main(String[] args) {
                        Application.launch(AdminApp.class, args);
                    }

                    public static final class AdminApp extends Application {
                        @Override
                        public void start(Stage stage) {
                            new PlatformApp(Guard.ADMIN, List.of(ProductPlugin.productPlugin())).start(stage);
                        }
                    }
                }
                JAVA,
            self::ROOT.'/desktop/packages/plugin-kit/src/main/java/dev/shardev/pluginkit/Guard.java' => <<<'JAVA'
                package dev.shardev.pluginkit;

                public enum Guard {
                    WEB("web", ""),
                    ADMIN("admin", "admin/"),
                    EMPLOYEE("employee", "employee/");

                    public String label() {
                        return switch (this) {
                            case WEB -> "Web";
                            case ADMIN -> "Admin";
                            case EMPLOYEE -> "Employee";
                        };
                    }
                }
                JAVA,
            self::ROOT.'/desktop/settings.gradle.kts' => "include(\n    \":apps:admin\",\n)\n",
            self::ROOT.'/mobile/packages/plugin_kit/lib/src/guard.dart' => <<<'DART'
                enum Guard {
                  web('web', ''),
                  admin('admin', 'admin/'),
                  employee('employee', 'employee/');

                  const Guard(this.id, this.prefix);

                  String get label {
                    switch (this) {
                      case Guard.web:
                        return 'Web';
                      case Guard.admin:
                        return 'Admin';
                      case Guard.employee:
                        return 'Employee';
                    }
                  }
                }
                DART,
        ]);
    }

    /**
     * @param list<string> $argv
     */
    private function execute(
        array $argv,
        FakeFilesystem $filesystem,
        ?FakeProcessRunner $process = null,
        ?RecordingPrinter $recorder = null,
    ): int {
        $recorder ??= RecordingPrinter::create();

        $context = new Context(
            output: $recorder->printer(),
            filesystem: $filesystem,
            process: $process ?? new FakeProcessRunner(),
            workingDirectory: self::ROOT,
            frameworkRoot: '/framework',
            catalog: new PackageCatalog($filesystem, '/framework'),
        );

        return (new Application($context))->run($argv);
    }
}

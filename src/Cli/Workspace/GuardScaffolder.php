<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Context;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Plugins\PluginCatalog;
use Shardevcom\Cli\Plugins\PluginGuardEditor;

/**
 * Boundary: adds a guard to a workspace end-to-end.
 *
 * The API is the source of truth, so the guard is registered there first
 * (config/platforms.php + model + migrations + a demo seeder), then mirrored
 * into platform.json, every installed plugin manifest and, optionally, into
 * every client's Guard enum and app tree.
 */
final class GuardScaffolder
{
    public function __construct(private readonly Context $context) {}

    /**
     * @return list<string> paths written, relative to the workspace root
     */
    public function add(
        Workspace $workspace,
        GuardDefinition $guard,
        bool $scaffoldClients,
        bool $migrate,
        bool $seed = true,
        bool $registerPlugins = true,
    ): array {
        $manifest = $workspace->manifest;

        if (! $manifest->has('api')) {
            throw CommandFailed::create("The workspace has no 'api' component to register the '{$guard->key}' guard in.");
        }

        if (in_array($guard->key, $manifest->guards(), true)) {
            throw CommandFailed::create("Guard '{$guard->key}' is already part of the workspace.");
        }

        $api = $workspace->componentPath('api');
        $written = [];

        $written[] = $this->context->filesystem->relativePath(
            $this->registerPlatform($api, $guard),
            $workspace->root
        );

        foreach ($this->writeApiArtifacts($api, $guard) as $file) {
            $written[] = $this->context->filesystem->relativePath($file, $workspace->root);
        }

        if ($registerPlugins) {
            foreach ($this->registerInPlugins($guard) as $file) {
                $written[] = $this->context->filesystem->relativePath($file, $workspace->root);
            }
        }

        $manifest = $this->mirrorIntoManifest($manifest, $guard);
        $manifest->save($this->context->filesystem, $workspace->root);

        if ($scaffoldClients) {
            $plugins = $this->pluginResources();
            $scaffolder = new GuardClientScaffolder($this->context->filesystem);
            $pluginScaffolder = new GuardPluginScaffolder($this->context->filesystem);

            foreach ($manifest->components() as $component) {
                if ($component->type === ComponentType::Api || ! $component->enabled) {
                    continue;
                }

                $path = $workspace->componentPath($component->name);

                foreach ($scaffolder->scaffold($component->type, $path, $guard) as $file) {
                    $written[] = $component->path.'/'.$file;
                }

                foreach ($pluginScaffolder->scaffold($component->type, $path, $guard, $plugins) as $file) {
                    $written[] = $component->path.'/'.$file;
                }
            }
        }

        (new DockerScaffolder($this->context->filesystem))->write($workspace->root, $manifest);

        if ($migrate) {
            $this->migrate($api);

            if ($seed) {
                $this->seed($api, $guard);
            }
        }

        return $written;
    }

    private function registerPlatform(string $api, GuardDefinition $guard): string
    {
        $file = $api.'/config/platforms.php';

        if (! $this->context->filesystem->isFile($file)) {
            throw CommandFailed::create("config/platforms.php was not found at {$file}.");
        }

        $editor = new PlatformsEditor();
        $source = $this->context->filesystem->read($file);

        if ($editor->has($source, $guard->key)) {
            throw CommandFailed::create("Platform '{$guard->key}' already exists in config/platforms.php.");
        }

        $this->context->filesystem->write($file, $editor->add($source, $guard));

        return $file;
    }

    /**
     * @return list<string>
     */
    private function writeApiArtifacts(string $api, GuardDefinition $guard): array
    {
        $files = [];

        $model = $api.'/app/Models/'.$guard->model.'.php';
        $this->context->filesystem->write($model, $this->model($guard));
        $files[] = $model;

        $stamp = date('Y_m_d_His');
        $table = $api.'/database/migrations/'.$stamp.'_create_'.$guard->table.'_table.php';
        $this->context->filesystem->write($table, $this->tableMigration($guard));
        $files[] = $table;

        $resets = $api.'/database/migrations/'.date('Y_m_d_His', time() + 1)
            .'_create_'.$guard->passwordResetTable.'_table.php';
        $this->context->filesystem->write($resets, $this->passwordResetsMigration($guard));
        $files[] = $resets;

        $seeder = $api.'/database/seeders/'.$guard->model.'Seeder.php';
        $this->context->filesystem->write($seeder, $this->seeder($guard));
        $files[] = $seeder;

        return $files;
    }

    /**
     * Registers the guard in every installed plugin's manifest so `plugin list`
     * and `plugin show` know the resource now serves the new guard.
     *
     * @return list<string>
     */
    private function registerInPlugins(GuardDefinition $guard): array
    {
        $catalog = $this->pluginCatalog();

        if ($catalog === null) {
            return [];
        }

        $editor = new PluginGuardEditor();
        $written = [];

        foreach ($catalog->discover() as $plugin) {
            $source = $this->context->filesystem->read($plugin['file']);

            if ($editor->has($source, $guard->key)) {
                continue;
            }

            $this->context->filesystem->write($plugin['file'], $editor->add($source, $guard->key));
            $written[] = $plugin['file'];
        }

        return $written;
    }

    /**
     * API plugin name => resource, used to scaffold the client-side views.
     *
     * @return array<string, string>
     */
    private function pluginResources(): array
    {
        $catalog = $this->pluginCatalog();

        if ($catalog === null) {
            return [];
        }

        $resources = [];

        foreach ($catalog->all() as $plugin) {
            $resources[$plugin->name] = $plugin->resource;
        }

        return $resources;
    }

    private function pluginCatalog(): ?PluginCatalog
    {
        if (! $this->context->isInstalledProject()) {
            return null;
        }

        return new PluginCatalog($this->context->filesystem, $this->context->packageCatalog());
    }

    private function mirrorIntoManifest(WorkspaceManifest $manifest, GuardDefinition $guard): WorkspaceManifest
    {
        foreach ($manifest->components() as $component) {
            if ($component->serves($guard->key)) {
                continue;
            }

            $guards = $component->guards;
            $guards[$guard->key] = $guard->public;

            $manifest = $manifest->with($component->withGuards($guards));
        }

        return $manifest;
    }

    private function migrate(string $api): void
    {
        $result = $this->context->process->run('php artisan migrate --force', $api);

        if (! $result->successful()) {
            throw CommandFailed::create("The migration for the new guard failed:\n".$result->trimmedOutput());
        }
    }

    private function seed(string $api, GuardDefinition $guard): void
    {
        $class = 'Database\\Seeders\\'.$guard->model.'Seeder';

        $result = $this->context->process->run("php artisan db:seed --class='{$class}' --force", $api);

        if (! $result->successful()) {
            throw CommandFailed::create("Seeding the demo '{$guard->key}' user failed:\n".$result->trimmedOutput());
        }
    }

    private function model(GuardDefinition $guard): string
    {
        $model = $guard->model;
        $key = $guard->key;
        $table = $guard->table;

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\Models;

            use Illuminate\Notifications\Notifiable;
            use Shardevcom\Core\Models\PlatformUser;

            class {$model} extends PlatformUser
            {
                use Notifiable;

                /**
                 * The guard that this model uses.
                 */
                protected static string \$platformGuard = '{$key}';

                /**
                 * The table associated with the model.
                 */
                protected \$table = '{$table}';

                /**
                 * The attributes that are mass assignable.
                 *
                 * @var list<string>
                 */
                protected \$fillable = [
                    'name',
                    'email',
                    'password',
                ];

                /**
                 * The attributes that should be hidden for serialization.
                 *
                 * @var list<string>
                 */
                protected \$hidden = [
                    'password',
                    'remember_token',
                ];

                /**
                 * Get the attributes that should be cast.
                 *
                 * @return array<string, string>
                 */
                protected function casts(): array
                {
                    return [
                        'email_verified_at' => 'datetime',
                        'password' => 'hashed',
                    ];
                }
            }

            PHP;
    }

    private function tableMigration(GuardDefinition $guard): string
    {
        $table = $guard->table;

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('{$table}', function (Blueprint \$table) {
                        \$table->id();
                        \$table->string('name');
                        \$table->string('email')->unique();
                        \$table->timestamp('email_verified_at')->nullable();
                        \$table->string('password');
                        \$table->rememberToken();
                        \$table->timestamps();
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('{$table}');
                }
            };

            PHP;
    }

    private function passwordResetsMigration(GuardDefinition $guard): string
    {
        $table = $guard->passwordResetTable;

        return <<<PHP
            <?php

            declare(strict_types=1);

            use Illuminate\Database\Migrations\Migration;
            use Illuminate\Database\Schema\Blueprint;
            use Illuminate\Support\Facades\Schema;

            return new class extends Migration
            {
                public function up(): void
                {
                    Schema::create('{$table}', function (Blueprint \$table) {
                        \$table->string('email')->index();
                        \$table->string('token')->index();
                        \$table->timestamp('created_at')->nullable();
                    });
                }

                public function down(): void
                {
                    Schema::dropIfExists('{$table}');
                }
            };

            PHP;
    }

    private function seeder(GuardDefinition $guard): string
    {
        $model = $guard->model;
        $key = $guard->key;
        $label = $guard->label();

        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace Database\Seeders;

            use App\Models\\{$model};
            use Illuminate\Database\Seeder;

            /**
             * Seeds a demo {$key} user so the new guard is usable right away
             * (password: `password`).
             */
            class {$model}Seeder extends Seeder
            {
                public function run(): void
                {
                    \$user = {$model}::firstOrNew(['email' => '{$key}@example.com']);
                    \$user->name = 'Demo {$label}';
                    \$user->email_verified_at = now();
                    \$user->password = 'password';
                    \$user->save();
                }
            }

            PHP;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Contracts\AuthorizationManager as ManagerContract;
use Shardevcom\Core\Contracts\QueryExecutor as QueryExecutorContract;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Lifecycle\ActionLifecycle;
use Shardevcom\Product\Repositories\ProductRepository;
use Shardevcom\Resources\QueryOptions;
use Shardevcom\Resources\QueryResult;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * T-061: the separation between layers is a property of the code, not a
 * convention someone has to remember. Every rule here exists because its
 * violation was a realistic failure mode:
 *
 * - Repository = authorized access (auth + query + lifecycle + persistence).
 * - Use Case = orchestration of one workflow.
 * - DTO = the single validation point.
 * - Adapter = transport translation only.
 *
 * The smell these rules catch is not "a big class". It is a rule that lives
 * in two places, or an access path that skips authorization.
 */
final class LayeringRulesTest extends TestCase
{
    /**
     * A use case that imports the model can bypass the repository, and with
     * it the authorization the lifecycle performs. A use case that imports
     * HTTP or the ORM stops being callable from a second transport.
     */
    public function test_layers_do_not_import_the_layers_around_them(): void
    {
        $layers = [
            'application' => [base_path('packages/shardev/product/src/Application'), [
                'Illuminate\\Database',
                'Illuminate\\Http',
                'Illuminate\\Routing',
                'Illuminate\\GraphQL',
                'Illuminate\\Validation',
                'Shardevcom\\Product\\Models',
            ]],
            'repositories' => [base_path('packages/shardev/product/src/Repositories'), [
                'Illuminate\\Http',
                'Illuminate\\Routing',
                'Illuminate\\GraphQL',
            ]],
            'models' => [base_path('packages/shardev/product/src/Models'), [
                'Illuminate\\Http',
                'Illuminate\\Routing',
            ]],
        ];

        // ImportProducts is transport-neutral: the adapter stores the upload
        // and hands it a disk+path, and the products.import gate is decided by
        // the repository lifecycle funnel, not by the use case.
        $skipFiles = [];

        foreach ($layers as $name => [$directory, $forbidden]) {
            $files = $this->phpFilesIn($directory);
            $this->assertNotEmpty($files, "{$directory} must exist and contain PHP");

            foreach ($files as $file) {
                $basename = basename($file);

                if (in_array($basename, $skipFiles, true)) {
                    continue;
                }

                foreach ($this->importsIn($file) as $import) {
                    foreach ($forbidden as $prefix) {
                        $this->assertStringStartsNotWith(
                            $prefix,
                            $import,
                            $basename.' must not import '.$prefix.' — that is another layer\'s job.',
                        );
                    }
                }
            }
        }
    }

    public function test_use_cases_never_touch_a_model_or_a_query_builder(): void
    {
        $files = $this->phpFilesIn(base_path('packages/shardev/product/src/Application'));

        foreach ($files as $file) {
            $source = $this->withoutComments((string) file_get_contents($file));

            foreach (['::query(', '->where(', '->whereRaw(', '->orWhere(', '->paginate(', '->with(', 'DB::'] as $needle) {
                $this->assertStringNotContainsString(
                    $needle,
                    $source,
                    basename($file).' must delegate data access to the repository.',
                );
            }
        }
    }

    /**
     * No layer is allowed to decide authorization on its own. There is exactly
     * one decision point (the lifecycle, driven by the repository), so a second
     * decision point can only disagree with it.
     */
    public function test_authorization_is_decided_in_exactly_one_layer(): void
    {
        $forbidden = ['Gate::', '->can(', 'canBe(', 'authorize('];

        $checked = [
            base_path('packages/shardev/product/src/Application'),
            base_path('packages/shardev/product/src/Models'),
        ];

        foreach ($checked as $directory) {
            foreach ($this->phpFilesIn($directory) as $file) {
                $source = $this->withoutComments((string) file_get_contents($file));

                foreach ($forbidden as $needle) {
                    $this->assertStringNotContainsString(
                        $needle,
                        $source,
                        basename($file).' must not authorize: the repository owns that decision.',
                    );
                }
            }
        }
    }

    /**
     * Generic hooks receive the action and must not know resource names.
     * Business logic in a hook would run on every resource and could not be
     * reused.
     */
    public function test_lifecycle_hooks_stay_resource_agnostic(): void
    {
        $hookSources = [
            base_path('packages/shardev/core/src/Lifecycle'),
        ];

        foreach ($hookSources as $dir) {
            foreach ($this->phpFilesIn($dir) as $file) {
                $source = $this->withoutComments((string) file_get_contents($file));

                $this->assertStringNotContainsString('product', $source);
            }
        }
    }

    /**
     * The repository must not validate input. Validation is the DTO's job.
     */
    public function test_the_repository_does_not_validate_input(): void
    {
        $files = $this->phpFilesIn(base_path('packages/shardev/product/src/Repositories'));

        foreach ($files as $file) {
            $source = $this->withoutComments((string) file_get_contents($file));

            $this->assertStringNotContainsString('Validator', $source);
        }
    }

    /**
     * ServiceProviders only wire dependencies. No business logic in boot().
     */
    public function test_service_providers_only_wire(): void
    {
        $files = $this->phpFilesIn(base_path('packages/shardev/product/src'));

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            if (Str::contains($source, 'class ') && Str::contains($source, 'extends ServiceProvider')) {
                $this->assertStringNotContainsString('if (', $source);
            }
        }
    }

    /**
     * The repository must not open transactions itself. Transaction boundary
     * is a use case concern.
     */
    public function test_repositories_do_not_open_transactions(): void
    {
        $files = $this->phpFilesIn(base_path('packages/shardev/product/src/Repositories'));

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);

            $this->assertStringNotContainsString('DB::transaction', $source);
            $this->assertStringNotContainsString('beginTransaction', $source);
            $this->assertStringNotContainsString('commit', $source);
            $this->assertStringNotContainsString('rollBack', $source);
        }
    }

    /**
     * Use cases call the repository directly, not through a service.
     */
    public function test_use_cases_call_the_repository_directly(): void
    {
        $useCases = $this->phpFilesIn(base_path('packages/shardev/product/src/Application/Commands'))
            + $this->phpFilesIn(base_path('packages/shardev/product/src/Application/Queries'));

        $this->assertNotEmpty($useCases);

        // ImportProducts is an orchestrator that dispatches jobs; it still
        // consumes the ProductRepository directly for the authorization gate.
        $skipFiles = [];

        foreach ($useCases as $file) {
            $basename = basename($file);

            if (in_array($basename, $skipFiles, true)) {
                continue;
            }

            $imports = $this->importsIn($file);

            $this->assertContains(
                'Shardevcom\\Product\\Repositories\\ProductRepository',
                $imports,
                $basename.' should consume the repository directly instead of forwarding through another layer.',
            );

            foreach ($imports as $import) {
                $this->assertDoesNotMatchRegularExpression(
                    '~(Service|Manager)$~',
                    $import,
                    $basename.' adds a pass-through service with no responsibility of its own.',
                );
            }
        }
    }

    /**
     * Core is the layer every package depends on, so it must not depend on any
     * feature package back. A contract that imports a word from a feature
     * package makes that package uninstallable, which is invisible until
     * someone tries.
     *
     * `resources` is deliberately not treated as a feature: it holds the
     * shared query/definition value objects (QueryOptions, ResourceDefinition)
     * that the Core ports speak by design, per T-020/T-051.
     */
    public function test_core_does_not_depend_on_feature_packages(): void
    {
        $prefixes = $this->namespacePrefixesExcept(['core', 'resources']);

        $this->assertNotEmpty($prefixes);

        foreach ($this->phpFilesIn(base_path('packages/shardev/core/src')) as $file) {
            foreach ($this->importsIn($file) as $import) {
                foreach ($prefixes as $prefix) {
                    $this->assertStringStartsNotWith(
                        $prefix,
                        $import,
                        basename($file).' must not import '.$import.' — core cannot depend on a package built on top of it.',
                    );
                }
            }
        }
    }

    /**
     * Use cases and repositories stay small. No God classes.
     */
    public function test_use_cases_and_repositories_stay_small(): void
    {
        $useCases = $this->phpFilesIn(base_path('packages/shardev/product/src/Application'));
        $repos = $this->phpFilesIn(base_path('packages/shardev/product/src/Repositories'));
        $models = $this->phpFilesIn(base_path('packages/shardev/product/src/Models'));

        foreach ([...$useCases, ...$repos, ...$models] as $file) {
            $lines = count(explode("\n", (string) file_get_contents($file)));

            $this->assertLessThanOrEqual(250, $lines, basename($file).' exceeds 250 lines — consider splitting.');
        }
    }

    public function test_guides_exist_for_key_directories(): void
    {
        $guides = [
            'packages/shardev/product/src/Application/README.md',
            'packages/shardev/product/src/Repositories/README.md',
            'packages/shardev/product/src/Models/README.md',
        ];

        foreach ($guides as $relative) {
            $file = base_path($relative);
            $this->assertFileExists($file);
        }
    }

    private function phpFilesIn(string $directory): array
{
    if (! is_dir($directory)) {
        return [];
    }

    return array_filter(
        array_merge(glob($directory.'/*.php'), glob($directory.'/**/*.php')),
        static fn (string $f): bool => is_file($f) && ! Str::endsWith($f, 'Test.php')
    );
    }

    /**
     * Namespace prefixes of every installed package outside the given ones.
     *
     * Read from composer.json rather than guessed from the directory name: a
     * package can live in `widgets/` and still own `Shardevcom\\Widgets\\`,
     * and a rule built on the lowercase directory would silently never match.
     *
     * @param list<string> $except package directories to skip
     * @return list<string>
     */
    private function namespacePrefixesExcept(array $except): array
    {
        $psr4 = json_decode(
            (string) file_get_contents(base_path('composer.json')),
            true,
        )['autoload']['psr-4'] ?? [];

        $prefixes = [];

        foreach (array_keys((array) $psr4) as $namespace) {
            if (! str_starts_with($namespace, 'Shardevcom\\') || $namespace === 'Shardevcom\\') {
                continue;
            }

            $segment = trim(substr($namespace, strlen('Shardevcom\\')), '\\');

            if ($segment !== '' && ! in_array(strtolower($segment), $except, true)) {
                $prefixes[] = $namespace;
            }
        }

        return $prefixes;
    }

    private function importsIn(string $file): array
    {
        $content = (string) file_get_contents($file);
        preg_match_all('/^use\s+([^;]+);/m', $content, $matches);

        return $matches[1] ?? [];
    }

    private function withoutComments(string $source): string
    {
        return preg_replace('~/\*.*?\*/~s', '', $source);
    }
}

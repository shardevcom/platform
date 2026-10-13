<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Config;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: reads and writes the `packages` block of config/platform.php.
 *
 * The file is PHP, so it is parsed with a targeted pattern instead of being
 * included: the CLI must never execute project code to answer "where do my
 * packages live", and only the two scalar keys it owns are ever touched.
 */
final class PlatformConfigFile
{
    private const BLOCK_PATTERN = "/'packages'\s*=>\s*\[(.*?)\]/s";

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $projectRoot,
    ) {}

    public function path(): string
    {
        return rtrim($this->projectRoot, '/').'/config/platform.php';
    }

    /**
     * Falls back to the framework defaults when the project has no config yet,
     * so commands work before `platform new` finished writing one.
     */
    public function load(): PlatformConfig
    {
        $file = $this->path();

        if (! $this->filesystem->isFile($file)) {
            return PlatformConfig::defaults();
        }

        $contents = $this->filesystem->read($file);

        if (preg_match(self::BLOCK_PATTERN, $contents, $matches) !== 1) {
            return PlatformConfig::defaults();
        }

        $block = $matches[1];

        return new PlatformConfig(
            $this->extract($block, 'dir') ?? PlatformConfig::DEFAULT_PACKAGES_DIR,
            $this->extract($block, 'vendor') ?? PlatformConfig::DEFAULT_VENDOR_NAMESPACE,
        );
    }

    public function save(PlatformConfig $config): void
    {
        $file = $this->path();
        $block = $config->toPhpBlock();

        if (! $this->filesystem->isFile($file)) {
            $this->filesystem->write($file, $this->template($block));

            return;
        }

        $contents = $this->filesystem->read($file);

        if (preg_match(self::BLOCK_PATTERN, $contents) === 1) {
            $this->filesystem->write($file, (string) preg_replace(
                self::BLOCK_PATTERN,
                str_replace('$', '\\$', $block),
                $contents,
                1
            ));

            return;
        }

        $this->filesystem->write($file, (string) preg_replace(
            '/\n\];\s*$/',
            "\n\n{$block}\n];\n",
            $contents,
            1
        ));
    }

    /**
     * Reads a single scalar out of the owned block.
     *
     * Both spellings are accepted: a literal (`'dir' => 'packages'`, what the
     * CLI writes) and the env-backed form a hand-written config uses
     * (`'dir' => env('PLATFORM_PACKAGES_DIR', 'packages')`). The second capture
     * group is the fallback the project itself declares, which is the honest
     * answer for the value in effect when the env var is unset.
     */
    private function extract(string $block, string $key): ?string
    {
        if (preg_match("/'{$key}'\\s*=>\\s*'([^']*)'/", $block, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match("/'{$key}'\\s*=>\\s*env\\(\\s*'[^']*'\\s*,\\s*'([^']*)'/", $block, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    private function template(string $block): string
    {
        return <<<PHP
        <?php

        declare(strict_types=1);

        return [
            'version' => env('PLATFORM_VERSION', '0.1.0-dev'),
            'api' => [
                'rest' => env('API_REST_ENABLED', true),
                'graphql' => env('API_GRAPHQL_ENABLED', true),
            ],
        {$block}
        ];

        PHP;
    }
}
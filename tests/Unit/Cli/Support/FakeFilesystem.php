<?php

declare(strict_types=1);

namespace Tests\Unit\Cli\Support;

use Shardevcom\Cli\Filesystem\Filesystem;
use Shardevcom\Cli\Process\ProcessResult;
use Shardevcom\Cli\Process\ProcessRunner;

/**
 * Test doubles for the CLI boundaries.
 *
 * They exist so commands can be asserted end-to-end (including `platform new`)
 * without a real filesystem, a real shell or a real Laravel install.
 */
final class FakeFilesystem implements Filesystem
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var list<string> */
    private array $directories = [];

    /** @var list<string> */
    private array $unwritable = [];

    public function __construct(array $files = [], array $directories = [], array $unwritable = [])
    {
        $this->files = $files;
        $this->unwritable = array_map([$this, 'normalize'], $unwritable);

        foreach (array_keys($files) as $file) {
            $this->addParents($file);
        }

        $this->directories = [...$directories, ...$this->directories];
    }

    public function exists(string $path): bool
    {
        return $this->isFile($path) || $this->isDirectory($path);
    }

    public function isDirectory(string $path): bool
    {
        return in_array($this->normalize($path), $this->directories, true);
    }

    public function isFile(string $path): bool
    {
        return array_key_exists($this->normalize($path), $this->files);
    }

    public function directories(string $path): array
    {
        $prefix = $this->normalize($path).'/';
        $directories = [];

        foreach ($this->directories as $directory) {
            if (str_starts_with($directory, $prefix) && ! str_contains(substr($directory, strlen($prefix)), '/')) {
                $directories[] = $directory;
            }
        }

        sort($directories);

        return $directories;
    }

    public function files(string $path): array
    {
        $prefix = $this->normalize($path).'/';
        $files = [];

        foreach ($this->files as $filePath => $contents) {
            if (str_starts_with($filePath, $prefix) && ! str_contains(substr($filePath, strlen($prefix)), '/')) {
                $files[] = $filePath;
            }
        }

        sort($files);

        return $files;
    }

    public function isWritable(string $path): bool
    {
        $key = $this->normalize($path);

        return $this->isDirectory($key) && ! in_array($key, $this->unwritable, true);
    }

    public function read(string $path): string
    {
        $key = $this->normalize($path);

        if (! array_key_exists($key, $this->files)) {
            throw new \RuntimeException("Missing file: {$key}");
        }

        return $this->files[$key];
    }

    public function readJson(string $path): ?array
    {
        if (! $this->isFile($path)) {
            return null;
        }

        $decoded = json_decode($this->read($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function write(string $path, string $contents): void
    {
        $key = $this->normalize($path);
        $this->files[$key] = $contents;
        $this->addParents($key);
    }

    public function writeJson(string $path, array $data): void
    {
        $this->write($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }

    public function createDirectory(string $path): void
    {
        $key = $this->normalize($path);

        if (! in_array($key, $this->directories, true)) {
            $this->directories[] = $key;
        }
    }

    public function moveDirectory(string $from, string $to): void
    {
        $from = $this->normalize($from);
        $to = $this->normalize($to);
        $prefix = $from.'/';

        foreach ($this->files as $path => $contents) {
            if ($path === $from || str_starts_with($path, $prefix)) {
                $this->files[$to.substr($path, strlen($from))] = $contents;
                unset($this->files[$path]);
            }
        }

        foreach ($this->directories as $directory) {
            if ($directory === $from || str_starts_with($directory, $prefix)) {
                $this->directories[] = $to.substr($directory, strlen($from));
                $this->directories = array_values(array_filter(
                    $this->directories,
                    static fn (string $candidate): bool => $candidate !== $directory
                ));
            }
        }

        $this->directories[] = $to;
        $this->directories = array_values(array_unique($this->directories));
    }

    public function copyDirectory(string $from, string $to): void
    {
        $this->createDirectory($to);
        $prefix = $this->normalize($from).'/';

        foreach ($this->files as $path => $contents) {
            if (str_starts_with($path, $prefix)) {
                $this->files[$this->normalize($to).'/'.substr($path, strlen($prefix))] = $contents;
            }
        }
    }

    public function touch(string $path): void
    {
        $key = $this->normalize($path);

        if (! array_key_exists($key, $this->files)) {
            $this->files[$key] = '';
            $this->addParents($key);
        }
    }

    public function delete(string $path): void
    {
        $key = $this->normalize($path);
        unset($this->files[$key]);
        $this->directories = array_values(array_filter(
            $this->directories,
            static fn (string $directory): bool => $directory !== $key
        ));
    }

    public function relativePath(string $path, string $base): string
    {
        $path = rtrim($path, '/');
        $base = rtrim($base, '/');

        return str_starts_with($path, $base.'/') ? substr($path, strlen($base) + 1) : $path;
    }

    /**
     * @return list<string>
     */
    public function writtenFiles(): array
    {
        $paths = array_keys($this->files);
        sort($paths);

        return $paths;
    }

    public function written(string $path): bool
    {
        return $this->isFile($path);
    }

    private function addParents(string $path): void
    {
        $directory = dirname($path);

        while ($directory !== '' && $directory !== '.' && $directory !== '/') {
            $this->createDirectory($directory);
            $directory = dirname($directory);
        }
    }

    private function normalize(string $path): string
    {
        return rtrim($path, '/');
    }
}
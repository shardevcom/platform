<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

/**
 * One guard/platform the API exposes, as declared in config/platforms.php.
 *
 * `platform guard add` builds one of these from the command input (filling the
 * Laravel-ish defaults) and hands it to PlatformsEditor, the model and the
 * migration generators, and the client scaffolders — so every artefact for a
 * new guard is derived from a single description.
 */
final class GuardDefinition
{
    /**
     * @param list<string> $middleware
     */
    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $model,
        public readonly string $table,
        public readonly string $guardDriver,
        public readonly string $prefix,
        public readonly ?string $apiGuard,
        public readonly string $passwordResetTable,
        public readonly array $middleware,
    ) {}

    public static function create(
        string $key,
        ?string $name = null,
        ?string $model = null,
        ?string $table = null,
        ?string $driver = null,
        ?string $prefix = null,
    ): self {
        $driver ??= 'passport';
        $table ??= $key.'s';

        $middleware = $driver === 'session'
            ? ['correlation', 'rate.limit', 'web']
            : ['correlation', 'rate.limit', 'api'];

        return new self(
            key: $key,
            name: $name ?? ucfirst($key).' Users',
            model: $model ?? ucfirst($key).'User',
            table: $table,
            guardDriver: $driver,
            prefix: $prefix ?? $key,
            apiGuard: $driver === 'session' ? 'api' : null,
            passwordResetTable: $table.'_password_resets',
            middleware: $middleware,
        );
    }

    public function modelClass(): string
    {
        return '\\App\\Models\\'.$this->model;
    }

    /**
     * URL prefix as the API config expects it (no trailing slash, empty for web).
     */
    public function platformPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Prefix as the clients' Guard enum expects it (trailing slash, empty for web).
     */
    public function clientPrefix(): string
    {
        return $this->prefix === '' ? '' : rtrim($this->prefix, '/').'/';
    }

    public function label(): string
    {
        return ucfirst($this->key);
    }

    /**
     * The PHP array entry as it is written into config/platforms.php.
     */
    public function toPhpBlock(string $indent): string
    {
        $pad = $indent.'    ';
        $lines = [
            $indent."'{$this->key}' => [",
            $pad."'key' => '{$this->key}',",
            $pad."'name' => '{$this->name}',",
            $pad."'guard' => '{$this->key}',",
            $pad."'model' => {$this->modelClass()}::class,",
            $pad."'table' => '{$this->table}',",
            $pad."'provider' => '{$this->key}',",
            $pad."'password_reset' => '{$this->key}',",
            $pad."'guard_driver' => '{$this->guardDriver}',",
        ];

        if ($this->apiGuard !== null) {
            $lines[] = $pad."'api_guard' => '{$this->apiGuard}',";
        }

        $lines[] = $pad."'prefix' => '".$this->platformPrefix()."',";
        $lines[] = $pad.'\'middleware\' => ['.implode(', ', array_map(
            static fn (string $middleware): string => "'{$middleware}'",
            $this->middleware
        )).'],';
        $lines[] = $pad."'password_reset_table' => '{$this->passwordResetTable}',";
        $lines[] = $indent.'],';

        return implode("\n", $lines);
    }
}

<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Exception\InvalidInput;

/**
 * The kinds of component a workspace can hold.
 *
 * Each type owns the knowledge that would otherwise be scattered across the
 * CLI: which reference template it is scaffolded from, which guard it defaults
 * to, which directory proves its dependencies were installed, and which paths
 * must never be copied out of the template.
 */
enum ComponentType: string
{
    case Api = 'api';
    case Web = 'web';
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    /**
     * Directory under the framework's `demo/` that ships the reference client.
     * The API has no template: it is created with `composer create-project`.
     */
    public function template(): string
    {
        return 'demo/'.$this->value;
    }

    /**
     * Guards a component serves when nothing more specific is known.
     *
     * A workspace derives its real guard list from the API's platforms; this is
     * only the fallback for a client created without one.
     *
     * @return list<string>
     */
    public function defaultGuards(): array
    {
        return match ($this) {
            self::Api => [],
            self::Web, self::Mobile, self::Desktop => ['web'],
        };
    }

    /**
     * Directory that exists once dependencies are installed.
     */
    public function dependenciesMarker(): string
    {
        return match ($this) {
            self::Api => 'vendor',
            self::Web => 'node_modules',
            self::Mobile => '.dart_tool',
            self::Desktop => '.gradle',
        };
    }

    /**
     * @return list<string>
     */
    public function exclude(): array
    {
        return match ($this) {
            self::Api => [],
            self::Web => ['node_modules', 'dist', '.git', '.vite'],
            self::Mobile => ['build', '.dart_tool', '.git', '.idea', '.flutter-plugins', '.flutter-plugins-dependencies'],
            self::Desktop => ['build', '.gradle', '.git', '.idea', 'out'],
        };
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }

    public static function fromName(string $name): self
    {
        return self::tryFrom($name) ?? throw InvalidInput::create(
            "Unknown component '{$name}'. Expected one of: ".implode(', ', self::names()).'.'
        );
    }
}

<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: closes the loop opened by `plugin.json`.
 *
 * When a guard is added, the API plugin manifests declare it, but each client
 * plugin still has no screen for it, so the guard would render nothing. This
 * scaffolder drops a compiling placeholder view per client plugin (React,
 * JavaFX, Flutter) and registers it in the plugin's guard map, deriving the
 * component name, route and title from the API plugin's resource.
 *
 * The placeholder only uses the client's standard toolchain (no plugin-owned
 * components), so it builds before the author replaces it with a real screen.
 */
final class GuardPluginScaffolder
{
    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @param array<string, string> $plugins API plugin name => resource
     *
     * @return list<string> paths written, relative to the component
     */
    public function scaffold(ComponentType $type, string $path, GuardDefinition $guard, array $plugins): array
    {
        return match ($type) {
            ComponentType::Web => $this->web(rtrim($path, '/'), $guard, $plugins),
            ComponentType::Desktop => $this->desktop(rtrim($path, '/'), $guard, $plugins),
            ComponentType::Mobile => $this->mobile(rtrim($path, '/'), $guard, $plugins),
            ComponentType::Api => [],
        };
    }

    /**
     * @param array<string, string> $plugins
     *
     * @return list<string>
     */
    private function web(string $path, GuardDefinition $guard, array $plugins): array
    {
        $written = [];

        foreach ($plugins as $name => $resource) {
            $index = $path.'/plugins/'.$name.'/src/index.ts';

            if (! $this->filesystem->isFile($index)) {
                continue;
            }

            $source = $this->filesystem->read($index);

            if (str_contains($source, $guard->key.': {')) {
                continue;
            }

            $view = $this->pascal($name).$this->pascal($guard->key).'View';
            $relative = 'guards/'.$guard->key.'/'.$view;

            $this->filesystem->write($path.'/plugins/'.$name.'/src/'.$relative.'.tsx', $this->webView($view, $guard));

            $source = $this->insertAfterImport($source, './guards/', 'import { '.$view.' } from "./'.$relative.'"');
            $source = $this->insertAfterOpening($source, 'guards: {', $this->webGuardEntry($guard, $resource, $view));
            $this->filesystem->write($index, $source);

            $written[] = 'plugins/'.$name.'/src/'.$relative.'.tsx';
            $written[] = 'plugins/'.$name.'/src/index.ts';
        }

        return $written;
    }

    /**
     * @param array<string, string> $plugins
     *
     * @return list<string>
     */
    private function mobile(string $path, GuardDefinition $guard, array $plugins): array
    {
        $written = [];

        foreach ($plugins as $name => $resource) {
            $plugin = $path.'/plugins/'.$name.'/lib/src/'.$name.'_plugin.dart';

            if (! $this->filesystem->isFile($plugin)) {
                continue;
            }

            $source = $this->filesystem->read($plugin);

            if (str_contains($source, 'Guard.'.$guard->key.':')) {
                continue;
            }

            $view = $this->pascal($name).$this->pascal($guard->key).'View';

            $this->filesystem->write(
                $path.'/plugins/'.$name.'/lib/src/guards/'.$guard->key.'_view.dart',
                $this->mobileView($view, $guard)
            );

            $source = $this->insertAfterImport($source, "import 'guards/", "import 'guards/".$guard->key.'_view.dart\';');
            $source = $this->insertAfterOpening($source, 'guards: {', $this->mobileGuardEntry($guard, $resource, $view));
            $this->filesystem->write($plugin, $source);

            $written[] = 'plugins/'.$name.'/lib/src/guards/'.$guard->key.'_view.dart';
            $written[] = 'plugins/'.$name.'/lib/src/'.$name.'_plugin.dart';
        }

        return $written;
    }

    /**
     * @param array<string, string> $plugins
     *
     * @return list<string>
     */
    private function desktop(string $path, GuardDefinition $guard, array $plugins): array
    {
        $written = [];

        foreach ($plugins as $name => $resource) {
            $javaRoot = $path.'/plugins/'.$name.'/src/main/java';
            $package = $this->javaPackage($javaRoot);

            if ($package === null) {
                continue;
            }

            $plugin = $javaRoot.'/'.str_replace('.', '/', $package).'/'.$this->pascal($name).'Plugin.java';

            if (! $this->filesystem->isFile($plugin)) {
                continue;
            }

            $source = $this->filesystem->read($plugin);

            if (str_contains($source, 'Guard.'.strtoupper($guard->key))) {
                continue;
            }

            $view = $this->pascal($name).$this->pascal($guard->key).'View';
            $file = $javaRoot.'/'.str_replace('.', '/', $package).'/'.$view.'.java';

            $this->filesystem->write($file, $this->desktopView($package, $view, $guard));

            $source = $this->insertIntoMapOf($source, $guard, $resource, $view);
            $this->filesystem->write($plugin, $source);

            $written[] = 'plugins/'.$name.'/src/main/java/'.str_replace('.', '/', $package).'/'.$view.'.java';
            $written[] = 'plugins/'.$name.'/src/main/java/'.str_replace('.', '/', $package).'/'.$this->pascal($name).'Plugin.java';
        }

        return $written;
    }

    private function webView(string $view, GuardDefinition $guard): string
    {
        $label = $guard->label();
        $key = $guard->key;

        return <<<TSX
            "use client"

            /** `{$key}` guard view for the plugin. Generated placeholder: replace with the real screen. */
            export function {$view}() {
              return (
                <div className="space-y-6">
                  <h2 className="text-2xl font-bold">{$label}</h2>
                  <p className="text-muted-foreground">Vista pendiente para el guard {$key}.</p>
                </div>
              )
            }

            TSX;
    }

    private function webGuardEntry(GuardDefinition $guard, string $resource, string $view): string
    {
        $key = $guard->key;
        $label = $guard->label();

        return <<<TSX
                {$key}: {
                  path: "{$resource}",
                  component: {$view},
                  nav: { label: "{$label}", to: "/{$resource}", order: 10 },
                },

            TSX;
    }

    private function mobileView(string $view, GuardDefinition $guard): string
    {
        $key = $guard->key;
        $label = $guard->label();

        return <<<DART
            import 'package:flutter/material.dart';

            /// `{$key}` guard placeholder view: replace with the real screen.
            class {$view} extends StatelessWidget {
              const {$view}({super.key});

              @override
              Widget build(BuildContext context) => const Center(child: Text('{$label} · {$key}'));
            }

            DART;
    }

    private function mobileGuardEntry(GuardDefinition $guard, string $resource, string $view): string
    {
        $key = $guard->key;
        $label = $guard->label();

        return <<<DART
                  Guard.{$key}: GuardView(
                    path: '{$resource}',
                    builder: (_) => const {$view}(),
                    nav: const NavItem(label: '{$label}', to: '/{$resource}', order: 10),
                  ),

            DART;
    }

    private function desktopView(string $package, string $view, GuardDefinition $guard): string
    {
        $key = $guard->key;
        $label = $guard->label();

        return <<<JAVA
            package {$package};

            import dev.shardev.pluginkit.PluginContext;
            import javafx.geometry.Insets;
            import javafx.scene.control.Label;
            import javafx.scene.layout.BorderPane;

            /** `{$key}` guard placeholder view: replace with the real screen. */
            public final class {$view} extends BorderPane {
                public {$view}(PluginContext context) {
                    setPadding(new Insets(16));
                    setCenter(new Label("{$label} · {$key}"));
                }
            }

            JAVA;
    }

    /**
     * Appends a `GuardView` entry inside the plugin's `Map.of(...)` literal.
     */
    private function insertIntoMapOf(string $source, GuardDefinition $guard, string $resource, string $view): string
    {
        $open = strpos($source, 'Map.of(');

        if ($open === false) {
            return $source;
        }

        $close = $this->matchingParen($source, $open + strlen('Map.of(') - 1);

        if ($close === null) {
            return $source;
        }

        $entry = ', Guard.'.strtoupper($guard->key).', new GuardView("'.$resource.'", context -> new '
            .$view.'(context), new NavItem("'.$guard->label().'", "/'.$resource.'", 10))';

        return substr($source, 0, $close).$entry.substr($source, $close);
    }

    /**
     * Inserts a new import line right after the last import that matches $namespace.
     */
    private function insertAfterImport(string $source, string $namespace, string $import): string
    {
        $position = strrpos($source, $namespace);

        if ($position === false) {
            return $source;
        }

        $end = strpos($source, "\n", $position);

        if ($end === false) {
            return $source;
        }

        return substr($source, 0, $end)."\n".$import.substr($source, $end);
    }

    /**
     * Inserts a block right after the `{` that opens the first $needle object.
     */
    private function insertAfterOpening(string $source, string $needle, string $block): string
    {
        $position = strpos($source, $needle);

        if ($position === false) {
            return $source;
        }

        $open = strpos($source, '{', $position);

        if ($open === false) {
            return $source;
        }

        return substr($source, 0, $open + 1)."\n".rtrim($block, "\n").substr($source, $open + 1);
    }

    /**
     * Index of the `)` matching the `(` at $open, skipping strings and chars.
     */
    private function matchingParen(string $source, int $open): ?int
    {
        $depth = 0;
        $length = strlen($source);
        $string = false;
        $char = false;

        for ($i = $open; $i < $length; $i++) {
            $current = $source[$i];

            if ($string) {
                if ($current === '\\') {
                    $i++;
                } elseif ($current === '"') {
                    $string = false;
                }

                continue;
            }

            if ($char) {
                if ($current === '\\') {
                    $i++;
                } elseif ($current === "'") {
                    $char = false;
                }

                continue;
            }

            if ($current === '"') {
                $string = true;
            } elseif ($current === "'") {
                $char = true;
            } elseif ($current === '(') {
                $depth++;
            } elseif ($current === ')') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function javaPackage(string $javaRoot): ?string
    {
        $parts = [];
        $current = $javaRoot;

        while (true) {
            $directories = $this->filesystem->directories($current);

            if (count($directories) !== 1) {
                break;
            }

            $current = $directories[0];
            $parts[] = basename($current);
        }

        return $parts === [] ? null : implode('.', $parts);
    }

    private function pascal(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}

<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Workspace;

use Closure;
use Shardevcom\Cli\Exception\CommandFailed;
use Shardevcom\Cli\Filesystem\Filesystem;

/**
 * Boundary: teaches a client monorepo about a new guard.
 *
 * Every client keeps a fixed `Guard` enum/union plus one app per guard, so a
 * new guard is added by (a) extending that enum and (b) cloning the closest
 * existing app for the new guard. The desktop client also needs its module
 * wired into settings.gradle.kts; the mobile client only extends its enum
 * because its guard is a build-time --dart-define, not a directory.
 */
final class GuardClientScaffolder
{
    /**
     * @var list<string>
     */
    private const EXCLUDE_WEB = ['node_modules', 'dist', '.vite', '.git'];

    /**
     * @var list<string>
     */
    private const EXCLUDE_DESKTOP = ['build', '.gradle', 'out', '.git', '.idea'];

    public function __construct(private readonly Filesystem $filesystem) {}

    /**
     * @return list<string> paths created, relative to the component
     */
    public function scaffold(ComponentType $type, string $path, GuardDefinition $guard): array
    {
        return match ($type) {
            ComponentType::Web => $this->web(rtrim($path, '/'), $guard),
            ComponentType::Desktop => $this->desktop(rtrim($path, '/'), $guard),
            ComponentType::Mobile => $this->mobile(rtrim($path, '/'), $guard),
            ComponentType::Api => [],
        };
    }

    /**
     * @return list<string>
     */
    private function web(string $path, GuardDefinition $guard): array
    {
        $this->extendTypeScriptGuard($path.'/packages/plugin-kit/src/types.ts', $guard);
        $this->extendTypeScriptPlatform($path.'/packages/core/src/types.ts', $guard);
        $this->addWebLoginPresets($path.'/packages/core/src/pages/LoginPage.tsx', $guard);

        $apps = $path.'/apps';
        $app = $this->app($guard->key);
        $target = $apps.'/'.$app;

        if (! $this->filesystem->isDirectory($apps) || $this->filesystem->isDirectory($target)) {
            return [];
        }

        $source = $this->sourceApp($apps);

        if ($source === null) {
            return [];
        }

        $sourceApp = basename($source);
        $sourceGuard = $this->guardOf($sourceApp);

        $this->filesystem->copyDirectory($source, $target, self::EXCLUDE_WEB);

        $this->replace($target.'/src/main.tsx', 'setPlatform("'.$sourceGuard.'")', 'setPlatform("'.$guard->key.'")');
        $this->renameAppGuard($target.'/src/App.tsx', $sourceGuard, $guard);
        $this->replaceFilePattern(
            $target.'/vite.config.ts',
            '/createAppConfig\(([^,]+),\s*\d+\)/',
            'createAppConfig($1, '.$this->nextPort($apps).')'
        );
        $this->replaceFilePattern(
            $target.'/index.html',
            '/<title>[^<]*<\/title>/',
            '<title>Shardev · '.$guard->label().'</title>'
        );

        $this->addWebScripts($path.'/package.json', $app);

        return ['apps/'.$app];
    }

    /**
     * @return list<string>
     */
    private function desktop(string $path, GuardDefinition $guard): array
    {
        $this->extendJavaGuard($path.'/packages/plugin-kit/src/main/java/dev/shardev/pluginkit/Guard.java', $guard);
        $this->extendJavaDemoEmail(
            $path.'/packages/core/src/main/java/dev/shardev/core/ui/LoginView.java',
            $guard
        );

        $apps = $path.'/apps';
        $app = $this->app($guard->key);
        $target = $apps.'/'.$app;

        if (! $this->filesystem->isDirectory($apps) || $this->filesystem->isDirectory($target)) {
            return [];
        }

        $source = $this->sourceApp($apps);

        if ($source === null) {
            return [];
        }

        $sourceApp = basename($source);
        $sourceGuard = $this->guardOf($sourceApp);

        $this->filesystem->copyDirectory($source, $target, self::EXCLUDE_DESKTOP);
        $this->replace($target.'/build.gradle.kts', 'dev.shardev.apps.'.$sourceApp, 'dev.shardev.apps.'.$app);

        $javaRoot = $target.'/src/main/java/dev/shardev/apps';
        $this->filesystem->moveDirectory($javaRoot.'/'.$sourceApp, $javaRoot.'/'.$app);

        $main = $javaRoot.'/'.$app.'/Main.java';
        $this->replace($main, 'dev.shardev.apps.'.$sourceApp, 'dev.shardev.apps.'.$app);
        $this->replace($main, 'Guard.'.strtoupper($sourceGuard), 'Guard.'.strtoupper($guard->key));
        $this->replace($main, ucfirst($sourceApp).'App', ucfirst($app).'App');
        $this->replace($main, ucfirst($sourceApp).' desktop app', ucfirst($app).' desktop app');
        $this->replace($main, 'the {@code '.$sourceGuard.'} guard', 'the {@code '.$guard->key.'} guard');

        $this->addGradleInclude($path.'/settings.gradle.kts', $app);

        return ['apps/'.$app];
    }

    /**
     * @return list<string>
     */
    private function mobile(string $path, GuardDefinition $guard): array
    {
        $this->extendDartGuard($path.'/packages/plugin_kit/lib/src/guard.dart', $guard);
        $this->extendDartDemoEmail($path.'/packages/core/lib/src/pages/login_page.dart', $guard);

        return [];
    }

    private function extendTypeScriptGuard(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $source = $this->filesystem->read($file);

        if (str_contains($source, '"'.$guard->key.'"')) {
            return;
        }

        $source = $this->replacePattern(
            $source,
            '/(export type Guard = [^\n;]+)/',
            '$1 | "'.$guard->key.'"'
        );

        $this->filesystem->write($file, $source);
    }

    /**
     * The web app has a second guard union, `Platform` (used by setPlatform and
     * the API base), that must grow with the plugin-kit `Guard` union.
     */
    private function extendTypeScriptPlatform(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file) || str_contains($this->filesystem->read($file), '"'.$guard->key.'"')) {
            return;
        }

        $source = $this->replacePattern(
            $this->filesystem->read($file),
            '/(export type Platform = [^\n;]+)/',
            '$1 | "'.$guard->key.'"'
        );

        $this->filesystem->write($file, $source);
    }

    /**
     * The web login presets are exhaustive `Record<Guard, string>` maps, so a
     * new guard needs its demo email and label or the app stops type-checking.
     */
    private function addWebLoginPresets(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $source = $this->filesystem->read($file);

        if (str_contains($source, $guard->key.': "')) {
            return;
        }

        $source = $this->insertAfterOpeningBrace(
            $source,
            'const DEMO_EMAIL: Record<Guard, string> = {',
            '  '.$guard->key.': "'.$guard->key.'@example.com",'
        );
        $source = $this->insertAfterOpeningBrace(
            $source,
            'const GUARD_LABEL: Record<Guard, string> = {',
            '  '.$guard->key.': "'.$guard->label().'",'
        );

        $this->filesystem->write($file, $source);
    }

    /**
     * The Java login screen resolves the demo email from the guard in a switch
     * expression, which stays exhaustive only if every guard has a case.
     */
    private function extendJavaDemoEmail(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file)
            || str_contains($this->filesystem->read($file), 'case '.strtoupper($guard->key).' ->')) {
            return;
        }

        $source = $this->insertBeforeClosingBrace(
            $this->filesystem->read($file),
            'return switch (guard) {',
            static fn (string $indent): string => $indent.'    case '.strtoupper($guard->key)
                .' -> "'.$guard->key.'@example.com";'.PHP_EOL
        );

        $this->filesystem->write($file, $source);
    }

    /**
     * The Flutter login screen resolves the demo email from the guard in a
     * switch statement, which stays exhaustive only if every guard has a case.
     */
    private function extendDartDemoEmail(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file)
            || str_contains($this->filesystem->read($file), 'case Guard.'.$guard->key.':')) {
            return;
        }

        $source = $this->insertBeforeClosingBrace(
            $this->filesystem->read($file),
            'switch (guard) {',
            static fn (string $indent): string => $indent.'  case Guard.'.$guard->key.':'.PHP_EOL
                .$indent."    return '".$guard->key."@example.com';".PHP_EOL
        );

        $this->filesystem->write($file, $source);
    }

    /**
     * Inserts $block right after the `{` that opens the object named by $needle.
     */
    private function insertAfterOpeningBrace(string $source, string $needle, string $block): string
    {
        $position = strpos($source, $needle);

        if ($position === false) {
            return $source;
        }

        $open = strpos($source, '{', $position + strlen($needle) - 1);

        if ($open === false) {
            return $source;
        }

        return substr($source, 0, $open + 1)."\n".$block.substr($source, $open + 1);
    }

    private function extendDartGuard(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file) || str_contains($this->filesystem->read($file), $guard->key.'(')) {
            return;
        }

        $source = $this->filesystem->read($file);

        $source = $this->replacePattern(
            $source,
            '/\n(\s+)(\w+\(\'[^\']*\',\s*\'[^\']*\'\));/',
            static function (array $matches) use ($guard): string {
                return "\n".$matches[1].substr($matches[0], strlen("\n".$matches[1]), -1)
                    .",\n".$matches[1].$guard->key."('".$guard->key."', '".$guard->clientPrefix()."');";
            }
        );

        $source = $this->insertBeforeClosingBrace($source, 'switch (this) {', static function (string $indent) use ($guard): string {
            return $indent.'  case Guard.'.$guard->key.':'.PHP_EOL
                .$indent."    return '".$guard->label()."';".PHP_EOL;
        });

        $this->filesystem->write($file, $source);
    }

    private function extendJavaGuard(string $file, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file) || str_contains($this->filesystem->read($file), strtoupper($guard->key).'(')) {
            return;
        }

        $source = $this->filesystem->read($file);

        $source = $this->replacePattern(
            $source,
            '/\n(\s+)([A-Z_]+\([^)]*\));/',
            static function (array $matches) use ($guard): string {
                return "\n".$matches[1].substr($matches[0], strlen("\n".$matches[1]), -1)
                    .",\n".$matches[1].strtoupper($guard->key).'("'.$guard->key.'", "'.$guard->clientPrefix().'");';
            }
        );

        $source = $this->insertBeforeClosingBrace($source, 'switch (this) {', static function (string $indent) use ($guard): string {
            return $indent.'    case '.strtoupper($guard->key).' -> "'.$guard->label().'";'.PHP_EOL;
        });

        $this->filesystem->write($file, $source);
    }

    private function addWebScripts(string $file, string $app): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $json = $this->filesystem->read($file);

        if (str_contains($json, '"dev:'.$app.'"')) {
            return;
        }

        $json = $this->insertScriptLine($json, 'dev', $app, 'vite --config apps/'.$app.'/vite.config.ts');
        $json = $this->insertScriptLine($json, 'build', $app, 'vite build --config apps/'.$app.'/vite.config.ts');
        $json = $this->insertScriptLine($json, 'preview', $app, 'vite preview --config apps/'.$app.'/vite.config.ts');
        $json = $this->replacePattern($json, '/("build":\s*"[^"]*)"/', '$1 && npm run build:'.$app.'"');

        if (! str_contains($json, '"dev:'.$app.'":')) {
            throw CommandFailed::create(
                "Unable to add the '{$app}' scripts to {$file}: expected a pretty-printed package.json."
            );
        }

        $this->filesystem->write($file, $json);
    }

    private function insertScriptLine(string $json, string $namespace, string $app, string $command): string
    {
        $marker = '"'.$namespace.':';
        $position = strrpos($json, $marker);

        if ($position === false || str_contains($json, '"'.$namespace.':'.$app.'"')) {
            return $json;
        }

        $end = strpos($json, "\n", $position);

        if ($end === false) {
            return $json;
        }

        $lineStart = strrpos(substr($json, 0, $position), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $sibling = rtrim(substr($json, $lineStart, $end - $lineStart));

        $line = '    "'.$namespace.':'.$app.'": "'.$command.'"';

        if (str_ends_with($sibling, ',')) {
            $line .= ',';
        } else {
            $json = substr($json, 0, $end).','.substr($json, $end);
            $end++;
        }

        return substr($json, 0, $end + 1).$line."\n".substr($json, $end + 1);
    }

    private function addGradleInclude(string $file, string $app): void
    {
        if (! $this->filesystem->isFile($file) || str_contains($this->filesystem->read($file), '":apps:'.$app.'"')) {
            return;
        }

        $source = $this->filesystem->read($file);
        $start = strpos($source, 'include(');

        if ($start === false) {
            return;
        }

        $close = strpos($source, ')', $start);

        if ($close === false) {
            return;
        }

        $entry = '    ":apps:'.$app.'",'."\n";
        $this->filesystem->write($file, substr($source, 0, $close).$entry.substr($source, $close));
    }

    private function renameAppGuard(string $file, string $sourceGuard, GuardDefinition $guard): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $source = $this->filesystem->read($file);
        $source = str_replace('guard="'.$sourceGuard.'"', 'guard="'.$guard->key.'"', $source);
        $source = $this->replacePattern(
            $source,
            '/appName="[^"]*"/',
            'appName="Shardev '.$guard->label().'" public={'.($guard->public ? 'true' : 'false').'}'
        );

        $this->filesystem->write($file, $source);
    }

    private function sourceApp(string $apps): ?string
    {
        return $this->filesystem->directories($apps)[0] ?? null;
    }

    private function nextPort(string $apps): int
    {
        $port = 5175;

        foreach ($this->filesystem->directories($apps) as $directory) {
            $config = $directory.'/vite.config.ts';

            if ($this->filesystem->isFile($config)
                && preg_match('/createAppConfig\([^,]+,\s*(\d+)\)/', $this->filesystem->read($config), $matches) === 1) {
                $port = max($port, (int) $matches[1]);
            }
        }

        return $port + 1;
    }

    private function app(string $guard): string
    {
        return $guard === 'web' ? 'user' : $guard;
    }

    private function guardOf(string $app): string
    {
        return $app === 'user' ? 'web' : $app;
    }

    private function replace(string $file, string $search, string $replacement): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $this->filesystem->write($file, str_replace($search, $replacement, $this->filesystem->read($file)));
    }

    /**
     * @param string|Closure(array<int, string>): string $replacement
     */
    private function replacePattern(string $source, string $pattern, string|Closure $replacement): string
    {
        $result = is_string($replacement)
            ? preg_replace($pattern, $replacement, $source, 1)
            : preg_replace_callback($pattern, $replacement, $source, 1);

        return $result ?? $source;
    }

    private function replaceFilePattern(string $file, string $pattern, string $replacement): void
    {
        if (! $this->filesystem->isFile($file)) {
            return;
        }

        $this->filesystem->write($file, $this->replacePattern($this->filesystem->read($file), $pattern, $replacement));
    }

    /**
     * Inserts a block rendered from the closing brace's indentation just before
     * the `}` that closes the first `{` after $needle.
     *
     * @param Closure(string): string $render
     */
    private function insertBeforeClosingBrace(string $source, string $needle, Closure $render): string
    {
        $start = strpos($source, $needle);

        if ($start === false) {
            return $source;
        }

        $open = strpos($source, '{', $start);

        if ($open === false) {
            return $source;
        }

        $depth = 0;
        $length = strlen($source);

        for ($i = $open; $i < $length; $i++) {
            if ($source[$i] === '{') {
                $depth++;
            } elseif ($source[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    $lineStart = strrpos(substr($source, 0, $i), "\n");
                    $lineStart = $lineStart === false ? 0 : $lineStart + 1;
                    $indent = substr($source, $lineStart, $i - $lineStart);

                    if (trim($indent) !== '') {
                        $indent = '    ';
                    }

                    return substr($source, 0, $lineStart).$render($indent).substr($source, $lineStart);
                }
            }
        }

        return $source;
    }
}

<?php

declare(strict_types=1);

namespace Shardevcom\Cli\Plugins;

use RuntimeException;

/**
 * Adds a guard entry to a plugin's `plugin.json` manifest.
 *
 * The file is pretty-printed JSON, so it is edited textually (like
 * PlatformsEditor does with platforms.php) to keep the existing layout and the
 * one-line guard descriptors instead of re-encoding the whole document.
 */
final class PluginGuardEditor
{
    public function has(string $json, string $guard): bool
    {
        $data = json_decode($json, true);

        return is_array($data) && isset($data['guards'][$guard]);
    }

    /**
     * A brand-new guard defaults to the least privilege (`read`) over the
     * plugin's own resource; the plugin author widens it when its view needs to.
     */
    public function add(string $json, string $guard, string $mode = 'read'): string
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('The plugin manifest is not valid JSON.');
        }

        $resource = is_string($data['resource'] ?? null) && $data['resource'] !== ''
            ? $data['resource']
            : $guard;

        $entry = '"'.$guard.'": { "mode": "'.$mode.'", "path": "'.$resource.'" }';

        $key = strpos($json, '"guards"');
        $open = $key === false ? false : strpos($json, '{', $key);

        if ($open === false || $this->isEmptyObject($json, $open)) {
            $data['guards'][$guard] = ['mode' => $mode, 'path' => $resource];

            return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
        }

        $indent = $this->indentAfter($json, $open);

        return substr($json, 0, $open + 1)."\n".$indent.$entry.','.substr($json, $open + 1);
    }

    private function isEmptyObject(string $json, int $open): bool
    {
        $after = ltrim(substr($json, $open + 1));

        return str_starts_with($after, '}');
    }

    private function indentAfter(string $json, int $open): string
    {
        $newline = strpos($json, "\n", $open);

        if ($newline === false) {
            return '    ';
        }

        preg_match('/^[ \t]*/', substr($json, $newline + 1), $matches);

        return $matches[0] === '' ? '    ' : $matches[0];
    }
}

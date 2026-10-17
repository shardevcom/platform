package dev.shardev.pluginkit;

import java.util.Map;

/**
 * A self-contained feature contributed to the desktop client.
 *
 * @param name matches the API package name, e.g. {@code product}
 * @param title human title used by the shell
 * @param resource permission prefix (informational), e.g. {@code products}
 * @param guards one view per guard; guards absent from the map have no screen
 */
public record PluginDefinition(String name, String title, String resource, Map<Guard, GuardView> guards) {

    public PluginDefinition {
        guards = Map.copyOf(guards);
    }

    public PluginDefinition(String name, String title, Map<Guard, GuardView> guards) {
        this(name, title, null, guards);
    }
}

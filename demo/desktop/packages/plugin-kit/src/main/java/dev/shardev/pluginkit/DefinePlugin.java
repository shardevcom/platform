package dev.shardev.pluginkit;

/**
 * Identity helper for type-checking a plugin definition, mirroring the web and
 * mobile {@code definePlugin}. A plugin is a module added as a dependency and
 * registered in the app's entrypoint.
 */
public final class DefinePlugin {

    private DefinePlugin() {
    }

    public static PluginDefinition definePlugin(PluginDefinition definition) {
        return definition;
    }
}

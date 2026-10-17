package dev.shardev.pluginkit;

import java.util.function.Function;
import javafx.scene.Parent;

/** A plugin view resolved for the active guard. */
public record ResolvedView(
        String plugin,
        String title,
        String path,
        Function<PluginContext, Parent> builder,
        NavItem nav) {
}

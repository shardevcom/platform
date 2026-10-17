package dev.shardev.pluginkit;

import java.util.function.Function;
import javafx.scene.Parent;

/**
 * The view a plugin exposes for exactly one guard.
 *
 * @param path route path relative to the app root, e.g. {@code products}. Must
 *     match the API {@code plugin.json} {@code guards.<guard>.path} by convention
 * @param builder builds the screen from the platform context
 * @param nav optional menu entry; {@code null} keeps the route reachable but hidden
 */
public record GuardView(String path, Function<PluginContext, Parent> builder, NavItem nav) {

    public GuardView(String path, Function<PluginContext, Parent> builder) {
        this(path, builder, null);
    }
}

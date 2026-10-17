package dev.shardev.pluginkit;

import java.util.ArrayList;
import java.util.Comparator;
import java.util.List;
import java.util.Objects;

/**
 * The registry the app mounts: the views and nav entries a guard can see.
 *
 * @param views routes to mount for the active guard
 * @param nav menu entries contributed by plugins, already sorted
 */
public record PluginRegistry(List<ResolvedView> views, List<NavItem> nav) {

    public PluginRegistry {
        views = List.copyOf(views);
        nav = List.copyOf(nav);
    }

    /**
     * Resolves {@code plugins} for {@code guard}: keeps only the views declared
     * for that guard and sorts their nav entries by {@link NavItem#order()}.
     *
     * <p>This is the single place mapping a plugin to a guard, so neither the
     * app nor the plugin needs to know about the other's routing.
     */
    public static PluginRegistry create(List<PluginDefinition> plugins, Guard guard) {
        List<ResolvedView> views = new ArrayList<>();

        for (PluginDefinition plugin : plugins) {
            GuardView view = plugin.guards().get(guard);
            if (view == null) {
                continue;
            }
            views.add(new ResolvedView(
                    plugin.name(), plugin.title(), view.path(), view.builder(), view.nav()));
        }

        List<NavItem> nav = views.stream()
                .map(ResolvedView::nav)
                .filter(Objects::nonNull)
                .sorted(Comparator.comparingInt(NavItem::order))
                .toList();

        return new PluginRegistry(views, nav);
    }
}

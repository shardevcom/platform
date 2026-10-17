package dev.shardev.product;

import dev.shardev.pluginkit.DefinePlugin;
import dev.shardev.pluginkit.Guard;
import dev.shardev.pluginkit.GuardView;
import dev.shardev.pluginkit.NavItem;
import dev.shardev.pluginkit.PluginDefinition;
import java.util.Map;

/**
 * Product plugin (desktop side). One view per guard; the matching API package
 * declares the same contract in {@code plugin.json}.
 */
public final class ProductPlugin {

    private ProductPlugin() {
    }

    public static PluginDefinition productPlugin() {
        return DefinePlugin.definePlugin(new PluginDefinition(
                "product",
                "Products",
                "products",
                Map.of(
                        Guard.WEB, new GuardView(
                                "products",
                                context -> new ProductList(context, ProductCapabilities.READ_ONLY, "Catálogo"),
                                new NavItem("Catálogo", "/products", 10)),
                        Guard.EMPLOYEE, new GuardView(
                                "products",
                                context -> new ProductList(context, ProductCapabilities.MANAGE, "Productos"),
                                new NavItem("Productos", "/products", 10)),
                        Guard.ADMIN, new GuardView(
                                "products",
                                context -> new ProductList(context, ProductCapabilities.FULL, "Products"),
                                new NavItem("Products", "/products", 10)))));
    }
}

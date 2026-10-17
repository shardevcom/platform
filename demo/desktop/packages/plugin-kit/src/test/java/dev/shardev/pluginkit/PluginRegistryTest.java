package dev.shardev.pluginkit;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertSame;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.Test;

class PluginRegistryTest {

    private static PluginDefinition plugin() {
        return DefinePlugin.definePlugin(new PluginDefinition("product", "Products", "products", Map.of(
                Guard.WEB, new GuardView("products", ctx -> null, new NavItem("Catálogo", "/products", 10)),
                Guard.EMPLOYEE, new GuardView("products", ctx -> null, new NavItem("Productos", "/products", 10)),
                Guard.ADMIN, new GuardView("products", ctx -> null, new NavItem("Products", "/products", 10)))));
    }

    @Test
    void guardResolvesFromIdAndFallsBackToAdmin() {
        assertSame(Guard.WEB, Guard.fromId("web"));
        assertSame(Guard.EMPLOYEE, Guard.fromId("employee"));
        assertSame(Guard.ADMIN, Guard.fromId("unknown"));
    }

    @Test
    void guardExposesTheVersionedApiBase() {
        assertEquals("/api/v1", Guard.WEB.apiBase());
        assertEquals("/admin/api/v1", Guard.ADMIN.apiBase());
        assertEquals("/employee/api/v1", Guard.EMPLOYEE.apiBase());
    }

    @Test
    void registryKeepsOnlyTheViewsDeclaredForTheActiveGuard() {
        PluginRegistry registry = PluginRegistry.create(List.of(plugin()), Guard.ADMIN);

        assertEquals(1, registry.views().size());
        assertEquals("product", registry.views().get(0).plugin());
        assertEquals("products", registry.views().get(0).path());
    }

    @Test
    void registryExposesTheGuardNavEntry() {
        PluginRegistry registry = PluginRegistry.create(List.of(plugin()), Guard.WEB);

        assertEquals(1, registry.nav().size());
        assertEquals("Catálogo", registry.nav().get(0).label());
    }

    @Test
    void registryIsEmptyForAGuardThePluginDoesNotSupport() {
        PluginDefinition adminOnly = DefinePlugin.definePlugin(new PluginDefinition(
                "admin-only", "Admin only", Map.of(
                        Guard.ADMIN, new GuardView("admin-only", ctx -> null))));

        assertTrue(PluginRegistry.create(List.of(adminOnly), Guard.WEB).views().isEmpty());
        assertTrue(PluginRegistry.create(List.of(adminOnly), Guard.EMPLOYEE).views().isEmpty());
        assertEquals(1, PluginRegistry.create(List.of(adminOnly), Guard.ADMIN).views().size());
    }

    @Test
    void registrySortsNavEntriesByOrder() {
        PluginDefinition late = DefinePlugin.definePlugin(new PluginDefinition(
                "x", "X", Map.of(Guard.ADMIN,
                        new GuardView("x", ctx -> null, new NavItem("X", "/x", 50)))));
        PluginDefinition early = DefinePlugin.definePlugin(new PluginDefinition(
                "y", "Y", Map.of(Guard.ADMIN,
                        new GuardView("y", ctx -> null, new NavItem("Y", "/y", 5)))));

        PluginRegistry registry = PluginRegistry.create(List.of(late, early), Guard.ADMIN);

        assertEquals(List.of("Y", "X"), registry.nav().stream().map(NavItem::label).toList());
    }
}

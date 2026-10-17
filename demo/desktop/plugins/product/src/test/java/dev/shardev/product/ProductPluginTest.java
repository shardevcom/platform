package dev.shardev.product;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonParser;
import dev.shardev.pluginkit.Guard;
import dev.shardev.pluginkit.GuardView;
import dev.shardev.pluginkit.PluginRegistry;
import java.util.List;
import org.junit.jupiter.api.Test;

class ProductPluginTest {

    @Test
    void declaresOneViewPerGuard() {
        var plugin = ProductPlugin.productPlugin();

        assertEquals("product", plugin.name());
        assertEquals("products", plugin.resource());
        assertTrue(plugin.guards().keySet().containsAll(List.of(Guard.WEB, Guard.EMPLOYEE, Guard.ADMIN)));
    }

    @Test
    void everyGuardViewUsesTheProductsPathAndNav() {
        for (GuardView view : ProductPlugin.productPlugin().guards().values()) {
            assertEquals("products", view.path());
            assertNotNull(view.nav());
        }
    }

    @Test
    void registryResolvesThePluginForEachGuard() {
        for (Guard guard : Guard.values()) {
            PluginRegistry registry = PluginRegistry.create(List.of(ProductPlugin.productPlugin()), guard);

            assertEquals(1, registry.views().size());
            assertEquals("product", registry.views().get(0).plugin());
        }
    }

    @Test
    void productFromJsonMapsTheRow() {
        var json = JsonParser.parseString("""
                {"id":"7","sku":"SKU-7","name":"Anvil","ean":"123","brand_id":3,
                 "active":true,"brand":{"id":3,"name":"ACME"}}
                """).getAsJsonObject();

        Product product = Product.fromJson(json);

        assertEquals(7, product.id());
        assertEquals("SKU-7", product.sku());
        assertEquals(3, product.brandId());
        assertEquals("ACME", product.brand().name());
        assertTrue(product.active());
    }

    @Test
    void productInputSerializesToTheRestShape() {
        var json = new ProductInput("A", "B", null, null, false).toJson();

        assertEquals("A", json.get("sku").getAsString());
        assertTrue(json.get("ean").isJsonNull());
        assertTrue(json.get("brand_id").isJsonNull());
        assertFalse(json.get("active").getAsBoolean());
    }
}

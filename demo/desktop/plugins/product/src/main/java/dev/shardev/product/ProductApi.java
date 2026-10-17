package dev.shardev.product;

import com.google.gson.JsonElement;
import com.google.gson.JsonParser;
import dev.shardev.pluginkit.PluginContext;
import java.util.ArrayList;
import java.util.List;

/**
 * Product REST endpoints. They live with the plugin (not in core) because the
 * resource belongs to the plugin; core only provides the transport.
 */
public final class ProductApi {

    private final PluginContext context;

    public ProductApi(PluginContext context) {
        this.context = context;
    }

    public List<Product> list() {
        String json = context.apiRequest("GET", "products", null, null);
        List<Product> products = new ArrayList<>();
        if (json == null || json.isBlank()) {
            return products;
        }

        JsonElement parsed = JsonParser.parseString(json);
        if (!parsed.isJsonArray()) {
            return products;
        }

        for (JsonElement row : parsed.getAsJsonArray()) {
            if (row.isJsonObject()) {
                products.add(Product.fromJson(row.getAsJsonObject()));
            }
        }
        return products;
    }

    public Product create(ProductInput input) {
        String json = context.apiRequest("POST", "products", null, input.toJson().toString());
        return Product.fromJson(JsonParser.parseString(json).getAsJsonObject());
    }

    public Product update(int id, ProductInput input) {
        String json = context.apiRequest("PUT", "products/" + id, null, input.toJson().toString());
        return Product.fromJson(JsonParser.parseString(json).getAsJsonObject());
    }

    public void remove(int id) {
        context.apiRequest("DELETE", "products/" + id, null, null);
    }
}

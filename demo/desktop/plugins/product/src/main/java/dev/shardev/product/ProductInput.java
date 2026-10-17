package dev.shardev.product;

import com.google.gson.JsonObject;

/** Payload for create/update. */
public record ProductInput(String sku, String name, String ean, Integer brandId, boolean active) {

    public ProductInput(String sku, String name) {
        this(sku, name, null, null, true);
    }

    public JsonObject toJson() {
        JsonObject json = new JsonObject();
        json.addProperty("sku", sku);
        json.addProperty("name", name);
        json.addProperty("ean", ean);
        json.addProperty("brand_id", brandId);
        json.addProperty("active", active);
        return json;
    }
}

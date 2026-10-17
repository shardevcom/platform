package dev.shardev.product;

import com.google.gson.JsonObject;

/** A product row as returned by the REST adapter. */
public record Product(
        int id,
        String sku,
        String name,
        String ean,
        Integer brandId,
        boolean active,
        Brand brand) {

    public static Product fromJson(JsonObject json) {
        Brand brand = json.has("brand") && json.get("brand").isJsonObject()
                ? Brand.fromJson(json.getAsJsonObject("brand"))
                : null;

        boolean active = !json.has("active") || json.get("active").isJsonNull()
                || json.get("active").getAsBoolean();

        return new Product(
                Json.intOr(json, "id", 0),
                Json.stringOr(json, "sku", ""),
                Json.stringOr(json, "name", ""),
                Json.stringOr(json, "ean", null),
                Json.intOrNull(json, "brand_id"),
                active,
                brand);
    }
}

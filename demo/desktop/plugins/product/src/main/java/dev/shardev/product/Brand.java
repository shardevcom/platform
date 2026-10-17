package dev.shardev.product;

import com.google.gson.JsonObject;

/** Product domain models, mirroring the REST adapter's rows. */
public record Brand(int id, String name) {

    public static Brand fromJson(JsonObject json) {
        return new Brand(Json.intOr(json, "id", 0), Json.stringOr(json, "name", ""));
    }
}

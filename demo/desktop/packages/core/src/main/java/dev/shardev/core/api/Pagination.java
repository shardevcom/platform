package dev.shardev.core.api;

import com.google.gson.JsonObject;

/** Pagination block carried by {@code meta} on list responses. */
public record Pagination(int total, int perPage, int currentPage, int lastPage, Integer count) {

    public static Pagination fromJson(JsonObject json) {
        return new Pagination(
                asInt(json, "total"),
                asInt(json, "per_page"),
                asInt(json, "current_page"),
                asInt(json, "last_page"),
                json.has("count") && !json.get("count").isJsonNull() ? asInt(json, "count") : null);
    }

    private static int asInt(JsonObject json, String key) {
        try {
            return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsInt() : 0;
        } catch (RuntimeException error) {
            return 0;
        }
    }
}

package dev.shardev.product;

import com.google.gson.JsonObject;

/** Null-safe JSON readers for the product rows. */
final class Json {

    private Json() {
    }

    static int intOr(JsonObject json, String key, int fallback) {
        try {
            return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsInt() : fallback;
        } catch (RuntimeException error) {
            return fallback;
        }
    }

    static Integer intOrNull(JsonObject json, String key) {
        try {
            return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsInt() : null;
        } catch (RuntimeException error) {
            return null;
        }
    }

    static String stringOr(JsonObject json, String key, String fallback) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : fallback;
    }
}

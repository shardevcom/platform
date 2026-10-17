package dev.shardev.core.api;

import com.google.gson.JsonElement;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;

/**
 * The platform response envelope: {@code {status, code, message, data, meta}}.
 *
 * @param status {@code success} or {@code error}
 * @param code semantic code ({@code OK}, {@code FORBIDDEN}, ...)
 * @param message human message
 * @param data payload
 * @param meta metadata block (pagination, details), or {@code null}
 */
public record ApiEnvelope(String status, String code, String message, JsonElement data, JsonObject meta) {

    public static ApiEnvelope fromJson(JsonObject json) {
        JsonElement data = json.has("data") ? json.get("data") : JsonNull.INSTANCE;
        JsonObject meta = json.has("meta") && json.get("meta").isJsonObject()
                ? json.getAsJsonObject("meta")
                : null;
        return new ApiEnvelope(
                asString(json, "status", "success"),
                asString(json, "code", "OK"),
                asString(json, "message", ""),
                data,
                meta);
    }

    private static String asString(JsonObject json, String key, String fallback) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : fallback;
    }
}

package dev.shardev.core.account;

import com.google.gson.JsonObject;

/** One row of the signed-in account's notification inbox. */
public record AccountNotification(String id, String type, JsonObject data, String readAt, String createdAt) {

    public boolean read() {
        return readAt != null && !readAt.isBlank();
    }

    public static AccountNotification fromJson(JsonObject json) {
        JsonObject data = json.has("data") && json.get("data").isJsonObject()
                ? json.getAsJsonObject("data")
                : new JsonObject();

        return new AccountNotification(
                string(json, "id", ""),
                string(json, "type", "notification"),
                data,
                string(json, "read_at", null),
                string(json, "created_at", null));
    }

    private static String string(JsonObject json, String key, String fallback) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : fallback;
    }
}

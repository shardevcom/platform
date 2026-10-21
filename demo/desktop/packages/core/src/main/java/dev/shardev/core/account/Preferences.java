package dev.shardev.core.account;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import java.util.LinkedHashMap;
import java.util.Map;

/** Account preferences, returned complete by {@code GET auth/preferences}. */
public record Preferences(String theme, String language, Map<String, Boolean> notifications) {

    public Preferences {
        notifications = Map.copyOf(notifications);
    }

    public static Preferences fromJson(JsonObject json) {
        Map<String, Boolean> notifications = new LinkedHashMap<>();

        JsonElement raw = json.get("notifications");
        if (raw != null && raw.isJsonObject()) {
            for (Map.Entry<String, JsonElement> entry : raw.getAsJsonObject().entrySet()) {
                notifications.put(entry.getKey(), entry.getValue().isJsonPrimitive()
                        && entry.getValue().getAsBoolean());
            }
        }

        return new Preferences(
                string(json, "theme", "system"),
                string(json, "language", "es"),
                notifications);
    }

    public JsonObject toJson() {
        JsonObject json = new JsonObject();
        json.addProperty("theme", theme);
        json.addProperty("language", language);

        JsonObject channels = new JsonObject();
        notifications.forEach(channels::addProperty);
        json.add("notifications", channels);

        return json;
    }

    public Preferences withTheme(String value) {
        return new Preferences(value, language, notifications);
    }

    public Preferences withLanguage(String value) {
        return new Preferences(theme, value, notifications);
    }

    public Preferences withNotification(String channel, boolean enabled) {
        Map<String, Boolean> next = new LinkedHashMap<>(notifications);
        next.put(channel, enabled);
        return new Preferences(theme, language, next);
    }

    private static String string(JsonObject json, String key, String fallback) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : fallback;
    }
}

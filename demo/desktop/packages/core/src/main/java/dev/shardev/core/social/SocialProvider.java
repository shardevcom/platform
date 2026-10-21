package dev.shardev.core.social;

import com.google.gson.JsonObject;

/** An OAuth provider enabled by the API (`GET auth/social/providers`). */
public record SocialProvider(String key, String name) {

    public static SocialProvider fromJson(JsonObject json) {
        return new SocialProvider(
                string(json, "key"),
                string(json, "name"));
    }

    private static String string(JsonObject json, String key) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : null;
    }
}

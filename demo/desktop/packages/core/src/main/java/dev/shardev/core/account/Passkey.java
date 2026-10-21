package dev.shardev.core.account;

import com.google.gson.JsonObject;

/** A registered passkey, as returned by {@code auth/webauthn/credentials}. */
public record Passkey(String id, String name, String credentialId, String createdAt, String lastUsedAt) {

    public static Passkey fromJson(JsonObject json) {
        return new Passkey(
                string(json, "id"),
                string(json, "name"),
                string(json, "credential_id"),
                string(json, "created_at"),
                string(json, "last_used_at"));
    }

    private static String string(JsonObject json, String key) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : null;
    }
}

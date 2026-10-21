package dev.shardev.core.auth;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import java.util.ArrayList;
import java.util.List;

/** The authenticated identity returned by {@code auth/me} and {@code auth/login}. */
public final class AuthUser {

    private final String id;
    private final String name;
    private final String email;
    private final String guard;
    private final boolean emailVerified;
    private final List<String> abilities;

    public AuthUser(String id, String name, String email, String guard, List<String> abilities) {
        this(id, name, email, guard, abilities, false);
    }

    public AuthUser(
            String id,
            String name,
            String email,
            String guard,
            List<String> abilities,
            boolean emailVerified) {
        this.id = id;
        this.name = name;
        this.email = email;
        this.guard = guard;
        this.emailVerified = emailVerified;
        this.abilities = List.copyOf(abilities);
    }

    public String id() {
        return id;
    }

    public String name() {
        return name;
    }

    public String email() {
        return email;
    }

    public String guard() {
        return guard;
    }

    public boolean emailVerified() {
        return emailVerified;
    }

    public List<String> abilities() {
        return abilities;
    }

    public static AuthUser fromJson(JsonObject json) {
        List<String> abilities = new ArrayList<>();
        JsonElement raw = json.get("abilities");
        if (raw != null && raw.isJsonArray()) {
            JsonArray array = raw.getAsJsonArray();
            for (JsonElement item : array) {
                if (!item.isJsonNull()) {
                    abilities.add(item.getAsString());
                }
            }
        }

        return new AuthUser(
                string(json, "id", ""),
                string(json, "name", null),
                string(json, "email", null),
                string(json, "guard", null),
                abilities,
                json.has("email_verified") && !json.get("email_verified").isJsonNull()
                        && json.get("email_verified").getAsBoolean());
    }

    /**
     * True when the token's abilities grant {@code permission}.
     *
     * <p>Supports the exact ability, a {@code resource.*} wildcard and the
     * global {@code *}.
     */
    public boolean can(String permission) {
        if (abilities.contains("*") || abilities.contains(permission)) {
            return true;
        }
        int dot = permission.indexOf('.');
        String resource = dot >= 0 ? permission.substring(0, dot) : permission;
        return abilities.contains(resource + ".*");
    }

    private static String string(JsonObject json, String key, String fallback) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : fallback;
    }
}

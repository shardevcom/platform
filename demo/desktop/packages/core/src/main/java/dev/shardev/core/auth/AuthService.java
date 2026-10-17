package dev.shardev.core.auth;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import dev.shardev.core.api.ApiClient;
import dev.shardev.core.api.ApiError;
import dev.shardev.pluginkit.Guard;

/**
 * Real auth against the module's guard.
 *
 * <p>The Passport token is persisted with a {@link TokenStore}; on cold start it
 * is restored and validated with {@code auth/me} before the shell is shown.
 */
public final class AuthService {

    private final Guard guard;
    private final ApiClient api;
    private final TokenStore tokens;
    private AuthState state = AuthState.restoring();

    public AuthService(Guard guard, ApiClient api, TokenStore tokens) {
        this.guard = guard;
        this.api = api;
        this.tokens = tokens;
    }

    public AuthState state() {
        return state;
    }

    public AuthUser user() {
        return state.user();
    }

    /** Restores and validates a stored token; returns the user, or {@code null}. */
    public AuthUser restore() {
        String stored = tokens.read(guard);
        if (stored == null || stored.isBlank()) {
            state = AuthState.anonymous();
            return null;
        }

        api.setToken(stored);

        try {
            AuthUser user = AuthUser.fromJson(api.get("auth/me").getAsJsonObject());
            state = AuthState.authenticated(user);
            return user;
        } catch (ApiError | IllegalStateException error) {
            api.setToken(null);
            tokens.clear(guard);
            state = AuthState.anonymous();
            return null;
        }
    }

    public AuthUser login(String email, String password) {
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("device_name", "desktop");

        JsonObject result = api.data("POST", "auth/login", null, body).getAsJsonObject();

        JsonElement rawToken = result.get("access_token");
        String token = rawToken != null && !rawToken.isJsonNull() ? rawToken.getAsString() : null;
        if (token == null || token.isBlank()) {
            throw new ApiError("Login response did not include a token", "NO_TOKEN", 0, null);
        }

        api.setToken(token);
        tokens.write(guard, token);

        JsonElement rawUser = result.get("user");
        AuthUser user = rawUser != null && rawUser.isJsonObject()
                ? AuthUser.fromJson(rawUser.getAsJsonObject())
                : null;

        state = new AuthState(false, user);
        return user;
    }

    public void logout() {
        try {
            api.data("POST", "auth/logout", null, null);
        } catch (ApiError ignored) {
            // A revoked or expired token must still be dropped locally.
        } finally {
            api.setToken(null);
            tokens.clear(guard);
            state = AuthState.anonymous();
        }
    }
}

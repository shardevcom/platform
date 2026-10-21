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

        return authenticate("auth/login", body);
    }

    /** Passwordless sign-in with the code emailed to {@code email}. */
    public AuthUser loginWithOtp(String email, String code) {
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("code", code);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/otp/verify", body);
    }

    /** Self-registration; only offered when the guard is public. */
    public AuthUser register(String name, String email, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("name", name);
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/register", body);
    }

    /** Accepts an invitation and leaves the new account signed in. */
    public AuthUser acceptInvitation(String token, String name, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("token", token);
        body.addProperty("name", name);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/invitations/accept", body);
    }

    /** Re-reads {@code auth/me}, e.g. after editing the profile. */
    public AuthUser refreshUser() {
        AuthUser user = AuthUser.fromJson(api.get("auth/me").getAsJsonObject());
        state = AuthState.authenticated(user);
        return user;
    }

    private AuthUser authenticate(String path, JsonObject body) {
        JsonObject result = api.data("POST", path, null, body).getAsJsonObject();

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

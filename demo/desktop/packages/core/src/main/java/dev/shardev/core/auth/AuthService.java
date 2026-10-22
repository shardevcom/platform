package dev.shardev.core.auth;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import dev.shardev.core.api.ApiClient;
import dev.shardev.core.api.ApiError;
import dev.shardev.pluginkit.Guard;
import java.util.List;

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
        LoginResult result = login(email, password, false);
        if (!result.authenticated()) {
            throw new ApiError("Second factor required", "TWO_FACTOR_REQUIRED", 0, null);
        }
        return result.user();
    }

    /** Password login; privileged guards may return a second-factor challenge. */
    public LoginResult login(String email, String password, boolean remember) {
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("device_name", "desktop");
        body.addProperty("remember", remember);

        return authenticate("auth/login", body);
    }

    /** Passwordless sign-in with the code emailed to {@code email}. */
    public AuthUser loginWithOtp(String email, String code) {
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        body.addProperty("code", code);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/otp/verify", body).user();
    }

    /** Self-registration; only offered when the guard is public. */
    public AuthUser register(String name, String email, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("name", name);
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/register", body).user();
    }

    /** Accepts an invitation and leaves the new account signed in. */
    public AuthUser acceptInvitation(String token, String name, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("token", token);
        body.addProperty("name", name);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        body.addProperty("device_name", "desktop");

        return authenticate("auth/invitations/accept", body).user();
    }

    /**
     * Adopts a token issued outside the app (passkey ceremony in the system
     * browser), persists it and resolves the current user.
     */
    public AuthUser authenticateWithToken(String token) {
        if (token == null || token.isBlank()) {
            throw new ApiError("Login response did not include a token", "NO_TOKEN", 0, null);
        }

        api.setToken(token);

        try {
            AuthUser user = AuthUser.fromJson(api.get("auth/me").getAsJsonObject());
            tokens.write(guard, token);
            state = AuthState.authenticated(user);

            return user;
        } catch (ApiError | IllegalStateException error) {
            api.setToken(null);
            throw error;
        }
    }

    public String token() {
        return api.token();
    }

    /** Re-reads {@code auth/me}, e.g. after editing the profile. */
    public AuthUser refreshUser() {
        AuthUser user = AuthUser.fromJson(api.get("auth/me").getAsJsonObject());
        state = AuthState.authenticated(user);
        return user;
    }

    private LoginResult authenticate(String path, JsonObject body) {
        JsonObject result = api.data("POST", path, null, body).getAsJsonObject();

        JsonElement rawToken = result.get("access_token");
        String token = rawToken != null && !rawToken.isJsonNull() ? rawToken.getAsString() : null;
        if (token != null && !token.isBlank()) {
            return LoginResult.authenticated(adopt(token, result));
        }

        if (booleanValue(result, "two_factor_enrollment_required")) {
            String enrollmentToken = string(result, "enrollment_token");
            TwoFactorSetup setup = startTwoFactorSetup(enrollmentToken);
            api.setToken(null);
            state = AuthState.anonymous();
            return LoginResult.challenged(new TwoFactorChallenge(
                    enrollmentToken, true, strings(result, "methods"), setup));
        }

        if (booleanValue(result, "two_factor_required")) {
            api.setToken(null);
            state = AuthState.anonymous();
            return LoginResult.challenged(new TwoFactorChallenge(
                    string(result, "challenge_token"), false, strings(result, "methods")));
        }

        throw new ApiError("Login response did not include a token", "NO_TOKEN", 0, null);
    }

    /** Starts TOTP enrollment for a privileged login. */
    public TwoFactorSetup startTwoFactorSetup(String enrollmentToken) {
        JsonObject body = new JsonObject();
        body.addProperty("enrollment_token", enrollmentToken);

        return TwoFactorSetup.fromJson(
                api.data("POST", "auth/2fa/setup", null, body).getAsJsonObject());
    }

    /** Confirms TOTP enrollment and adopts the issued session. */
    public AuthUser confirmTwoFactorSetup(String enrollmentToken, String code) {
        JsonObject body = new JsonObject();
        body.addProperty("enrollment_token", enrollmentToken);
        body.addProperty("code", code);
        body.addProperty("device_name", "desktop");

        return session("auth/2fa/setup/confirm", body);
    }

    /** Completes a login challenge with a TOTP or recovery code. */
    public AuthUser verifyTwoFactor(String challengeToken, String code, String type) {
        JsonObject body = new JsonObject();
        body.addProperty("challenge_token", challengeToken);
        body.addProperty("code", code);
        body.addProperty("type", type);
        body.addProperty("device_name", "desktop");

        return session("auth/2fa/challenge", body);
    }

    private AuthUser session(String path, JsonObject body) {
        JsonObject result = api.data("POST", path, null, body).getAsJsonObject();
        String token = string(result, "access_token");
        if (token == null || token.isBlank()) {
            throw new ApiError("Login response did not include a token", "NO_TOKEN", 0, null);
        }
        return adopt(token, result);
    }

    private AuthUser adopt(String token, JsonObject result) {
        api.setToken(token);
        tokens.write(guard, token);

        JsonElement rawUser = result.get("user");
        AuthUser user = rawUser != null && rawUser.isJsonObject()
                ? AuthUser.fromJson(rawUser.getAsJsonObject())
                : null;

        state = new AuthState(false, user);
        return user;
    }

    private static boolean booleanValue(JsonObject json, String key) {
        return json.has(key) && !json.get(key).isJsonNull() && json.get(key).getAsBoolean();
    }

    private static String string(JsonObject json, String key) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : null;
    }

    private static List<String> strings(JsonObject json, String key) {
        JsonElement raw = json.get(key);
        if (raw == null || !raw.isJsonArray()) {
            return List.of();
        }
        List<String> values = new java.util.ArrayList<>();
        for (JsonElement item : raw.getAsJsonArray()) {
            if (!item.isJsonNull()) {
                values.add(item.getAsString());
            }
        }
        return List.copyOf(values);
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

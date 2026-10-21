package dev.shardev.core.account;

import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import dev.shardev.core.api.ApiClient;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;

/** Account endpoints that are not part of the sign-in flow. */
public final class AccountApi {

    private final ApiClient api;

    public AccountApi(ApiClient api) {
        this.api = api;
    }

    public Preferences preferences() {
        return Preferences.fromJson(api.get("auth/preferences").getAsJsonObject());
    }

    public Preferences savePreferences(Preferences preferences) {
        return Preferences.fromJson(
                api.data("PUT", "auth/preferences", null, preferences.toJson()).getAsJsonObject());
    }

    public void updateProfile(String name, String email) {
        JsonObject body = new JsonObject();
        if (name != null) {
            body.addProperty("name", name);
        }
        if (email != null) {
            body.addProperty("email", email);
        }
        api.data("PATCH", "auth/profile", null, body);
    }

    public void changePassword(String currentPassword, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("current_password", currentPassword);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        api.data("PUT", "auth/password", null, body);
    }

    public NotificationsFeed notifications() {
        return NotificationsFeed.fromJson(api.get("auth/notifications").getAsJsonObject());
    }

    public void readNotification(String id) {
        api.data("POST", "auth/notifications/" + encode(id) + "/read", null, null);
    }

    public void readAllNotifications() {
        api.data("POST", "auth/notifications/read-all", null, null);
    }

    public void deleteNotification(String id) {
        api.data("DELETE", "auth/notifications/" + encode(id), null, null);
    }

    public void disable(String currentPassword, String reason) {
        JsonObject body = new JsonObject();
        body.addProperty("current_password", currentPassword);
        if (reason != null && !reason.isBlank()) {
            body.addProperty("reason", reason);
        }
        api.data("POST", "auth/account/disable", null, body);
    }

    public InvitationSummary invitation(String token) {
        return InvitationSummary.fromJson(
                api.get("auth/invitations/" + encode(token)).getAsJsonObject());
    }

    public void requestOtp(String email) {
        api.post("auth/otp/request", emailBody(email));
    }

    public void forgotPassword(String email) {
        api.post("auth/forgot-password", emailBody(email));
    }

    public void resetPassword(String token, String email, String password, String confirmation) {
        JsonObject body = new JsonObject();
        body.addProperty("token", token);
        body.addProperty("email", email);
        body.addProperty("password", password);
        body.addProperty("password_confirmation", confirmation);
        api.post("auth/reset-password", body);
    }

    public void resendVerification() {
        api.data("POST", "auth/email/verify/resend", null, null);
    }

    public JsonElement verifyEmail(String verifyUrl) {
        return api.absolute("GET", verifyUrl, null);
    }

    private static JsonObject emailBody(String email) {
        JsonObject body = new JsonObject();
        body.addProperty("email", email);
        return body;
    }

    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }
}

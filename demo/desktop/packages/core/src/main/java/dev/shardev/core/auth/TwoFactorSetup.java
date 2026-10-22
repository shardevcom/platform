package dev.shardev.core.auth;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import java.util.ArrayList;
import java.util.List;

/** TOTP enrollment payload, including one-time plaintext recovery codes. */
public record TwoFactorSetup(
        String secret,
        String otpauthUrl,
        String qrCode,
        List<String> recoveryCodes,
        String instructions) {

    public static TwoFactorSetup fromJson(JsonObject json) {
        List<String> recoveryCodes = new ArrayList<>();
        JsonElement raw = json.get("recovery_codes");
        if (raw != null && raw.isJsonArray()) {
            JsonArray array = raw.getAsJsonArray();
            for (JsonElement item : array) {
                if (!item.isJsonNull()) {
                    recoveryCodes.add(item.getAsString());
                }
            }
        }

        return new TwoFactorSetup(
                string(json, "secret"),
                string(json, "otpauth_url"),
                string(json, "qr_code"),
                List.copyOf(recoveryCodes),
                string(json, "instructions"));
    }

    private static String string(JsonObject json, String key) {
        return json.has(key) && !json.get(key).isJsonNull() ? json.get(key).getAsString() : null;
    }
}

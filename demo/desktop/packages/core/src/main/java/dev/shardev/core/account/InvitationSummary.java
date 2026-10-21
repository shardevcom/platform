package dev.shardev.core.account;

import com.google.gson.JsonObject;

/** Public preview of an invitation token. */
public record InvitationSummary(String email, String guard) {

    public static InvitationSummary fromJson(JsonObject json) {
        return new InvitationSummary(
                json.has("email") && !json.get("email").isJsonNull() ? json.get("email").getAsString() : "",
                json.has("guard") && !json.get("guard").isJsonNull() ? json.get("guard").getAsString() : "");
    }
}

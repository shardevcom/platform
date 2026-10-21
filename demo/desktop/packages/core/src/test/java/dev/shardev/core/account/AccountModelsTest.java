package dev.shardev.core.account;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonParser;
import org.junit.jupiter.api.Test;

class AccountModelsTest {

    @Test
    void decodesPreferencesAndRoundTripsThem() {
        Preferences preferences = Preferences.fromJson(JsonParser.parseString("""
                {"theme":"dark","language":"en","notifications":{"email":true,"push":false}}
                """).getAsJsonObject());

        assertEquals("dark", preferences.theme());
        assertTrue(preferences.notifications().get("email"));
        assertFalse(preferences.notifications().get("push"));
        assertEquals("dark", preferences.toJson().get("theme").getAsString());
        assertEquals("light", preferences.withTheme("light").theme());
    }

    @Test
    void decodesTheNotificationFeed() {
        NotificationsFeed feed = NotificationsFeed.fromJson(JsonParser.parseString("""
                {"unread_count":1,"items":[
                  {"id":"n1","type":"invitation","data":{"email":"ada@example.com"},"read_at":null}
                ]}
                """).getAsJsonObject());

        assertEquals(1, feed.unreadCount());
        assertEquals(1, feed.items().size());
        assertFalse(feed.items().get(0).read());
        assertEquals("ada@example.com", feed.items().get(0).data().get("email").getAsString());
    }

    @Test
    void decodesPasskey() {
        Passkey passkey = Passkey.fromJson(JsonParser.parseString("""
                {"id":"p1","name":"MacBook","credential_id":"abc","created_at":"2026-01-01","last_used_at":null}
                """).getAsJsonObject());

        assertEquals("p1", passkey.id());
        assertEquals("MacBook", passkey.name());
        assertEquals("abc", passkey.credentialId());
        assertEquals("2026-01-01", passkey.createdAt());
    }

    @Test
    void decodesInvitationSummary() {
        InvitationSummary invitation = InvitationSummary.fromJson(
                JsonParser.parseString("{\"email\":\"ada@example.com\",\"guard\":\"web\"}").getAsJsonObject());

        assertEquals("ada@example.com", invitation.email());
        assertEquals("web", invitation.guard());
    }
}

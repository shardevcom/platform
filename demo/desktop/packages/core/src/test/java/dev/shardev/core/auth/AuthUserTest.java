package dev.shardev.core.auth;

import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonParser;
import java.util.List;
import org.junit.jupiter.api.Test;

class AuthUserTest {

    @Test
    void grantsAnExactAbility() {
        AuthUser user = new AuthUser("1", null, null, null, List.of("products.view"));

        assertTrue(user.can("products.view"));
        assertFalse(user.can("products.delete"));
    }

    @Test
    void grantsAResourceWildcard() {
        AuthUser user = new AuthUser("1", null, null, null, List.of("products.*"));

        assertTrue(user.can("products.create"));
        assertFalse(user.can("brands.create"));
    }

    @Test
    void readsTheEmailVerificationFlag() {
        AuthUser user = AuthUser.fromJson(JsonParser.parseString("""
                {"id":"1","email":"ada@example.com","email_verified":true,"abilities":[]}
                """).getAsJsonObject());

        assertTrue(user.emailVerified());
        assertFalse(user.can("products.view"));
    }

    @Test
    void grantsEverythingWithTheGlobalWildcard() {
        AuthUser user = new AuthUser("1", null, null, null, List.of("*"));

        assertTrue(user.can("anything.at.all"));
    }
}

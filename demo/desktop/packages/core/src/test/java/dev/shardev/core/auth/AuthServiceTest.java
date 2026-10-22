package dev.shardev.core.auth;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.sun.net.httpserver.HttpServer;
import dev.shardev.core.api.ApiClient;
import dev.shardev.pluginkit.Guard;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.atomic.AtomicReference;
import org.junit.jupiter.api.Test;

class AuthServiceTest {

    @Test
    void adoptsAnExternalTokenAndPersistsIt() throws IOException {
        AtomicReference<String> authorization = new AtomicReference<>();
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);

        server.createContext("/api/v1/auth/me", exchange -> {
            authorization.set(exchange.getRequestHeaders().getFirst("Authorization"));
            byte[] body = """
                    {"status":"success","code":"OK","message":"ok","data":{
                      "id":"1","name":"Ada","email":"ada@example.com","guard":"web",
                      "email_verified":true,"abilities":["products.view"]
                    }}
                    """.getBytes(StandardCharsets.UTF_8);

            exchange.getResponseHeaders().add("Content-Type", "application/json");
            exchange.sendResponseHeaders(200, body.length);
            try (OutputStream output = exchange.getResponseBody()) {
                output.write(body);
            }
        });
        server.start();

        try {
            MemoryTokenStore tokens = new MemoryTokenStore();
            ApiClient api = new ApiClient(Guard.WEB, "http://127.0.0.1:" + server.getAddress().getPort());
            AuthService auth = new AuthService(Guard.WEB, api, tokens);

            AuthUser user = auth.authenticateWithToken("external-token");

            assertEquals("ada@example.com", user.email());
            assertEquals("Bearer external-token", authorization.get());
            assertEquals("external-token", tokens.read(Guard.WEB));
            assertEquals("external-token", auth.token());
            assertTrue(user.can("products.view"));
        } finally {
            server.stop(0);
        }
    }

    @Test
    void privilegedLoginRequiresEnrollmentThenConfirmsTheSession() throws IOException {
        AtomicReference<String> confirmAuthorization = new AtomicReference<>();
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);

        server.createContext("/admin/api/v1/auth/login", exchange -> respond(exchange, 200, """
                {"status":"success","code":"OK","message":"ok","data":{
                  "two_factor_enrollment_required":true,
                  "enrollment_token":"enroll-token",
                  "methods":["totp"],
                  "expires_in":600
                }}
                """));
        server.createContext("/admin/api/v1/auth/2fa/setup", exchange -> respond(exchange, 200, """
                {"status":"success","code":"OK","message":"ok","data":{
                  "secret":"JBSWY3DPEHPK3PXP",
                  "otpauth_url":"otpauth://totp/Platform:admin@example.com",
                  "qr_code":"<svg/>",
                  "recovery_codes":["ONE","TWO"]
                }}
                """));
        server.createContext("/admin/api/v1/auth/2fa/setup/confirm", exchange -> {
            confirmAuthorization.set(exchange.getRequestHeaders().getFirst("Authorization"));
            respond(exchange, 200, """
                    {"status":"success","code":"OK","message":"ok","data":{
                      "token_type":"Bearer","access_token":"mfa-token","expires_in":3600,
                      "abilities":["*"],
                      "user":{"id":"2","name":"Root","email":"admin@example.com",
                        "guard":"admin","email_verified":true,"abilities":["*"]}
                    }}
                    """);
        });
        server.start();

        try {
            MemoryTokenStore tokens = new MemoryTokenStore();
            ApiClient api = new ApiClient(
                    Guard.ADMIN, "http://127.0.0.1:" + server.getAddress().getPort());
            AuthService auth = new AuthService(Guard.ADMIN, api, tokens);

            LoginResult result = auth.login("admin@example.com", "password", true);

            assertNull(result.user());
            TwoFactorChallenge challenge = result.challenge();
            assertEquals("enroll-token", challenge.token());
            assertTrue(challenge.enrollment());
            assertEquals("JBSWY3DPEHPK3PXP", challenge.setup().secret());
            assertEquals(java.util.List.of("ONE", "TWO"), challenge.setup().recoveryCodes());
            assertNull(tokens.read(Guard.ADMIN));

            AuthUser user = auth.confirmTwoFactorSetup(challenge.token(), "123456");

            assertEquals("admin@example.com", user.email());
            assertEquals("mfa-token", tokens.read(Guard.ADMIN));
            assertNull(confirmAuthorization.get());
        } finally {
            server.stop(0);
        }
    }

    private static void respond(com.sun.net.httpserver.HttpExchange exchange, int status, String json)
            throws IOException {
        byte[] body = json.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().add("Content-Type", "application/json");
        exchange.sendResponseHeaders(status, body.length);
        try (OutputStream output = exchange.getResponseBody()) {
            output.write(body);
        }
    }

    private static final class MemoryTokenStore implements TokenStore {

        private final Map<Guard, String> tokens = new HashMap<>();

        @Override
        public String read(Guard guard) {
            return tokens.get(guard);
        }

        @Override
        public void write(Guard guard, String token) {
            tokens.put(guard, token);
        }

        @Override
        public void clear(Guard guard) {
            tokens.remove(guard);
        }
    }
}

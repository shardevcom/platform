package dev.shardev.core.passkeys;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import dev.shardev.pluginkit.Guard;
import java.net.URI;
import java.net.URLDecoder;
import java.net.URLEncoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.atomic.AtomicReference;
import org.junit.jupiter.api.Test;

class PasskeyBrowserFlowTest {

    @Test
    void loginReturnsTheTokenFromTheLoopbackCallback() {
        AtomicReference<URI> opened = new AtomicReference<>();
        PasskeyBrowserFlow flow = new PasskeyBrowserFlow(uri -> {
            opened.set(uri);
            respond(uri, Map.of("token", "token-1"));
        }, Duration.ofSeconds(5));

        String token = flow.login(Guard.WEB, "ada@example.com");

        assertEquals("token-1", token);
        assertEquals("/passkey", opened.get().getPath());
        assertEquals("login", query(opened.get()).get("mode"));
        assertEquals("ada@example.com", query(opened.get()).get("email"));
        assertTrue(opened.get().getRawQuery().contains("state="));
    }

    @Test
    void registerSendsTheAccessTokenInTheFragment() {
        AtomicReference<URI> opened = new AtomicReference<>();
        PasskeyBrowserFlow flow = new PasskeyBrowserFlow(uri -> {
            opened.set(uri);
            respond(uri, Map.of("status", "registered"));
        }, Duration.ofSeconds(5));

        flow.register(Guard.ADMIN, "desktop-token", "Desktop");

        assertEquals("register", query(opened.get()).get("mode"));
        assertEquals("Desktop", query(opened.get()).get("name"));
        assertEquals("token=desktop-token", opened.get().getFragment());
    }

    @Test
    void surfacesTheErrorReturnedByThePage() {
        PasskeyBrowserFlow flow = new PasskeyBrowserFlow(
                uri -> respond(uri, Map.of("error", "El usuario canceló")),
                Duration.ofSeconds(5));

        PasskeyFlowException error = assertThrows(
                PasskeyFlowException.class,
                () -> flow.login(Guard.WEB, "ada@example.com"));

        assertEquals("El usuario canceló", error.getMessage());
    }

    @Test
    void timesOutWhenTheBrowserNeverReturns() {
        PasskeyBrowserFlow flow = new PasskeyBrowserFlow(uri -> {
        }, Duration.ofMillis(100));

        assertThrows(PasskeyFlowException.class, () -> flow.login(Guard.WEB, "ada@example.com"));
    }

    private static void respond(URI opened, Map<String, String> values) {
        Map<String, String> params = query(opened);
        StringBuilder url = new StringBuilder(params.get("return"))
                .append("?state=").append(encode(params.get("state")));

        values.forEach((key, value) -> url.append('&').append(key).append('=').append(encode(value)));

        try {
            HttpClient.newHttpClient().send(
                    HttpRequest.newBuilder(URI.create(url.toString())).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
        } catch (Exception error) {
            throw new IllegalStateException(error);
        }
    }

    private static Map<String, String> query(URI uri) {
        Map<String, String> values = new HashMap<>();

        for (String pair : uri.getRawQuery().split("&")) {
            int separator = pair.indexOf('=');
            values.put(
                    decode(pair.substring(0, separator)),
                    decode(pair.substring(separator + 1)));
        }

        return values;
    }

    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }

    private static String decode(String value) {
        return URLDecoder.decode(value, StandardCharsets.UTF_8);
    }
}

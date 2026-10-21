package dev.shardev.core.social;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.sun.net.httpserver.HttpServer;
import dev.shardev.core.account.AccountApi;
import dev.shardev.core.api.ApiClient;
import dev.shardev.core.browser.BrowserFlowException;
import dev.shardev.pluginkit.Guard;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URI;
import java.net.URLDecoder;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.concurrent.atomic.AtomicReference;
import org.junit.jupiter.api.Test;

class SocialBrowserFlowTest {

    @Test
    void decodesTheProviderCatalog() {
        SocialProvider provider = SocialProvider.fromJson(
                com.google.gson.JsonParser.parseString("{\"key\":\"google\",\"name\":\"Google\"}")
                        .getAsJsonObject());

        assertEquals("google", provider.key());
        assertEquals("Google", provider.name());
    }

    @Test
    void returnsTheTokenFromTheLoopbackCallback() throws IOException {
        AtomicReference<String> callback = new AtomicReference<>();
        HttpServer server = server(exchange -> {
            String query = exchange.getRequestURI().getRawQuery();
            callback.set(URLDecoder.decode(query.substring("return=".length()), StandardCharsets.UTF_8));
            respond(exchange, 200, """
                    {"status":"success","code":"OK","message":"","data":{
                      "redirect_url":"https://provider.example/oauth"
                    }}
                    """);
        });

        try {
            AccountApi account = new AccountApi(new ApiClient(Guard.WEB, host(server)));
            AtomicReference<URI> opened = new AtomicReference<>();
            SocialBrowserFlow flow = new SocialBrowserFlow(uri -> {
                opened.set(uri);
                get(callback.get() + "&token=token-1");
            }, Duration.ofSeconds(5));

            String token = flow.login(Guard.WEB, account, "google");

            assertEquals("token-1", token);
            assertEquals(URI.create("https://provider.example/oauth"), opened.get());
            assertTrue(callback.get().startsWith("http://127.0.0.1:"));
            assertTrue(callback.get().contains("state="));
        } finally {
            server.stop(0);
        }
    }

    @Test
    void surfacesTheErrorReturnedByTheProvider() throws IOException {
        AtomicReference<String> callback = new AtomicReference<>();
        HttpServer server = server(exchange -> {
            String query = exchange.getRequestURI().getRawQuery();
            callback.set(URLDecoder.decode(query.substring("return=".length()), StandardCharsets.UTF_8));
            respond(exchange, 200, """
                    {"status":"success","code":"OK","message":"","data":{
                      "redirect_url":"https://provider.example/oauth"
                    }}
                    """);
        });

        try {
            AccountApi account = new AccountApi(new ApiClient(Guard.WEB, host(server)));
            SocialBrowserFlow flow = new SocialBrowserFlow(
                    uri -> get(callback.get() + "&error=Acceso+cancelado"),
                    Duration.ofSeconds(5));

            BrowserFlowException error = assertThrows(
                    BrowserFlowException.class,
                    () -> flow.login(Guard.WEB, account, "google"));

            assertEquals("Acceso cancelado", error.getMessage());
        } finally {
            server.stop(0);
        }
    }

    @Test
    void timesOutWhenTheBrowserNeverReturns() throws IOException {
        HttpServer server = server(exchange -> respond(exchange, 200, """
                {"status":"success","code":"OK","message":"","data":{
                  "redirect_url":"https://provider.example/oauth"
                }}
                """));

        try {
            AccountApi account = new AccountApi(new ApiClient(Guard.WEB, host(server)));
            SocialBrowserFlow flow = new SocialBrowserFlow(uri -> {
            }, Duration.ofMillis(100));

            assertThrows(BrowserFlowException.class, () -> flow.login(Guard.WEB, account, "google"));
        } finally {
            server.stop(0);
        }
    }

    private static HttpServer server(com.sun.net.httpserver.HttpHandler handler) throws IOException {
        HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
        server.createContext("/api/v1/auth/social/google", handler);
        server.start();

        return server;
    }

    private static String host(HttpServer server) {
        return "http://127.0.0.1:" + server.getAddress().getPort();
    }

    private static void respond(com.sun.net.httpserver.HttpExchange exchange, int status, String body)
            throws IOException {
        byte[] bytes = body.getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().add("Content-Type", "application/json");
        exchange.sendResponseHeaders(status, bytes.length);

        try (OutputStream output = exchange.getResponseBody()) {
            output.write(bytes);
        }
    }

    private static void get(String url) {
        try {
            HttpClient.newHttpClient().send(
                    HttpRequest.newBuilder(URI.create(url)).GET().build(),
                    HttpResponse.BodyHandlers.ofString());
        } catch (Exception error) {
            throw new IllegalStateException(error);
        }
    }
}

package dev.shardev.core.passkeys;

import com.sun.net.httpserver.HttpExchange;
import com.sun.net.httpserver.HttpServer;
import java.io.IOException;
import java.io.OutputStream;
import java.net.InetSocketAddress;
import java.net.URI;
import java.net.URLDecoder;
import java.nio.charset.StandardCharsets;
import java.time.Duration;
import java.util.HashMap;
import java.util.Map;
import java.util.concurrent.CompletableFuture;
import java.util.concurrent.ExecutionException;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.TimeUnit;
import java.util.concurrent.TimeoutException;

/**
 * Single-use loopback callback for the browser-based passkey ceremony.
 *
 * <p>Binds an ephemeral port on the loopback interface, validates the random
 * {@code state} and completes once with the values the SPA returns.
 */
public final class LoopbackReceiver implements AutoCloseable {

    public record Callback(String token, String status, String error) {
    }

    private final HttpServer server;
    private final ExecutorService executor;
    private final CompletableFuture<Callback> result = new CompletableFuture<>();
    private final String state;
    private final Duration timeout;

    private LoopbackReceiver(HttpServer server, ExecutorService executor, String state, Duration timeout) {
        this.server = server;
        this.executor = executor;
        this.state = state;
        this.timeout = timeout;
    }

    public static LoopbackReceiver start(String state, Duration timeout) {
        try {
            HttpServer server = HttpServer.create(new InetSocketAddress("127.0.0.1", 0), 0);
            ExecutorService executor = Executors.newSingleThreadExecutor(runnable -> {
                Thread thread = new Thread(runnable, "passkey-loopback");
                thread.setDaemon(true);
                return thread;
            });
            LoopbackReceiver receiver = new LoopbackReceiver(server, executor, state, timeout);

            server.createContext("/callback", receiver::handle);
            server.setExecutor(executor);
            server.start();

            return receiver;
        } catch (IOException error) {
            throw new PasskeyFlowException("No se pudo abrir el callback local: " + error.getMessage());
        }
    }

    public URI callbackUrl() {
        return URI.create("http://127.0.0.1:" + server.getAddress().getPort() + "/callback");
    }

    public Callback await() {
        try {
            return result.get(timeout.toMillis(), TimeUnit.MILLISECONDS);
        } catch (TimeoutException error) {
            throw new PasskeyFlowException("La ceremonia de passkey caducó. Inténtalo de nuevo.");
        } catch (InterruptedException error) {
            Thread.currentThread().interrupt();
            throw new PasskeyFlowException("La ceremonia de passkey se interrumpió.");
        } catch (ExecutionException error) {
            throw new PasskeyFlowException(error.getCause() != null
                    ? error.getCause().getMessage()
                    : error.getMessage());
        }
    }

    private void handle(HttpExchange exchange) throws IOException {
        try {
            Map<String, String> query = parseQuery(exchange.getRequestURI().getRawQuery());

            if (!state.equals(query.get("state"))) {
                respond(exchange, 400, "Estado de passkey inválido.");
                return;
            }

            result.complete(new Callback(query.get("token"), query.get("status"), query.get("error")));
            respond(exchange, 200, "Passkey completada. Puedes cerrar esta pestaña y volver a la app.");
        } catch (RuntimeException error) {
            respond(exchange, 500, "No se pudo completar la passkey.");
        }
    }

    private static Map<String, String> parseQuery(String rawQuery) {
        Map<String, String> values = new HashMap<>();

        if (rawQuery == null || rawQuery.isBlank()) {
            return values;
        }

        for (String pair : rawQuery.split("&")) {
            int separator = pair.indexOf('=');
            String key = separator < 0 ? pair : pair.substring(0, separator);
            String value = separator < 0 ? "" : pair.substring(separator + 1);

            values.put(
                    URLDecoder.decode(key, StandardCharsets.UTF_8),
                    URLDecoder.decode(value, StandardCharsets.UTF_8));
        }

        return values;
    }

    private static void respond(HttpExchange exchange, int status, String message) throws IOException {
        byte[] body = ("<!doctype html><meta charset=\"utf-8\"><p>" + escape(message) + "</p>")
                .getBytes(StandardCharsets.UTF_8);
        exchange.getResponseHeaders().add("Content-Type", "text/html; charset=utf-8");
        exchange.sendResponseHeaders(status, body.length);

        try (OutputStream output = exchange.getResponseBody()) {
            output.write(body);
        }
    }

    private static String escape(String value) {
        return value
                .replace("&", "&amp;")
                .replace("<", "&lt;")
                .replace(">", "&gt;")
                .replace("\"", "&quot;");
    }

    @Override
    public void close() {
        server.stop(0);
        executor.shutdownNow();
    }
}

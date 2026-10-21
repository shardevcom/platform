package dev.shardev.core.api;

import com.google.gson.Gson;
import com.google.gson.JsonElement;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import dev.shardev.pluginkit.Guard;
import java.io.IOException;
import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.util.Map;

/**
 * Thin REST adapter over one guard's versioned surface.
 *
 * <p>Mirrors the web {@code apiRequest}: builds the platform query shape
 * ({@code filter[sku]}, {@code sort[]}), unwraps the
 * {@code {status, code, message, data, meta}} envelope and turns a non-2xx into
 * an {@link ApiError} carrying the semantic code.
 */
public final class ApiClient {

    private final HttpClient http = HttpClient.newHttpClient();
    private final Gson gson = new Gson();
    private final String baseUrl;
    private String token;

    public ApiClient(Guard guard, String host) {
        this.baseUrl = stripTrailingSlash(host) + guard.apiBase();
    }

    public void setToken(String token) {
        this.token = token;
    }

    public String token() {
        return token;
    }

    public JsonElement get(String path) {
        return get(path, null);
    }

    public JsonElement get(String path, Map<String, Object> query) {
        return data("GET", path, query, null);
    }

    public JsonElement post(String path, JsonElement body) {
        return data("POST", path, null, body);
    }

    public JsonElement put(String path, JsonElement body) {
        return data("PUT", path, null, body);
    }

    public JsonElement delete(String path) {
        return data("DELETE", path, null, null);
    }

    /** Hits an absolute URL (e.g. the signed email-verification link). */
    public JsonElement absolute(String method, String url, JsonElement body) {
        return request(method, URI.create(url), body);
    }

    /** Performs a request and returns the envelope's {@code data}. */
    public JsonElement data(String method, String path, Map<String, Object> query, JsonElement body) {
        String suffix = path.startsWith("/") ? path : "/" + path;

        return request(method, URI.create(baseUrl + suffix + QueryString.build(query)), body);
    }

    private JsonElement request(String method, URI uri, JsonElement body) {
        HttpRequest.Builder builder = HttpRequest.newBuilder(uri)
                .header("Accept", "application/json");

        if (token != null && !token.isBlank()) {
            builder.header("Authorization", "Bearer " + token);
        }

        String jsonBody = body == null ? null : gson.toJson(body);
        if (jsonBody != null) {
            builder.header("Content-Type", "application/json");
        }

        builder.method(method, jsonBody == null
                ? HttpRequest.BodyPublishers.noBody()
                : HttpRequest.BodyPublishers.ofString(jsonBody));

        HttpResponse<String> response;
        try {
            response = http.send(builder.build(), HttpResponse.BodyHandlers.ofString());
        } catch (IOException error) {
            throw new ApiError("Network error: " + error.getMessage(), "NETWORK_ERROR", 0, null);
        } catch (InterruptedException error) {
            Thread.currentThread().interrupt();
            throw new ApiError("Request interrupted", "NETWORK_ERROR", 0, null);
        }

        return unwrap(response);
    }

    private JsonElement unwrap(HttpResponse<String> response) {
        JsonObject envelope = parseEnvelope(response.body());
        int status = response.statusCode();
        boolean isError = envelope != null && "error".equals(asString(envelope, "status"));

        if (status >= 200 && status < 300 && !isError) {
            if (envelope != null && envelope.has("data")) {
                return envelope.get("data");
            }
            return JsonNull.INSTANCE;
        }

        throw toApiError(status, envelope);
    }

    private static JsonObject parseEnvelope(String body) {
        if (body == null || body.isBlank()) {
            return null;
        }
        try {
            JsonElement parsed = JsonParser.parseString(body);
            return parsed.isJsonObject() ? parsed.getAsJsonObject() : null;
        } catch (RuntimeException error) {
            return null;
        }
    }

    @SuppressWarnings("unchecked")
    private ApiError toApiError(int status, JsonObject envelope) {
        String message = envelope != null ? asString(envelope, "message") : null;
        String code = envelope != null ? asString(envelope, "code") : null;

        Map<String, Object> details = null;
        if (envelope != null && envelope.has("meta") && envelope.get("meta").isJsonObject()) {
            JsonObject meta = envelope.getAsJsonObject("meta");
            if (meta.has("details") && meta.get("details").isJsonObject()) {
                details = gson.fromJson(meta.get("details"), Map.class);
            }
        }

        return new ApiError(
                message != null ? message : "Request failed",
                code != null ? code : "HTTP_" + status,
                status,
                details);
    }

    private static String asString(JsonObject object, String key) {
        return object.has(key) && !object.get(key).isJsonNull() ? object.get(key).getAsString() : null;
    }

    private static String stripTrailingSlash(String value) {
        return value.endsWith("/") ? value.substring(0, value.length() - 1) : value;
    }
}

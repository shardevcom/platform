package dev.shardev.pluginkit;

import java.util.Map;

/**
 * The minimal platform surface a plugin view needs, implemented by the core
 * shell. It keeps the plugin contract free of the transport and auth details:
 * a plugin only asks for JSON in and JSON out.
 */
public interface PluginContext {

    /** The guard this app was built for. */
    Guard guard();

    /**
     * Performs an authenticated request against the active guard's REST surface
     * and returns the envelope's {@code data} as raw JSON.
     *
     * @param method HTTP method ({@code GET}, {@code POST}, ...)
     * @param path path relative to the guard base, e.g. {@code products/1}
     * @param query platform query shape ({@code filter}, {@code sort}, ...), or {@code null}
     * @param jsonBody JSON request body, or {@code null}
     * @return the {@code data} field serialized as JSON (may be {@code null})
     */
    String apiRequest(String method, String path, Map<String, Object> query, String jsonBody);

    /** True when the signed-in user's token grants {@code permission}. */
    boolean can(String permission);
}

package dev.shardev.core.ui;

import com.google.gson.JsonElement;
import com.google.gson.JsonParser;
import dev.shardev.core.account.AccountApi;
import dev.shardev.core.api.ApiClient;
import dev.shardev.core.auth.AuthService;
import dev.shardev.core.auth.AuthUser;
import dev.shardev.pluginkit.Guard;
import dev.shardev.pluginkit.PluginContext;
import dev.shardev.pluginkit.PluginRegistry;
import java.util.Map;

/** The core implementation of the plugin-facing {@link PluginContext}. */
public final class PlatformContext implements PluginContext {

    private final Guard guard;
    private final ApiClient api;
    private final AuthService auth;
    private final AccountApi account;
    private final PluginRegistry registry;

    PlatformContext(Guard guard, ApiClient api, AuthService auth, AccountApi account, PluginRegistry registry) {
        this.guard = guard;
        this.api = api;
        this.auth = auth;
        this.account = account;
        this.registry = registry;
    }

    @Override
    public Guard guard() {
        return guard;
    }

    @Override
    public String apiRequest(String method, String path, Map<String, Object> query, String jsonBody) {
        JsonElement body = jsonBody == null ? null : JsonParser.parseString(jsonBody);
        JsonElement data = api.data(method, path, query, body);
        return data == null || data.isJsonNull() ? null : data.toString();
    }

    @Override
    public boolean can(String permission) {
        AuthUser user = auth.user();
        return user != null && user.can(permission);
    }

    public PluginRegistry registry() {
        return registry;
    }

    public AuthUser user() {
        return auth.user();
    }

    public AuthService auth() {
        return auth;
    }

    public AccountApi account() {
        return account;
    }
}

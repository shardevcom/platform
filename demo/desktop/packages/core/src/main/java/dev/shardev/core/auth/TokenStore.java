package dev.shardev.core.auth;

import dev.shardev.pluginkit.Guard;

/** Persists the Passport token per guard, so each app remembers its own session. */
public interface TokenStore {

    String read(Guard guard);

    void write(Guard guard, String token);

    void clear(Guard guard);
}

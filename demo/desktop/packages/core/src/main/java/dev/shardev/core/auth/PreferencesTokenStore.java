package dev.shardev.core.auth;

import dev.shardev.pluginkit.Guard;
import java.util.prefs.Preferences;

/** Default {@link TokenStore} backed by the JDK {@link Preferences} API. */
public final class PreferencesTokenStore implements TokenStore {

    private final Preferences preferences = Preferences.userRoot().node("dev/shardev/desktop");

    @Override
    public String read(Guard guard) {
        return preferences.get(guard.id(), null);
    }

    @Override
    public void write(Guard guard, String token) {
        preferences.put(guard.id(), token);
    }

    @Override
    public void clear(Guard guard) {
        preferences.remove(guard.id());
    }
}

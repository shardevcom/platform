package dev.shardev.core.passkeys;

import java.net.URI;

/** Opens the passkey page in the user's browser (injectable for tests). */
@FunctionalInterface
public interface FrontendLauncher {

    void open(URI uri);
}

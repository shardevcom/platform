package dev.shardev.core.browser;

import java.net.URI;

/** Opens a URI in the user's browser (injectable for tests). */
@FunctionalInterface
public interface FrontendLauncher {

    void open(URI uri);
}

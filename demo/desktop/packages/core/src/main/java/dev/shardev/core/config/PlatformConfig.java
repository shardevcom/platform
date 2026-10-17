package dev.shardev.core.config;

/**
 * Build/run configuration, read from system properties or the environment.
 *
 * <p>The guard is fixed per app module (one module per guard), so the client
 * never asks the user which platform to log into: a Passport token is only
 * valid for the guard that issued it.
 */
public final class PlatformConfig {

    private PlatformConfig() {
    }

    /** API host; {@code -Dshardev.api.host=...} or {@code SHARDEV_API_HOST}. */
    public static String host() {
        return firstNonBlank(
                System.getProperty("shardev.api.host"),
                System.getenv("SHARDEV_API_HOST"),
                "http://localhost:8000");
    }

    /** Window/app name; {@code -Dshardev.app.name=...}. */
    public static String appName() {
        return firstNonBlank(
                System.getProperty("shardev.app.name"),
                "Shardev Desktop");
    }

    private static String firstNonBlank(String... values) {
        for (String value : values) {
            if (value != null && !value.isBlank()) {
                return value;
            }
        }
        return "";
    }
}

package dev.shardev.core.config;

import dev.shardev.pluginkit.Guard;

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

    /**
     * Whether the guard allows self-registration.
     *
     * <p>{@code -Dshardev.guard.public=true|false} or
     * {@code SHARDEV_GUARD_PUBLIC}; defaults to {@code true} only for the web
     * guard, mirroring {@code platform.json}.
     */
    public static boolean isPublic(Guard guard) {
        String value = firstNonBlank(
                System.getProperty("shardev.guard.public"),
                System.getenv("SHARDEV_GUARD_PUBLIC"),
                "");

        return value.isBlank() ? guard == Guard.WEB : Boolean.parseBoolean(value);
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

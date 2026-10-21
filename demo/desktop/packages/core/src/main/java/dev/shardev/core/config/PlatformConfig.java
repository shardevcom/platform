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

    /**
     * SPA base URL that runs the passkey ceremony for this guard.
     *
     * <p>{@code -Dshardev.frontend.url=...} or {@code SHARDEV_FRONTEND_URL};
     * defaults to the demo app ports (admin 5173, employee 5174, web 5175).
     */
    public static String frontendUrl(Guard guard) {
        String configured = firstNonBlank(
                System.getProperty("shardev.frontend.url"),
                System.getenv("SHARDEV_FRONTEND_URL"),
                "");

        if (!configured.isBlank()) {
            return configured.endsWith("/")
                    ? configured.substring(0, configured.length() - 1)
                    : configured;
        }

        return switch (guard) {
            case ADMIN -> "http://localhost:5173";
            case EMPLOYEE -> "http://localhost:5174";
            case WEB -> "http://localhost:5175";
        };
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

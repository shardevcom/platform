package dev.shardev.pluginkit;

/**
 * The platform guards the desktop client can run as.
 *
 * <p>Each guard maps to an independent API surface ({@code /admin/api/v1},
 * {@code /employee/api/v1}, {@code /api/v1}). A Passport token is only accepted
 * by the guard that issued it, so the client ships one app module per guard and
 * never offers a platform selector at login.
 */
public enum Guard {
    WEB("web", ""),
    ADMIN("admin", "admin/"),
    EMPLOYEE("employee", "employee/");

    private final String id;
    private final String prefix;

    Guard(String id, String prefix) {
        this.id = id;
        this.prefix = prefix;
    }

    /** Stable identifier shared with the API {@code plugin.json} and the web client. */
    public String id() {
        return id;
    }

    /** Path prefix of this guard's versioned REST surface (empty for {@link #WEB}). */
    public String prefix() {
        return prefix;
    }

    /** Base path of the guard's versioned REST surface. */
    public String apiBase() {
        return "/" + prefix + "api/v1";
    }

    /** Resolves a guard from its id, defaulting to {@link #ADMIN}. */
    public static Guard fromId(String id) {
        for (Guard guard : values()) {
            if (guard.id.equals(id)) {
                return guard;
            }
        }
        return ADMIN;
    }

    /** Human label used by the shell and the login screen. */
    public String label() {
        return switch (this) {
            case WEB -> "Web";
            case ADMIN -> "Admin";
            case EMPLOYEE -> "Employee";
        };
    }
}

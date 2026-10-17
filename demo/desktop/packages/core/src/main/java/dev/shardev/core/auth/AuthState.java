package dev.shardev.core.auth;

/** Immutable auth state for the shell and the login gate. */
public record AuthState(boolean loading, AuthUser user) {

    public boolean authenticated() {
        return user != null;
    }

    public static AuthState restoring() {
        return new AuthState(true, null);
    }

    public static AuthState anonymous() {
        return new AuthState(false, null);
    }

    public static AuthState authenticated(AuthUser user) {
        return new AuthState(false, user);
    }
}

package dev.shardev.core.auth;

/** Outcome of a password login: either a session or a pending second factor. */
public record LoginResult(AuthUser user, TwoFactorChallenge challenge) {

    public boolean authenticated() {
        return user != null;
    }

    public static LoginResult authenticated(AuthUser user) {
        return new LoginResult(user, null);
    }

    public static LoginResult challenged(TwoFactorChallenge challenge) {
        return new LoginResult(null, challenge);
    }
}

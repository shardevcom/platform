package dev.shardev.core.auth;

import java.util.List;

/** Pending challenge or enrollment returned by a privileged login. */
public record TwoFactorChallenge(
        String token,
        boolean enrollment,
        List<String> methods,
        TwoFactorSetup setup) {

    public TwoFactorChallenge(String token, boolean enrollment, List<String> methods) {
        this(token, enrollment, List.copyOf(methods), null);
    }
}

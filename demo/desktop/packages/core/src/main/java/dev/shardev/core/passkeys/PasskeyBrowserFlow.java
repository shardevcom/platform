package dev.shardev.core.passkeys;

import dev.shardev.core.browser.BrowserFlowException;
import dev.shardev.core.browser.FrontendLauncher;
import dev.shardev.core.browser.LoopbackReceiver;
import dev.shardev.core.browser.SystemBrowserLauncher;
import dev.shardev.core.config.PlatformConfig;
import dev.shardev.pluginkit.Guard;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.time.Duration;
import java.util.Base64;

/**
 * Runs the WebAuthn ceremony in the system browser and returns the result to
 * the desktop app through a one-shot loopback callback.
 *
 * <p>JavaFX has no platform authenticator API, so the SPA (which already owns
 * the passkey UI) performs the ceremony; the desktop only orchestrates the
 * redirect and the token hand-off.
 */
public final class PasskeyBrowserFlow {

    private static final SecureRandom RANDOM = new SecureRandom();

    private final FrontendLauncher launcher;
    private final Duration timeout;

    public PasskeyBrowserFlow() {
        this(new SystemBrowserLauncher(), Duration.ofSeconds(180));
    }

    PasskeyBrowserFlow(FrontendLauncher launcher, Duration timeout) {
        this.launcher = launcher;
        this.timeout = timeout;
    }

    /** Opens the SPA in login mode and returns the Passport token. */
    public String login(Guard guard, String email) {
        LoopbackReceiver.Callback callback = run(guard, "login", email, null, null);

        if (callback.token() == null || callback.token().isBlank()) {
            throw new BrowserFlowException("La passkey no devolvió un token de acceso.");
        }

        return callback.token();
    }

    /** Opens the SPA in registration mode using the current access token. */
    public void register(Guard guard, String accessToken, String name) {
        LoopbackReceiver.Callback callback = run(guard, "register", null, name, accessToken);

        if (!"registered".equals(callback.status())) {
            throw new BrowserFlowException("La passkey no confirmó el registro.");
        }
    }

    private LoopbackReceiver.Callback run(
            Guard guard,
            String mode,
            String email,
            String name,
            String accessToken) {
        String state = randomState();

        try (LoopbackReceiver receiver = LoopbackReceiver.start(state, timeout)) {
            launcher.open(buildUri(
                    PlatformConfig.frontendUrl(guard),
                    mode,
                    email,
                    name,
                    accessToken,
                    receiver.callbackUrl().toString(),
                    state));

            LoopbackReceiver.Callback callback = receiver.await();

            if (callback.error() != null && !callback.error().isBlank()) {
                throw new BrowserFlowException(callback.error());
            }

            return callback;
        }
    }

    static URI buildUri(
            String frontend,
            String mode,
            String email,
            String name,
            String accessToken,
            String returnUrl,
            String state) {
        StringBuilder uri = new StringBuilder(stripTrailingSlash(frontend))
                .append("/passkey?mode=").append(encode(mode))
                .append("&state=").append(encode(state))
                .append("&return=").append(encode(returnUrl));

        if (email != null && !email.isBlank()) {
            uri.append("&email=").append(encode(email));
        }
        if (name != null && !name.isBlank()) {
            uri.append("&name=").append(encode(name));
        }
        if (accessToken != null && !accessToken.isBlank()) {
            uri.append("#token=").append(encode(accessToken));
        }

        return URI.create(uri.toString());
    }

    private static String randomState() {
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);

        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }

    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }

    private static String stripTrailingSlash(String value) {
        return value.endsWith("/") ? value.substring(0, value.length() - 1) : value;
    }
}

package dev.shardev.core.social;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.browser.BrowserFlowException;
import dev.shardev.core.browser.FrontendLauncher;
import dev.shardev.core.browser.LoopbackReceiver;
import dev.shardev.core.browser.SystemBrowserLauncher;
import dev.shardev.pluginkit.Guard;
import java.net.URI;
import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.security.SecureRandom;
import java.time.Duration;
import java.util.Base64;

/**
 * Runs the OAuth ceremony in the system browser and receives the Passport
 * token through a one-shot loopback callback.
 *
 * <p>JavaFX has no embedded browser, so the API redirects the provider back to
 * a loopback URL owned by this process with a random {@code state}.
 */
public final class SocialBrowserFlow {

    private static final SecureRandom RANDOM = new SecureRandom();

    private final FrontendLauncher launcher;
    private final Duration timeout;

    public SocialBrowserFlow() {
        this(new SystemBrowserLauncher(), Duration.ofSeconds(180));
    }

    SocialBrowserFlow(FrontendLauncher launcher, Duration timeout) {
        this.launcher = launcher;
        this.timeout = timeout;
    }

    /** Opens the provider's OAuth page and returns the issued token. */
    public String login(Guard guard, AccountApi account, String provider) {
        String state = randomState();

        try (LoopbackReceiver receiver = LoopbackReceiver.start(state, timeout)) {
            String callback = receiver.callbackUrl() + "?state=" + encode(state);
            String redirect = account.socialRedirect(provider, callback);

            if (redirect == null || redirect.isBlank()) {
                throw new BrowserFlowException("El proveedor no devolvió una URL de acceso.");
            }

            launcher.open(URI.create(redirect));

            LoopbackReceiver.Callback result = receiver.await();

            if (result.error() != null && !result.error().isBlank()) {
                throw new BrowserFlowException(result.error());
            }
            if (result.token() == null || result.token().isBlank()) {
                throw new BrowserFlowException("El proveedor no devolvió un token de acceso.");
            }

            return result.token();
        }
    }

    private static String randomState() {
        byte[] bytes = new byte[32];
        RANDOM.nextBytes(bytes);

        return Base64.getUrlEncoder().withoutPadding().encodeToString(bytes);
    }

    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }
}

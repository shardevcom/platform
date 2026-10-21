package dev.shardev.core.passkeys;

import java.awt.Desktop;
import java.io.IOException;
import java.net.URI;

/** Opens a URI with the platform's default browser. */
public final class SystemBrowserLauncher implements FrontendLauncher {

    @Override
    public void open(URI uri) {
        try {
            if (Desktop.isDesktopSupported() && Desktop.getDesktop().isSupported(Desktop.Action.BROWSE)) {
                Desktop.getDesktop().browse(uri);
                return;
            }

            String os = System.getProperty("os.name", "").toLowerCase();

            if (os.contains("win")) {
                new ProcessBuilder("rundll32", "url.dll,FileProtocolHandler", uri.toString()).start();
            } else {
                new ProcessBuilder(os.contains("mac") ? "open" : "xdg-open", uri.toString()).start();
            }
        } catch (IOException | UnsupportedOperationException error) {
            throw new PasskeyFlowException("No se pudo abrir el navegador: " + error.getMessage());
        }
    }
}

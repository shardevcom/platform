package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.api.ApiClient;
import dev.shardev.core.auth.AuthService;
import dev.shardev.core.auth.PreferencesTokenStore;
import dev.shardev.core.config.PlatformConfig;
import dev.shardev.pluginkit.Guard;
import dev.shardev.pluginkit.PluginDefinition;
import dev.shardev.pluginkit.PluginRegistry;
import java.net.URL;
import java.util.List;
import javafx.scene.Scene;
import javafx.scene.layout.StackPane;
import javafx.stage.Stage;

/**
 * Wires the app: builds the guard's plugin registry, the transport and auth,
 * then swaps between the login screen and the authenticated shell.
 *
 * <p>Each app module instantiates this with its own {@link Guard} and plugin
 * list; everything guard-specific lives in the registry.
 */
public final class PlatformApp {

    private final Guard guard;
    private final PluginRegistry registry;
    private final ApiClient api;
    private final AuthService auth;
    private final AccountApi account;
    private final StackPane root = new StackPane();

    public PlatformApp(Guard guard, List<PluginDefinition> plugins) {
        this.guard = guard;
        this.registry = PluginRegistry.create(plugins, guard);
        this.api = new ApiClient(guard, PlatformConfig.host());
        this.auth = new AuthService(guard, api, new PreferencesTokenStore());
        this.account = new AccountApi(api);
    }

    public void start(Stage stage) {
        stage.setTitle(PlatformConfig.appName() + " · " + guard.label());

        Scene scene = new Scene(root, 1100, 760);
        URL stylesheet = PlatformApp.class.getResource("/dev/shardev/core/ui/app.css");
        if (stylesheet != null) {
            scene.getStylesheets().add(stylesheet.toExternalForm());
        }

        stage.setScene(scene);
        stage.setMinWidth(900);
        stage.setMinHeight(600);
        stage.show();

        showLogin();

        Async.run(auth::restore,
                user -> {
                    if (user != null) {
                        showShell();
                    }
                },
                error -> {
                    // Stay on the login screen; a cold start with no session is normal.
                });
    }

    private void showLogin() {
        root.getChildren().setAll(new LoginFlowView(guard, auth, account, this::showShell));
    }

    private void showShell() {
        root.getChildren().setAll(
                new ShellView(new PlatformContext(guard, api, auth, account, registry), this::showLogin));
    }
}

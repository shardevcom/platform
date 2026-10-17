package dev.shardev.apps.user;

import dev.shardev.core.ui.PlatformApp;
import dev.shardev.pluginkit.Guard;
import dev.shardev.product.ProductPlugin;
import java.util.List;
import javafx.application.Application;
import javafx.stage.Stage;

/** User desktop app: the {@code web} guard, read-only product catalog. */
public final class Main {

    private Main() {
    }

    public static void main(String[] args) {
        Application.launch(UserApp.class, args);
    }

    public static final class UserApp extends Application {
        @Override
        public void start(Stage stage) {
            new PlatformApp(Guard.WEB, List.of(ProductPlugin.productPlugin())).start(stage);
        }
    }
}

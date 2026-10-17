package dev.shardev.apps.admin;

import dev.shardev.core.ui.PlatformApp;
import dev.shardev.pluginkit.Guard;
import dev.shardev.product.ProductPlugin;
import java.util.List;
import javafx.application.Application;
import javafx.stage.Stage;

/**
 * Admin desktop app: the {@code admin} guard, full product CRUD.
 *
 * <p>{@code main} lives in a class that does not extend {@link Application} so
 * JavaFX runs from the classpath without the module launcher check.
 */
public final class Main {

    private Main() {
    }

    public static void main(String[] args) {
        Application.launch(AdminApp.class, args);
    }

    public static final class AdminApp extends Application {
        @Override
        public void start(Stage stage) {
            new PlatformApp(Guard.ADMIN, List.of(ProductPlugin.productPlugin())).start(stage);
        }
    }
}

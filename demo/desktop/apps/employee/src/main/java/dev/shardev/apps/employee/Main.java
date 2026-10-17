package dev.shardev.apps.employee;

import dev.shardev.core.ui.PlatformApp;
import dev.shardev.pluginkit.Guard;
import dev.shardev.product.ProductPlugin;
import java.util.List;
import javafx.application.Application;
import javafx.stage.Stage;

/** Employee desktop app: the {@code employee} guard, product create/edit. */
public final class Main {

    private Main() {
    }

    public static void main(String[] args) {
        Application.launch(EmployeeApp.class, args);
    }

    public static final class EmployeeApp extends Application {
        @Override
        public void start(Stage stage) {
            new PlatformApp(Guard.EMPLOYEE, List.of(ProductPlugin.productPlugin())).start(stage);
        }
    }
}

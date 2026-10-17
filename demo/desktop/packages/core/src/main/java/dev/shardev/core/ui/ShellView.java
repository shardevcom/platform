package dev.shardev.core.ui;

import dev.shardev.pluginkit.NavItem;
import dev.shardev.pluginkit.ResolvedView;
import java.util.ArrayList;
import java.util.List;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.Parent;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.StackPane;
import javafx.scene.layout.VBox;

/** The authenticated shell: a top bar, a nav rail built from the guard's registry, and the routed view. */
public final class ShellView extends BorderPane {

    private static final String DASHBOARD = "/dashboard";
    private static final String PROFILE = "/profile";

    private final PlatformContext context;
    private final StackPane content = new StackPane();
    private final List<Button> navButtons = new ArrayList<>();
    private final Runnable onLogout;

    public ShellView(PlatformContext context, Runnable onLogout) {
        this.context = context;
        this.onLogout = onLogout;

        getStyleClass().add("shell");

        Label title = new Label("Shardev · " + context.guard().label());
        title.getStyleClass().add("shell-title");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        Button logout = new Button("Cerrar sesión");
        logout.setOnAction(event -> doLogout());

        HBox top = new HBox(title, spacer, logout);
        top.getStyleClass().add("shell-top");
        top.setAlignment(Pos.CENTER_LEFT);
        top.setSpacing(12);
        top.setPadding(new Insets(12, 16, 12, 16));

        VBox nav = new VBox(4);
        nav.getStyleClass().add("shell-nav");
        nav.setPadding(new Insets(12, 8, 12, 8));
        nav.setPrefWidth(220);

        addNav(nav, "Dashboard", DASHBOARD);
        for (NavItem item : context.registry().nav()) {
            addNav(nav, item.label(), item.to());
        }
        addNav(nav, "Perfil", PROFILE);

        setTop(top);
        setLeft(nav);
        setCenter(content);

        show(DASHBOARD);
    }

    private void addNav(VBox nav, String label, String path) {
        Button button = new Button(label);
        button.getStyleClass().add("nav-button");
        button.setMaxWidth(Double.MAX_VALUE);
        button.setOnAction(event -> show(path));
        navButtons.add(button);
        nav.getChildren().add(button);
    }

    private void show(String path) {
        String label = labelFor(path);
        for (Button button : navButtons) {
            button.getStyleClass().remove("active");
            if (button.getText().equals(label)) {
                button.getStyleClass().add("active");
            }
        }
        content.getChildren().setAll(viewFor(path));
    }

    private String labelFor(String path) {
        if (path.equals(DASHBOARD)) {
            return "Dashboard";
        }
        if (path.equals(PROFILE)) {
            return "Perfil";
        }
        return context.registry().nav().stream()
                .filter(item -> item.to().equals(path))
                .map(NavItem::label)
                .findFirst()
                .orElse(path);
    }

    private Parent viewFor(String path) {
        if (path.equals(DASHBOARD)) {
            return new DashboardView(context);
        }
        if (path.equals(PROFILE)) {
            return new ProfileView(context, this::doLogout);
        }
        for (ResolvedView view : context.registry().views()) {
            if (("/" + view.path()).equals(path)) {
                return view.builder().apply(context);
            }
        }
        return new Label("No encontrado: " + path);
    }

    private void doLogout() {
        Async.run(() -> {
            context.auth().logout();
            return null;
        }, ignored -> onLogout.run(), error -> onLogout.run());
    }
}

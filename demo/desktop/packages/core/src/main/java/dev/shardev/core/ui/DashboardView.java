package dev.shardev.core.ui;

import dev.shardev.core.auth.AuthUser;
import javafx.geometry.Insets;
import javafx.scene.control.Label;
import javafx.scene.layout.FlowPane;
import javafx.scene.layout.VBox;

/** Landing screen: who is signed in and what this guard can do. */
public final class DashboardView extends VBox {

    public DashboardView(PlatformContext context) {
        setPadding(new Insets(24));
        setSpacing(12);

        AuthUser user = context.user();

        Label welcome = new Label("Bienvenido");
        welcome.getStyleClass().add("view-title");

        Label who = new Label(user == null
                ? "Sesión"
                : (user.name() != null ? user.name() : user.email()));

        String guardId = user != null && user.guard() != null ? user.guard() : context.guard().id();
        Label guardLabel = new Label("Guard: " + guardId);

        Label permissions = new Label("Permisos");

        FlowPane abilities = new FlowPane(8, 8);
        if (user != null) {
            for (String ability : user.abilities()) {
                Label chip = new Label(ability);
                chip.getStyleClass().add("chip");
                abilities.getChildren().add(chip);
            }
        }

        getChildren().addAll(welcome, who, guardLabel, permissions, abilities);
    }
}

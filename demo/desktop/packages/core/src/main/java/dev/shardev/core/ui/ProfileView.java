package dev.shardev.core.ui;

import dev.shardev.core.auth.AuthUser;
import javafx.geometry.Insets;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.layout.VBox;

/** Read-only profile for the signed-in user, plus logout. */
public final class ProfileView extends VBox {

    public ProfileView(PlatformContext context, Runnable onLogout) {
        setPadding(new Insets(24));
        setSpacing(8);

        AuthUser user = context.user();

        Label title = new Label("Perfil");
        title.getStyleClass().add("view-title");

        getChildren().addAll(
                title,
                row("Nombre", user != null ? user.name() : null),
                row("Email", user != null ? user.email() : null),
                row("Guard", user != null ? user.guard() : null),
                row("ID", user != null ? user.id() : null));

        Button logout = new Button("Cerrar sesión");
        logout.setOnAction(event -> Async.run(() -> {
            context.auth().logout();
            return null;
        }, ignored -> onLogout.run(), error -> onLogout.run()));

        getChildren().add(logout);
    }

    private static Label row(String label, String value) {
        return new Label(label + ": " + (value == null || value.isBlank() ? "—" : value));
    }
}

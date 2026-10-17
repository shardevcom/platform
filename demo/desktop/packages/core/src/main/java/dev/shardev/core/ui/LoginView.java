package dev.shardev.core.ui;

import dev.shardev.core.auth.AuthService;
import dev.shardev.core.config.PlatformConfig;
import dev.shardev.pluginkit.Guard;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/**
 * Login against the module's guard. The token issued here is only valid for
 * this platform, so the screen never asks which platform to use.
 */
public final class LoginView extends VBox {

    public LoginView(Guard guard, AuthService auth, Runnable onAuthenticated) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label(PlatformConfig.appName());
        title.getStyleClass().add("login-title");

        Label subtitle = new Label("Plataforma " + guard.label());

        TextField email = new TextField(demoEmail(guard));
        email.setPromptText("Email");
        email.setMaxWidth(360);

        PasswordField password = new PasswordField();
        password.setText("password");
        password.setMaxWidth(360);

        Label error = new Label();
        error.getStyleClass().add("error");
        error.setWrapText(true);
        error.setMaxWidth(360);
        error.setVisible(false);
        error.setManaged(false);

        Button submit = new Button("Entrar");
        submit.setDefaultButton(true);
        submit.setMaxWidth(360);

        submit.setOnAction(event -> {
            error.setVisible(false);
            error.setManaged(false);
            submit.setDisable(true);
            submit.setText("Entrando…");

            Async.run(() -> auth.login(email.getText().trim(), password.getText()),
                    user -> onAuthenticated.run(),
                    failure -> {
                        error.setText(Errors.message(failure));
                        error.setVisible(true);
                        error.setManaged(true);
                        submit.setDisable(false);
                        submit.setText("Entrar");
                    });
        });

        password.setOnAction(event -> submit.fire());

        getChildren().addAll(title, subtitle, email, password, submit, error);
    }

    private static String demoEmail(Guard guard) {
        return switch (guard) {
            case WEB -> "user@example.com";
            case ADMIN -> "admin@example.com";
            case EMPLOYEE -> "employee@example.com";
        };
    }
}

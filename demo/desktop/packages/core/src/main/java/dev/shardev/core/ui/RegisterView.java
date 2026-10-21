package dev.shardev.core.ui;

import dev.shardev.core.auth.AuthService;
import dev.shardev.pluginkit.Guard;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/** Self-registration; the guard must be public. */
public final class RegisterView extends VBox {

    public RegisterView(Guard guard, AuthService auth, Runnable onAuthenticated, Runnable onBack) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label("Crear cuenta");
        title.getStyleClass().add("login-title");
        Label subtitle = new Label("Plataforma " + guard.label());

        TextField name = field("Nombre");
        TextField email = field("Email");
        PasswordField password = passwordField("Contraseña");
        PasswordField confirmation = passwordField("Repetir contraseña");

        Label error = errorLabel();
        Button submit = new Button("Crear cuenta");
        submit.setDefaultButton(true);
        submit.setMaxWidth(360);

        submit.setOnAction(event -> {
            error.setVisible(false);
            error.setManaged(false);

            if (!password.getText().equals(confirmation.getText())) {
                show(error, "Las contraseñas no coinciden.");
                return;
            }

            submit.setDisable(true);
            submit.setText("Creando…");

            Async.run(
                    () -> auth.register(name.getText().trim(), email.getText().trim(), password.getText(),
                            confirmation.getText()),
                    user -> onAuthenticated.run(),
                    failure -> {
                        submit.setDisable(false);
                        submit.setText("Crear cuenta");
                        show(error, Errors.message(failure));
                    });
        });

        Button back = new Button("Ya tengo cuenta");
        back.setOnAction(event -> onBack.run());

        getChildren().addAll(title, subtitle, name, email, password, confirmation, submit, error, back);
    }

    private static TextField field(String prompt) {
        TextField field = new TextField();
        field.setPromptText(prompt);
        field.setMaxWidth(360);
        return field;
    }

    private static PasswordField passwordField(String prompt) {
        PasswordField field = new PasswordField();
        field.setPromptText(prompt);
        field.setMaxWidth(360);
        return field;
    }

    private static Label errorLabel() {
        Label error = new Label();
        error.getStyleClass().add("error");
        error.setWrapText(true);
        error.setMaxWidth(360);
        error.setVisible(false);
        error.setManaged(false);
        return error;
    }

    private static void show(Label label, String message) {
        label.setText(message);
        label.setVisible(true);
        label.setManaged(true);
    }
}

package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/** Chooses a new password from the link's token. */
public final class ResetPasswordView extends VBox {

    public ResetPasswordView(AccountApi account, Runnable onBack) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label("Nueva contraseña");
        title.getStyleClass().add("login-title");

        TextField token = new TextField();
        token.setPromptText("Token del enlace");
        token.setMaxWidth(360);

        TextField email = new TextField();
        email.setPromptText("Email");
        email.setMaxWidth(360);

        PasswordField password = new PasswordField();
        password.setPromptText("Contraseña");
        password.setMaxWidth(360);

        PasswordField confirmation = new PasswordField();
        confirmation.setPromptText("Repetir contraseña");
        confirmation.setMaxWidth(360);

        Label message = messageLabel();

        Button submit = new Button("Guardar contraseña");
        submit.setDefaultButton(true);
        submit.setMaxWidth(360);

        submit.setOnAction(event -> {
            if (!password.getText().equals(confirmation.getText())) {
                show(message, "Las contraseñas no coinciden.");
                return;
            }

            submit.setDisable(true);
            submit.setText("Guardando…");

            Async.run(() -> {
                account.resetPassword(token.getText().trim(), email.getText().trim(), password.getText(),
                        confirmation.getText());
                return null;
            }, ignored -> {
                show(message, "Contraseña actualizada. Vuelve a iniciar sesión.");
                submit.setDisable(false);
                submit.setText("Guardar contraseña");
            }, failure -> {
                show(message, Errors.message(failure));
                submit.setDisable(false);
                submit.setText("Guardar contraseña");
            });
        });

        Button back = new Button("Volver al login");
        back.setOnAction(event -> onBack.run());

        getChildren().addAll(title, token, email, password, confirmation, submit, message, back);
    }

    private static Label messageLabel() {
        Label message = new Label();
        message.getStyleClass().add("error");
        message.setWrapText(true);
        message.setMaxWidth(360);
        message.setVisible(false);
        message.setManaged(false);
        return message;
    }

    private static void show(Label label, String text) {
        label.setText(text);
        label.setVisible(true);
        label.setManaged(true);
    }
}

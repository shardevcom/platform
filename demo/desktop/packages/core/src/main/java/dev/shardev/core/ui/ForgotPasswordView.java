package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Hyperlink;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/** Sends the password-reset link; the API responds the same for unknown emails. */
public final class ForgotPasswordView extends VBox {

    public ForgotPasswordView(AccountApi account, Runnable onBack, Runnable onReset) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label("Recuperar contraseña");
        title.getStyleClass().add("login-title");
        Label hint = new Label("Te enviaremos un enlace para restablecerla.");

        TextField email = new TextField();
        email.setPromptText("Email");
        email.setMaxWidth(360);

        Label message = new Label();
        message.getStyleClass().add("error");
        message.setWrapText(true);
        message.setMaxWidth(360);
        message.setVisible(false);
        message.setManaged(false);

        Button submit = new Button("Enviar enlace");
        submit.setDefaultButton(true);
        submit.setMaxWidth(360);

        submit.setOnAction(event -> {
            submit.setDisable(true);
            submit.setText("Enviando…");

            Async.run(() -> {
                account.forgotPassword(email.getText().trim());
                return null;
            }, ignored -> {
                message.setText("Si la cuenta existe, recibirás un enlace en tu email.");
                message.setVisible(true);
                message.setManaged(true);
                submit.setDisable(false);
                submit.setText("Enviar enlace");
            }, failure -> {
                message.setText(Errors.message(failure));
                message.setVisible(true);
                message.setManaged(true);
                submit.setDisable(false);
                submit.setText("Enviar enlace");
            });
        });

        Hyperlink reset = new Hyperlink("Ya tengo un token");
        reset.setOnAction(event -> onReset.run());

        Button back = new Button("Volver al login");
        back.setOnAction(event -> onBack.run());

        getChildren().addAll(title, hint, email, submit, message, reset, back);
    }
}

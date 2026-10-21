package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.config.PlatformConfig;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/** Confirms the signed URL embedded in the verification email. */
public final class VerifyEmailView extends VBox {

    public VerifyEmailView(AccountApi account, Runnable onBack) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label("Verificación de email");
        title.getStyleClass().add("login-title");

        TextField url = new TextField();
        url.setPromptText("Enlace de verificación");
        url.setMaxWidth(360);

        Label message = new Label();
        message.setWrapText(true);
        message.setMaxWidth(360);
        message.setVisible(false);
        message.setManaged(false);

        Button verify = new Button("Verificar");
        verify.setDefaultButton(true);
        verify.setMaxWidth(360);
        verify.setOnAction(event -> {
            String value = url.getText().trim();
            String absolute = value.contains("://") ? value : PlatformConfig.host() + value;

            verify.setDisable(true);
            verify.setText("Verificando…");

            Async.run(() -> {
                account.verifyEmail(absolute);
                return null;
            }, ignored -> {
                message.setText("Tu email quedó verificado.");
                message.setVisible(true);
                message.setManaged(true);
                verify.setDisable(false);
                verify.setText("Verificar");
            }, failure -> {
                message.setText(Errors.message(failure));
                message.setVisible(true);
                message.setManaged(true);
                verify.setDisable(false);
                verify.setText("Verificar");
            });
        });

        Button back = new Button("Volver al login");
        back.setOnAction(event -> onBack.run());

        getChildren().addAll(title, url, verify, message, back);
    }
}

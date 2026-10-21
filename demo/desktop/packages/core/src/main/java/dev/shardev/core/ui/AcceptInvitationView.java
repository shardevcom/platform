package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.account.InvitationSummary;
import dev.shardev.core.auth.AuthService;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.TextField;
import javafx.scene.layout.VBox;

/** Accepts an invitation: the guest picks a name and password and is signed in. */
public final class AcceptInvitationView extends VBox {

    private final AccountApi account;
    private final AuthService auth;
    private final Runnable onAuthenticated;

    private final TextField token = new TextField();
    private final TextField name = new TextField();
    private final PasswordField password = new PasswordField();
    private final PasswordField confirmation = new PasswordField();
    private final Label summary = new Label();
    private final Label message = new Label();

    public AcceptInvitationView(
            AccountApi account,
            AuthService auth,
            Runnable onAuthenticated,
            Runnable onBack) {
        this.account = account;
        this.auth = auth;
        this.onAuthenticated = onAuthenticated;

        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label("Aceptar invitación");
        title.getStyleClass().add("login-title");

        token.setPromptText("Token de la invitación");
        token.setMaxWidth(360);
        name.setPromptText("Nombre");
        name.setMaxWidth(360);
        password.setPromptText("Contraseña");
        password.setMaxWidth(360);
        confirmation.setPromptText("Repetir contraseña");
        confirmation.setMaxWidth(360);

        summary.setWrapText(true);
        summary.setMaxWidth(360);
        message.getStyleClass().add("error");
        message.setWrapText(true);
        message.setMaxWidth(360);
        message.setVisible(false);
        message.setManaged(false);

        Button check = new Button("Comprobar invitación");
        check.setOnAction(event -> checkInvitation(check));

        Button submit = new Button("Crear cuenta y entrar");
        submit.setDefaultButton(true);
        submit.setMaxWidth(360);
        submit.setOnAction(event -> accept(submit));

        Button back = new Button("Volver al login");
        back.setOnAction(event -> onBack.run());

        getChildren().addAll(title, token, check, summary, name, password, confirmation, submit, message, back);
    }

    private void checkInvitation(Button check) {
        check.setDisable(true);

        Async.run(() -> account.invitation(token.getText().trim()),
                (InvitationSummary invitation) -> {
                    summary.setText("Te unes como " + invitation.email());
                    check.setDisable(false);
                },
                failure -> {
                    summary.setText(Errors.message(failure));
                    check.setDisable(false);
                });
    }

    private void accept(Button submit) {
        if (!password.getText().equals(confirmation.getText())) {
            show("Las contraseñas no coinciden.");
            return;
        }

        submit.setDisable(true);
        submit.setText("Activando…");

        Async.run(
                () -> auth.acceptInvitation(token.getText().trim(), name.getText().trim(), password.getText(),
                        confirmation.getText()),
                user -> onAuthenticated.run(),
                failure -> {
                    submit.setDisable(false);
                    submit.setText("Crear cuenta y entrar");
                    show(Errors.message(failure));
                });
    }

    private void show(String text) {
        message.setText(text);
        message.setVisible(true);
        message.setManaged(true);
    }
}

package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.auth.AuthService;
import dev.shardev.core.config.PlatformConfig;
import dev.shardev.pluginkit.Guard;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Hyperlink;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.Tab;
import javafx.scene.control.TabPane;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.VBox;

/**
 * Login against the module's guard. Password or one-time code, plus links to
 * the recovery, registration, invitation and verification flows.
 */
public final class LoginView extends VBox {

    private enum Mode {
        PASSWORD,
        CODE
    }

    private final TextField email;
    private final PasswordField password = new PasswordField();
    private final TextField code = new TextField();
    private final Button submit = new Button();
    private final Button sendCode = new Button("Enviar código");
    private final Label error = new Label();
    private Mode mode = Mode.PASSWORD;

    public LoginView(
            Guard guard,
            AuthService auth,
            AccountApi account,
            Runnable onAuthenticated,
            Runnable onForgot,
            Runnable onRegister,
            Runnable onInvitation,
            Runnable onVerifyEmail) {
        getStyleClass().add("login");
        setAlignment(Pos.CENTER);
        setSpacing(12);
        setPadding(new Insets(40));

        Label title = new Label(PlatformConfig.appName());
        title.getStyleClass().add("login-title");

        Label subtitle = new Label("Plataforma " + guard.label());

        email = new TextField(demoEmail(guard));
        email.setPromptText("Email");
        email.setMaxWidth(360);

        password.setText("password");
        password.setMaxWidth(360);

        code.setPromptText("Código");
        HBox.setHgrow(code, Priority.ALWAYS);

        error.getStyleClass().add("error");
        error.setWrapText(true);
        error.setMaxWidth(360);
        error.setVisible(false);
        error.setManaged(false);

        submit.setDefaultButton(true);
        submit.setMaxWidth(360);
        submit.setOnAction(event -> submit(auth, onAuthenticated));

        password.setOnAction(event -> submit.fire());

        Tab passwordTab = new Tab("Contraseña", password);
        passwordTab.setClosable(false);

        HBox codeRow = new HBox(8, code, sendCode);
        codeRow.setAlignment(Pos.CENTER_LEFT);
        codeRow.setMaxWidth(360);
        Tab codeTab = new Tab("Código", codeRow);
        codeTab.setClosable(false);

        TabPane modes = new TabPane(passwordTab, codeTab);
        modes.setMaxWidth(360);
        modes.setTabClosingPolicy(TabPane.TabClosingPolicy.UNAVAILABLE);
        modes.getSelectionModel().selectedItemProperty().addListener((observable, previous, selected) -> {
            mode = selected == codeTab ? Mode.CODE : Mode.PASSWORD;
            renderSubmit();
        });

        sendCode.setOnAction(event -> {
            clearError();
            sendCode.setDisable(true);

            Async.run(() -> {
                account.requestOtp(email.getText().trim());
                return null;
            }, ignored -> {
                sendCode.setDisable(false);
                showError("Si la cuenta existe, te enviamos un código de acceso.");
            }, failure -> {
                sendCode.setDisable(false);
                showError(Errors.message(failure));
            });
        });

        Hyperlink forgot = new Hyperlink("¿Olvidaste tu contraseña?");
        forgot.setOnAction(event -> onForgot.run());

        Hyperlink invitation = new Hyperlink("Invitación");
        invitation.setOnAction(event -> onInvitation.run());

        Hyperlink verifyEmail = new Hyperlink("Verificar email");
        verifyEmail.setOnAction(event -> onVerifyEmail.run());

        HBox links = new HBox(8, forgot);
        links.setAlignment(Pos.CENTER);

        HBox secondary = new HBox(8, invitation, verifyEmail);
        secondary.setAlignment(Pos.CENTER);

        if (PlatformConfig.isPublic(guard)) {
            Hyperlink register = new Hyperlink("Crear una cuenta");
            register.setOnAction(event -> onRegister.run());
            links.getChildren().add(register);
        }

        renderSubmit();

        getChildren().addAll(title, subtitle, email, modes, submit, error, links, secondary);
    }

    private void submit(AuthService auth, Runnable onAuthenticated) {
        clearError();
        submit.setDisable(true);
        submit.setText("Entrando…");

        Async.run(
                () -> mode == Mode.PASSWORD
                        ? auth.login(email.getText().trim(), password.getText())
                        : auth.loginWithOtp(email.getText().trim(), code.getText().trim()),
                user -> onAuthenticated.run(),
                failure -> {
                    submit.setDisable(false);
                    renderSubmit();
                    showError(Errors.message(failure));
                });
    }

    private void renderSubmit() {
        submit.setText(mode == Mode.PASSWORD ? "Entrar" : "Entrar con código");
    }

    private void showError(String message) {
        error.setText(message);
        error.setVisible(true);
        error.setManaged(true);
    }

    private void clearError() {
        error.setVisible(false);
        error.setManaged(false);
    }

    private static String demoEmail(Guard guard) {
        return switch (guard) {
            case WEB -> "user@example.com";
            case ADMIN -> "admin@example.com";
            case EMPLOYEE -> "employee@example.com";
        };
    }
}

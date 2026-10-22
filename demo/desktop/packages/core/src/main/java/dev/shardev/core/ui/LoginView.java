package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.auth.AuthService;
import dev.shardev.core.auth.LoginResult;
import dev.shardev.core.auth.TwoFactorChallenge;
import dev.shardev.core.auth.TwoFactorSetup;
import dev.shardev.core.config.PlatformConfig;
import dev.shardev.core.passkeys.PasskeyBrowserFlow;
import dev.shardev.core.social.SocialBrowserFlow;
import dev.shardev.core.social.SocialProvider;
import dev.shardev.pluginkit.Guard;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Hyperlink;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.Tab;
import javafx.scene.Node;
import javafx.scene.control.TabPane;
import javafx.scene.control.TextArea;
import javafx.scene.control.TextField;
import java.util.List;
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

    private final Guard guard;
    private final TextField email;
    private final PasswordField password = new PasswordField();
    private final TextField code = new TextField();
    private final Button submit = new Button();
    private final Button passkey = new Button("Entrar con passkey");
    private final VBox socialBox = new VBox(8);
    private final Button sendCode = new Button("Enviar código");
    private final Label error = new Label();
    private Mode mode = Mode.PASSWORD;
    private List<Node> loginNodes = List.of();

    public LoginView(
            Guard guard,
            AuthService auth,
            AccountApi account,
            PasskeyBrowserFlow passkeys,
            SocialBrowserFlow social,
            Runnable onAuthenticated,
            Runnable onForgot,
            Runnable onRegister,
            Runnable onInvitation,
            Runnable onVerifyEmail) {
        this.guard = guard;
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

        passkey.setMaxWidth(360);
        passkey.setOnAction(event -> passkeyLogin(auth, passkeys, onAuthenticated));

        socialBox.setFillWidth(true);
        socialBox.setMaxWidth(360);

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

        getChildren().addAll(title, subtitle, email, modes, submit, passkey, socialBox, error, links, secondary);
        loginNodes = List.copyOf(getChildren());

        loadSocial(account, social, auth, onAuthenticated);
    }

    private void submit(AuthService auth, Runnable onAuthenticated) {
        clearError();
        submit.setDisable(true);
        submit.setText("Entrando…");

        if (mode == Mode.CODE) {
            Async.run(
                    () -> auth.loginWithOtp(email.getText().trim(), code.getText().trim()),
                    user -> onAuthenticated.run(),
                    this::submitFailed);
            return;
        }

        Async.run(
                () -> auth.login(email.getText().trim(), password.getText(), false),
                result -> {
                    submit.setDisable(false);
                    renderSubmit();
                    if (result.challenge() != null) {
                        showTwoFactor(result.challenge(), auth, onAuthenticated);
                    } else {
                        onAuthenticated.run();
                    }
                },
                this::submitFailed);
    }

    private void submitFailed(Throwable failure) {
        submit.setDisable(false);
        renderSubmit();
        showError(Errors.message(failure));
    }

    private void showTwoFactor(TwoFactorChallenge challenge, AuthService auth, Runnable onAuthenticated) {
        VBox pane = new VBox(12);
        pane.setAlignment(Pos.CENTER);
        pane.setMaxWidth(360);

        Label heading = new Label(challenge.enrollment()
                ? "Configura tu segundo factor"
                : "Verificación en dos pasos");
        heading.getStyleClass().add("login-title");
        pane.getChildren().add(heading);

        if (challenge.enrollment()) {
            TwoFactorSetup setup = challenge.setup();
            Label hint = new Label(
                    "Añade esta clave a tu app de autenticación y guarda los recovery codes.");
            hint.setWrapText(true);
            pane.getChildren().add(hint);

            if (setup != null) {
                TextArea secret = new TextArea(setup.secret());
                secret.setEditable(false);
                secret.setWrapText(true);
                secret.setMaxWidth(360);
                secret.setPrefRowCount(2);
                pane.getChildren().addAll(new Label("Clave TOTP"), secret);

                if (!setup.recoveryCodes().isEmpty()) {
                    Label codes = new Label(String.join("  ·  ", setup.recoveryCodes()));
                    codes.setWrapText(true);
                    codes.setMaxWidth(360);
                    pane.getChildren().addAll(new Label("Recovery codes"), codes);
                }
            }
        } else {
            Label hint = new Label("Introduce el código de tu app de autenticación.");
            hint.setWrapText(true);
            pane.getChildren().add(hint);
        }

        TextField twoFactorCode = new TextField();
        twoFactorCode.setPromptText("Código TOTP");
        twoFactorCode.setMaxWidth(360);
        pane.getChildren().add(twoFactorCode);

        Button confirm = new Button(challenge.enrollment() ? "Activar y entrar" : "Verificar y entrar");
        confirm.setDefaultButton(true);
        confirm.setMaxWidth(360);
        pane.getChildren().add(confirm);

        boolean[] recovery = {false};
        if (!challenge.enrollment()) {
            Button toggle = new Button("Usar un recovery code");
            toggle.setOnAction(event -> {
                recovery[0] = !recovery[0];
                twoFactorCode.setPromptText(recovery[0] ? "Recovery code" : "Código TOTP");
                toggle.setText(recovery[0] ? "Usar código TOTP" : "Usar un recovery code");
            });
            pane.getChildren().add(toggle);
        }

        Label challengeError = new Label();
        challengeError.getStyleClass().add("error");
        challengeError.setWrapText(true);
        challengeError.setMaxWidth(360);
        challengeError.setVisible(false);
        challengeError.setManaged(false);
        pane.getChildren().add(challengeError);

        Button cancel = new Button("Cancelar");
        cancel.setOnAction(event -> getChildren().setAll(loginNodes));
        pane.getChildren().add(cancel);

        confirm.setOnAction(event -> {
            String codeValue = twoFactorCode.getText().trim();
            if (codeValue.isEmpty()) {
                return;
            }

            confirm.setDisable(true);
            confirm.setText("Verificando…");
            challengeError.setVisible(false);
            challengeError.setManaged(false);

            Async.run(
                    () -> challenge.enrollment()
                            ? auth.confirmTwoFactorSetup(challenge.token(), codeValue)
                            : auth.verifyTwoFactor(
                                    challenge.token(),
                                    codeValue,
                                    recovery[0] ? "recovery_code" : "totp"),
                    user -> onAuthenticated.run(),
                    failure -> {
                        confirm.setDisable(false);
                        confirm.setText(challenge.enrollment()
                                ? "Activar y entrar"
                                : "Verificar y entrar");
                        challengeError.setText(Errors.message(failure));
                        challengeError.setVisible(true);
                        challengeError.setManaged(true);
                    });
        });

        getChildren().setAll(pane);
    }

    private void passkeyLogin(AuthService auth, PasskeyBrowserFlow passkeys, Runnable onAuthenticated) {
        clearError();
        passkey.setDisable(true);
        passkey.setText("Esperando passkey…");

        Async.run(
                () -> auth.authenticateWithToken(passkeys.login(guard, email.getText().trim())),
                user -> onAuthenticated.run(),
                failure -> {
                    passkey.setDisable(false);
                    passkey.setText("Entrar con passkey");
                    showError(Errors.message(failure));
                });
    }

    private void loadSocial(
            AccountApi account,
            SocialBrowserFlow social,
            AuthService auth,
            Runnable onAuthenticated) {
        if (guard != Guard.WEB) {
            return;
        }

        Async.run(
                account::socialProviders,
                providers -> renderSocial(providers, account, social, auth, onAuthenticated),
                failure -> {
                });
    }

    private void renderSocial(
            List<SocialProvider> providers,
            AccountApi account,
            SocialBrowserFlow social,
            AuthService auth,
            Runnable onAuthenticated) {
        socialBox.getChildren().clear();

        for (SocialProvider provider : providers) {
            Button button = new Button("Continuar con " + provider.name());
            button.setMaxWidth(360);
            button.setOnAction(event -> socialLogin(button, provider, account, social, auth, onAuthenticated));
            socialBox.getChildren().add(button);
        }
    }

    private void socialLogin(
            Button button,
            SocialProvider provider,
            AccountApi account,
            SocialBrowserFlow social,
            AuthService auth,
            Runnable onAuthenticated) {
        clearError();
        button.setDisable(true);
        button.setText("Esperando al proveedor…");

        Async.run(
                () -> auth.authenticateWithToken(social.login(guard, account, provider.key())),
                user -> onAuthenticated.run(),
                failure -> {
                    button.setDisable(false);
                    button.setText("Continuar con " + provider.name());
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

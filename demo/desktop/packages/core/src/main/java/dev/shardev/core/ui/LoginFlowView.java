package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.auth.AuthService;
import dev.shardev.pluginkit.Guard;
import javafx.scene.layout.StackPane;

/** Swaps the public authentication screens before the shell is shown. */
public final class LoginFlowView extends StackPane {

    private final Guard guard;
    private final AuthService auth;
    private final AccountApi account;
    private final Runnable onAuthenticated;

    public LoginFlowView(Guard guard, AuthService auth, AccountApi account, Runnable onAuthenticated) {
        this.guard = guard;
        this.auth = auth;
        this.account = account;
        this.onAuthenticated = onAuthenticated;

        showLogin();
    }

    private void showLogin() {
        setAll(new LoginView(
                guard,
                auth,
                account,
                onAuthenticated,
                this::showForgot,
                this::showRegister,
                this::showInvitation,
                this::showVerifyEmail));
    }

    private void showRegister() {
        setAll(new RegisterView(guard, auth, onAuthenticated, this::showLogin));
    }

    private void showForgot() {
        setAll(new ForgotPasswordView(account, this::showLogin, this::showReset));
    }

    private void showReset() {
        setAll(new ResetPasswordView(account, this::showLogin));
    }

    private void showInvitation() {
        setAll(new AcceptInvitationView(account, auth, onAuthenticated, this::showLogin));
    }

    private void showVerifyEmail() {
        setAll(new VerifyEmailView(account, this::showLogin));
    }

    private void setAll(javafx.scene.Node node) {
        getChildren().setAll(node);
    }
}

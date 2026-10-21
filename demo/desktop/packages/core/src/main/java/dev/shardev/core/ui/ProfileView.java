package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.account.Passkey;
import dev.shardev.core.auth.AuthUser;
import java.util.List;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.PasswordField;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.Separator;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

/** Account screen: profile edition, verification, password and disable. */
public final class ProfileView extends ScrollPane {

    private final PlatformContext context;
    private final AccountApi account;
    private final Runnable onLogout;

    private final TextField name = new TextField();
    private final TextField email = new TextField();
    private final PasswordField currentPassword = new PasswordField();
    private final PasswordField newPassword = new PasswordField();
    private final PasswordField confirmation = new PasswordField();
    private final PasswordField disablePassword = new PasswordField();
    private final Label verified = new Label();
    private final Label message = new Label();
    private final VBox passkeysBox = new VBox(6);
    private final Button addPasskey = new Button("Añadir passkey");

    public ProfileView(PlatformContext context, Runnable onLogout) {
        this.context = context;
        this.account = context.account();
        this.onLogout = onLogout;

        setFitToWidth(true);

        VBox body = new VBox(12);
        body.setPadding(new Insets(24));

        Label title = new Label("Perfil");
        title.getStyleClass().add("view-title");

        AuthUser user = context.user();
        name.setText(user != null ? user.name() : "");
        email.setText(user != null ? user.email() : "");

        message.setWrapText(true);

        Button saveProfile = new Button("Guardar perfil");
        saveProfile.setOnAction(event -> saveProfile(saveProfile));

        Button resend = new Button("Reenviar verificación");
        resend.setOnAction(event -> resendVerification());

        Button savePassword = new Button("Cambiar contraseña");
        savePassword.setOnAction(event -> savePassword(savePassword));

        addPasskey.setOnAction(event -> addPasskey(addPasskey));

        Button disable = new Button("Deshabilitar cuenta");
        disable.setOnAction(event -> disable(disable));

        Button logout = new Button("Cerrar sesión");
        logout.setOnAction(event -> logout());

        body.getChildren().addAll(
                title,
                new Label("Email"),
                email,
                verified,
                resend,
                new Separator(),
                new Label("Nombre"),
                name,
                saveProfile,
                new Separator(),
                new Label("Contraseña"),
                currentPassword,
                newPassword,
                confirmation,
                savePassword,
                new Separator(),
                new Label("Passkeys"),
                passkeysBox,
                addPasskey,
                new Separator(),
                new Label("Deshabilitar cuenta"),
                disablePassword,
                disable,
                new Separator(),
                logout,
                message);

        setContent(body);
        renderVerified(user);
        loadPasskeys();
    }

    private void loadPasskeys() {
        Async.run(account::passkeys, this::renderPasskeys,
                failure -> message.setText(Errors.message(failure)));
    }

    private void renderPasskeys(List<Passkey> passkeys) {
        passkeysBox.getChildren().clear();

        if (passkeys.isEmpty()) {
            passkeysBox.getChildren().add(new Label("No hay passkeys registradas."));
            return;
        }

        for (Passkey passkey : passkeys) {
            Label label = new Label(passkey.name() != null && !passkey.name().isBlank()
                    ? passkey.name()
                    : "Passkey");
            Button remove = new Button("Eliminar");
            remove.setOnAction(event -> removePasskey(passkey, remove));
            Region spacer = new Region();
            HBox.setHgrow(spacer, Priority.ALWAYS);
            HBox row = new HBox(8, label, spacer, remove);
            row.setAlignment(Pos.CENTER_LEFT);
            passkeysBox.getChildren().add(row);
        }
    }

    private void addPasskey(Button button) {
        button.setDisable(true);
        button.setText("Esperando passkey…");

        Async.run(() -> {
            context.passkeys().register(context.guard(), context.auth().token(), "Desktop");
            return account.passkeys();
        }, passkeys -> {
            renderPasskeys(passkeys);
            message.setText("Passkey añadida.");
            button.setDisable(false);
            button.setText("Añadir passkey");
        }, failure -> {
            message.setText(Errors.message(failure));
            button.setDisable(false);
            button.setText("Añadir passkey");
        });
    }

    private void removePasskey(Passkey passkey, Button button) {
        button.setDisable(true);

        Async.run(() -> {
            account.removePasskey(passkey.id());
            return account.passkeys();
        }, passkeys -> {
            renderPasskeys(passkeys);
            message.setText("Passkey eliminada.");
        }, failure -> {
            button.setDisable(false);
            message.setText(Errors.message(failure));
        });
    }

    private void saveProfile(Button save) {
        save.setDisable(true);

        Async.run(() -> {
            account.updateProfile(name.getText().trim(), email.getText().trim());
            return context.auth().refreshUser();
        }, user -> {
            renderVerified(user);
            message.setText("Perfil actualizado.");
            save.setDisable(false);
        }, failure -> {
            message.setText(Errors.message(failure));
            save.setDisable(false);
        });
    }

    private void savePassword(Button save) {
        if (!newPassword.getText().equals(confirmation.getText())) {
            message.setText("Las contraseñas no coinciden.");
            return;
        }

        save.setDisable(true);

        Async.run(() -> {
            account.changePassword(currentPassword.getText(), newPassword.getText(), confirmation.getText());
            return null;
        }, ignored -> {
            currentPassword.clear();
            newPassword.clear();
            confirmation.clear();
            message.setText("Contraseña actualizada.");
            save.setDisable(false);
        }, failure -> {
            message.setText(Errors.message(failure));
            save.setDisable(false);
        });
    }

    private void resendVerification() {
        Async.run(() -> {
            account.resendVerification();
            return null;
        }, ignored -> message.setText("Te enviamos un nuevo enlace de verificación."),
                failure -> message.setText(Errors.message(failure)));
    }

    private void disable(Button disable) {
        disable.setDisable(true);

        Async.run(() -> {
            account.disable(disablePassword.getText(), null);
            context.auth().logout();
            return null;
        }, ignored -> onLogout.run(), failure -> {
            message.setText(Errors.message(failure));
            disable.setDisable(false);
        });
    }

    private void logout() {
        Async.run(() -> {
            context.auth().logout();
            return null;
        }, ignored -> onLogout.run(), error -> onLogout.run());
    }

    private void renderVerified(AuthUser user) {
        boolean ok = user != null && user.emailVerified();
        verified.setText("Email: " + (user != null ? user.email() : "—")
                + (ok ? " · verificado" : " · sin verificar"));
    }
}

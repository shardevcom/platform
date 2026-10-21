package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.account.Preferences;
import java.util.LinkedHashMap;
import java.util.Map;
import javafx.geometry.Insets;
import javafx.scene.control.Button;
import javafx.scene.control.CheckBox;
import javafx.scene.control.ComboBox;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.control.TextField;
import javafx.scene.layout.HBox;
import javafx.scene.layout.VBox;

/** Interface and notification preferences stored per account. */
public final class PreferencesView extends ScrollPane {

    private final AccountApi account;
    private final VBox body = new VBox(12);
    private final Label message = new Label();
    private final ComboBox<String> theme = new ComboBox<>();
    private final TextField language = new TextField();
    private final Map<String, CheckBox> channels = new LinkedHashMap<>();

    public PreferencesView(AccountApi account) {
        this.account = account;

        setFitToWidth(true);
        body.setPadding(new Insets(24));

        Label title = new Label("Preferencias");
        title.getStyleClass().add("view-title");

        theme.getItems().addAll("system", "light", "dark");
        language.setPromptText("Idioma");

        Button save = new Button("Guardar preferencias");
        save.setOnAction(event -> save(save));

        message.setWrapText(true);

        body.getChildren().addAll(title, new Label("Tema"), theme, new Label("Idioma"), language, save, message);
        setContent(body);

        load();
    }

    private void load() {
        Async.run(account::preferences, this::render, failure -> message.setText(Errors.message(failure)));
    }

    private void render(Preferences preferences) {
        theme.setValue(preferences.theme());
        language.setText(preferences.language());

        body.getChildren().removeIf(node -> node instanceof CheckBox);
        channels.clear();

        for (Map.Entry<String, Boolean> entry : preferences.notifications().entrySet()) {
            CheckBox check = new CheckBox(entry.getKey());
            check.setSelected(entry.getValue());
            channels.put(entry.getKey(), check);
            body.getChildren().add(check);
        }
    }

    private void save(Button save) {
        Map<String, Boolean> notifications = new LinkedHashMap<>();
        channels.forEach((channel, check) -> notifications.put(channel, check.isSelected()));

        Preferences preferences = new Preferences(theme.getValue(), language.getText().trim(), notifications);

        save.setDisable(true);
        Async.run(() -> account.savePreferences(preferences),
                saved -> {
                    message.setText("Preferencias guardadas.");
                    save.setDisable(false);
                },
                failure -> {
                    message.setText(Errors.message(failure));
                    save.setDisable(false);
                });
    }
}

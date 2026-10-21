package dev.shardev.core.ui;

import dev.shardev.core.account.AccountApi;
import dev.shardev.core.account.AccountNotification;
import dev.shardev.core.account.NotificationsFeed;
import javafx.geometry.Insets;
import javafx.geometry.Pos;
import javafx.scene.control.Button;
import javafx.scene.control.Label;
import javafx.scene.control.ScrollPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;
import javafx.scene.layout.VBox;

/** The signed-in account's notification inbox. */
public final class NotificationsView extends ScrollPane {

    private final AccountApi account;
    private final VBox body = new VBox(8);
    private final Label heading = new Label("Notificaciones");
    private final Button readAll = new Button("Marcar todas como leídas");

    public NotificationsView(AccountApi account) {
        this.account = account;

        setFitToWidth(true);
        body.setPadding(new Insets(24));

        heading.getStyleClass().add("view-title");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox top = new HBox(12, heading, spacer, readAll);
        top.setAlignment(Pos.CENTER_LEFT);

        readAll.setOnAction(event -> act(account::readAllNotifications));

        body.getChildren().add(top);
        setContent(body);

        load();
    }

    private void load() {
        Async.run(account::notifications, this::render, failure -> body.getChildren().add(new Label(Errors.message(failure))));
    }

    private void render(NotificationsFeed feed) {
        while (body.getChildren().size() > 1) {
            body.getChildren().remove(1);
        }

        heading.setText("Notificaciones (" + feed.unreadCount() + ")");
        readAll.setDisable(feed.unreadCount() == 0);

        if (feed.items().isEmpty()) {
            body.getChildren().add(new Label("No tienes notificaciones."));
            return;
        }

        for (AccountNotification notification : feed.items()) {
            body.getChildren().add(card(notification));
        }
    }

    private HBox card(AccountNotification notification) {
        Label title = new Label(notification.type());
        title.setStyle("-fx-font-weight: bold;");
        Label data = new Label(notification.data().toString());
        data.setWrapText(true);
        VBox text = new VBox(4, title, data);
        HBox.setHgrow(text, Priority.ALWAYS);

        Button read = new Button("Leída");
        read.setDisable(notification.read());
        read.setOnAction(event -> act(() -> account.readNotification(notification.id())));

        Button delete = new Button("Eliminar");
        delete.setOnAction(event -> act(() -> account.deleteNotification(notification.id())));

        HBox card = new HBox(12, text, read, delete);
        card.setPadding(new Insets(12));
        card.getStyleClass().add("card");
        card.setAlignment(Pos.CENTER_LEFT);
        return card;
    }

    private void act(Action action) {
        Async.run(() -> {
            action.run();
            return null;
        }, ignored -> load(), failure -> body.getChildren().add(new Label(Errors.message(failure))));
    }

    @FunctionalInterface
    private interface Action {
        void run();
    }
}

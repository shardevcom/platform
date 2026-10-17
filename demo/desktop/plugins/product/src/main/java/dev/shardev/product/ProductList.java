package dev.shardev.product;

import dev.shardev.core.ui.Async;
import dev.shardev.core.ui.Errors;
import dev.shardev.pluginkit.PluginContext;
import java.util.concurrent.Callable;
import javafx.collections.FXCollections;
import javafx.collections.ObservableList;
import javafx.geometry.Insets;
import javafx.scene.control.Alert;
import javafx.scene.control.Button;
import javafx.scene.control.ButtonType;
import javafx.scene.control.Label;
import javafx.scene.control.ListCell;
import javafx.scene.control.ListView;
import javafx.scene.layout.BorderPane;
import javafx.scene.layout.HBox;
import javafx.scene.layout.Priority;
import javafx.scene.layout.Region;

/**
 * Shared product list, gated by {@link ProductCapabilities}.
 *
 * <p>The three guard views differ only in the capabilities they pass, exactly
 * like the web and mobile clients: the resource logic lives here, once.
 */
public final class ProductList extends BorderPane {

    private final ProductApi api;
    private final ProductCapabilities capabilities;
    private final ObservableList<Product> products = FXCollections.observableArrayList();
    private final ListView<Product> list = new ListView<>(products);

    public ProductList(PluginContext context, ProductCapabilities capabilities, String title) {
        this.api = new ProductApi(context);
        this.capabilities = capabilities;

        setPadding(new Insets(16));

        Label heading = new Label(title);
        heading.getStyleClass().add("view-title");

        Region spacer = new Region();
        HBox.setHgrow(spacer, Priority.ALWAYS);

        HBox toolbar = new HBox(8, heading, spacer);
        if (capabilities.create()) {
            Button create = new Button("Nuevo");
            create.setOnAction(event -> openForm(null));
            toolbar.getChildren().add(create);
        }
        if (capabilities.edit()) {
            Button edit = new Button("Editar");
            edit.setOnAction(event -> {
                Product selected = selected();
                if (selected != null) {
                    openForm(selected);
                }
            });
            toolbar.getChildren().add(edit);
        }
        if (capabilities.remove()) {
            Button remove = new Button("Eliminar");
            remove.setOnAction(event -> {
                Product selected = selected();
                if (selected != null) {
                    confirmDelete(selected);
                }
            });
            toolbar.getChildren().add(remove);
        }
        Button refresh = new Button("Refrescar");
        refresh.setOnAction(event -> reload());
        toolbar.getChildren().add(refresh);

        list.setCellFactory(view -> new ListCell<>() {
            @Override
            protected void updateItem(Product product, boolean empty) {
                super.updateItem(product, empty);
                setText(empty || product == null ? null : product.name() + "  ·  " + product.sku());
            }
        });
        list.setPlaceholder(new Label("Sin productos."));

        setTop(toolbar);
        setCenter(list);

        reload();
    }

    private Product selected() {
        return list.getSelectionModel().getSelectedItem();
    }

    private void reload() {
        Async.run(api::list,
                rows -> products.setAll(rows),
                error -> alert(Alert.AlertType.ERROR, "No se pudieron cargar los productos", Errors.message(error)));
    }

    private void openForm(Product existing) {
        new ProductForm(existing).showAndWait().ifPresent(input ->
                run(() -> existing == null ? api.create(input) : api.update(existing.id(), input)));
    }

    private void confirmDelete(Product product) {
        Alert alert = new Alert(Alert.AlertType.CONFIRMATION,
                "¿Eliminar «" + product.name() + "»?", ButtonType.OK, ButtonType.CANCEL);
        alert.setHeaderText("Eliminar producto");
        alert.showAndWait()
                .filter(ButtonType.OK::equals)
                .ifPresent(button -> run(() -> {
                    api.remove(product.id());
                    return null;
                }));
    }

    private void run(Callable<?> task) {
        Async.run(task,
                ignored -> reload(),
                error -> alert(Alert.AlertType.ERROR, "Operación fallida", Errors.message(error)));
    }

    private static void alert(Alert.AlertType type, String title, String message) {
        Alert alert = new Alert(type, message, ButtonType.OK);
        alert.setHeaderText(title);
        alert.showAndWait();
    }
}

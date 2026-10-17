package dev.shardev.product;

import javafx.scene.control.ButtonBar;
import javafx.scene.control.ButtonType;
import javafx.scene.control.Dialog;
import javafx.scene.control.Label;
import javafx.scene.control.TextField;
import javafx.scene.layout.GridPane;

/** Create/edit dialog returning a {@link ProductInput} on accept. */
final class ProductForm extends Dialog<ProductInput> {

    ProductForm(Product existing) {
        setTitle(existing == null ? "Nuevo producto" : "Editar producto");
        setHeaderText(null);

        TextField sku = new TextField(existing == null ? "" : existing.sku());
        TextField name = new TextField(existing == null ? "" : existing.name());
        TextField ean = new TextField(existing == null || existing.ean() == null ? "" : existing.ean());

        GridPane grid = new GridPane();
        grid.setHgap(8);
        grid.setVgap(8);
        grid.addRow(0, new Label("SKU"), sku);
        grid.addRow(1, new Label("Nombre"), name);
        grid.addRow(2, new Label("EAN"), ean);
        getDialogPane().setContent(grid);

        ButtonType save = new ButtonType(existing == null ? "Crear" : "Guardar", ButtonBar.ButtonData.OK_DONE);
        getDialogPane().getButtonTypes().addAll(save, ButtonType.CANCEL);

        setResultConverter(button -> {
            if (button != save) {
                return null;
            }
            String eanText = ean.getText().trim();
            return new ProductInput(
                    sku.getText().trim(),
                    name.getText().trim(),
                    eanText.isEmpty() ? null : eanText,
                    null,
                    true);
        });
    }
}

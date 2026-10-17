package dev.shardev.product;

/** What a guard's product view is allowed to do (mirrors the token abilities). */
public record ProductCapabilities(boolean create, boolean edit, boolean remove, boolean transfer) {

    public static final ProductCapabilities READ_ONLY = new ProductCapabilities(false, false, false, false);
    public static final ProductCapabilities MANAGE = new ProductCapabilities(true, true, false, false);
    public static final ProductCapabilities FULL = new ProductCapabilities(true, true, true, true);
}

package dev.shardev.pluginkit;

/**
 * A navigation entry contributed by a plugin for the active guard.
 *
 * <p>Lower {@code order} comes first. Core items use 0/90+; plugins default to
 * {@value #DEFAULT_ORDER} so a plugin sits right after the dashboard but before
 * the account section.
 *
 * @param label menu text
 * @param to absolute route path, e.g. {@code /products}
 * @param icon optional icon key resolved by the shell
 * @param order sort order, lower first
 */
public record NavItem(String label, String to, String icon, int order) {

    public static final int DEFAULT_ORDER = 10;

    public NavItem(String label, String to) {
        this(label, to, null, DEFAULT_ORDER);
    }

    public NavItem(String label, String to, int order) {
        this(label, to, null, order);
    }
}

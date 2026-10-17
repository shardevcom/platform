package dev.shardev.core.ui;

import dev.shardev.core.api.ApiError;

/** Turns a thrown error into a message safe to show to the user. */
public final class Errors {

    private Errors() {
    }

    public static String message(Throwable error) {
        if (error instanceof ApiError api) {
            return api.getMessage();
        }
        String message = error.getMessage();
        return message != null && !message.isBlank() ? message : error.toString();
    }
}

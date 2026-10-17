package dev.shardev.core.ui;

import java.util.concurrent.Callable;
import java.util.function.Consumer;
import javafx.application.Platform;

/** Runs a blocking task off the FX thread and hops back to it to publish the result. */
public final class Async {

    private Async() {
    }

    public static <T> void run(Callable<T> task, Consumer<T> onSuccess, Consumer<Throwable> onError) {
        Thread thread = new Thread(() -> {
            try {
                T result = task.call();
                Platform.runLater(() -> onSuccess.accept(result));
            } catch (Throwable error) {
                Platform.runLater(() -> onError.accept(error));
            }
        }, "shardev-async");
        thread.setDaemon(true);
        thread.start();
    }
}

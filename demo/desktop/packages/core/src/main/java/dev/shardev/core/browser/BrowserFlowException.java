package dev.shardev.core.browser;

/** A passkey ceremony could not be completed (cancelled, timed out or rejected). */
public final class BrowserFlowException extends RuntimeException {

    public BrowserFlowException(String message) {
        super(message);
    }
}

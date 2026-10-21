package dev.shardev.core.passkeys;

/** A passkey ceremony could not be completed (cancelled, timed out or rejected). */
public final class PasskeyFlowException extends RuntimeException {

    public PasskeyFlowException(String message) {
        super(message);
    }
}

package dev.shardev.core.api;

import java.util.Map;

/**
 * Error carrying the platform's semantic code ({@code FORBIDDEN},
 * {@code VALIDATION_FAILED}, ...) plus the HTTP status, so callers branch on
 * meaning instead of parsing messages.
 */
public class ApiError extends RuntimeException {

    private final String code;
    private final int status;
    private final Map<String, Object> details;

    public ApiError(String message, String code, int status, Map<String, Object> details) {
        super(message);
        this.code = code;
        this.status = status;
        this.details = details;
    }

    public String code() {
        return code;
    }

    public int status() {
        return status;
    }

    public Map<String, Object> details() {
        return details;
    }
}

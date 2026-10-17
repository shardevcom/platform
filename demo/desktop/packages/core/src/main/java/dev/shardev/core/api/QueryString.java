package dev.shardev.core.api;

import java.net.URLEncoder;
import java.nio.charset.StandardCharsets;
import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * Serializes the query shape the REST adapter expects: nested objects as
 * {@code filter[sku]}, lists as {@code sort[]}, scalars verbatim.
 */
public final class QueryString {

    private QueryString() {
    }

    public static String build(Map<String, Object> query) {
        if (query == null || query.isEmpty()) {
            return "";
        }

        List<String> parts = new ArrayList<>();
        query.forEach((key, value) -> append(parts, key, value));

        return parts.isEmpty() ? "" : "?" + String.join("&", parts);
    }

    private static void append(List<String> parts, String key, Object value) {
        if (value == null) {
            return;
        }

        if (value instanceof Iterable<?> items) {
            for (Object item : items) {
                append(parts, key + "[]", item);
            }
        } else if (value instanceof Map<?, ?> map) {
            map.forEach((nestedKey, nestedValue) -> append(parts, key + "[" + nestedKey + "]", nestedValue));
        } else {
            String text = String.valueOf(value);
            if (!text.isEmpty()) {
                parts.add(encode(key) + "=" + encode(text));
            }
        }
    }

    private static String encode(String value) {
        return URLEncoder.encode(value, StandardCharsets.UTF_8);
    }
}

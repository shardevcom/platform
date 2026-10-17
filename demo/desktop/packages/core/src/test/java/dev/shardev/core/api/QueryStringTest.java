package dev.shardev.core.api;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertTrue;

import java.util.List;
import java.util.Map;
import org.junit.jupiter.api.Test;

class QueryStringTest {

    @Test
    void emptyWhenThereIsNoQuery() {
        assertEquals("", QueryString.build(null));
        assertEquals("", QueryString.build(Map.of()));
    }

    @Test
    void serializesNestedFilterAndListSort() {
        Map<String, Object> query = Map.of(
                "filter", Map.of("sku", "A"),
                "sort", List.of("name", "-sku"),
                "page", 2);

        String result = QueryString.build(query);

        assertTrue(result.startsWith("?"));
        assertTrue(result.contains("filter%5Bsku%5D=A"));
        assertTrue(result.contains("sort%5B%5D=name"));
        assertTrue(result.contains("sort%5B%5D=-sku"));
        assertTrue(result.contains("page=2"));
    }
}

package dev.shardev.core.api;

import static org.junit.jupiter.api.Assertions.assertEquals;

import com.google.gson.JsonParser;
import org.junit.jupiter.api.Test;

class ApiEnvelopeTest {

    @Test
    void decodesDataAndMeta() {
        var json = JsonParser.parseString("""
                {"status":"success","code":"OK","message":"ok",
                 "data":{"id":"1"},"meta":{"request_id":"r-1"}}
                """).getAsJsonObject();

        ApiEnvelope envelope = ApiEnvelope.fromJson(json);

        assertEquals("OK", envelope.code());
        assertEquals("1", envelope.data().getAsJsonObject().get("id").getAsString());
        assertEquals("r-1", envelope.meta().get("request_id").getAsString());
    }
}

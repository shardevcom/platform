package dev.shardev.core.browser;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertThrows;

import java.net.URI;
import java.net.http.HttpClient;
import java.net.http.HttpRequest;
import java.net.http.HttpResponse;
import java.time.Duration;
import org.junit.jupiter.api.Test;

class LoopbackReceiverTest {

    @Test
    void completesWithTheValuesFromTheCallback() throws Exception {
        try (LoopbackReceiver receiver = LoopbackReceiver.start("state-1", Duration.ofSeconds(5))) {
            HttpResponse<String> response = get(receiver.callbackUrl() + "?state=state-1&token=tok");
            LoopbackReceiver.Callback callback = receiver.await();

            assertEquals(200, response.statusCode());
            assertEquals("tok", callback.token());
        }
    }

    @Test
    void ignoresACallbackWithTheWrongState() throws Exception {
        try (LoopbackReceiver receiver = LoopbackReceiver.start("state-1", Duration.ofSeconds(5))) {
            assertEquals(400, get(receiver.callbackUrl() + "?state=wrong&token=evil").statusCode());
            assertEquals(200, get(receiver.callbackUrl() + "?state=state-1&status=registered").statusCode());

            assertEquals("registered", receiver.await().status());
        }
    }

    @Test
    void timesOutWithoutACallback() {
        try (LoopbackReceiver receiver = LoopbackReceiver.start("state-1", Duration.ofMillis(100))) {
            assertThrows(BrowserFlowException.class, receiver::await);
        }
    }

    private static HttpResponse<String> get(String url) throws Exception {
        HttpClient client = HttpClient.newHttpClient();

        return client.send(
                HttpRequest.newBuilder(URI.create(url)).GET().build(),
                HttpResponse.BodyHandlers.ofString());
    }
}

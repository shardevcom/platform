package dev.shardev.core.account;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import java.util.ArrayList;
import java.util.List;

/** {@code items} plus {@code unread_count} from {@code GET auth/notifications}. */
public record NotificationsFeed(List<AccountNotification> items, int unreadCount) {

    public NotificationsFeed {
        items = List.copyOf(items);
    }

    public static NotificationsFeed fromJson(JsonObject json) {
        List<AccountNotification> items = new ArrayList<>();

        JsonElement raw = json.get("items");
        if (raw != null && raw.isJsonArray()) {
            JsonArray array = raw.getAsJsonArray();
            for (JsonElement item : array) {
                if (item.isJsonObject()) {
                    items.add(AccountNotification.fromJson(item.getAsJsonObject()));
                }
            }
        }

        int unread = json.has("unread_count") && !json.get("unread_count").isJsonNull()
                ? json.get("unread_count").getAsInt()
                : 0;

        return new NotificationsFeed(items, unread);
    }
}

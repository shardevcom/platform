# Communications

Multi-channel communications for Shardev: event/channel matrix, per-channel
templates, opt-in/opt-out preferences, delivery history, admin alerts and
provider delivery receipts.

## Data model

| Table | Purpose |
| --- | --- |
| `communication_events` | Event catalogue (`key`, `name`, `category`, `active`). |
| `communication_event_channels` | Enabled channels per event. A missing row means disabled. |
| `communication_templates` | Template per `(event, channel, locale)`. |
| `communication_preferences` | Opt-out rows. `*` means all channels/events; no row means opt-in. |
| `communication_messages` | One delivery-history row per event x channel x recipient attempt. |

`category=admin` marks operational alert events; only those can be sent through
`AdminNotifier`/`POST .../alerts`.

## Dispatch

```php
use Shardevcom\Communications\Application\CommunicationDispatcher;
use Shardevcom\Communications\Domain\Data\Recipient;

$dispatcher->dispatch(
    'user.registered',
    Recipient::user('42', 'web', ['email' => 'ana@example.com']),
    ['name' => 'Ana'],
    'es',
);
```

The dispatcher resolves the event, enabled channels, locale template and
recipient address. Messages are logged before delivery and preferences are
checked again in the worker, so an opt-out between enqueue and delivery still
wins.

## API

Routes are registered per platform. The `web` platform has no prefix; others
use their configured prefix (e.g. `/admin`).

### Self-service preferences (any authenticated user)

```
GET  /api/v1/communications/preferences
POST /api/v1/communications/preferences/opt-out
POST /api/v1/communications/preferences/opt-in
```

Body: `channel` (optional), `event_key` (optional), `reason` (opt-out only).
Omitting `channel`/`event_key` means all channels/events.

### Admin (platforms listed in `communications.admin.guards`, default `admin`)

Scopes: `communications.view`, `communications.update`, `communications.send`.

```
GET  /admin/api/v1/communications/admin/events
PUT  /admin/api/v1/communications/admin/events/{eventKey}/channels
GET  /admin/api/v1/communications/admin/events/{eventKey}/templates
PUT  /admin/api/v1/communications/admin/events/{eventKey}/templates
GET  /admin/api/v1/communications/admin/messages
POST /admin/api/v1/communications/admin/alerts
```

Channel update body: `{"channels": ["email", "in_app"]}` (replaces the matrix).
Template body: `channel`, `locale`, `subject`, `body`, `format`, `metadata`,
`active`. History filters: `event_key`, `channel`, `status`, `recipient_type`,
`recipient_id`, `recipient_guard`, `from`, `to`, `page`, `per_page`.

### Provider webhooks

```
POST /api/v1/communications/webhooks/{provider}
Header: X-Communications-Signature: sha256=<HMAC-SHA256(raw body, secret)>
```

Body:

```json
{"message_id": "provider-message-id", "status": "delivered", "error": null}
```

Accepted statuses: `queued`, `sent`/`accepted`, `delivered`, `read`,
`failed`/`undelivered`/`bounced`/`rejected`. Unknown message/provider
combinations return `404`; invalid signatures `401`; an unconfigured secret
returns `503`.

Canonical JSON/form bodies are accepted directly. Twilio form callbacks
(`MessageSid`, `MessageStatus`) and Meta WhatsApp nested `statuses[0]` payloads
are normalized automatically. For native Twilio verification set
`COMMUNICATIONS_TWILIO_WEBHOOK_DRIVER=twilio` and `TWILIO_AUTH_TOKEN`; the
controller validates `X-Twilio-Signature` (HMAC-SHA1 over URL + sorted form
params). For Meta, set `COMMUNICATIONS_WEBHOOK_HEADER=X-Hub-Signature-256` and
use the Meta app secret as `COMMUNICATIONS_WEBHOOK_SECRET`.

## Configuration

| Env | Default | Purpose |
| --- | --- | --- |
| `COMMUNICATIONS_DEFAULT_LOCALE` | `en` | Recipient/template locale. |
| `COMMUNICATIONS_FALLBACK_LOCALE` | `en` | Locale used when the template is missing. |
| `COMMUNICATIONS_STRICT_VARIABLES` | `false` | Missing template variables fail validation. |
| `COMMUNICATIONS_QUEUE` | `false` | Enqueue instead of sending synchronously. |
| `COMMUNICATIONS_QUEUE_CONNECTION` / `_NAME` | `null` | Queue routing. |
| `COMMUNICATIONS_QUEUE_TRIES` | `3` | Worker attempts before the final failure. |
| `COMMUNICATIONS_SMS_DRIVER` / `_URL` / `_TOKEN` | `log` / `null` | SMS: `log`, `http`, `twilio`. |
| `TWILIO_ACCOUNT_SID` / `TWILIO_AUTH_TOKEN` / `TWILIO_SMS_FROM` | `null` | Twilio SMS. |
| `COMMUNICATIONS_WHATSAPP_DRIVER` / `_URL` / `_TOKEN` | `log` / `null` | WhatsApp: `log`, `http`, `twilio`, `meta_whatsapp`. |
| `TWILIO_WHATSAPP_FROM` | `null` | Twilio WhatsApp sender. |
| `META_WHATSAPP_PHONE_NUMBER_ID` / `META_WHATSAPP_VERSION` | `null` / `v20.0` | Meta WhatsApp Cloud API. |
| `COMMUNICATIONS_PUSH_DRIVER` / `_URL` / `_TOKEN` | `log` / `null` | Push: `log`, `http`, `fcm`. |
| `FCM_PROJECT_ID` | `null` | Firebase Cloud Messaging HTTP v1. |
| `COMMUNICATIONS_ADMIN_RESOLVER` | `config` | `config` or `database`. |
| `COMMUNICATIONS_ADMIN_GUARD` / `_MODEL` / `_ROLE_COLUMN` | `admin` / platform model / `role` | Database admin directory. |
| `COMMUNICATIONS_LISTEN_REGISTERED` | `true` | Bridge Laravel `Registered` to a communications event. |
| `COMMUNICATIONS_REGISTERED_EVENT` | `user.registered` | Event used by the registration bridge. |
| `COMMUNICATIONS_WEBHOOK_SECRET` | `null` | Enables generic HMAC-SHA256 webhooks. |
| `COMMUNICATIONS_WEBHOOK_HEADER` | `X-Communications-Signature` | Signature header (use `X-Hub-Signature-256` for Meta). |
| `COMMUNICATIONS_TWILIO_WEBHOOK_DRIVER` | `null` | Set to `twilio` for native Twilio signature checks. |

Drivers: `mail`, `in_app`/`database`, `http` (generic JSON provider), `twilio`
(SMS/WhatsApp), `meta_whatsapp` (Meta Cloud API), `fcm` (Firebase HTTP v1) and
`log` (safe local fallback). Email metadata may include
`{"attachments": [{"path": "/tmp/file.pdf", "name": "file.pdf", "mime": "application/pdf"}]}`
or `{"data": "raw-bytes", "name": "file.txt"}`.

When `communications.queue.enabled=true`, delivery failures are rethrown from
`SendCommunicationMessageJob`, so Laravel retries with `tries`/`backoff`; the
history row keeps the last `FAILED` error and attempt count.

## Bootstrap events and templates

Define them in `config/communications.php` under `events` and run:

```bash
php artisan communications:sync
php artisan communications:sync --event=user.registered
```

The command is idempotent: events/templates are created or updated, and
configured channel matrices are replaced. It never deletes events omitted from
config.

## Domain events and admin directory

By default `Illuminate\Auth\Events\Registered` (dispatched by the security
registration controller) is bridged to the configured event (default
`user.registered`). The guard is inferred from the registered model, so web,
admin and employee users keep separate preference namespaces. Disable it with
`COMMUNICATIONS_LISTEN_REGISTERED=false`, or remap it with
`COMMUNICATIONS_REGISTERED_EVENT`. If the event is missing/disabled, registration
is unaffected.

For an admin directory stored in a database, set
`COMMUNICATIONS_ADMIN_RESOLVER=database` and configure
`communications.admin.database`: `model` defaults to the platform model,
`role_column` is queried with the requested roles, and address columns default
to `email`, `phone`, `push_token`, `locale`, `id`. Set `active_column` to
exclude disabled rows. A many-to-many/team role model should bind its own
`AdminRecipientResolver`.

## Extension points

- `AdminRecipientResolver`: replace the config-driven resolver with a database
  role/team lookup. It receives the requested roles and returns `Recipient`s.
- `ChannelTransport` / `TransportRegistry`: add a provider-specific adapter.
- `MessageLog`, `MessageHistory`, `TemplateStore`, `PreferenceStore`,
  `EventConfiguration`: replace persistence without touching use cases.
- `CommunicationQueue`: route jobs to another queue backend.

## Tests

```bash
composer test                 # framework unit suite
composer test --working-dir=demo/api   # feature suite, including the API
```

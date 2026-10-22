<?php

declare(strict_types=1);

/*
 * Communications configuration.
 *
 * The event/channel matrix and the templates live in the database so an
 * administrator can change them without a deployment. This file only wires
 * infrastructure (transports, locales, queueing and admin recipients).
 *
 * Events are stored in `communication_events` with one row per enabled
 * channel in `communication_event_channels`. A channel with no row is
 * disabled; a row with `enabled = false` is explicitly disabled.
 */
return [

    'default_locale' => env('COMMUNICATIONS_DEFAULT_LOCALE', 'en'),

    'fallback_locale' => env('COMMUNICATIONS_FALLBACK_LOCALE', 'en'),

    'templates' => [
        // When true, a template variable missing from the payload is a
        // ValidationFailed instead of being rendered as an empty string.
        'strict_variables' => (bool) env('COMMUNICATIONS_STRICT_VARIABLES', false),
    ],

    'queue' => [
        // Enabled queues the already-validated/logged message and lets a
        // worker deliver it. Preferences are checked again before delivery.
        'enabled' => (bool) env('COMMUNICATIONS_QUEUE', false),
        'connection' => env('COMMUNICATIONS_QUEUE_CONNECTION'),
        'queue' => env('COMMUNICATIONS_QUEUE_NAME'),
        // Delivery failures are rethrown from the worker path so the queue
        // retries them; the final attempt remains FAILED in the history.
        'tries' => (int) env('COMMUNICATIONS_QUEUE_TRIES', 3),
        'backoff' => [10, 60],
    ],

    /*
     * One transport per channel.
     *
     * Drivers:
     * - mail: Laravel mailer (email)
     * - in_app: Laravel database notifications (in-app panel)
     * - http: generic JSON POST to an external provider (SMS/WhatsApp/push)
     * - log: safe local fallback; records the send in the application log
     */
    'channels' => [
        'email' => [
            'driver' => 'mail',
        ],
        'sms' => [
            // log | http | twilio
            'driver' => env('COMMUNICATIONS_SMS_DRIVER', 'log'),
            'url' => env('COMMUNICATIONS_SMS_URL'),
            'token' => env('COMMUNICATIONS_SMS_TOKEN'),
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_SMS_FROM'),
            'timeout' => (int) env('COMMUNICATIONS_SMS_TIMEOUT', 10),
            'provider' => env('COMMUNICATIONS_SMS_PROVIDER', 'sms'),
        ],
        'whatsapp' => [
            // log | http | twilio | meta_whatsapp
            'driver' => env('COMMUNICATIONS_WHATSAPP_DRIVER', 'log'),
            'url' => env('COMMUNICATIONS_WHATSAPP_URL'),
            'token' => env('COMMUNICATIONS_WHATSAPP_TOKEN'),
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
            'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),
            'version' => env('META_WHATSAPP_VERSION', 'v20.0'),
            'timeout' => (int) env('COMMUNICATIONS_WHATSAPP_TIMEOUT', 10),
            'provider' => env('COMMUNICATIONS_WHATSAPP_PROVIDER', 'whatsapp'),
        ],
        'push' => [
            // log | http | fcm
            'driver' => env('COMMUNICATIONS_PUSH_DRIVER', 'log'),
            'url' => env('COMMUNICATIONS_PUSH_URL'),
            'token' => env('COMMUNICATIONS_PUSH_TOKEN'),
            'project_id' => env('FCM_PROJECT_ID'),
            'timeout' => (int) env('COMMUNICATIONS_PUSH_TIMEOUT', 10),
            'provider' => env('COMMUNICATIONS_PUSH_PROVIDER', 'push'),
        ],
        'in_app' => [
            'driver' => 'in_app',
            // Optional explicit notifiable model. When null, the model of the
            // recipient guard in config/platforms.php is used.
            'model' => null,
        ],
    ],

    /*
     * Internal admin alerts.
     *
     * Each recipient may declare a guard, id, locale, roles and channel
     * addresses. Roles are only metadata for filtering: the module does not
     * assume a role storage. Bind AdminRecipientResolver to replace this
     * config-driven resolver with your own role/team lookup.
     */
    'admin' => [
        // Platforms whose routes expose the admin surface.
        'guards' => ['admin'],

        // config: use the recipients list below.
        // database: query the platform model's role column.
        'resolver' => env('COMMUNICATIONS_ADMIN_RESOLVER', 'config'),

        'database' => [
            'guard' => env('COMMUNICATIONS_ADMIN_GUARD', 'admin'),
            'model' => env('COMMUNICATIONS_ADMIN_MODEL'),
            'role_column' => env('COMMUNICATIONS_ADMIN_ROLE_COLUMN', 'role'),
            'id_column' => 'id',
            'email_column' => 'email',
            'phone_column' => 'phone',
            'push_token_column' => 'push_token',
            'locale_column' => 'locale',
            // Optional boolean column to exclude disabled admins.
            'active_column' => null,
        ],

        'recipients' => [
            // [
            //     'id' => '1',
            //     'guard' => 'admin',
            //     'email' => 'ops@example.com',
            //     'phone' => '+34600000000',
            //     'push_token' => null,
            //     'roles' => ['superadmin', 'ops'],
            //     'locale' => 'es',
            // ],
        ],
    ],

    /*
     * Provider webhooks for delivery receipts.
     *
     * POST /api/v1/communications/webhooks/{provider}
     * Header: X-Communications-Signature: sha256=<HMAC of the raw body>
     *
     * Payload: {"message_id": "provider-id", "status": "delivered", "error": null}
     * Accepted statuses: sent, delivered, read, failed/bounced/rejected.
     */
    'webhooks' => [
        'secret' => env('COMMUNICATIONS_WEBHOOK_SECRET'),
        'header' => env('COMMUNICATIONS_WEBHOOK_HEADER', 'X-Communications-Signature'),

        // Provider-specific native signature verification.
        // Twilio signs (URL + sorted form params) with HMAC-SHA1.
        'providers' => [
            'twilio' => [
                'driver' => env('COMMUNICATIONS_TWILIO_WEBHOOK_DRIVER'),
                'auth_token' => env('TWILIO_AUTH_TOKEN'),
            ],
        ],
    ],

    /*
     * Optional bootstrap matrix + templates. Run:
     *   php artisan communications:sync
     *
     * Example:
     * 'events' => [
     *     'user.registered' => [
     *         'name' => 'User registered',
     *         'category' => 'transactional',
     *         'channels' => ['email', 'in_app'],
     *         'templates' => [
     *             'email' => [
     *                 'en' => ['subject' => 'Welcome', 'body' => '<p>Welcome {{ name }}</p>'],
     *                 'es' => ['subject' => 'Bienvenido', 'body' => '<p>Hola {{ name }}</p>'],
     *             ],
     *             'in_app' => [
     *                 'en' => ['body' => 'Welcome {{ name }}'],
     *             ],
     *         ],
     *     ],
     * ],
     */
    'events' => [],

    /*
     * Bridges from framework/domain events to communications events.
     *
     * The registered listener infers the platform from the user model, so
     * `user.registered` can be disabled or remapped without touching code.
     */
    'listeners' => [
        'registered' => [
            'enabled' => (bool) env('COMMUNICATIONS_LISTEN_REGISTERED', true),
            'event' => env('COMMUNICATIONS_REGISTERED_EVENT', 'user.registered'),
        ],
    ],

];

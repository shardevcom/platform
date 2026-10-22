<?php

declare(strict_types=1);

namespace Shardevcom\Communications;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Communications\Application\AdminNotifier;
use Shardevcom\Communications\Application\CommunicationDispatcher;
use Shardevcom\Communications\Application\MessageDeliveryService;
use Shardevcom\Communications\Application\PreferenceManager;
use Shardevcom\Communications\Application\TemplateRenderer;
use Shardevcom\Communications\Console\Commands\SyncCommunicationEvents;
use Shardevcom\Communications\Contracts\AdminRecipientResolver;
use Shardevcom\Communications\Contracts\CommunicationQueue;
use Shardevcom\Communications\Contracts\EventConfiguration;
use Shardevcom\Communications\Contracts\MessageHistory;
use Shardevcom\Communications\Contracts\MessageLog;
use Shardevcom\Communications\Contracts\PreferenceStore;
use Shardevcom\Communications\Contracts\TemplateStore;
use Shardevcom\Communications\Contracts\TransportRegistry;
use Shardevcom\Communications\Domain\Enums\Channel;
use Shardevcom\Communications\Infrastructure\Admin\ConfigAdminRecipientResolver;
use Shardevcom\Communications\Infrastructure\Admin\DatabaseAdminRecipientResolver;
use Shardevcom\Communications\Infrastructure\Persistence\EloquentEventConfiguration;
use Shardevcom\Communications\Infrastructure\Persistence\EloquentMessageHistory;
use Shardevcom\Communications\Infrastructure\Persistence\EloquentMessageLog;
use Shardevcom\Communications\Infrastructure\Persistence\EloquentPreferenceStore;
use Shardevcom\Communications\Infrastructure\Persistence\EloquentTemplateStore;
use Shardevcom\Communications\Infrastructure\Queue\LaravelCommunicationQueue;
use Shardevcom\Communications\Infrastructure\Transport\HttpTransport;
use Shardevcom\Communications\Infrastructure\Transport\InAppTransport;
use Shardevcom\Communications\Infrastructure\Transport\LogTransport;
use Shardevcom\Communications\Infrastructure\Transport\MailTransport;
use Shardevcom\Communications\Infrastructure\Transport\Providers\FcmTransport;
use Shardevcom\Communications\Infrastructure\Transport\Providers\MetaWhatsAppTransport;
use Shardevcom\Communications\Infrastructure\Transport\Providers\TwilioTransport;
use Shardevcom\Communications\Infrastructure\Transport\TransportManager;
use Shardevcom\Communications\Listeners\SendUserRegisteredCommunication;
use Shardevcom\Core\Contracts\ResourceRegistry;

/**
 * Integration only: contracts -> adapters and config -> transports.
 * No delivery/conversation logic lives here.
 */
final class CommunicationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/communications.php', 'communications');

        $this->app->singleton(EventConfiguration::class, EloquentEventConfiguration::class);
        $this->app->singleton(TemplateStore::class, EloquentTemplateStore::class);
        $this->app->singleton(PreferenceStore::class, EloquentPreferenceStore::class);
        $this->app->singleton(MessageLog::class, EloquentMessageLog::class);
        $this->app->singleton(MessageHistory::class, EloquentMessageHistory::class);
        $this->app->singleton(AdminRecipientResolver::class, function (): AdminRecipientResolver {
            return config('communications.admin.resolver', 'config') === 'database'
                ? new DatabaseAdminRecipientResolver()
                : new ConfigAdminRecipientResolver();
        });
        $this->app->singleton(CommunicationQueue::class, LaravelCommunicationQueue::class);

        $this->app->singleton(TransportRegistry::class, fn (): TransportManager => $this->transports());

        $this->app->singleton(TemplateRenderer::class, fn (): TemplateRenderer => new TemplateRenderer(
            (bool) config('communications.templates.strict_variables', false),
        ));

        $this->app->singleton(MessageDeliveryService::class, fn (Application $app): MessageDeliveryService => new MessageDeliveryService(
            $app->make(MessageLog::class),
            $app->make(PreferenceStore::class),
            $app->make(TransportRegistry::class),
        ));

        $this->app->singleton(CommunicationDispatcher::class, fn (Application $app): CommunicationDispatcher => new CommunicationDispatcher(
            $app->make(EventConfiguration::class),
            $app->make(TemplateStore::class),
            $app->make(PreferenceStore::class),
            $app->make(MessageLog::class),
            $app->make(TemplateRenderer::class),
            $app->make(MessageDeliveryService::class),
            $app->make(CommunicationQueue::class),
            (bool) config('communications.queue.enabled', false),
            (string) config('communications.default_locale', 'en'),
            (string) config('communications.fallback_locale', 'en'),
        ));

        $this->app->singleton(PreferenceManager::class);
        $this->app->singleton(AdminNotifier::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->commands([SyncCommunicationEvents::class]);

        $this->app->make(ResourceRegistry::class)->register(CommunicationResourceDefinition::definition());

        Event::listen(\Illuminate\Auth\Events\Registered::class, SendUserRegisteredCommunication::class);

        $this->loadRoutes();

        $this->publishes([
            __DIR__.'/../config/communications.php' => config_path('communications.php'),
        ], 'communications-config');
    }

    private function loadRoutes(): void
    {
        $platforms = (array) config('platforms.platforms', []);
        $adminGuards = (array) config('communications.admin.guards', ['admin']);

        foreach ($platforms as $platform) {
            $guard = (string) ($platform['guard'] ?? $platform['key'] ?? '');
            $prefix = (string) ($platform['prefix'] ?? '');

            if ($guard === '') {
                continue;
            }

            $apiGuard = (string) ($platform['api_guard'] ?? $guard);
            $routePrefix = $prefix !== '' ? $prefix.'/api/v1' : 'api/v1';
            $middleware = array_merge(['bind.guard'], $platform['middleware'] ?? []);
            $isAdmin = in_array($guard, $adminGuards, true);

            Route::middleware($middleware)
                ->prefix($routePrefix)
                ->group(function () use ($guard, $apiGuard, $isAdmin): void {
                    require __DIR__.'/../routes/preferences.php';

                    if ($isAdmin) {
                        require __DIR__.'/../routes/admin.php';
                    }
                });
        }

        Route::middleware('api.version')
            ->prefix('api/v1/communications/webhooks')
            ->group(__DIR__.'/../routes/webhooks.php');
    }

    private function transports(): TransportManager
    {
        $configured = config('communications.channels', []);
        $transports = [];

        foreach (is_array($configured) ? $configured : [] as $key => $settings) {
            $channel = Channel::tryFrom((string) $key);

            if ($channel === null || ! is_array($settings)) {
                continue;
            }

            $transports[$channel->value] = match ((string) ($settings['driver'] ?? 'log')) {
                'mail' => new MailTransport(),
                'in_app', 'database' => new InAppTransport(),
                'http' => new HttpTransport($channel, $settings),
                'twilio' => new TwilioTransport($channel, $settings),
                'meta_whatsapp', 'meta' => new MetaWhatsAppTransport($settings),
                'fcm', 'firebase' => new FcmTransport($settings),
                default => new LogTransport(),
            };
        }

        return new TransportManager($transports);
    }
}

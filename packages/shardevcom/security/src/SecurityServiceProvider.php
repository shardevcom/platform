<?php

declare(strict_types=1);

namespace Shardevcom\Security;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Shardevcom\Security\Authentication\AuthBootstrapServiceProvider;
use Shardevcom\Security\Authentication\ScopeCatalogue;
use Shardevcom\Security\Http\Controllers\AccountController;
use Shardevcom\Security\Http\Controllers\AuthController;
use Shardevcom\Security\Http\Controllers\EmailVerificationController;
use Shardevcom\Security\Http\Controllers\InvitationController;
use Shardevcom\Security\Http\Controllers\NotificationsController;
use Shardevcom\Security\Http\Controllers\OtpController;
use Shardevcom\Security\Http\Controllers\PasswordController;
use Shardevcom\Security\Http\Controllers\PreferencesController;
use Shardevcom\Security\Http\Controllers\RegistrationController;
use Shardevcom\Security\Http\Controllers\WebAuthnController;
use Shardevcom\Security\Http\Middleware\BindGuard;
use Shardevcom\Security\Http\Middleware\CrossTenantIdorMiddleware;
use Shardevcom\Security\Http\Middleware\CspMiddleware;
use Shardevcom\Security\Http\Middleware\EnsureAccountActive;
use Shardevcom\Security\Http\Middleware\RateLimitMiddleware;
use Shardevcom\Security\Http\Middleware\SecurityHeadersMiddleware;

/**
 * Security package integration.
 *
 * Registers security middleware for cross-tenant IDOR protection, CSP headers
 * and rate limiting.
 */
final class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/security.php', 'security');

        // Multi-platform auth wiring (guards, providers, password resets).
        // Eloquent user providers are an infrastructure concern: they live in
        // this adapter, not in core (T-090/T-CASA-012).
        $this->app->register(AuthBootstrapServiceProvider::class);

        // Scope vocabulary shared by Passport::tokensCan() (app) and the login
        // adapter's createToken() (issuance).
        $this->app->singleton(ScopeCatalogue::class);

        $this->app->bindIf(
            \Shardevcom\Core\Contracts\SecondFactorGate::class,
            \Shardevcom\Security\Authentication\NullSecondFactorGate::class
        );

        // WebAuthn wiring: one bridge keeps the (expensive) serializer around.
        $this->app->singleton(\Shardevcom\Security\WebAuthn\WebAuthnBridge::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/security.php' => config_path('security.php'),
        ], 'security-config');

        // Account tables owned by the package (user preferences, ...) rather
        // than by each platform's user table.
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Register middleware aliases. Router::aliasMiddleware is the only
        // registry the pipeline reads during route resolution;
        // Route::middleware('name', Class) here would return a pending
        // registration and silently register nothing.
        $router = $this->app['router'];
        $router->aliasMiddleware('bind.guard', BindGuard::class);
        $router->aliasMiddleware('cross-tenant-idor', CrossTenantIdorMiddleware::class);
        $router->aliasMiddleware('csp', CspMiddleware::class);
        $router->aliasMiddleware('rate.limit', RateLimitMiddleware::class);
        $router->aliasMiddleware('security.headers', SecurityHeadersMiddleware::class);
        $router->aliasMiddleware('account.active', EnsureAccountActive::class);

        // Auto-apply CSP to all API routes
        Route::middleware('csp')->group(function () {
            // CSP is applied globally via middleware registration in kernel
        });

        // Point the recovery/verification emails at the first-party SPA instead
        // of Laravel's default `password.reset`/`verification.verify` routes,
        // which do not exist in an API-only app.
        $frontend = rtrim((string) config('security.frontend.url', ''), '/');

        if ($frontend === '') {
            $frontend = rtrim((string) config('app.url', 'http://localhost'), '/');
        }

        $resetPath = (string) config('security.frontend.password_reset_path', '/reset-password');
        $verifyPath = (string) config('security.frontend.email_verification_path', '/verify-email');

        ResetPassword::createUrlUsing(static function (object $notifiable, string $token) use ($frontend, $resetPath): string {
            $guard = method_exists($notifiable, 'getGuard') ? (string) $notifiable->getGuard() : '';

            return $frontend.$resetPath.'?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'guard' => $guard,
            ]);
        });

        VerifyEmail::createUrlUsing(static function (object $notifiable) use ($frontend, $verifyPath): string {
            $guard = method_exists($notifiable, 'getGuard') ? (string) $notifiable->getGuard() : '';
            $signed = URL::temporarySignedRoute(
                'auth.email.verify.'.$guard,
                now()->addMinutes(60),
                ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
            );

            return $frontend.$verifyPath.'?'.http_build_query([
                'verify_url' => $signed,
                'guard' => $guard,
            ]);
        });

        $this->loadAuthRoutes();
    }

    /**
     * Register the first-party token login surface once per platform.
     *
     * `auth/login` is public (it is the credential exchange); `auth/me` and
     * `auth/logout` require the platform guard. The `{guard}` route default
     * lets BindGuard resolve the platform and the controller authenticate
     * against the right provider without duplicating the controller.
     */
    private function loadAuthRoutes(): void
    {
        $platforms = (array) config('platforms.platforms', []);

        foreach ($platforms as $platform) {
            $guard = $platform['guard'];
            // The authenticated surface must use the platform's API guard when
            // it has one: a session platform (web) still answers its API with
            // the sibling passport guard, and a Bearer token is not a session.
            $apiGuard = $platform['api_guard'] ?? $guard;
            $middleware = array_merge(['bind.guard'], $platform['middleware'] ?? []);
            $prefix = ($platform['prefix'] ?? '') !== ''
                ? $platform['prefix'].'/api/v1'
                : 'api/v1';

            Route::middleware($middleware)
                ->prefix($prefix)
                ->group(function () use ($guard, $apiGuard): void {
                    Route::middleware('api.version')->group(function () use ($guard, $apiGuard): void {
                        Route::post('auth/login', [AuthController::class, 'login'])
                            ->defaults('guard', $guard)
                            ->name('auth.login.'.$guard);

                        Route::post('auth/forgot-password', [PasswordController::class, 'sendResetLink'])
                            ->defaults('guard', $guard)
                            ->name('auth.password.email.'.$guard);

                        Route::post('auth/reset-password', [PasswordController::class, 'reset'])
                            ->defaults('guard', $guard)
                            ->name('auth.password.reset.'.$guard);

                        Route::post('auth/otp/request', [OtpController::class, 'request'])
                            ->defaults('guard', $guard)
                            ->name('auth.otp.request.'.$guard);

                        Route::post('auth/otp/verify', [OtpController::class, 'verify'])
                            ->defaults('guard', $guard)
                            ->name('auth.otp.verify.'.$guard);

                        Route::post('auth/webauthn/login/options', [WebAuthnController::class, 'loginOptions'])
                            ->defaults('guard', $guard)
                            ->name('auth.webauthn.login.options.'.$guard);

                        Route::post('auth/webauthn/login', [WebAuthnController::class, 'login'])
                            ->defaults('guard', $guard)
                            ->name('auth.webauthn.login.'.$guard);

                        Route::post('auth/register', [RegistrationController::class, 'register'])
                            ->defaults('guard', $guard)
                            ->name('auth.register.'.$guard);

                        Route::post('auth/account/reactivate', [AccountController::class, 'reactivate'])
                            ->defaults('guard', $guard)
                            ->name('auth.account.reactivate.'.$guard);

                        Route::get('auth/invitations/{token}', [InvitationController::class, 'show'])
                            ->defaults('guard', $guard)
                            ->name('auth.invitations.show.'.$guard);

                        Route::post('auth/invitations/accept', [InvitationController::class, 'accept'])
                            ->defaults('guard', $guard)
                            ->name('auth.invitations.accept.'.$guard);

                        Route::get('auth/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
                            ->middleware('signed')
                            ->defaults('guard', $guard)
                            ->name('auth.email.verify.'.$guard);

                        Route::middleware(['auth:'.$apiGuard, 'account.active'])->group(function () use ($guard, $apiGuard): void {
                            Route::get('auth/me', [AuthController::class, 'me'])
                                ->defaults('guard', $guard)
                                ->name('auth.me.'.$guard);

                            Route::post('auth/logout', [AuthController::class, 'logout'])
                                ->defaults('guard', $guard)
                                ->name('auth.logout.'.$guard);

                            Route::patch('auth/profile', [AuthController::class, 'updateProfile'])
                                ->defaults('guard', $guard)
                                ->name('auth.profile.update.'.$guard);

                            Route::put('auth/password', [AuthController::class, 'updatePassword'])
                                ->defaults('guard', $guard)
                                ->name('auth.password.update.'.$guard);

                            Route::get('auth/preferences', [PreferencesController::class, 'show'])
                                ->defaults('guard', $guard)
                                ->name('auth.preferences.show.'.$guard);

                            Route::put('auth/preferences', [PreferencesController::class, 'update'])
                                ->defaults('guard', $guard)
                                ->name('auth.preferences.update.'.$guard);

                            Route::get('auth/notifications', [NotificationsController::class, 'index'])
                                ->defaults('guard', $guard)
                                ->name('auth.notifications.index.'.$guard);

                            Route::post('auth/notifications/read-all', [NotificationsController::class, 'readAll'])
                                ->defaults('guard', $guard)
                                ->name('auth.notifications.read-all.'.$guard);

                            Route::post('auth/notifications/{id}/read', [NotificationsController::class, 'read'])
                                ->defaults('guard', $guard)
                                ->name('auth.notifications.read.'.$guard);

                            Route::delete('auth/notifications/{id}', [NotificationsController::class, 'destroy'])
                                ->defaults('guard', $guard)
                                ->name('auth.notifications.destroy.'.$guard);

                            Route::post('auth/email/verify/resend', [EmailVerificationController::class, 'resend'])
                                ->defaults('guard', $guard)
                                ->name('auth.email.verify.resend.'.$guard);

                            Route::post('auth/account/disable', [AccountController::class, 'disable'])
                                ->defaults('guard', $guard)
                                ->name('auth.account.disable.'.$guard);

                            Route::post('auth/invitations', [InvitationController::class, 'store'])
                                ->defaults('guard', $guard)
                                ->name('auth.invitations.store.'.$guard);

                            Route::get('auth/webauthn/credentials', [WebAuthnController::class, 'index'])
                                ->defaults('guard', $guard)
                                ->name('auth.webauthn.credentials.index.'.$guard);

                            Route::post('auth/webauthn/register/options', [WebAuthnController::class, 'registerOptions'])
                                ->defaults('guard', $guard)
                                ->name('auth.webauthn.register.options.'.$guard);

                            Route::post('auth/webauthn/register', [WebAuthnController::class, 'register'])
                                ->defaults('guard', $guard)
                                ->name('auth.webauthn.register.'.$guard);

                            Route::delete('auth/webauthn/credentials/{id}', [WebAuthnController::class, 'destroy'])
                                ->defaults('guard', $guard)
                                ->name('auth.webauthn.credentials.destroy.'.$guard);
                        });
                    });
                });
        }
    }
}
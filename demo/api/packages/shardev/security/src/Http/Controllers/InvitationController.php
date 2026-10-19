<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;
use Shardevcom\Security\Infrastructure\PlatformInvitation;
use Shardevcom\Security\Infrastructure\UserRegistrar;
use Shardevcom\Security\Notifications\InvitationNotification;

/**
 * Invitations ({prefix}/api/v1/auth/invitations).
 *
 * An authenticated member invites an email; the invitee inspects the token and
 * accepts it with a name and password, which creates the account already
 * verified (they proved ownership of the inbox). Invitations are how a private
 * guard grows, since it does not expose self-registration.
 */
final class InvitationController
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
        private readonly UserRegistrar $registrar,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $platform = $this->platform($request);

        if ($user === null || $platform === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $email = $request->input('email');

        if (! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email field must be a valid email address.'],
            ], 422);
        }

        if ($this->registrar->exists($platform, $email)) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email has already been taken.'],
            ], 422);
        }

        $plain = Str::random(48);

        $invitation = PlatformInvitation::create([
            'guard' => $platform['guard'],
            'email' => $email,
            'token' => hash('sha256', $plain),
            'invited_by' => $user->getAuthIdentifier(),
            'expires_at' => now()->addHours(max(1, (int) $this->config->get('security.invitations.ttl_hours', 72))),
        ]);

        Notification::route('mail', $email)->notify(new InvitationNotification($this->url($plain, (string) $platform['guard'])));

        return response()->json(
            ApiResponse::success([
                'email' => $invitation->email,
                'expires_at' => $invitation->expires_at?->toIso8601String(),
            ], 'Invitation sent.')->toArray(),
            201
        );
    }

    public function show(Request $request, string $token): JsonResponse
    {
        $invitation = $this->find($token);

        if ($invitation === null || ! $invitation->isPending()) {
            return $this->error('INVALID_INVITATION', 'This invitation is no longer valid.', [], 404);
        }

        return $this->ok([
            'email' => $invitation->email,
            'guard' => $invitation->guard,
            'valid' => true,
        ], 'Invitation is valid.');
    }

    public function accept(Request $request): JsonResponse
    {
        $token = $request->input('token');
        $name = $request->input('name');
        $password = $request->input('password');
        $confirmation = $request->input('password_confirmation');

        $invitation = is_string($token) ? $this->find($token) : null;

        if ($invitation === null || ! $invitation->isPending()) {
            return $this->error('INVALID_INVITATION', 'This invitation is no longer valid.', [], 404);
        }

        $platform = $this->config->get('platforms.platforms.'.$invitation->guard);

        if (! is_array($platform)) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $errors = [];

        if (! is_string($name) || trim($name) === '') {
            $errors['name'][] = 'The name field is required.';
        }

        if (! is_string($password) || strlen($password) < 8) {
            $errors['password'][] = 'The password must be at least 8 characters.';
        } elseif ($password !== $confirmation) {
            $errors['password_confirmation'][] = 'The password confirmation does not match.';
        }

        if ($errors !== []) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', $errors, 422);
        }

        if ($this->registrar->exists($platform, $invitation->email)) {
            return $this->error('VALIDATION_FAILED', 'The given data was invalid.', [
                'email' => ['The email has already been taken.'],
            ], 422);
        }

        // The account is verified: accepting from the inbox proved ownership.
        $user = $this->registrar->create($platform, (string) $name, $invitation->email, (string) $password, verified: true);

        if ($user === null) {
            return $this->error('REGISTRATION_UNAVAILABLE', 'This platform cannot create accounts.', [], 500);
        }

        $invitation->forceFill(['accepted_at' => now()])->save();

        $capabilities = $this->tokens->issue($user, (string) $request->input('device_name', 'spa'));

        return response()->json(
            ApiResponse::success(
                $capabilities + ['user' => $this->presenter->present($user, $capabilities['abilities'])],
                'Invitation accepted.'
            )->toArray(),
            201
        );
    }

    private function find(string $plain): ?PlatformInvitation
    {
        return PlatformInvitation::query()->where('token', hash('sha256', $plain))->first();
    }

    private function url(string $plain, string $guard): string
    {
        $frontend = rtrim((string) config('security.frontend.url', ''), '/');

        if ($frontend === '') {
            $frontend = rtrim((string) config('app.url', 'http://localhost'), '/');
        }

        $path = (string) $this->config->get('security.frontend.invitation_path', '/accept-invitation');

        return $frontend.$path.'?'.http_build_query(['token' => $plain, 'guard' => $guard]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function platform(Request $request): ?array
    {
        $guard = $request->route('guard') ?? $this->config->get('platforms.default');
        $platform = $this->config->get('platforms.platforms.'.$guard);

        return is_array($platform) ? $platform : null;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function error(string $code, string $message, array $details, int $status): JsonResponse
    {
        return response()->json(
            ApiResponse::error(new ApiError($code, $message, $details, $status))->toArray(),
            $status
        );
    }

    private function ok(mixed $data, string $message): JsonResponse
    {
        return response()->json(ApiResponse::success($data, $message)->toArray());
    }
}

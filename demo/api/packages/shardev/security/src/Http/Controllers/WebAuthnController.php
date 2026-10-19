<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Controllers;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;
use Shardevcom\Security\Infrastructure\AccountLock;
use Shardevcom\Security\Infrastructure\UserRegistrar;
use Shardevcom\Security\Infrastructure\WebAuthnCredential;
use Shardevcom\Security\Infrastructure\WebAuthnCredentialRepository;
use Shardevcom\Security\WebAuthn\WebAuthnBridge;
use Throwable;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * WebAuthn / passkey ceremonies ({prefix}/api/v1/auth/webauthn/*).
 *
 * Registration is authenticated (a signed-in user adds a passkey); login is
 * passwordless. Challenges are random and stored in the cache keyed by the
 * challenge itself, so no server state leaks between users and a ceremony can
 * only be completed once. The library does the cryptographic verification; this
 * adapter only frames input/output and binds the ceremony to a platform user.
 */
final class WebAuthnController
{
    private const CEREMONY_CREATE = 'webauthn:create:';

    private const CEREMONY_REQUEST = 'webauthn:request:';

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly WebAuthnBridge $bridge,
        private readonly WebAuthnCredentialRepository $credentials,
        private readonly UserRegistrar $registrar,
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
        private readonly AccountLock $locks,
    ) {}

    public function registerOptions(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $guard = $this->guard($request);
        $handle = $this->handle($guard, $user->getAuthIdentifier());

        $exclude = $this->credentials->forUser($guard, $user->getAuthIdentifier())
            ->map(fn (WebAuthnCredential $credential): array => $this->descriptorJson($this->credentials->descriptorOf($credential)))
            ->all();

        $challenge = $this->challenge();

        Cache::put(self::CEREMONY_CREATE.$challenge, [
            'guard' => $guard,
            'userId' => (string) $user->getAuthIdentifier(),
            'handle' => $handle,
            'name' => (string) ($user->name ?? ''),
            'email' => (string) ($user->email ?? ''),
        ], $this->bridge->challengeTtl());

        return $this->ok(['publicKey' => [
            'challenge' => $challenge,
            'rp' => ['id' => $this->bridge->rpId(), 'name' => $this->bridge->rpName()],
            'user' => [
                'id' => $handle,
                'name' => (string) ($user->email ?? ''),
                'displayName' => (string) ($user->name ?? ''),
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
                ['type' => 'public-key', 'alg' => -257],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'authenticatorSelection' => [
                'residentKey' => 'preferred',
                'userVerification' => 'preferred',
            ],
            'excludeCredentials' => $exclude,
        ]], 'Passkey registration options.');
    }

    public function register(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $credential = $this->decode($request);

        if ($credential === null) {
            return $this->error('INVALID_CREDENTIAL', 'The credential payload is malformed.', [], 400);
        }

        $response = $credential->response;

        if (! $response instanceof AuthenticatorAttestationResponse) {
            return $this->error('INVALID_CREDENTIAL', 'The credential payload is malformed.', [], 400);
        }

        $context = Cache::pull(self::CEREMONY_CREATE.$this->challengeOf($response));

        if (! is_array($context)) {
            return $this->error('CHALLENGE_EXPIRED', 'The ceremony has expired. Start again.', [], 400);
        }

        $guard = $this->guard($request);

        if (($context['guard'] ?? null) !== $guard
            || (string) ($context['userId'] ?? '') !== (string) $user->getAuthIdentifier()) {
            return $this->error('INVALID_CREDENTIAL', 'The credential payload is malformed.', [], 403);
        }

        $options = PublicKeyCredentialCreationOptions::create(
            new PublicKeyCredentialRpEntity($this->bridge->rpName(), $this->bridge->rpId()),
            new PublicKeyCredentialUserEntity(
                (string) ($user->email ?? ''),
                (string) $context['handle'],
                (string) ($user->name ?? ''),
            ),
            (string) $response->clientDataJSON->challenge,
            [
                PublicKeyCredentialParameters::createPk(-7),
                PublicKeyCredentialParameters::createPk(-257),
            ],
            AuthenticatorSelectionCriteria::create(null, 'preferred', 'preferred'),
            'none',
        );

        try {
            $record = $this->bridge->creationValidator()
                ->check($response, $options, $this->bridge->rpId());
        } catch (Throwable) {
            return $this->error('REGISTRATION_FAILED', 'The passkey could not be verified.', [], 400);
        }

        $name = $request->input('name');

        $stored = $this->credentials->store(
            $guard,
            $user->getAuthIdentifier(),
            $record,
            is_string($name) ? $name : null,
        );

        return response()->json(
            ApiResponse::success($this->credentialJson($stored), 'Passkey registered.')->toArray(),
            201
        );
    }

    public function loginOptions(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $email = $request->input('email');
        $guard = $this->guard($request);

        $allow = [];
        $userId = null;
        $handle = null;

        if (is_string($email)) {
            $user = $this->registrar->find($platform, $email);

            if ($user !== null) {
                $userId = (string) $user->getAuthIdentifier();
                $handle = $this->handle($guard, $user->getAuthIdentifier());
                $allow = $this->credentials->forUser($guard, $user->getAuthIdentifier())
                    ->map(fn (WebAuthnCredential $credential): array => $this->descriptorJson($this->credentials->descriptorOf($credential)))
                    ->all();
            }
        }

        $challenge = $this->challenge();

        Cache::put(self::CEREMONY_REQUEST.$challenge, [
            'guard' => $guard,
            'userId' => $userId,
            'handle' => $handle,
        ], $this->bridge->challengeTtl());

        return $this->ok(['publicKey' => [
            'challenge' => $challenge,
            'rpId' => $this->bridge->rpId(),
            'allowCredentials' => $allow,
            'userVerification' => 'preferred',
            'timeout' => 60000,
        ]], 'Passkey login options.');
    }

    public function login(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->error('PLATFORM_NOT_FOUND', 'Unknown platform.', [], 404);
        }

        $credential = $this->decode($request);

        if ($credential === null) {
            return $this->error('INVALID_CREDENTIAL', 'The credential payload is malformed.', [], 400);
        }

        $response = $credential->response;

        if (! $response instanceof AuthenticatorAssertionResponse) {
            return $this->error('INVALID_CREDENTIAL', 'The credential payload is malformed.', [], 400);
        }

        $context = Cache::pull(self::CEREMONY_REQUEST.$this->challengeOf($response));

        if (! is_array($context) || ($context['userId'] ?? null) === null) {
            return $this->error('INVALID_CREDENTIAL', 'The passkey could not be verified.', [], 401);
        }

        $guard = $this->guard($request);

        if (($context['guard'] ?? null) !== $guard) {
            return $this->error('INVALID_CREDENTIAL', 'The passkey could not be verified.', [], 401);
        }

        $stored = $this->credentials->findByCredentialId(Base64UrlSafe::encodeUnpadded($credential->rawId));

        if ($stored === null || (string) $stored->user_id !== (string) $context['userId']) {
            return $this->error('INVALID_CREDENTIAL', 'The passkey could not be verified.', [], 401);
        }

        $record = $this->credentials->recordOf($stored);

        $options = PublicKeyCredentialRequestOptions::create(
            (string) $response->clientDataJSON->challenge,
            $this->bridge->rpId(),
            [$record->getPublicKeyCredentialDescriptor()],
            'preferred',
        );

        try {
            $updated = $this->bridge->assertionValidator()
                ->check($record, $response, $options, $this->bridge->rpId(), $record->userHandle);
        } catch (Throwable) {
            return $this->error('INVALID_CREDENTIAL', 'The passkey could not be verified.', [], 401);
        }

        $this->credentials->refresh($stored, $updated);

        $user = $this->registrar->findById($platform, $stored->user_id);

        if ($user === null) {
            return $this->error('INVALID_CREDENTIAL', 'The passkey could not be verified.', [], 401);
        }

        if ($this->locks->isDisabled($guard, $user->getAuthIdentifier())) {
            return $this->error('ACCOUNT_DISABLED', 'This account has been disabled.', [], 403);
        }

        $capabilities = $this->tokens->issue($user, (string) $request->input('device_name', 'spa'));

        return $this->ok(
            $capabilities + ['user' => $this->presenter->present($user, $capabilities['abilities'])],
            'Authenticated.'
        );
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $items = $this->credentials->forUser($this->guard($request), $user->getAuthIdentifier())
            ->map(fn (WebAuthnCredential $credential): array => $this->credentialJson($credential))
            ->all();

        return $this->ok(['items' => $items], 'Passkeys loaded.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if ($user === null) {
            return $this->error('UNAUTHENTICATED', 'Unauthenticated.', [], 401);
        }

        $stored = $this->credentials->find($id);

        if ($stored === null
            || $stored->guard !== $this->guard($request)
            || (string) $stored->user_id !== (string) $user->getAuthIdentifier()) {
            return $this->error('RESOURCE_NOT_FOUND', 'Passkey not found.', ['id' => $id], 404);
        }

        $this->credentials->delete($stored);

        return $this->ok(null, 'Passkey removed.');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(Request $request): ?\Webauthn\PublicKeyCredential
    {
        /** @var array<string, mixed>|null $body */
        $body = json_decode((string) $request->getContent(), true);

        if (! is_array($body)) {
            return null;
        }

        $payload = isset($body['credential']) && is_array($body['credential']) ? $body['credential'] : $body;

        if (! isset($payload['id'], $payload['rawId'], $payload['response']) || ! is_array($payload['response'])) {
            return null;
        }

        try {
            return $this->bridge->decode($payload);
        } catch (Throwable) {
            return null;
        }
    }

    private function handle(string $guard, int|string $userId): string
    {
        return Base64UrlSafe::encodeUnpadded(hash('sha256', $guard.':'.$userId, true));
    }

    /**
     * @return array<string, mixed>
     */
    private function descriptorJson(PublicKeyCredentialDescriptor $descriptor): array
    {
        return [
            'type' => $descriptor->type,
            'id' => Base64UrlSafe::encodeUnpadded($descriptor->id),
            'transports' => $descriptor->transports,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function credentialJson(WebAuthnCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'credential_id' => $credential->credential_id,
            'created_at' => $credential->created_at?->toIso8601String(),
            'last_used_at' => $credential->last_used_at?->toIso8601String(),
        ];
    }

    private function challenge(): string
    {
        return Base64UrlSafe::encodeUnpadded(random_bytes(32));
    }

    private function challengeOf(AuthenticatorAssertionResponse|AuthenticatorAttestationResponse $response): string
    {
        return Base64UrlSafe::encodeUnpadded($response->clientDataJSON->challenge);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function platform(Request $request): ?array
    {
        $platform = $this->config->get('platforms.platforms.'.$this->guard($request));

        return is_array($platform) ? $platform : null;
    }

    private function guard(Request $request): string
    {
        return (string) ($request->route('guard') ?? $this->config->get('platforms.default', 'web'));
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

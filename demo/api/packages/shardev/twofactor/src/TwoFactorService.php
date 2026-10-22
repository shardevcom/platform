<?php

declare(strict_types=1);

namespace Shardevcom\TwoFactor;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Shardevcom\Core\Contracts\SecondFactorGate;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Security\Authentication\AccessTokenIssuer;
use Shardevcom\Security\Authentication\UserPresenter;

/**
 * TOTP second factor for the platform login surface.
 *
 * Enrollment and challenge tokens are short-lived encrypted payloads, not
 * Passport tokens: before the second factor is satisfied the client has no
 * credential that the API accepts. Recovery codes are stored as hashes and
 * removed on first use.
 */
final class TwoFactorService implements SecondFactorGate
{
    public function __construct(
        private readonly AccessTokenIssuer $tokens,
        private readonly UserPresenter $presenter,
        private readonly AuthManager $auth,
        private readonly ConfigRepository $config,
    ) {}

    public function challenge(object $user, string $guard): ?array
    {
        if (! $this->required($guard)) {
            return null;
        }

        $ttl = $this->ttlMinutes();

        if ($this->confirmed($user)) {
            return [
                'two_factor_required' => true,
                'challenge_token' => $this->token($user, $guard, 'challenge'),
                'methods' => ['totp', 'recovery_code'],
                'expires_in' => $ttl * 60,
            ];
        }

        return [
            'two_factor_enrollment_required' => true,
            'enrollment_token' => $this->token($user, $guard, 'enrollment'),
            'methods' => ['totp'],
            'expires_in' => $ttl * 60,
        ];
    }

    /**
     * @return array{secret: string, otpauth_url: string, qr_code: string, recovery_codes: list<string>, instructions: string}
     */
    public function setup(string $token): array
    {
        $user = $this->userFromToken($token, 'enrollment');
        $secret = $this->google2fa()->generateSecretKey();
        $codes = $this->newRecoveryCodes();

        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = json_encode(array_map(
            static fn (string $code): string => Hash::make($code),
            $codes
        ));
        $user->two_factor_confirmed_at = null;
        $user->save();

        $otpauthUrl = $this->google2fa()->getQRCodeUrl(
            (string) $this->config->get('twofactor.issuer', 'Shardev Platform'),
            (string) $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code' => $this->qrCode($otpauthUrl),
            'recovery_codes' => $codes,
            'instructions' => 'Escanea el código, guarda los recovery codes y confirma con un código TOTP.',
        ];
    }

    /**
     * @return array<string, mixed> issued session (token + user)
     */
    public function confirmSetup(string $token, string $code, string $device): array
    {
        $user = $this->userFromToken($token, 'enrollment');

        if (! $this->confirmEnrollment($user, $code)) {
            throw new BusinessRuleViolation('Invalid 2FA code.');
        }

        return $this->issue($user, $device);
    }

    /**
     * @return array<string, mixed> issued session (token + user)
     */
    public function verifyChallenge(string $token, string $code, string $type, string $device): array
    {
        $user = $this->userFromToken($token, 'challenge');

        if (! $this->confirmed($user) || ! $this->verifyForUser($user, $code, $type)) {
            throw new BusinessRuleViolation('Invalid 2FA code.');
        }

        return $this->issue($user, $device);
    }

    /**
     * @return array{secret: string, otpauth_url: string, qr_code: string, recovery_codes: list<string>, instructions: string}
     */
    public function createEnrollment(object $user): array
    {
        if ($this->confirmed($user)) {
            throw new BusinessRuleViolation('Two-factor authentication is already enabled.');
        }

        $secret = $this->google2fa()->generateSecretKey();
        $codes = $this->newRecoveryCodes();

        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = json_encode(array_map(
            static fn (string $code): string => Hash::make($code),
            $codes
        ));
        $user->two_factor_confirmed_at = null;
        $user->save();

        $otpauthUrl = $this->google2fa()->getQRCodeUrl(
            (string) $this->config->get('twofactor.issuer', 'Shardev Platform'),
            (string) $user->email,
            $secret
        );

        return [
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
            'qr_code' => $this->qrCode($otpauthUrl),
            'recovery_codes' => $codes,
            'instructions' => 'Escanea el código, guarda los recovery codes y confirma con un código TOTP.',
        ];
    }

    public function confirmEnrollment(object $user, string $code): bool
    {
        if (! is_string($user->two_factor_secret ?? null) || $user->two_factor_secret === '') {
            throw new BusinessRuleViolation('No pending two-factor enrollment found.');
        }

        if (! $this->google2fa()->verifyKey($user->two_factor_secret, $code)) {
            return false;
        }

        $user->two_factor_confirmed_at = now();
        $user->save();

        return true;
    }

    public function verifyForUser(object $user, string $code, string $type = 'totp'): bool
    {
        if ($type === 'recovery_code') {
            return $this->verifyRecoveryCode($user, $code);
        }

        return $this->verifyTotp($user, $code);
    }

    public function verifyTotp(object $user, string $code): bool
    {
        $secret = $user->two_factor_secret ?? null;

        return is_string($secret) && $secret !== '' && $this->google2fa()->verifyKey($secret, $code);
    }

    public function verifyRecoveryCode(object $user, string $code): bool
    {
        $stored = $user->two_factor_recovery_codes ?? null;

        if (! is_string($stored) || $code === '') {
            return false;
        }

        $codes = json_decode($stored, true);

        if (! is_array($codes)) {
            return false;
        }

        foreach ($codes as $index => $hash) {
            if (is_string($hash) && Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = json_encode(array_values($codes));
                $user->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(object $user): array
    {
        $codes = $this->newRecoveryCodes();

        $user->two_factor_recovery_codes = json_encode(array_map(
            static fn (string $code): string => Hash::make($code),
            $codes
        ));
        $user->save();

        return $codes;
    }

    public function remainingRecoveryCodes(object $user): int
    {
        $stored = $user->two_factor_recovery_codes ?? null;

        if (! is_string($stored)) {
            return 0;
        }

        $codes = json_decode($stored, true);

        return is_array($codes) ? count($codes) : 0;
    }

    public function qrCodeFor(object $user): string
    {
        $secret = $user->two_factor_secret ?? null;

        if (! is_string($secret) || $secret === '') {
            throw new BusinessRuleViolation('No 2FA secret found. Please enable 2FA first.');
        }

        return $this->qrCode($this->google2fa()->getQRCodeUrl(
            (string) $this->config->get('twofactor.issuer', 'Shardev Platform'),
            (string) $user->email,
            $secret
        ));
    }

    public function confirmed(object $user): bool
    {
        return ($user->two_factor_confirmed_at ?? null) !== null
            && is_string($user->two_factor_secret ?? null)
            && $user->two_factor_secret !== '';
    }

    public function required(string $guard): bool
    {
        if (! (bool) $this->config->get('twofactor.enabled', true)) {
            return false;
        }

        $guards = $this->config->get('twofactor.required_guards', ['admin', 'employee']);

        if (is_string($guards)) {
            $guards = array_map('trim', explode(',', $guards));
        }

        return in_array($guard, (array) $guards, true);
    }

    /**
     * @return array<string, mixed>
     */
    private function issue(object $user, string $device): array
    {
        $capabilities = $this->tokens->issue($user, $device !== '' ? $device : '2fa');

        return $capabilities + [
            'user' => $this->presenter->present($user, $capabilities['abilities']),
        ];
    }

    private function token(object $user, string $guard, string $purpose): string
    {
        return Crypt::encryptString((string) json_encode([
            'id' => (string) $user->getAuthIdentifier(),
            'guard' => $guard,
            'purpose' => $purpose,
            'exp' => now()->addMinutes($this->ttlMinutes())->getTimestamp(),
            'nonce' => Str::random(16),
        ]));
    }

    private function userFromToken(string $token, string $purpose): object
    {
        try {
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new BusinessRuleViolation('Invalid or expired two-factor token.');
        }

        if (! is_array($payload)
            || ($payload['purpose'] ?? null) !== $purpose
            || ($payload['exp'] ?? 0) < now()->getTimestamp()) {
            throw new BusinessRuleViolation('Invalid or expired two-factor token.');
        }

        $guard = (string) ($payload['guard'] ?? '');
        $platform = $this->config->get('platforms.platforms.'.$guard);

        if (! is_array($platform)) {
            throw new BusinessRuleViolation('Invalid or expired two-factor token.');
        }

        $provider = $this->auth->createUserProvider($platform['provider']);
        $user = $provider?->retrieveById($payload['id'] ?? null);

        if ($user === null) {
            throw new BusinessRuleViolation('Invalid or expired two-factor token.');
        }

        return $user;
    }

    /**
     * @return list<string>
     */
    private function newRecoveryCodes(): array
    {
        $count = (int) $this->config->get('twofactor.recovery_codes_count', 8);
        $length = (int) $this->config->get('twofactor.recovery_codes_length', 10);
        $codes = [];

        for ($i = 0; $i < $count; $i++) {
            $codes[] = Str::upper(Str::random($length));
        }

        return $codes;
    }

    private function qrCode(string $url): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(300),
                new SvgImageBackEnd()
            )
        ))->writeString($url);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function ttlMinutes(): int
    {
        return max(1, (int) $this->config->get('twofactor.challenge_ttl_minutes', 10));
    }

    private function google2fa(): Google2FA
    {
        return new Google2FA();
    }
}

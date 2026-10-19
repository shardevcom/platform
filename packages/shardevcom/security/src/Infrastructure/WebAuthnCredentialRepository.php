<?php

declare(strict_types=1);

namespace Shardevcom\Security\Infrastructure;

use Illuminate\Database\Eloquent\Collection;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;
use Shardevcom\Security\WebAuthn\WebAuthnBridge;

/**
 * Persists and reads WebAuthn credentials for a platform user.
 *
 * The library's `CredentialRecord` is stored as JSON and rehydrated on demand;
 * `credential_id` (base64url of the raw id) is the unique lookup key.
 */
final class WebAuthnCredentialRepository
{
    public function __construct(private readonly WebAuthnBridge $bridge) {}

    /**
     * @return Collection<int, WebAuthnCredential>
     */
    public function forUser(string $guard, int|string $userId): Collection
    {
        return WebAuthnCredential::query()
            ->where('guard', $guard)
            ->where('user_id', $userId)
            ->orderBy('id')
            ->get();
    }

    public function find(int $id): ?WebAuthnCredential
    {
        return WebAuthnCredential::query()->find($id);
    }

    public function findByCredentialId(string $credentialId): ?WebAuthnCredential
    {
        return WebAuthnCredential::query()->where('credential_id', $credentialId)->first();
    }

    public function recordOf(WebAuthnCredential $credential): CredentialRecord
    {
        /** @var array<string, mixed> $data */
        $data = json_decode($credential->record, true);

        /** @var CredentialRecord $record */
        $record = $this->bridge->serializer()->denormalize($data, CredentialRecord::class, 'json');

        return $record;
    }

    public function store(string $guard, int|string $userId, CredentialRecord $record, ?string $name = null): WebAuthnCredential
    {
        return WebAuthnCredential::query()->create([
            'guard' => $guard,
            'user_id' => $userId,
            'credential_id' => Base64UrlSafe::encodeUnpadded($record->publicKeyCredentialId),
            'record' => json_encode($this->bridge->serializer()->normalize($record, 'json')),
            'counter' => $record->counter,
            'name' => $name,
        ]);
    }

    public function refresh(WebAuthnCredential $credential, CredentialRecord $record): void
    {
        $credential->forceFill([
            'record' => json_encode($this->bridge->serializer()->normalize($record, 'json')),
            'counter' => $record->counter,
            'last_used_at' => now(),
        ])->save();
    }

    public function descriptorOf(WebAuthnCredential $credential): PublicKeyCredentialDescriptor
    {
        return $this->recordOf($credential)->getPublicKeyCredentialDescriptor();
    }

    public function delete(WebAuthnCredential $credential): void
    {
        $credential->delete();
    }
}

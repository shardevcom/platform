<?php

declare(strict_types=1);

namespace Tests\Support;

use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * A minimal software WebAuthn authenticator for tests.
 *
 * It mints an ES256 (P-256) key pair and produces spec-shaped registration
 * (attestation fmt "none") and assertion responses, so the full ceremony can be
 * exercised over HTTP without a browser or a hardware authenticator. CBOR is
 * hand-encoded: the structures are fixed and small.
 */
final class WebAuthnAuthenticator
{
    private \OpenSSLAsymmetricKey $key;

    private string $credentialId;

    public function __construct()
    {
        $this->key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);

        $this->credentialId = random_bytes(16);
    }

    /**
     * @return array<string, mixed>
     */
    public function registration(string $rpId, string $origin, string $challenge): array
    {
        $clientData = $this->clientData('webauthn.create', $origin, $challenge);
        $authData = $this->authData($rpId, "\x45", 0, $this->attestedCredentialData());
        $attestationObject = $this->attestationObject($authData);

        return $this->credential($clientData, [
            'attestationObject' => Base64UrlSafe::encodeUnpadded($attestationObject),
            'transports' => ['internal'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function assertion(string $rpId, string $origin, string $challenge, int $signCount, string $userHandle): array
    {
        $clientData = $this->clientData('webauthn.get', $origin, $challenge);
        $authData = $this->authData($rpId, "\x05", $signCount, '');

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return $this->credential($clientData, [
            'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded((string) $signature),
            'userHandle' => $userHandle,
        ]);
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function credential(string $clientData, array $response): array
    {
        return [
            'id' => Base64UrlSafe::encodeUnpadded($this->credentialId),
            'rawId' => base64_encode($this->credentialId),
            'type' => 'public-key',
            'response' => array_merge(
                ['clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientData)],
                $response,
            ),
            'clientExtensionResults' => [],
        ];
    }

    private function clientData(string $type, string $origin, string $challenge): string
    {
        return (string) json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $origin,
            'crossOrigin' => false,
        ]);
    }

    private function authData(string $rpId, string $flags, int $signCount, string $attested): string
    {
        return hash('sha256', $rpId, true)
            .$flags
            .pack('N', $signCount)
            .$attested;
    }

    private function attestedCredentialData(): string
    {
        $details = openssl_pkey_get_details($this->key);

        return str_repeat("\x00", 16)
            .pack('n', strlen($this->credentialId))
            .$this->credentialId
            .$this->coseEc2((string) $details['ec']['x'], (string) $details['ec']['y']);
    }

    private function coseEc2(string $x, string $y): string
    {
        return "\xa5"
            ."\x01\x02"                        // 1: 2 (kty = EC2)
            ."\x03\x26"                        // 3: -7 (alg = ES256)
            ."\x20\x01"                        // -1: 1 (crv = P-256)
            ."\x21".$this->byteString($x)      // -2: x
            ."\x22".$this->byteString($y);     // -3: y
    }

    private function attestationObject(string $authData): string
    {
        return "\xa3"
            .$this->textString('fmt').$this->textString('none')
            .$this->textString('attStmt')."\xa0"
            .$this->textString('authData').$this->byteString($authData);
    }

    private function byteString(string $value): string
    {
        $length = strlen($value);

        if ($length < 24) {
            return chr(0x40 | $length).$value;
        }

        if ($length < 256) {
            return "\x58".chr($length).$value;
        }

        return "\x59".pack('n', $length).$value;
    }

    private function textString(string $value): string
    {
        $length = strlen($value);

        if ($length < 24) {
            return chr(0x60 | $length).$value;
        }

        return "\x78".chr($length).$value;
    }
}

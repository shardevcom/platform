<?php

declare(strict_types=1);

namespace Shardevcom\Security\WebAuthn;

use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;

/**
 * Thin, stateless wiring of the `web-auth/webauthn-lib` pieces (serializer and
 * ceremony validators) against the configured Relying Party.
 *
 * Keeping this in one place means the register/login controllers never touch
 * the library's low-level ceremony objects, only the options they build and the
 * credential payload they hand back.
 */
final class WebAuthnBridge
{
    private ?SerializerInterface $serializer = null;

    public function __construct(private readonly ConfigRepository $config) {}

    public function rpId(): string
    {
        return (string) $this->config->get('security.webauthn.rp_id', 'localhost');
    }

    public function rpName(): string
    {
        return (string) $this->config->get('security.webauthn.rp_name', 'Shardev');
    }

    /**
     * @return list<string>
     */
    public function allowedOrigins(): array
    {
        return array_values((array) $this->config->get('security.webauthn.allowed_origins', []));
    }

    public function challengeTtl(): int
    {
        return max(30, (int) $this->config->get('security.webauthn.challenge_ttl_seconds', 300));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function decode(array $payload): PublicKeyCredential
    {
        /** @var PublicKeyCredential $credential */
        $credential = $this->serializer()->denormalize($payload, PublicKeyCredential::class, 'json');

        return $credential;
    }

    public function creationValidator(): AuthenticatorAttestationResponseValidator
    {
        return AuthenticatorAttestationResponseValidator::create($this->factory()->creationCeremony());
    }

    public function assertionValidator(): AuthenticatorAssertionResponseValidator
    {
        return AuthenticatorAssertionResponseValidator::create($this->factory()->requestCeremony());
    }

    public function serializer(): SerializerInterface
    {
        if ($this->serializer === null) {
            $this->serializer = (new WebauthnSerializerFactory($this->attestationManager()))->create();
        }

        return $this->serializer;
    }

    private function attestationManager(): AttestationStatementSupportManager
    {
        return new AttestationStatementSupportManager([new NoneAttestationStatementSupport()]);
    }

    private function factory(): CeremonyStepManagerFactory
    {
        $factory = new CeremonyStepManagerFactory();
        $factory->setAllowedOrigins($this->allowedOrigins());
        $factory->setAttestationStatementSupportManager($this->attestationManager());

        return $factory;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Authentication;

use Shardevcom\Core\ApplicationIdentity;
use Shardevcom\Core\Authentication\BearerTokenAuthentication;
use Shardevcom\Core\Authentication\TokenRecord;
use Shardevcom\Core\Contracts\AuthenticationProvider;
use Shardevcom\Core\Contracts\TokenResolver;
use Shardevcom\Core\Exceptions\Unauthorized;
use PHPUnit\Framework\TestCase;

/**
 * An in-memory store. Deliberately not a database: it proves the Bearer rules
 * live in Core and need no framework to be exercised.
 */
final class InMemoryTokenResolver implements TokenResolver
{
    /** @var array<string, TokenRecord> */
    private array $records = [];

    public function add(string $token, TokenRecord $record): void
    {
        $this->records[$token] = $record;
    }

    public function resolve(string $token): ?TokenRecord
    {
        return $this->records[$token] ?? null;
    }
}

final class BearerTokenAuthenticationTest extends TestCase
{
    private const NOW = 1_700_000_000;

    private InMemoryTokenResolver $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryTokenResolver();
    }

    public function test_a_valid_bearer_token_produces_an_identity(): void
    {
        $this->store->add('tok-live', new TokenRecord(
            id: 'pat_1',
            actorId: 'user-42',
            abilities: ['products.create'],
            expiresAt: self::NOW + 3600,
            device: ['name' => 'iPhone', 'ip' => '10.0.0.7'],
        ));

        $identity = $this->auth()->identityFromToken('tok-live');

        $this->assertSame('user-42', $identity->id);
        $this->assertSame('user', $identity->kind);
        $this->assertSame('pat_1', $identity->attributes['token_id']);
        $this->assertSame(['products.create'], $identity->attributes['abilities']);
        $this->assertSame('bearer', $identity->attributes['via']);
        $this->assertSame('iPhone', $identity->attributes['device']['name'], 'device/session context survives');
    }

    public function test_the_identity_carries_no_trace_of_the_secret(): void
    {
        $this->store->add('tok-live', new TokenRecord(id: 'pat_1', actorId: 'user-42'));

        $identity = $this->auth()->identityFromToken('tok-live');

        $this->assertStringNotContainsString('tok-live', json_encode($identity->attributes, JSON_THROW_ON_ERROR));
    }

    public function test_a_missing_token_is_unauthorized(): void
    {
        $this->expectException(Unauthorized::class);

        $this->auth()->identityFromToken('   ');
    }

    public function test_an_unknown_token_is_unauthorized(): void
    {
        $this->expectException(Unauthorized::class);

        $this->auth()->identityFromToken('tok-nope');
    }

    public function test_an_expired_token_is_unauthorized(): void
    {
        $this->store->add('tok-old', new TokenRecord(
            id: 'pat_2',
            actorId: 'user-42',
            expiresAt: self::NOW - 1,
        ));

        $this->expectException(Unauthorized::class);

        $this->auth()->identityFromToken('tok-old');
    }

    public function test_a_revoked_token_is_unauthorized(): void
    {
        $this->store->add('tok-revoked', new TokenRecord(
            id: 'pat_3',
            actorId: 'user-42',
            revokedAt: self::NOW - 10,
        ));

        $this->expectException(Unauthorized::class);

        $this->auth()->identityFromToken('tok-revoked');
    }

    /**
     * Unknown, expired and revoked must be indistinguishable to the caller.
     * Differing messages turn the endpoint into an oracle for valid token ids.
     */
    public function test_the_three_failure_reasons_share_one_public_message(): void
    {
        $this->store->add('tok-old', new TokenRecord(id: 'pat_2', actorId: 'u', expiresAt: self::NOW - 1));
        $this->store->add('tok-revoked', new TokenRecord(id: 'pat_3', actorId: 'u', revokedAt: self::NOW));

        $messages = [];

        foreach (['tok-nope', 'tok-old', 'tok-revoked'] as $token) {
            try {
                $this->auth()->identityFromToken($token);
                $this->fail("{$token} should not authenticate");
            } catch (Unauthorized $e) {
                $this->assertSame('UNAUTHORIZED', $e->errorCode());
                $messages[$token] = $e->getMessage();
            }
        }

        $this->assertCount(1, array_unique($messages), 'failure messages must be identical: '.json_encode($messages));
    }

    /**
     * The reason is for logs. It is what lets an operator tell a stolen token
     * from an outage without reading the raw token.
     */
    public function test_the_failure_reason_is_available_for_server_side_logs(): void
    {
        $this->store->add('tok-old', new TokenRecord(id: 'pat_2', actorId: 'u', expiresAt: self::NOW - 1));

        try {
            $this->auth()->identityFromToken('tok-old');
            $this->fail('expired token should not authenticate');
        } catch (Unauthorized $e) {
            $this->assertSame('expired', $e->context()['reason']);
            $this->assertSame('pat_2', $e->context()['token_id']);
        }
    }

    public function test_a_token_without_expiry_never_expires(): void
    {
        $this->store->add('tok-permanent', new TokenRecord(id: 'pat_4', actorId: 'user-42'));

        $this->assertSame('user-42', $this->auth(now: self::NOW + 10 * 365 * 86_400)->identityFromToken('tok-permanent')->id);
    }

    public function test_a_token_expiring_exactly_now_is_already_expired(): void
    {
        $this->store->add('tok-edge', new TokenRecord(id: 'pat_5', actorId: 'u', expiresAt: self::NOW));

        $this->expectException(Unauthorized::class);

        $this->auth()->identityFromToken('tok-edge');
    }

    public function test_token_abilities_are_carried_and_checkable(): void
    {
        $this->assertTrue((new TokenRecord('a', 'u', abilities: ['*']))->can('anything'));
        $this->assertTrue((new TokenRecord('a', 'u', abilities: ['products.create']))->can('products.create'));
        $this->assertFalse((new TokenRecord('a', 'u', abilities: ['products.create']))->can('products.delete'));
        $this->assertFalse((new TokenRecord('a', 'u'))->can('products.create'));
    }

    public function test_it_only_claims_bearer_headers(): void
    {
        $auth = $this->auth();

        $this->assertTrue($auth->accepts('Bearer abc'));
        $this->assertTrue($auth->accepts('bearer abc'), 'scheme comparison must be case-insensitive per RFC 7235');
        $this->assertFalse($auth->accepts('Basic abc'));
        $this->assertFalse($auth->accepts('abc'), 'a bare token in Authorization is not a Bearer credential');
        $this->assertFalse($auth->accepts(''));
    }

    public function test_it_satisfies_the_platform_contract(): void
    {
        $this->assertInstanceOf(AuthenticationProvider::class, $this->auth());
    }

    public function test_it_can_be_used_without_any_framework_class_loaded(): void
    {
        // Same reason ApplicationIdentity refuses a user model: the identity
        // must be buildable from a CLI, a queue job or a test.
        $reflection = new \ReflectionClass(BearerTokenAuthentication::class);

        foreach ($reflection->getMethods() as $method) {
            $this->assertStringNotContainsString(
                'Illuminate\\',
                (string) $method->getDocComment(),
                basename($method->getName()).' must not reference the framework.',
            );
        }
    }

    private function auth(?int $now = null): BearerTokenAuthentication
    {
        return new BearerTokenAuthentication(
            $this->store,
            static fn (): int => $now ?? self::NOW,
        );
    }
}
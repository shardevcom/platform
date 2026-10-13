<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\ApplicationIdentity;
use PHPUnit\Framework\TestCase;

/**
 * T-011 acceptance: transport-agnostic context, independent user/tenant,
 * generated request ids, immutable derivation.
 */
final class ApplicationContextTest extends TestCase
{
    public function test_create_generates_uuid_request_id_and_propagates_correlation(): void
    {
        $context = ApplicationContext::create();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $context->requestId()
        );
        $this->assertSame($context->requestId(), $context->correlationId());
    }

    public function test_explicit_ids_are_preserved(): void
    {
        $context = ApplicationContext::create([
            'requestId' => 'req-1',
            'correlationId' => 'corr-1',
        ]);

        $this->assertSame('req-1', $context->requestId());
        $this->assertSame('corr-1', $context->correlationId());
    }

    public function test_user_tenant_application_and_device_stay_independent(): void
    {
        $context = ApplicationContext::create([
            'userId' => 'user-7',
            'tenantId' => 'tenant-3',
            'applicationId' => 'app-1',
            'deviceId' => 'device-9',
        ]);

        $this->assertSame('user-7', $context->userId());
        $this->assertSame('tenant-3', $context->tenantId());
        $this->assertSame('app-1', $context->applicationId());
        $this->assertSame('device-9', $context->deviceId());
        $this->assertNotSame($context->userId(), $context->tenantId());
    }

    public function test_with_returns_new_instance_leaving_original_untouched(): void
    {
        $original = ApplicationContext::create(['locale' => 'en']);

        $derived = $original->with(['locale' => 'es-CO', 'tenantId' => 't-1']);

        $this->assertNotSame($original, $derived);
        $this->assertSame('en', $original->locale());
        $this->assertNull($original->tenantId());
        $this->assertSame('es-CO', $derived->locale());
        $this->assertSame('t-1', $derived->tenantId());
        $this->assertSame($original->requestId(), $derived->requestId());
    }

    public function test_permissions_and_capabilities_lookup(): void
    {
        $context = ApplicationContext::create([
            'permissions' => ['products.view'],
            'capabilities' => ['export'],
        ]);

        $this->assertTrue($context->hasPermission('products.view'));
        $this->assertFalse($context->hasPermission('products.delete'));
        $this->assertTrue($context->hasCapability('export'));
        $this->assertFalse($context->hasCapability('import'));
    }

    public function test_to_array_round_trips_through_create(): void
    {
        $context = ApplicationContext::create(['userId' => 'u-1', 'tenantId' => 't-1']);
        $rebuilt = ApplicationContext::create($context->toArray());

        $this->assertSame($context->toArray(), $rebuilt->toArray());
    }

    public function test_identity_holds_id_kind_and_attributes(): void
    {
        $identity = new ApplicationIdentity('user-7', 'user', ['email' => 'a@b.c']);

        $this->assertSame('user-7', $identity->id);
        $this->assertSame('user', $identity->kind);
        $this->assertSame(['email' => 'a@b.c'], $identity->attributes);
    }

    public function test_core_value_objects_do_not_import_illuminate(): void
    {
        foreach (['ApplicationContext.php', 'ApplicationIdentity.php', 'ResourceAction.php'] as $file) {
            $source = (string) file_get_contents(dirname(__DIR__, 3).'/packages/shardevcom/core/src/'.$file);
            $this->assertStringNotContainsString('Illuminate', $source, "{$file} must stay transport-agnostic");
        }
    }
}

<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\DomainException;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\RequestEntityTooLarge;
use Shardevcom\Core\Exceptions\Unauthorized;
use Shardevcom\Core\Exceptions\UnsupportedMediaType;
use Shardevcom\Core\Exceptions\ValidationFailed;
use PHPUnit\Framework\TestCase;

/**
 * T-013 acceptance: domain failures exist with stable codes, carry safe
 * context, and never touch HTTP.
 */
final class ExceptionsTest extends TestCase
{
    public function test_every_exception_extends_the_domain_base(): void
    {
        $instances = [
            new Unauthorized('m'),
            new Forbidden('m'),
            ResourceNotFound::for('products', 1),
            new ValidationFailed(['f' => 'e']),
            new Conflict('m'),
            new BusinessRuleViolation('m'),
            ResourceOperationNotSupported::for('products', 'restore'),
            new RequestEntityTooLarge('m'),
            new UnsupportedMediaType('m'),
        ];

        $this->assertCount(9, $instances);

        foreach ($instances as $exception) {
            $this->assertInstanceOf(DomainException::class, $exception);
            $this->assertNotEmpty($exception->errorCode());
        }
    }

    public function test_factories_build_safe_context(): void
    {
        $notFound = ResourceNotFound::for('products', 10);

        $this->assertSame('RESOURCE_NOT_FOUND', $notFound->errorCode());
        $this->assertSame(['resource' => 'products', 'id' => 10], $notFound->context());
        $this->assertStringNotContainsString('SQL', $notFound->getMessage());

        $unsupported = ResourceOperationNotSupported::for('products', 'restore');

        $this->assertSame('RESOURCE_OPERATION_NOT_SUPPORTED', $unsupported->errorCode());
        $this->assertSame('restore', $unsupported->context()['operation']);
    }

    public function test_validation_failed_carries_field_errors(): void
    {
        $failure = new ValidationFailed(['sku' => ['required']]);

        $this->assertSame('VALIDATION_FAILED', $failure->errorCode());
        $this->assertSame(['sku' => ['required']], $failure->errors());
    }

    public function test_core_never_throws_http_exceptions(): void
    {
        $files = glob(dirname(__DIR__, 3).'/packages/shardevcom/core/src/Exceptions/*.php');
        $this->assertNotEmpty($files);

        foreach ($files as $file) {
            $source = (string) file_get_contents($file);
            $this->assertStringNotContainsString('HttpException', $source, basename($file));
            $this->assertStringNotContainsString('abort(', $source, basename($file));
            $this->assertStringNotContainsString('Illuminate', $source, basename($file));
        }
    }
}

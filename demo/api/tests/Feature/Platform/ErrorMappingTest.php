<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
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
use Shardevcom\Core\Results\ApiError;
use Tests\TestCase;

/**
 * T-102: the domain-to-status table is asserted, not assumed.
 *
 * `ApiError::fromDomain()` is the single place that mapping lives, so a test
 * written per exception class is what stops the table from drifting as new
 * domain exceptions appear. The second half of the task is the one that
 * matters most: a 500 must not become an information leak, which no status
 * code assertion can catch on its own.
 */
final class ErrorMappingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{class-string<DomainException>, int, string}>
     */
    public static function domainExceptionProvider(): array
    {
        return [
            'unauthorized' => [Unauthorized::class, 401, 'UNAUTHORIZED'],
            'forbidden' => [Forbidden::class, 403, 'FORBIDDEN'],
            'not found' => [ResourceNotFound::class, 404, 'RESOURCE_NOT_FOUND'],
            'conflict' => [Conflict::class, 409, 'CONFLICT'],
            'business rule' => [BusinessRuleViolation::class, 422, 'BUSINESS_RULE_VIOLATION'],
            'operation not supported' => [ResourceOperationNotSupported::class, 405, 'RESOURCE_OPERATION_NOT_SUPPORTED'],
            'request entity too large' => [RequestEntityTooLarge::class, 413, 'REQUEST_ENTITY_TOO_LARGE'],
            'unsupported media type' => [UnsupportedMediaType::class, 415, 'UNSUPPORTED_MEDIA_TYPE'],
        ];
    }

    /**
     * @param class-string<DomainException> $exceptionClass
     */
    #[DataProvider('domainExceptionProvider')]
    public function test_each_domain_exception_maps_to_its_status(string $exceptionClass, int $status, string $code): void
    {
        $response = $this->throwThroughHttp($exceptionClass);

        $response->assertStatus($status)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', $code)
            ->assertJsonPath('data', null);
    }

    public function test_validation_failures_carry_the_field_errors(): void
    {
        $response = $this->throwThroughHttp(ValidationFailed::class, ['sku' => 'Required.', 'ean' => 'Too long.']);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_FAILED')
            ->assertJsonPath('meta.details.sku', 'Required.')
            ->assertJsonPath('meta.details.ean', 'Too long.');
    }

    public function test_an_unexpected_failure_is_a_generic_500_that_leaks_nothing(): void
    {
        config(['app.debug' => false]);

        $response = $this->throwThroughHttp(\RuntimeException::class, null, 'SQLSTATE[42S02]: no such table: users');

        $response->assertStatus(500)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INTERNAL_ERROR')
            ->assertJsonPath('message', 'Internal server error.');

        $body = $response->getContent();

        $this->assertStringNotContainsString('SQLSTATE', (string) $body);
        $this->assertStringNotContainsString('no such table', (string) $body);
        $this->assertStringNotContainsString('RuntimeException', (string) $body);
        $this->assertStringNotContainsString(__FILE__, (string) $body);
    }

    public function test_the_same_table_drives_the_transport_neutral_value(): void
    {
        $this->assertSame(404, ApiError::fromDomain(new ResourceNotFound('gone'))->status);
        $this->assertSame(409, ApiError::fromDomain(new Conflict('duplicate'))->status);
        $this->assertSame(422, ApiError::fromDomain(new BusinessRuleViolation('rule'))->status);
        $this->assertSame(405, ApiError::fromDomain(new ResourceOperationNotSupported('restore'))->status);
    }

    /**
     * Drives one exception through the real HTTP stack, because the mapping
     * under test lives in the handler's renderable, not in the exception.
     *
     * @param class-string<Throwable> $exceptionClass
     * @param array<string, string>|null $errors
     */
    private function throwThroughHttp(string $exceptionClass, ?array $errors = null, string $message = 'boom'): \Illuminate\Testing\TestResponse
    {
        Route::middleware('api')->get('/api/v1/__throw', function () use ($exceptionClass, $errors, $message) {
            throw match (true) {
                $exceptionClass === ValidationFailed::class => new ValidationFailed($errors ?? [], $message),
                $exceptionClass === Unauthorized::class => new Unauthorized($message),
                $exceptionClass === Forbidden::class => new Forbidden($message),
                $exceptionClass === ResourceNotFound::class => new ResourceNotFound($message),
                $exceptionClass === Conflict::class => new Conflict($message),
                $exceptionClass === BusinessRuleViolation::class => new BusinessRuleViolation($message),
                $exceptionClass === ResourceOperationNotSupported::class => new ResourceOperationNotSupported($message),
                $exceptionClass === RequestEntityTooLarge::class => new RequestEntityTooLarge($message),
                $exceptionClass === UnsupportedMediaType::class => new UnsupportedMediaType($message),
                default => new $exceptionClass($message),
            };
        });

        return $this->getJson('/api/v1/__throw');
    }
}
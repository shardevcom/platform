<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Shardevcom\Core\ApplicationContext;
use Shardevcom\Core\Exceptions\BusinessRuleViolation;
use Shardevcom\Core\Exceptions\Conflict;
use Shardevcom\Core\Exceptions\Forbidden;
use Shardevcom\Core\Exceptions\ResourceNotFound;
use Shardevcom\Core\Exceptions\ResourceOperationNotSupported;
use Shardevcom\Core\Exceptions\RequestEntityTooLarge;
use Shardevcom\Core\Exceptions\Unauthorized;
use Shardevcom\Core\Exceptions\UnsupportedMediaType;
use Shardevcom\Core\Exceptions\ValidationFailed;
use Shardevcom\Core\Results\ApiError;
use Shardevcom\Core\Results\ApiMeta;
use Shardevcom\Core\Results\ApiResponse;
use Shardevcom\Core\Results\PaginationMeta;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * T-014 acceptance: one internal result shape feeds both transports.
 */
final class ApiResponseTest extends TestCase
{
    public function test_success_envelope_shape(): void
    {
        $response = ApiResponse::success(['id' => 1], 'Product loaded.', ApiMeta::fromContext(ApplicationContext::create()));

        $array = $response->toArray();

        $this->assertSame(['status', 'code', 'message', 'data', 'meta'], array_keys($array));
        $this->assertSame('success', $array['status']);
        $this->assertSame('OK', $array['code']);
        $this->assertSame(200, $response->httpStatus);
        $this->assertSame('Product loaded.', $array['message']);
        $this->assertSame(['id' => 1], $array['data']);
        $this->assertArrayHasKey('request_id', $array['meta']);
        $this->assertArrayHasKey('correlation_id', $array['meta']);
    }

    /**
     * @return array<string, array{object, int, string}>
     */
    public static function domainMapping(): array
    {
        return [
            'unauthorized' => [new Unauthorized('Unauthenticated.'), 401, 'UNAUTHORIZED'],
            'forbidden' => [new Forbidden('Forbidden.'), 403, 'FORBIDDEN'],
            'not found' => [ResourceNotFound::for('products', 1), 404, 'RESOURCE_NOT_FOUND'],
            'validation' => [new ValidationFailed(['a' => 'b']), 422, 'VALIDATION_FAILED'],
            'business rule' => [new BusinessRuleViolation('No.'), 422, 'BUSINESS_RULE_VIOLATION'],
            'conflict' => [new Conflict('Dup.'), 409, 'CONFLICT'],
            'unsupported' => [ResourceOperationNotSupported::for('products', 'x'), 405, 'RESOURCE_OPERATION_NOT_SUPPORTED'],
            'too large' => [new RequestEntityTooLarge('Big.'), 413, 'REQUEST_ENTITY_TOO_LARGE'],
            'unsupported media' => [new UnsupportedMediaType('No.'), 415, 'UNSUPPORTED_MEDIA_TYPE'],
        ];
    }

    #[DataProvider('domainMapping')]
    public function test_from_domain_maps_status_once_for_both_adapters(object $exception, int $status, string $code): void
    {
        $error = ApiError::fromDomain($exception);

        $this->assertSame($status, $error->status);
        $this->assertSame($code, $error->code);
    }

    public function test_validation_details_travel_in_meta(): void
    {
        $response = ApiResponse::error(ApiError::fromDomain(new ValidationFailed(['sku' => ['required']])));

        $this->assertSame('error', $response->toArray()['status']);
        $this->assertSame('VALIDATION_FAILED', $response->toArray()['code']);
        $this->assertSame(422, $response->httpStatus);
        $this->assertSame(['sku' => ['required']], $response->toArray()['meta']['details']);
    }

    public function test_partial_for_configuration_aggregation(): void
    {
        $response = ApiResponse::partial(['core' => []], 'Partial.');

        $this->assertSame('partial', $response->status);
        $this->assertSame('PARTIAL', $response->code);
        $this->assertSame(207, $response->httpStatus);
    }

    public function test_pagination_math(): void
    {
        $this->assertSame(1, (new PaginationMeta(1, 15, 0))->lastPage());
        $this->assertSame(3, (new PaginationMeta(1, 10, 25))->lastPage());
        $this->assertSame(
            ['page' => 2, 'per_page' => 10, 'total' => 25, 'last_page' => 3],
            (new PaginationMeta(2, 10, 25))->toArray()
        );
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Psr\Log\LoggerInterface;
use Shardevcom\Observability\Logging\JsonFormatter;
use Tests\TestCase;

/**
 * T-CASA-023 acceptance over real HTTP: a full request produces structured
 * JSON lines carrying the same correlation_id across the request line, the
 * lifecycle line and the error line; inbound ids are only trusted as UUIDv4;
 * two requests never share a request_id.
 */
final class ObservabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var list<string>
     */
    private const ALL_ABILITIES = ['products.view', 'products.create', 'products.update', 'products.delete', 'products.list', 'products.import', 'products.export'];

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = tempnam(sys_get_temp_dir(), 'structured-');

        $logger = Log::build([
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => $this->logPath],
            'formatter' => JsonFormatter::class,
            'level' => 'debug',
        ]);

        // Everything — the Log facade used by exception handlers AND the
        // Psr logger the contract Logger wraps — writes to the same file.
        $this->app->instance('log', $logger);
        $this->app->instance(LoggerInterface::class, $logger);
    }

    public function test_a_full_request_keeps_one_correlation_id_across_request_lifecycle_and_error(): void
    {
        $viewOnly = AdminUser::factory()->create()
            ->createToken('cli', ['products.view', 'products.list'])
            ->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$viewOnly);

        // One successful read → request + lifecycle lines…
        $this->getJson('/admin/api/v1/products')->assertOk();

        // …and one operation that fails inside the lifecycle (export denied)
        // → request + error lines.
        $this->get('/admin/api/v1/products/export')->assertForbidden();

        $lines = $this->lines();

        $requestLine = $this->findLine($lines, static fn (array $l): bool => ($l['message'] ?? '') === 'HTTP request completed');
        $lifecycleLine = $this->findLine($lines, static fn (array $l): bool => ($l['message'] ?? '') === 'Resource action completed');
        $failedRequestLine = $this->findLine($lines, static fn (array $l): bool => ($l['message'] ?? '') === 'HTTP request failed');
        $errorLine = $this->findLine($lines, static fn (array $l): bool => ($l['message'] ?? '') === 'Resource action failed');

        $this->assertNotNull($requestLine, 'request line missing');
        $this->assertNotNull($lifecycleLine, 'lifecycle line missing');
        $this->assertNotNull($failedRequestLine, 'failed-request line missing');
        $this->assertNotNull($errorLine, 'error line missing');

        // Same correlation across the request and the lifecycle of the SAME read…
        $this->assertSame($requestLine['correlation_id'], $lifecycleLine['correlation_id']);

        // …and across the request and the error of the same denied export.
        $this->assertSame($failedRequestLine['correlation_id'], $errorLine['correlation_id']);

        // Two different requests => two different correlations.
        $this->assertNotSame($requestLine['correlation_id'], $failedRequestLine['correlation_id']);

        $this->assertSame('products', $lifecycleLine['resource']);
        $this->assertSame('view', $lifecycleLine['action']);
        $this->assertSame('admin', $lifecycleLine['guard']);
        $this->assertSame(403, $failedRequestLine['status']);
    }

    public function test_an_inbound_correlation_id_is_only_honoured_when_it_is_a_uuidv4(): void
    {
        $this->token();

        $this->getJson('/admin/api/v1/products', ['X-Correlation-ID' => 'not-a-uuid'])->assertOk();
        $afterInvalid = $this->requestLine();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $afterInvalid['correlation_id']
        );
        $this->assertNotSame('not-a-uuid', $afterInvalid['correlation_id']);

        $valid = '11111111-1111-4111-8111-111111111111';
        $this->getJson('/admin/api/v1/products', ['X-Correlation-ID' => $valid])->assertOk();
        $afterValid = $this->requestLine();

        $this->assertSame($valid, $afterValid['correlation_id']);
    }

    public function test_two_requests_never_share_a_request_id(): void
    {
        $this->token();

        $this->getJson('/admin/api/v1/products')->assertOk();
        $first = $this->requestLine();

        $this->getJson('/admin/api/v1/products')->assertOk();
        $second = $this->requestLine();

        $this->assertNotSame($first['request_id'], $second['request_id']);
        $this->assertNotSame($first['correlation_id'], $second['correlation_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function requestLine(): array
    {
        $line = $this->findLastLine($this->lines(), static fn (array $l): bool => ($l['message'] ?? '') === 'HTTP request completed');

        $this->assertNotNull($line, 'no request line in '.$this->logPath);

        return $line;
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     *
     * @return array<string, mixed>|null the LAST matching line
     */
    private function findLastLine(array $lines, callable $predicate): ?array
    {
        $last = null;

        foreach ($lines as $line) {
            if ($predicate($line)) {
                $last = $line;
            }
        }

        return $last;
    }

    /**
     * @param callable(array<string, mixed>): bool $predicate
     *
     * @return array<string, mixed>|null
     */
    private function findLine(array $lines, callable $predicate): ?array
    {
        foreach ($lines as $line) {
            if ($predicate($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lines(): array
    {
        $decoded = [];
        $raw = (string) file_get_contents($this->logPath);
        foreach (preg_split('/\R/', trim($raw)) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true);

            if (is_array($entry)) {
                $decoded[] = $entry;
            }
        }

        return $decoded;
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities = self::ALL_ABILITIES): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }
}
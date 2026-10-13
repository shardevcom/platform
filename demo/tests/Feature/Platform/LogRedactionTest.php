<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AdminUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\StreamHandler;
use Shardevcom\Observability\Logging\JsonFormatter;
use Shardevcom\Observability\Logging\SecretRedactionProcessor;
use Tests\TestCase;

/**
 * T-CASA-024 acceptance: token, password and failed-import-row values never
 * end up in a log line. The redaction processor is wired on the monolog
 * channel AND pushed onto the default application logger at boot, so the
 * queue/job logs (which bypass the structured channel) are covered too.
 */
final class LogRedactionTest extends TestCase
{
    use RefreshDatabase;

    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logPath = tempnam(sys_get_temp_dir(), 'redaction-');

        $logger = Log::build([
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'handler_with' => ['stream' => $this->logPath],
            'formatter' => JsonFormatter::class,
            'processors' => [SecretRedactionProcessor::class],
            'level' => 'debug',
        ]);

        $this->app->instance('log', $logger);
        $this->app->instance(\Psr\Log\LoggerInterface::class, $logger);
    }

    public function test_a_token_a_password_and_an_api_key_never_leave_the_sensitive_headers_grep(): void
    {
        $tokenValue = 'eyJciOiJIUzI1NiJ9.some.payload.sign';
        $passwordValue = 'S3cr3t-Pw!';
        $apiKeyValue = 'k-0987654321';

        // Resolved through the container so the test's capture logger is used
        // (the Log facade caches the original LogManager from app boot).
        $this->app->make(\Psr\Log\LoggerInterface::class)->info('login attempt', [
            'Authorization' => "Bearer {$tokenValue}",
            'password' => $passwordValue,
            'api_key' => $apiKeyValue,
            'Cookie' => 'session=abc123',
            'Set-Cookie' => 'refresh=def456',
            'correlation_id' => '11111111-1111-4111-8111-111111111111',
            'user_id' => '1',
        ]);

        $content = (string) file_get_contents($this->logPath);

        // Raw secret values are gone…
        foreach ([$tokenValue, $passwordValue, $apiKeyValue, 'abc123', 'def456'] as $value) {
            $this->assertStringNotContainsString($value, $content, "leaked {$value}");
        }

        // …and the grep-equivalent over the sensitive headers is empty (0 hits
        // for the secret values under those header names).
        foreach (['Authorization', 'password', 'api_key', 'Cookie', 'Set-Cookie'] as $header) {
            $this->assertSame(0, substr_count($content, $header.':'.' '));
        }

        $this->assertStringContainsString('Bearer [REDACTED]', $content);
        $this->assertSame(5, substr_count($content, '[REDACTED]'));
        $this->assertStringContainsString('11111111-1111-4111-8111-111111111111', $content);
        $this->assertStringContainsString('"user_id":"1"', $content);
    }

    public function test_a_failed_import_row_never_leaks_password_or_token_columns_into_the_log(): void
    {
        $this->token();

        // The row carries a password and a token COLUMN in the raw spreadsheet
        // (the leak vector: createFailure stores the raw row and the job logs
        // it). The missing `name` forces the row to fail.
        $file = UploadedFile::fake()->createWithContent(
            'import.csv',
            "sku,name,password,token\nI-1,,Sup3rRowSecret,rowtok99\n"
        );

        $this->post('/admin/api/v1/products/import', ['file' => $file, 'format' => 'csv'])
            ->assertStatus(202);

        $content = (string) file_get_contents($this->logPath);

        // The failure was logged (the job now surfaces it)…
        $this->assertStringContainsString('"message":"Import row failed"', $content);

        // …but the raw row values never made it.
        $this->assertStringNotContainsString('Sup3rRowSecret', $content);
        $this->assertStringNotContainsString('rowtok99', $content);
        $this->assertStringNotContainsString('"password"', $content);
        $this->assertStringNotContainsString('"token"', $content);

        // The redaction marker and the benign columns survive.
        $this->assertStringContainsString('[REDACTED]', $content);
        $this->assertStringContainsString('I-1', $content);
    }

    /**
     * @param list<string> $abilities
     */
    private function token(array $abilities = ['products.view', 'products.list', 'products.import']): void
    {
        $plain = AdminUser::factory()->create()->createToken('cli', $abilities)->accessToken;

        $this->withHeader('Authorization', 'Bearer '.$plain);
    }
}
<?php

declare(strict_types=1);

namespace Tests\Unit\Observability;

use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Shardevcom\Observability\Logging\SecretRedactionProcessor;

/**
 * T-CASA-024: secret values never reach a structured log line. Cover the
 * sensitive key names (Authorization, Cookie, X-API-Key, password, token,
 * access_token, refresh_token, api_token, secret) at any nesting depth and
 * the common message leak patterns (Authorization / Bearer / key=value).
 */
final class SecretRedactionProcessorTest extends TestCase
{
    public function test_context_keys_with_secret_values_are_redacted_at_any_depth(): void
    {
        $record = $this->record([
            'correlation_id' => '11111111-1111-4111-8111-111111111111',
            'user_id' => '1',
            'Authorization' => 'Bearer eyJhbGciOiJIUzI1NiJ9.payload.sign',
            'password' => 'S3cret!',
            'password_confirmation' => 'S3cret!',
            'api_token' => 'tok_1234',
            'access_token' => 'acc_1234',
            'refresh_token' => 'ref_1234',
            'X-API-Key' => 'key-9876',
            'secret' => 's3rv1ce',
            'Set-Cookie' => 'session=abc123',
            'headers' => [
                'authorization' => 'Bearer abc.def.ghi',
                'x-api-key' => 'hunter2',
                'cookie' => 'PHPSESSID=x',
            ],
            'row' => [
                'sku' => 'I-1',
                'name' => 'One',
                'password' => 'rowsecret',
                'raw_token' => 'tok_row',
            ],
        ]);

        $line = (new SecretRedactionProcessor())($record);
        $context = $line->context;

        // Sensitive keys replaced.
        $this->assertSame('[REDACTED]', $context['Authorization']);
        $this->assertSame('[REDACTED]', $context['password']);
        $this->assertSame('[REDACTED]', $context['api_token']);
        $this->assertSame('[REDACTED]', $context['X-API-Key']);
        $this->assertSame('[REDACTED]', $context['secret']);
        $this->assertSame('[REDACTED]', $context['Set-Cookie']);

        // Nested (headers map, failed-import row).
        $this->assertSame('[REDACTED]', $context['headers']['authorization']);
        $this->assertSame('[REDACTED]', $context['headers']['x-api-key']);
        $this->assertSame('[REDACTED]', $context['headers']['cookie']);
        $this->assertSame('[REDACTED]', $context['row']['password']);
        $this->assertSame('[REDACTED]', $context['row']['raw_token']);

        // Platform dimensions and benign row values are untouched.
        $this->assertSame('11111111-1111-4111-8111-111111111111', $context['correlation_id']);
        $this->assertSame('1', $context['user_id']);
        $this->assertSame('I-1', $context['row']['sku']);
        $this->assertSame('One', $context['row']['name']);

        // No raw secret survived anywhere in the serialized record.
        foreach (['S3cret!', 'tok_1234', 'acc_1234', 'ref_1234', 'key-9876', 's3rv1ce', 'abc123', 'hunter2', 'rowsecret', 'tok_row'] as $needle) {
            $this->assertStringNotContainsString($needle, json_encode($context));
        }
    }

    public function test_numeric_indexed_header_value_lines_are_redacted(): void
    {
        $record = $this->record([
            'server' => [
                'HTTP_AUTHORIZATION' => 'Bearer zzz.yyy.xxx',
            ],
            'headers' => [
                'Authorization' => ['Bearer aaa.bbb.ccc', 'Bearer ddd.eee.fff'],
            ],
        ]);

        $context = (new SecretRedactionProcessor())($record)->context;

        $this->assertSame('[REDACTED]', $context['server']['HTTP_AUTHORIZATION']);
        $this->assertSame('[REDACTED]', $context['headers']['Authorization']);
    }

    public function test_message_authorization_and_bearer_tokens_are_scrubbed(): void
    {
        $record = $this->record([
            'message' => 'Login failed for Authorization: Bearer eyJ.abc.xyz with token=plain123 and secret=s3cret and api_key=key9',
        ]);

        $message = (new SecretRedactionProcessor())($record)->message;

        $this->assertStringNotContainsString('eyJ.abc.xyz', $message);
        $this->assertStringNotContainsString('plain123', $message);
        $this->assertStringNotContainsString('s3cret', $message);
        $this->assertStringNotContainsString('key9', $message);
        $this->assertStringContainsString('Bearer [REDACTED]', $message);
        $this->assertStringContainsString('token=[REDACTED]', $message);
        $this->assertStringContainsString('secret=[REDACTED]', $message);
        $this->assertStringContainsString('api_key=[REDACTED]', $message);
    }

    public function test_json_double_quoted_secret_values_in_messages_are_scrubbed(): void
    {
        $record = $this->record([
            'message' => 'Payload {"password":"S3cret!","token":"tok123"}',
        ]);

        $message = (new SecretRedactionProcessor())($record)->message;

        $this->assertStringNotContainsString('S3cret!', $message);
        $this->assertStringNotContainsString('tok123', $message);
        $this->assertStringContainsString('password=[REDACTED]', $message);
        $this->assertStringContainsString('token=[REDACTED]', $message);
    }

    public function test_processing_is_idempotent(): void
    {
        $processor = new SecretRedactionProcessor();

        $once = $processor($this->record(['message' => 'Bearer eyJ.x.y', 'password' => 'pw1']));
        $twice = $processor($once);

        $this->assertSame($once->message, $twice->message);
        $this->assertSame($once->context, $twice->context);
        $this->assertStringContainsString('[REDACTED]', $twice->message);
    }

    public function test_null_sensitive_values_are_not_coerced(): void
    {
        $context = (new SecretRedactionProcessor())($this->record(['password' => null, 'token' => null]))->context;

        $this->assertNull($context['password']);
        $this->assertNull($context['token']);
    }

    private function record(array $overrides = []): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable('2026-10-07T00:00:00+00:00'),
            channel: 'test',
            level: Level::Info,
            message: $overrides['message'] ?? 'hello',
            context: $overrides,
        );
    }
}
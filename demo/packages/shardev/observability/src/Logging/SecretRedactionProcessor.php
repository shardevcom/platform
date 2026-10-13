<?php

declare(strict_types=1);

namespace Shardevcom\Observability\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Redacts secrets from every log record (T-CASA-024).
 *
 * - Context keys are matched case-insensitively by name (Authorization,
 *   Cookie, X-API-Key, password, token, access_token, refresh_token,
 *   api_token, ...) at any nesting depth; the whole value is replaced.
 * - The message is scrubbed against common leak patterns (Authorization,
 *   Bearer tokens, password=/token=/secret= assignments, headers).
 * - Idempotent: already-redacted values never match again.
 *
 * Registered as a processor on the structured channel (monolog driver)
 * and pushed onto the default application logger at boot, so request,
 * lifecycle and queue/job logs all pass through it.
 */
final class SecretRedactionProcessor implements ProcessorInterface
{
    private const REPLACEMENT = '[REDACTED]';

    /**
     * Context keys whose value is always replaced. Normalized form (lowercase,
     * non-alphanumeric collapsed to underscores).
     *
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'authorization',
        'proxy_authorization',
        'cookie',
        'set_cookie',
        'x_api_key',
        'apikey',
        'password',
        'password_confirmation',
        'secret',
        'client_secret',
        'private_key',
        'token',
        'access_token',
        'refresh_token',
        'api_token',
        'credentials',
    ];

    /**
     * Message patterns => replacement. Capture group 1 preserves the label.
     *
     * @var array<string, string>
     */
    private const MESSAGE_PATTERNS = [
        // Authorization: Bearer <token> / Authorization=<token>
        '~(?i)(authorization\s*[:=]\s*(?:bearer\s+)?)[a-z0-9._\-+/=]+~' => '$1'.self::REPLACEMENT,
        // password=value / token:value / secret'xxx' (incl. JSON values)
        '~(?i)\b(password|secret|token|access_token|refresh_token|api[_-]?key)\s*(?:"\s*)?[:=]\s*(?:"[^"]*"|\'[^\']*\'|[^\s,&;]+)~' => '$1='.self::REPLACEMENT,
        // Bare Bearer tokens anywhere in the message.
        '~(?i)(bearer\s+)[a-z0-9._\-+/=]+~' => '$1'.self::REPLACEMENT,
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactMessage($record->message),
            context: $this->redactValue($record->context),
        );
    }

    private function redactMessage(string $message): string
    {
        foreach (self::MESSAGE_PATTERNS as $pattern => $replacement) {
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        return $message;
    }

    /**
     * @param mixed $value
     */
    private function redactValue(mixed $value, string $key = ''): mixed
    {
        if ($this->isSensitiveKey($key)) {
            return $value === null ? null : self::REPLACEMENT;
        }

        if (!is_array($value)) {
            return $value;
        }

        $redacted = [];

        foreach ($value as $childKey => $childValue) {
            $redacted[$childKey] = $this->redactValue($childValue, (string) $childKey);
        }

        return $redacted;
    }

    private function isSensitiveKey(string $key): bool
    {
        if ($key === '') {
            return false;
        }

        $normalized = mb_strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $key));

        if (in_array($normalized, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return str_contains($normalized, 'token')
            || str_contains($normalized, 'password')
            || str_contains($normalized, 'secret')
            || str_contains($normalized, 'credential')
            || str_contains($normalized, 'authorization')
            || str_contains($normalized, 'private_key')
            || str_contains($normalized, 'cookie');
    }
}
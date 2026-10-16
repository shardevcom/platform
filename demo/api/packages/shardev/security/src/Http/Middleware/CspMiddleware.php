<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * CSP (Content Security Policy) Middleware.
 *
 * Adds Content Security Policy headers to HTTP responses.
 */
final class CspMiddleware
{
    public function __construct(
        private readonly array $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'unsafe-inline'"],
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'frame-ancestors' => ["'none'"],
            'form-action' => ["'self'"],
            'base-uri' => ["'self'"],
            'object-src' => ["'none'"],
        ],
        private readonly bool $reportOnly = false,
        private readonly ?string $reportUri = null,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $cspHeader = $this->buildCspHeader();

        $headerName = $this->reportOnly
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $response->headers->set($headerName, $cspHeader);

        return $response;
    }

    private function buildCspHeader(): string
    {
        $parts = [];

        foreach ($this->directives as $directive => $values) {
            $parts[] = $directive . ' ' . implode(' ', $values);
        }

        if ($this->reportUri !== null) {
            $parts[] = "report-uri {$this->reportUri}";
        }

        return implode('; ', $parts);
    }

    /**
     * Add a directive to the CSP.
     */
    public function addDirective(string $directive, string ...$values): self
    {
        $new = clone $this;
        $new->directives[$directive] = array_merge(
            $new->directives[$directive] ?? [],
            $values
        );

        return $new;
    }

    /**
     * Remove a directive from the CSP.
     */
    public function removeDirective(string $directive): self
    {
        $new = clone $this;
        unset($new->directives[$directive]);

        return $new;
    }

    /**
     * Enable/disable report-only mode.
     */
    public function setReportOnly(bool $reportOnly): self
    {
        $new = clone $this;
        $new->reportOnly = $reportOnly;

        return $new;
    }

    /**
     * Set the report URI for CSP violations.
     */
    public function setReportUri(?string $reportUri): self
    {
        $new = clone $this;
        $new->reportUri = $reportUri;

        return $new;
    }
}
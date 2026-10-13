<?php

declare(strict_types=1);

namespace Shardevcom\Security\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers aplicados a TODAS las respuestas.
 *
 * Estáticos y transport-agnósticos: nosniff, frame DENY, referrer policy,
 * permissions policy restrictiva, COOP/CORP (proceden en una API JSON).
 *
 * NO aplica COEP (`require-corp`): rompe consumidores JSON cross-origin y el
 * fetch cross-origin (T-CASA-018). No lleva wildcard y no pisa CORS.
 *
 * HSTS solo se emite sobre HTTPS (request segura) salvo override explícito
 * para despliegues http-only/tests: es un header que un proxy detrás de TLS
 * debe poder emitir, no la app.
 *
 * CSP solo donde hay HTML (response text/html); una API JSON no la lleva.
 */
final class SecurityHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $config = config('security.headers');

        if (($config['enabled'] ?? true) !== false) {
            $this->put($response, 'X-Content-Type-Options', $config['content_type_options'] ?? ['enabled' => true, 'value' => 'nosniff']);
            $this->put($response, 'X-Frame-Options', $config['frame_options'] ?? ['enabled' => true, 'value' => 'DENY']);
            $this->put($response, 'Referrer-Policy', $config['referrer_policy'] ?? ['enabled' => true, 'value' => 'strict-origin-when-cross-origin']);
            $this->put($response, 'Permissions-Policy', $config['permissions_policy'] ?? ['enabled' => true, 'value' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()']);
            $this->put($response, 'Cross-Origin-Opener-Policy', $config['cross_origin_opener_policy'] ?? ['enabled' => true, 'value' => 'same-origin']);
            $this->put($response, 'Cross-Origin-Resource-Policy', $config['cross_origin_resource_policy'] ?? ['enabled' => true, 'value' => 'same-site']);

            $hsts = $config['hsts'] ?? [];
            if (($hsts['enabled'] ?? true) && ($request->isSecure() || ($hsts['emit_http'] ?? false))) {
                $values = 'max-age='.($hsts['max_age'] ?? '31536000');
                if ($hsts['include_subdomains'] ?? true) {
                    $values .= '; includeSubDomains';
                }
                $response->headers->set('Strict-Transport-Security', $values);
            }

            $csp = $config['csp'] ?? [];
            if (($csp['enabled'] ?? true) && $this->isHtml($response)) {
                $response->headers->set('Content-Security-Policy', $this->buildCspHeader());
            }
        }

        return $response;
    }

    /**
     * @param array{enabled?:bool,value:string}|null $setting
     */
    private function put(Response $response, string $name, ?array $setting): void
    {
        if (($setting['enabled'] ?? true) === false) {
            $response->headers->remove($name);

            return;
        }

        $response->headers->set($name, (string) $setting['value']);
    }

    private function isHtml(Response $response): bool
    {
        return str_contains((string) $response->headers->get('Content-Type'), 'text/html');
    }

    private function buildCspHeader(): string
    {
        $parts = [];

        foreach (config('security.csp.directives', []) as $directive => $values) {
            $parts[] = $directive.' '.implode(' ', (array) $values);
        }

        return implode('; ', $parts);
    }
}
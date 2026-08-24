<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Framing headers for the Resayil embed (drawer + full-page view). Ported
 * near-verbatim from the aircon project's App\Http\Middleware\ResayilFrameHeaders.
 *
 * Two distinct concerns, handled together:
 *
 *  1. OUR page must NOT be framed by a third party (clickjacking protection):
 *     `Content-Security-Policy: frame-ancestors 'self'`.
 *
 *  2. WE must be allowed to frame the Resayil CRM origin INSIDE our page:
 *     `frame-src {resayil_origin}`. Only the configured Resayil origin is
 *     whitelisted as a frame source — nothing else.
 *
 * The Resayil origin is derived from the server-configured embed_url (never
 * user input), so this never opens an arbitrary-origin framing hole.
 *
 * Scope: registered as the `resayil.frame` alias and applied ONLY to the
 * Resayil route(s) (routes/web.php), not app-wide — unlike aircon, which
 * applies it to the whole authenticated group. TravelERP has no pre-existing
 * global CSP, so scoping this narrowly does not remove any protection that
 * exists today; it only adds explicit hardening to the routes that actually
 * render the Resayil iframe.
 */
class ResayilFrameHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $embedOrigin = $this->originOf((string) config('resayil.embed_url'));

        $frameSrc = $embedOrigin !== null
            ? "frame-src 'self' {$embedOrigin}"
            : "frame-src 'self'";

        $csp = "frame-ancestors 'self'; {$frameSrc}";

        $response->headers->set('Content-Security-Policy', $csp);
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    /**
     * Reduce a URL to its scheme://host[:port] origin, or null if unparseable.
     */
    protected function originOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $origin = "{$parts['scheme']}://{$parts['host']}";

        if (isset($parts['port'])) {
            $origin .= ":{$parts['port']}";
        }

        return $origin;
    }
}

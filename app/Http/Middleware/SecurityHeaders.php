<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers that tell a browser what this application is allowed to do.
 * The same set as the HRIS.
 *
 * They cost nothing and close whole classes of attack that no amount of care
 * in the views can reach: an attendance screen shown inside an invisible frame
 * on somebody else's page, a login form quietly re-pointed at another host, a
 * text file served as script because the browser guessed.
 *
 * **There is no `script-src` here, on purpose.** Filament runs on Livewire and
 * Alpine, and Alpine evaluates its directives at runtime, so the only script
 * policy this application could actually run under is one carrying
 * `unsafe-eval` and `unsafe-inline` — which permits exactly what a script
 * policy exists to forbid, while reading on an audit as though scripts were
 * locked down. A header that claims a protection it does not provide is worse
 * than its absence. See the CSP note in CLAUDE.md before adding one.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', implode('; ', [
            // Nobody may frame this application.
            "frame-ancestors 'none'",
            // An injected <base> tag cannot repoint every relative URL,
            // including the login form's.
            "base-uri 'self'",
            // A form on this site posts to this site.
            "form-action 'self'",
            // No plugin of any kind.
            "object-src 'none'",
        ]));

        // What frame-ancestors says, said again for browsers that predate it.
        $response->headers->set('X-Frame-Options', 'DENY');

        // A file served with the wrong type is not promoted to script because
        // the browser sniffed its contents and decided otherwise.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // A URL here can name an employee. It does not travel to another host
        // in a Referer header.
        $response->headers->set('Referrer-Policy', 'same-origin');

        // Nothing here needs a camera, a microphone or a location, so nothing
        // here — or anything injected into it — may ask.
        $response->headers->set('Permissions-Policy', implode(', ', [
            'camera=()',
            'microphone=()',
            'geolocation=()',
            'payment=()',
            'usb=()',
        ]));

        // Only over a connection that is already secure. Over plain http every
        // browser ignores it.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}

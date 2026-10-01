<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in HTML pages must not be served from the browser history cache,
 * otherwise pressing Back after logout shows private pages again.
 */
class PreventAuthenticatedPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $signedIn = Auth::guard('admin')->check() || Auth::guard('web')->check();
        $isHtml = str_contains((string) $response->headers->get('Content-Type'), 'text/html');

        if ($signedIn && $isHtml) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}

<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'suspended' => \App\Http\Middleware\CheckIfSuspended::class,
            'staff.only' => \App\Http\Middleware\EnsureNotStorefrontCustomer::class,
        ]);

        // A stale token must never block signing out (shared admin/storefront session rotates tokens).
        $middleware->validateCsrfTokens(except: [
            'logout',
            'account/logout',
        ]);

        // Drop leftover staff sessions from the customer (web) guard on every request.
        $middleware->appendToGroup('web', [
            \App\Http\Middleware\EnsureStorefrontWebGuardIsCustomer::class,
            \App\Http\Middleware\PreventAuthenticatedPageCache::class,
        ]);

        // Guests: customers → storefront sign-in; staff routes → admin login.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('account', 'account/*', 'checkout') || $request->routeIs('website.*')) {
                return route('login');
            }

            return route('admin.login');
        });

        $middleware->redirectUsersTo(function (Request $request) {
            if ($request->is('admin/login') || $request->routeIs('admin.login', 'admin.login.store')) {
                return route('dashboard');
            }

            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Laravel converts TokenMismatchException into HttpException(419) before render callbacks run,
        // so the CSRF handler must match on the 419 status, not the original exception class.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 && ! ($e->getPrevious() instanceof TokenMismatchException)) {
                return null;
            }

            if ($request->routeIs('website.account.logout') || $request->is('account/logout')) {
                if ($request->hasSession()) {
                    \App\Support\AuthSession::logout($request, 'web');
                }

                return redirect()->route('login');
            }

            if ($request->routeIs('logout') || $request->is('logout')) {
                if ($request->hasSession()) {
                    \App\Support\AuthSession::logout($request, 'admin');
                }

                return redirect()->route('admin.login');
            }

            if ($request->hasSession()) {
                $request->session()->regenerateToken();
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'message' => 'Your session expired. Please try again.',
                    'csrf_mismatch' => true,
                    'csrf_token' => csrf_token(),
                ], 419);
            }

            // Admin login: always re-render a fresh form (avoid stale back/bfcache tokens).
            if ($request->is('admin/login') || $request->routeIs('admin.login', 'admin.login.store')) {
                return redirect()
                    ->route('admin.login')
                    ->withInput($request->except('_token', 'password', 'password_confirmation'))
                    ->with('error', 'Your session expired for security. Please try again.');
            }

            return redirect()
                ->back()
                ->withInput($request->except('_token', 'password', 'password_confirmation'))
                ->with('error', 'Your session expired for security. Please try again.')
                ->with('session_expired', true);
        });

        $exceptions->render(function (\Illuminate\Auth\Access\AuthorizationException $e, Request $request) {
            $message = 'You do not have permission to do that.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 403);
            }

            return redirect()
                ->back()
                ->with('error', $message);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 403) {
                return null;
            }

            $message = $e->getMessage() ?: 'You do not have permission to do that.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['message' => $message], 403);
            }

            // Prefer a toastable redirect over a bare 403 page for admin actions.
            if ($request->user() && ! $request->is('login', 'register')) {
                return redirect()
                    ->back()
                    ->with('error', $message);
            }

            return null;
        });
    })->create();

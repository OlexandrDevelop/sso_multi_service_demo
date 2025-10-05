<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SsoAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->cookie(config('sso.cookie_name', 'sso_token')) ?: $request->bearerToken();
        $authBase = rtrim(config('sso.auth_base_url'), '/');

        if (!$token) {
            Log::channel('sso')->info('sso.auth.missing_token', ['service' => 'C', 'url' => $request->fullUrl(), 'ip' => $request->ip()]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $redirect = urlencode($request->fullUrl());
            return redirect("{$authBase}/login?redirect={$redirect}");
        }

        Log::channel('sso')->info('sso.auth.validate.start', ['service' => 'C', 'url' => $request->fullUrl()]);
        $response = Http::withToken($token)
            ->acceptJson()
            ->get($authBase . '/api/auth/me');

        if ($response->failed()) {
            Log::channel('sso')->info('sso.auth.validate.failed', ['service' => 'C', 'status' => $response->status()]);
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated'], 401);
            }
            $redirect = urlencode($request->fullUrl());
            return redirect("{$authBase}/login?redirect={$redirect}");
        }

        $json = $response->json();
        Log::channel('sso')->info('sso.auth.validate.success', ['service' => 'C', 'user' => $json['id'] ?? null]);
        $request->attributes->set('sso_user', $json);
        return $next($request);
    }
}

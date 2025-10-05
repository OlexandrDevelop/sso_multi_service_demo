<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\AuthService;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    private AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    public function login(LoginRequest $request): Response|JsonResponse|RedirectResponse
    {
        $redirectUrl = $request->input('redirect');
        $result = $this->authService->attemptLogin(
            $request->input('email'),
            $request->input('password'),
            $request->boolean('remember', false)
        );

        if (!$result->success) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }

            return response(view('login', [
                'error' => 'Invalid credentials',
                'redirect' => $redirectUrl,
            ]), 401);
        }

        // Establish Laravel session
        if ($result->user) {
            Auth::login($result->user, $request->boolean('remember', false));
        }


        [$cookieName, $cookie, $minutes] = $this->buildSsoCookie($result->accessToken, $result->expiresAt);
        $secure = (bool) config('sso.cookie_secure', false);

        if ($request->wantsJson()) {
            return response()->json([
                'token' => $result->accessToken,
                'expires_at' => $result->expiresAt?->toIso8601String(),
                'user' => new UserResource($result->user),
            ])->cookie(...[$cookieName, $cookie, $minutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
        }

        $target = $redirectUrl ?: config('app.url');
        if ($redirectUrl) {
            $encodedToken = rawurlencode($result->accessToken);
            if (str_contains($target, '#')) {
                $target .= '&token='.$encodedToken;
            } else {
                $target .= '#token='.$encodedToken;
            }
        }

        return response()->redirectTo($target)
            ->cookie(...[$cookieName, $cookie, $minutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
    }

    public function token(Request $request): JsonResponse
    {
        $token = $request->cookie(config('sso.cookie_name', 'sso_token')) ?: $request->bearerToken();
        $secure = (bool) config('sso.cookie_secure', false);

        if (!$token && Auth::check()) {
            // Mint a new token for the authenticated session
            /** @var User $user */
            $user = Auth::user();
            $personal = $user->createToken('SSO');
            $token = $personal->accessToken;
            $expiresAt = $personal->token->expires_at;
            [$cookieName, $cookie, $minutes] = $this->buildSsoCookie($token, $expiresAt);
            return response()->json(['token' => $token])
                ->cookie(...[$cookieName, $cookie, $minutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }
        return response()->json(['token' => $token]);
    }

    public function me(Request $request): JsonResponse
    {
        $token = $request->cookie(config('sso.cookie_name', 'sso_token'));
        if (!$token && $request->bearerToken()) {
            $token = $request->bearerToken();
        }

        if (!$token) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = $this->authService->userFromAccessToken($token);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        return response()->json(new UserResource($user));
    }

    public function logout(Request $request): Response|JsonResponse|RedirectResponse
    {
        $token = $request->cookie(config('sso.cookie_name', 'sso_token'));
        if (!$token && $request->bearerToken()) {
            $token = $request->bearerToken();
        }

        if ($token) {
            $this->authService->revokeAccessToken($token);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $cookieName = config('sso.cookie_name', 'sso_token');
        $forgetMinutes = -60;
        $secure = (bool) config('sso.cookie_secure', false);

        if ($request->wantsJson()) {
            return response()->json(['message' => 'Logged out'])
                ->cookie(...[$cookieName, '', $forgetMinutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
        }

        return response()->redirectTo(config('app.url'))
            ->cookie(...[$cookieName, '', $forgetMinutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
    }

    public function publicKey(ResponseFactory $response): Response
    {
        $publicKeyPath = storage_path('oauth-public.key');
        abort_unless(is_file($publicKeyPath), 404, 'Public key not found');

        return $response->make(file_get_contents($publicKeyPath), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function loginView(Request $request): Response|RedirectResponse
    {
        $redirect = $request->query('redirect');
        $secure = (bool) config('sso.cookie_secure', false);

        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            // If no valid cookie token, mint one
            $cookieToken = $request->cookie(config('sso.cookie_name', 'sso_token'));
            $valid = $cookieToken && $this->authService->userFromAccessToken($cookieToken);
            if (!$valid) {
                $personal = $user->createToken('SSO');
                $cookieToken = $personal->accessToken;
                $expiresAt = $personal->token->expires_at;
                [$cookieName, $cookie, $minutes] = $this->buildSsoCookie($cookieToken, $expiresAt);
                $target = $redirect ?: config('app.url');
                if ($redirect) {
                    $encodedToken = rawurlencode($cookieToken);
                    if (str_contains($target, '#')) {
                        $target .= '&token='.$encodedToken;
                    } else {
                        $target .= '#token='.$encodedToken;
                    }
                }
                return response()->redirectTo($target)
                    ->cookie(...[$cookieName, $cookie, $minutes, '/', config('sso.cookie_domain'), $secure, true, false, config('sso.cookie_samesite', 'None')]);
            }
            // Cookie valid: just redirect back with hash token
            $target = $redirect ?: config('app.url');
            if ($redirect) {
                $encodedToken = rawurlencode($cookieToken);
                if (str_contains($target, '#')) {
                    $target .= '&token='.$encodedToken;
                } else {
                    $target .= '#token='.$encodedToken;
                }
            }
            return response()->redirectTo($target);
        }

        return response(view('login', [
            'error' => null,
            'redirect' => $redirect,
        ]));
    }

    private function buildSsoCookie(string $token, \Carbon\CarbonInterface|null $expiresAt): array
    {
        $minutes = $expiresAt ? now()->diffInMinutes($expiresAt, false) : 60 * 24;
        if ($minutes <= 0) {
            $minutes = 5;
        }

        return [
            config('sso.cookie_name', 'sso_token'),
            $token,
            $minutes,
        ];
    }
}

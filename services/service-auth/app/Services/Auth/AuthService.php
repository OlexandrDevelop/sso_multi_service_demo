<?php

namespace App\Services\Auth;

use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class AuthService
{
    public function attemptLogin(string $email, string $password, bool $remember = false): LoginResult
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($password, $user->password)) {
            return new LoginResult(false);
        }

        $personal = $user->createToken('SSO');
        $accessToken = $personal->accessToken;

        $claims = $this->decodeJwtClaims($accessToken);
        $expiresAt = isset($claims['exp']) ? Carbon::createFromTimestamp((int)$claims['exp']) : null;

        return new LoginResult(true, $accessToken, $expiresAt, $user);
    }

    public function userFromAccessToken(string $jwt): ?User
    {
        $claims = $this->decodeJwtClaims($jwt);
        if ($claims === null) {
            return null;
        }

        $tokenId = $claims['jti'] ?? null;
        $userId = $claims['sub'] ?? null;
        $exp = isset($claims['exp']) ? (int)$claims['exp'] : null;
        if (!$tokenId || !$userId) {
            return null;
        }

        if ($exp !== null && $exp <= time()) {
            return null;
        }

        $dbToken = DB::table('oauth_access_tokens')
            ->where('id', $tokenId)
            ->where('revoked', false)
            ->first();

        if (!$dbToken) {
            return null;
        }

        return User::query()->find($userId);
    }

    public function revokeAccessToken(string $jwt): void
    {
        $claims = $this->decodeJwtClaims($jwt);
        if ($claims === null) {
            return;
        }

        $tokenId = $claims['jti'] ?? null;
        if (!$tokenId) {
            return;
        }

        DB::table('oauth_access_tokens')->where('id', $tokenId)->update(['revoked' => true]);
        DB::table('oauth_refresh_tokens')->where('access_token_id', $tokenId)->update(['revoked' => true]);
    }

    private function decodeJwtClaims(string $jwt): ?array
    {
        try {
            $parts = explode('.', $jwt);
            if (count($parts) !== 3) {
                return null;
            }
            $payload = $this->base64UrlDecode($parts[1]);
            $data = json_decode($payload, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padLen = 4 - $remainder;
            $data .= str_repeat('=', $padLen);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}

class LoginResult
{
    public bool $success;
    public ?string $accessToken;
    public ?CarbonInterface $expiresAt;
    public ?User $user;

    public function __construct(bool $success, ?string $accessToken = null, ?CarbonInterface $expiresAt = null, ?User $user = null)
    {
        $this->success = $success;
        $this->accessToken = $accessToken;
        $this->expiresAt = $expiresAt;
        $this->user = $user;
    }
} 
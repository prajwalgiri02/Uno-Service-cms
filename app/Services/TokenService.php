<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

/**
 * Issues and rotates stateless JWT pairs. The `type` claim separates short-lived
 * access tokens from long-lived refresh tokens; revocation relies on the JWT blacklist.
 */
class TokenService
{
    public const TYPE_ACCESS = 'access';

    public const TYPE_REFRESH = 'refresh';

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int, refresh_expires_in: int}
     */
    public function issueTokens(User $user): array
    {
        return [
            'access_token' => $this->makeToken($user, self::TYPE_ACCESS, $this->accessTtl()),
            'refresh_token' => $this->makeToken($user, self::TYPE_REFRESH, $this->refreshTtl()),
            'token_type' => 'bearer',
            'expires_in' => $this->accessTtl() * 60,
            'refresh_expires_in' => $this->refreshTtl() * 60,
        ];
    }

    /**
     * Single-use rotation: the consumed refresh token is blacklisted.
     *
     * @return array{user: User, tokens: array{access_token: string, refresh_token: string, token_type: string, expires_in: int, refresh_expires_in: int}}
     *
     * @throws JWTException
     */
    public function refresh(string $refreshToken): array
    {
        JWTAuth::setToken($refreshToken);

        if (JWTAuth::getPayload()->get('type') !== self::TYPE_REFRESH) {
            throw new TokenInvalidException('The provided token is not a refresh token.');
        }

        $user = JWTAuth::authenticate();

        if (! $user instanceof User) {
            throw new TokenInvalidException('The account for this token could not be found.');
        }

        JWTAuth::invalidate();

        return ['user' => $user, 'tokens' => $this->issueTokens($user)];
    }

    public function invalidate(string $token): void
    {
        try {
            JWTAuth::setToken($token)->invalidate();
        } catch (JWTException) {
        }
    }

    private function makeToken(User $user, string $type, int $ttlMinutes): string
    {
        $factory = JWTAuth::factory();
        $previousTtl = $factory->getTTL();

        $factory->setTTL($ttlMinutes);

        try {
            return JWTAuth::customClaims(['type' => $type])->fromUser($user);
        } finally {
            $factory->setTTL($previousTtl);
        }
    }

    private function accessTtl(): int
    {
        return (int) config('jwt.ttl', 60);
    }

    private function refreshTtl(): int
    {
        return (int) config('jwt.refresh_ttl', 20160);
    }
}

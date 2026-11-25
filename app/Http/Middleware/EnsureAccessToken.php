<?php

namespace App\Http\Middleware;

use App\Services\TokenService;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refresh tokens are valid JWTs too; only access tokens may call the API.
 */
class EnsureAccessToken
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('api')->payload()->get('type') !== TokenService::TYPE_ACCESS) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}

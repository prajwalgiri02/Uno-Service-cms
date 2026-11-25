<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserNotBlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('api')?->isBlocked()) {
            return response()->json([
                'status' => 423,
                'message' => 'Your account has been blocked.',
                'errors' => null,
            ], 423);
        }

        return $next($request);
    }
}

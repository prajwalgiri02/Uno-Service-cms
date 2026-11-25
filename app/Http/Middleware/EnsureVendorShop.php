<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the signed-in vendor's shop for the vendor API.
 */
class EnsureVendorShop
{
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $request->user('api')?->shop;

        if (! $shop) {
            return response()->json([
                'status' => 404,
                'message' => 'Set up your shop first.',
                'errors' => null,
            ], 404);
        }

        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}

<?php

namespace App\Traits;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;

trait ApiResponse
{
    protected function successResponse(mixed $data, ?string $message = null, int $code = 200): JsonResponse
    {
        $payload = [
            'status' => $code,
            'message' => $message,
            'data' => $data,
        ];

        if ($data instanceof AnonymousResourceCollection && $data->resource instanceof LengthAwarePaginator) {
            $paginator = $data->resource;

            $payload['data'] = $data->collection;
            $payload['meta'] = [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ];
        }

        return response()->json($payload, $code);
    }

    protected function errorResponse(?string $message, int $code, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => $code,
            'message' => $message,
            'errors' => $errors,
        ], $code);
    }

    /**
     * @param  array{access_token: string, refresh_token: string, token_type: string, expires_in: int}  $tokens
     * @param  array<string, mixed>  $extra
     */
    protected function respondWithToken(User $user, array $tokens, ?string $message = null, int $code = 200, array $extra = []): JsonResponse
    {
        return $this->successResponse([
            ...$tokens,
            ...$extra,
            'user' => new UserResource($user->loadMissing('roles')),
        ], $message, $code);
    }

    protected function resourceResponse(JsonResource $resource, ?string $message = null, int $code = 200): JsonResponse
    {
        return response()->json([
            'status' => $code,
            'message' => $message,
            'data' => $resource,
        ], $code);
    }
}

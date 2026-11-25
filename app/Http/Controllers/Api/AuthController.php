<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ApiLogoutRequest;
use App\Http\Requests\Auth\RefreshTokenRequest;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PhoneLoginService;
use App\Services\TokenService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly PhoneLoginService $phoneLogin,
        private readonly OtpService $otpService,
        private readonly TokenService $tokenService,
    ) {}

    public function sendOtp(SendOtpRequest $request): JsonResponse
    {
        $issued = $this->phoneLogin->sendCode($request->phone());

        $data = [
            'phone' => $request->phone(),
            'expires_in' => $this->otpService->expiresInSeconds($issued['otp']),
            'resend_in' => $this->otpService->resendInSeconds($issued['otp']),
        ];

        if (config('otp.expose_code') && ! app()->isProduction()) {
            $data['code'] = $issued['code'];
        }

        return $this->successResponse($data, 'Verification code sent.');
    }

    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        ['user' => $user, 'is_new_user' => $isNew] = $this->phoneLogin->verify(
            $request->phone(),
            $request->code(),
            $request->signInAs(),
        );

        if ($user->isBlocked()) {
            return $this->errorResponse('Your account has been blocked.', 403);
        }

        return $this->respondWithToken(
            $user,
            $this->tokenService->issueTokens($user),
            $isNew ? 'Account created.' : 'Signed in.',
            extra: ['is_new_user' => $isNew],
        );
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            ['user' => $user, 'tokens' => $tokens] = $this->tokenService->refresh($request->string('refresh_token')->toString());
        } catch (JWTException) {
            return $this->errorResponse('Invalid or expired refresh token.', 401);
        }

        if ($user->isBlocked()) {
            $this->tokenService->invalidate($tokens['refresh_token']);

            return $this->errorResponse('Your account has been blocked.', 403);
        }

        return $this->respondWithToken($user, $tokens);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('api');

        return $this->resourceResponse(new UserResource($user->loadMissing('roles')));
    }

    public function logout(ApiLogoutRequest $request): JsonResponse
    {
        Auth::guard('api')->logout();

        if ($refreshToken = $request->input('refresh_token')) {
            $this->tokenService->invalidate($refreshToken);
        }

        return $this->successResponse(null, 'Signed out.');
    }
}

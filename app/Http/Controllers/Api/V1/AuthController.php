<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Resources\V1\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @tags Authentication
 */
class AuthController extends ApiController
{
    /**
     * Issue an API token.
     *
     * Exchanges credentials for a personal access token. Send it as
     * `Authorization: Bearer <token>` on subsequent requests. Limited to
     * five attempts per minute per email and IP address.
     *
     * @unauthenticated
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', Str::lower($request->string('email')))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'This account has been deactivated.']);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? now()->addMinutes($minutes) : null;
        $token = $user->createToken($request->string('device_name'), ['*'], $expiresAt);

        return response()->json([
            'data' => [
                'token' => $token->plainTextToken,
                'token_type' => 'Bearer',
                'expires_at' => $expiresAt?->toIso8601String(),
                'user' => new UserResource($user->load('student')),
            ],
        ], 201);
    }

    /**
     * Current user.
     */
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user()->load('student'));
    }

    /**
     * Revoke the current token.
     */
    public function destroy(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}

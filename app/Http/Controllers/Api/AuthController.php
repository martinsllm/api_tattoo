<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function register(StoreUserRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->validated());
            $user->assignRole('client');

            return $user;
        });

        $token = $user->createToken('api-token', ['*'], now()->addMinutes(config('sanctum.expiration')));

        return ApiResponse::success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $user = Auth::user()->load('roles');
        $token = $user->createToken('api-token', ['*'], now()->addMinutes(config('sanctum.expiration')));

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ]);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me()
    {
        $user = Auth::user()->load('roles');

        return ApiResponse::success(new UserResource($user));
    }

    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = Auth::user();

        $validated = $request->validated();
        $profileAttributes = collect($validated)->except('email')->all();

        if (filled($request->email) && $request->email !== $user->email) {
            DB::transaction(function () use ($user, $request, $profileAttributes) {
                $user->pending_email = $request->email;

                if ($user->artistProfile && $user->artistProfile->is_active) {
                    $user->artist_catalog_suppressed_for_pending_email = true;
                    $user->artistProfile->update([
                        'is_active' => false,
                    ]);
                }

                $user->update($profileAttributes);

                $user->tokens()->delete();
            });

            $user->sendPendingEmailChangeNotification();

            return ApiResponse::success(null, 'Email de verificação enviado.');
        }

        $user->update($validated);

        return ApiResponse::success(new UserResource($user));
    }

    public function cancelPendingEmail()
    {
        $user = Auth::user();

        if (blank($user->pending_email)) {
            return ApiResponse::error('Nenhuma troca de e-mail pendente.', 422);
        }

        DB::transaction(function () use ($user) {
            $user->pending_email = null;

            if ($user->artistProfile && $user->artist_catalog_suppressed_for_pending_email) {
                $user->artist_catalog_suppressed_for_pending_email = false;
                $user->artistProfile->update([
                    'is_active' => true,
                ]);
            }

            $user->save();
        });

        return ApiResponse::success(null, 'Troca de e-mail cancelada.');
    }
}

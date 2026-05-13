<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\StoreUserRequest;
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

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user->load('roles')),
            'token' => $token,
        ]);
    }

    public function login(LoginRequest $request)
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $user = Auth::user()->load('roles');
        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function register(StoreUserRequest $request)
    {
        $user = User::create($request->all());

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $token = Auth::user()->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => Auth::user(),
            'token' => $token
        ]);
    }

    public function logout()
    {
        Auth::user()->tokens()->delete();
        return ApiResponse::success(null, 'Logged out successfully');
    }
}

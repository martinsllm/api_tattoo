<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function forgot(ForgotPasswordRequest $request)
    {
        Password::sendResetLink(
            $request->only('email')
        );

        return ApiResponse::success([], 'Se o e-mail estiver cadastrado, enviaremos as instruções de redefinição.');
    }

    public function reset(ResetPasswordRequest $request)
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PasswordReset
            ? ApiResponse::success([], 'Senha redefinida com sucesso.')
            : ApiResponse::error('Token inválido ou expirado.', 400);

    }
}

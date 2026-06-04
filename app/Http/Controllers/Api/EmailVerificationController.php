<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error('E-mail já verificado.', 422);
        }

        $user->sendEmailVerificationNotification();

        return ApiResponse::success(null, 'Link de verificação enviado.');
    }

    public function verify(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error('Link de verificação inválido.', 403);
        }

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::success(null, 'E-mail já verificado.');
        }

        $user->markEmailAsVerified();

        return ApiResponse::success(null, 'E-mail verificado com sucesso.');
    }

    public function verifyChange(string $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (blank($user->pending_email)) {
            return ApiResponse::error('Nenhuma troca de e-mail pendente.', 422);
        }

        if (! hash_equals((string) $hash, sha1($user->pending_email))) {
            return ApiResponse::error('Link de verificação inválido.', 403);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
        ])->save();

        if ($user->artistProfile) {
            $user->artistProfile->update([
                'is_active' => true,
            ]);
        }

        return ApiResponse::success(null, 'E-mail alterado com sucesso.');
    }
}

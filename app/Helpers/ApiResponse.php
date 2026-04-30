<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = [], $message = 'Success', $code = 200)
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    public static function error($message = 'Error', $code = 400, array $errors = [])
    {
        $body = ['message' => $message];

        if (! empty($errors)) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $code);
    }
}

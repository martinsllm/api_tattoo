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

    public static function error($message = 'Error', $code = 400)
    {
        return response()->json([
            'message' => $message,
        ], $code);
    }
}

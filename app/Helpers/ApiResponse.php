<?php

namespace App\Helpers;

class ApiResponse
{
    public static function success($data = [], $code = 200)
    {
        return response()->json([
            'data' => $data
        ], $code);
    }

    public static function error($message = 'Error', $code = 400)
    {
        return response()->json([
            'message' => $message
        ], $code);
    }
}
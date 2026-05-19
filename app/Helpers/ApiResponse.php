<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ApiResponse
{
    public static function success(mixed $data = [], string $message = 'Success', int $code = 200): JsonResponse
    {
        return response()->json([
            'data' => $data,
            'message' => $message,
        ], $code);
    }

    public static function paginate(ResourceCollection $collection, string $message = 'Success', int $code = 200): JsonResponse
    {
        $payload = $collection->response()->getData(true);

        return response()->json(array_merge($payload, ['message' => $message]), $code);
    }

    public static function error(string $message = 'Error', int $code = 400, array $errors = []): JsonResponse
    {
        $body = ['message' => $message];

        if (! empty($errors)) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $code);
    }
}

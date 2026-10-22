<?php

namespace App\Helpers;

use App\Enums\ApiError;

class ApiErrorMessage
{
    public const DEFAULT = ApiError::FORBIDDEN->value;

    public static function resolve(string $message, string $fallback = self::DEFAULT): string
    {
        return trim($message) === '' ? $fallback : $message;
    }
}

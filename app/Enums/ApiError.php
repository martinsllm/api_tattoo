<?php

namespace App\Enums;

enum ApiError: string
{
    case UNAUTHORIZED = 'Unauthorized';
    case UNAUTHENTICATED = 'Unauthenticated';
    case FORBIDDEN = 'Forbidden';
    case VALIDATION_ERROR = 'Validation error';
    case TOO_MANY_REQUESTS = 'Too many requests';
    case NOT_FOUND = 'Resource not found';
    case METHOD_NOT_ALLOWED = 'Method not allowed';
    case INTERNAL_SERVER_ERROR = 'Internal server error';
}

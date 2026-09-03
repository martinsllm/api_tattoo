<?php

namespace App\Enums;

enum ReportReason: string
{
    case SPAM = 'spam';
    case INAPPROPRIATE_CONTENT = 'inappropriate_content';
    case HARASSMENT = 'harassment';
    case MISINFORMATION = 'misinformation';
    case OTHER = 'other';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum ReportStatus: string
{
    case PENDING = 'pending';
    case RESOLVED = 'resolved';
    case DISMISSED = 'dismissed';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

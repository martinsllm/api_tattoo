<?php

namespace App\Traits;

use App\Models\ArtistProfile;
use App\Models\Review;

trait ResolvesReportableClass
{
    protected function resolveReportableClass(string $reportableType): ?string
    {
        return match ($reportableType) {
            'artist_profile' => ArtistProfile::class,
            'review' => Review::class,
            default => null,
        };
    }
}

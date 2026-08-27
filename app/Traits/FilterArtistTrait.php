<?php

namespace App\Traits;

trait FilterArtistTrait
{
    public function scopeApplyFilters($query, array $filters)
    {
        return $query
            ->when(
                ! empty($filters['styles']),
                fn ($query) => $query->filterStyles($filters['styles'])
            )
            ->when(
                ! empty($filters['tags']),
                fn ($query) => $query->filterTags($filters['tags'])
            )
            ->when(
                ! empty($filters['city']),
                fn ($query) => $query->filterCity($filters['city'])
            )
            ->when(
                ! empty($filters['state']),
                fn ($query) => $query->filterState($filters['state'])
            )
            ->when(
                ! empty($filters['q']),
                fn ($query) => $query->filterSearch($filters['q'])
            )
            ->when(
                array_key_exists('is_active', $filters),
                fn ($query) => $query->where('is_active', $filters['is_active'])
            )
            ->when(
                isset($filters['min_rating']),
                fn ($query) => $query->filterMinRating((int) $filters['min_rating'])
            );
    }
}

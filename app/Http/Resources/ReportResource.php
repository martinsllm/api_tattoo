<?php

namespace App\Http\Resources;

use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reportable_type' => $this->reportable_type,
            'reportable_id' => $this->reportable_id,
            'reportable' => $this->whenLoaded('reportable', function () {
                if ($this->reportable instanceof ArtistProfile) {
                    return [
                        'id' => $this->reportable?->id,
                        'studio_name' => $this->reportable?->studio_name,
                    ];
                }
                if ($this->reportable instanceof Review) {
                    return [
                        'id' => $this->reportable?->id,
                        'rating' => $this->reportable?->rating,
                        'comment' => $this->reportable?->comment,
                    ];
                }
            }),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ]),
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }
}

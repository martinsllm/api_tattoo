<?php

namespace App\Http\Resources;

use App\Models\ArtistProfile;
use App\Models\Review;
use Illuminate\Database\Eloquent\Model;
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
            'reportable' => $this->whenLoaded('reportable', fn () => $this->reportable ? $this->getReportableData($this->reportable) : null),
            'reporter' => $this->whenLoaded('reporter', fn () => [
                'id' => $this->reporter?->id,
                'name' => $this->reporter?->name,
            ]),
            'reason' => $this->reason,
            'status' => $this->status,
            'created_at' => $this->created_at,
        ];
    }

    private function getReportableData(Model $reportable): array
    {
        if ($reportable instanceof ArtistProfile) {
            return [
                'id' => $reportable->id,
                'studio_name' => $reportable->studio_name,
            ];
        }

        if ($reportable instanceof Review) {
            return [
                'id' => $reportable->id,
                'rating' => $reportable->rating,
                'comment' => $reportable->comment,
            ];
        }

        return [];
    }
}

<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Review;

class ReviewAdminController extends Controller
{
    public function destroy(Review $review)
    {
        $review->deleteOrFail();

        AuditLog::record('review.delete', $review);

        return ApiResponse::success(null, 'Review deleted successfully');
    }
}

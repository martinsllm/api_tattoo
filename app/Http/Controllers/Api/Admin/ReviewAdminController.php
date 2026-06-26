<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewAdminController extends Controller
{
    public function destroy(Review $review)
    {
        $review->deleteOrFail();

        return ApiResponse::success(null, 'Review deleted successfully');
    }
}

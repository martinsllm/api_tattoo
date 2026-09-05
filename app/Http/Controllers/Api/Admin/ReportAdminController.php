<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterAdminReportsRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;

class ReportAdminController extends Controller
{
    public function index(FilterAdminReportsRequest $request)
    {
        $perPage = $request->validated('per_page', 10);
        $status = $request->validated('status');

        $reports = Report::query()
            ->with(['reporter', 'reportable'])
            ->latest();

        if ($status) {
            $reports->where('status', $status);
        }

        return ApiResponse::paginate(ReportResource::collection($reports->paginate($perPage)), 'Reports retrieved successfully');
    }
}

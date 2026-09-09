<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ReportStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Report\FilterAdminReportsRequest;
use App\Http\Requests\Admin\Report\UpdateReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\AuditLog;
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

    public function update(UpdateReportRequest $request, Report $report)
    {
        if ($report->status !== ReportStatus::PENDING) {
            return ApiResponse::error('Report already processed', 422);
        }

        $report->update($request->validated());

        switch ($report->status) {
            case ReportStatus::RESOLVED:
                AuditLog::record('report.resolve', $report);
                break;
            case ReportStatus::DISMISSED:
                AuditLog::record('report.dismiss', $report);
                break;
        }

        return ApiResponse::success(new ReportResource($report), 'Report updated successfully');
    }
}

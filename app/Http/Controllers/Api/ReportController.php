<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Report\StoreReportRequest;
use App\Http\Requests\Shared\PaginatedRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Traits\ResolvesReportableClass;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReportController extends Controller
{
    use ResolvesReportableClass;

    public function index(PaginatedRequest $request)
    {
        $user = Auth::user();

        $reports = $user->reports()
            ->with('reportable')
            ->latest()
            ->paginate($request->integer('per_page', 10));

        return ApiResponse::paginate(ReportResource::collection($reports), 'Reports fetched successfully');
    }

    public function store(StoreReportRequest $request)
    {
        if ($request->user()->hasRole('admin')) {
            throw new AccessDeniedHttpException;
        }

        $reportableClass = $this->resolveReportableClass($request->validated('reportable_type'));

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $reportableClass,
            'reportable_id' => $request->validated('reportable_id'),
            'reason' => $request->validated('reason'),
            'status' => ReportStatus::PENDING,
        ]);

        return ApiResponse::success(new ReportResource($report), 'Report created successfully', 201);
    }
}

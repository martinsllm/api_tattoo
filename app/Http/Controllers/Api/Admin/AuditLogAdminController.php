<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAdminAuditLogsRequest;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;

class AuditLogAdminController extends Controller
{
    public function __invoke(FilterAdminAuditLogsRequest $request)
    {
        $auditLogs = AuditLog::latest()
            ->with('actor')
            ->paginate($request->validated('per_page', 10));

        return ApiResponse::paginate(AuditLogResource::collection($auditLogs), 'Audit logs fetched successfully');
    }
}

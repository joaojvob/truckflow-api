<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FinancialReportRequest;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewReports', \App\Models\Freight::class);

        return response()->json([
            'data' => $this->reportService->dashboard(),
        ]);
    }

    public function financial(FinancialReportRequest $request): JsonResponse
    {
        return response()->json([
            'data' => $this->reportService->financial($request->validated()),
        ]);
    }
}

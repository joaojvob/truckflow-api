<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExportFinancialReportRequest;
use App\Http\Requests\FinancialReportRequest;
use App\Services\ReportExportService;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function __construct(
        protected ReportService $reportService,
        protected ReportExportService $reportExportService,
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

    public function analytics(FinancialReportRequest $request): JsonResponse
    {
        $this->authorize('viewReports', \App\Models\Freight::class);

        return response()->json([
            'data' => $this->reportService->analytics($request->validated()),
        ]);
    }

    public function exportFinancial(ExportFinancialReportRequest $request): Response|BinaryFileResponse
    {
        return $this->reportExportService->exportFinancial(
            $request->safe()->only(['from', 'to']),
            $request->validated('format'),
        );
    }
}

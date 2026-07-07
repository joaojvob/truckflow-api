<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ReportExportService
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    public function exportFinancial(array $filters, string $format): Response|BinaryFileResponse
    {
        $report = $this->reportService->financial($filters);

        return match ($format) {
            'pdf'  => $this->asPdf($report),
            'xlsx' => $this->asXlsx($report),
        };
    }

    private function asPdf(array $report): Response
    {
        $filename = $this->filename('relatorio-financeiro', 'pdf');

        return Pdf::loadView('reports.financial', [
            'report'   => $report,
            'tenant'   => auth()->user()->tenant?->name ?? config('app.name'),
            'exported' => now()->format('d/m/Y H:i'),
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    private function asXlsx(array $report): BinaryFileResponse
    {
        $path = storage_path('app/temp/'.uniqid('financial_', true).'.xlsx');
        File::ensureDirectoryExists(dirname($path));

        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues(['TruckFlow — Relatório Financeiro']));
        $writer->addRow(Row::fromValues(['Período', "{$report['period']['from']} a {$report['period']['to']}"]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Resumo']));
        $writer->addRow(Row::fromValues(['Fretes concluídos', $report['summary']['freight_count']]));
        $writer->addRow(Row::fromValues(['Receita (R$)', $report['summary']['revenue']]));
        $writer->addRow(Row::fromValues(['Distância (km)', $report['summary']['distance_km']]));
        $writer->addRow(Row::fromValues(['Ticket médio (R$)', $report['summary']['avg_value']]));
        $writer->addRow(Row::fromValues([]));

        $writer->addRow(Row::fromValues(['Top motoristas']));
        $writer->addRow(Row::fromValues(['Motorista', 'Fretes', 'Receita (R$)']));

        foreach ($report['by_driver'] as $row) {
            $writer->addRow(Row::fromValues([
                $row['driver_name'] ?? '—',
                $row['freights'],
                $row['revenue'],
            ]));
        }

        $writer->close();

        return response()->download($path, $this->filename('relatorio-financeiro', 'xlsx'))->deleteFileAfterSend();
    }

    private function filename(string $base, string $extension): string
    {
        return sprintf('%s-%s.%s', $base, now()->format('Y-m-d_His'), $extension);
    }
}

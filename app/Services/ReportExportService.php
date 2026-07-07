<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporta relatórios agregados pelo {@see ReportService} em formatos para download.
 */
class ReportExportService
{
    public function __construct(
        protected ReportService $reportService,
    ) {}

    /**
     * Gera o relatório financeiro no formato solicitado.
     *
     * @param  array{from?: string, to?: string}  $filters  Período do relatório.
     * @param  string  $format  Formato de saída: `pdf` ou `xlsx`.
     * @return Response|BinaryFileResponse Arquivo para download HTTP.
     */
    public function exportFinancial(array $filters, string $format): Response|BinaryFileResponse
    {
        $report = $this->reportService->financial($filters);

        return match ($format) {
            'pdf'  => $this->asPdf($report),
            'xlsx' => $this->asXlsx($report),
        };
    }

    /**
     * Renderiza o relatório como PDF via DomPDF.
     *
     * @param  array  $report  Payload retornado por {@see ReportService::financial()}.
     */
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

    /**
     * Gera planilha XLSX via OpenSpout com resumo e ranking por motorista.
     *
     * @param  array  $report  Payload retornado por {@see ReportService::financial()}.
     */
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

    /**
     * Monta nome de arquivo com timestamp para evitar colisões.
     *
     * @param  string  $base  Prefixo do arquivo (ex.: relatorio-financeiro).
     * @param  string  $extension  Extensão sem ponto (pdf, xlsx).
     */
    private function filename(string $base, string $extension): string
    {
        return sprintf('%s-%s.%s', $base, now()->format('Y-m-d_His'), $extension);
    }
}

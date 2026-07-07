<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Relatório Financeiro — TruckFlow</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1a1a1a; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #555; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .summary td:first-child { font-weight: bold; width: 40%; }
        .footer { margin-top: 24px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <h1>Relatório Financeiro</h1>
    <p class="meta">
        <strong>{{ $tenant }}</strong><br>
        Período: {{ \Carbon\Carbon::parse($report['period']['from'])->format('d/m/Y') }}
        a {{ \Carbon\Carbon::parse($report['period']['to'])->format('d/m/Y') }}<br>
        Exportado em: {{ $exported }}
    </p>

    <h2>Resumo</h2>
    <table class="summary">
        <tr><td>Fretes concluídos</td><td>{{ $report['summary']['freight_count'] }}</td></tr>
        <tr><td>Receita total</td><td>R$ {{ number_format($report['summary']['revenue'], 2, ',', '.') }}</td></tr>
        <tr><td>Distância percorrida</td><td>{{ number_format($report['summary']['distance_km'], 1, ',', '.') }} km</td></tr>
        <tr><td>Ticket médio</td><td>R$ {{ number_format($report['summary']['avg_value'], 2, ',', '.') }}</td></tr>
    </table>

    <h2>Receita por motorista</h2>
    <table>
        <thead>
            <tr>
                <th>Motorista</th>
                <th>Fretes</th>
                <th>Receita (R$)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['by_driver'] as $row)
                <tr>
                    <td>{{ $row['driver_name'] ?? '—' }}</td>
                    <td>{{ $row['freights'] }}</td>
                    <td>{{ number_format($row['revenue'], 2, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Nenhum dado no período.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Gerado automaticamente pela API TruckFlow.</p>
</body>
</html>

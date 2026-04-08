<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Resumen Legal - {{ $loan->code }}</title>
    @php
        $isToDateMode = ($mode ?? 'to_date') === 'to_date';
        $totalDueDisplay = $isToDateMode
            ? (float) ($summary['total_due_to_date'] ?? $summary['total_due'] ?? 0)
            : (float) ($summary['total_due'] ?? 0);
    @endphp
    <style>
        :root {
            font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
        }
        body {
            margin: 0;
            background: #f8fafc;
        }
        .page {
            max-width: 820px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.08);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .badge-row {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .badge {
            background: #ecfeff;
            color: #0e7490;
            padding: 6px 14px;
            border-radius: 999px;
            font-weight: 600;
            font-size: 12px;
        }
        .badge-muted {
            background: #f1f5f9;
            color: #475569;
        }
        .client-card {
            background: #ecfdf3;
            border: 1px solid #bbf7d0;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 24px;
        }
        .client-title {
            margin: 0 0 12px;
            font-size: 22px;
            font-weight: 700;
            color: #14532d;
        }
        .client-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(180px, 1fr));
            gap: 12px 24px;
        }
        .client-item {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .client-item span {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #047857;
            font-weight: 600;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }
        .metric {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 16px;
        }
        .metric span {
            display: block;
            font-size: 12px;
            text-transform: uppercase;
            color: #64748b;
        }
        .metric strong {
            font-size: 18px;
        }
        .metric small {
            display: block;
            margin-top: 8px;
            font-size: 12px;
            font-weight: 600;
        }
        .metric small.info {
            color: #0369a1;
        }
        .metric small.warning {
            color: #b45309;
        }
        .total {
            margin-top: 20px;
            background: #0f172a;
            color: #ffffff;
            padding: 20px;
            border-radius: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .total h2 {
            margin: 0;
            font-size: 28px;
        }
        .footer {
            margin-top: 32px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
        @media (max-width: 680px) {
            .client-grid {
                grid-template-columns: 1fr;
            }
        }
        @media print {
            body {
                background: #ffffff;
            }
            .page {
                box-shadow: none;
                margin: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="header">
            <div>
                <h1>Resumen Legal</h1>
                <p>Prestamo {{ $loan->code }}</p>
            </div>
            <div class="badge-row">
                <div class="badge badge-muted">{{ $isToDateMode ? 'A la fecha' : 'Al corte' }}</div>
                <div class="badge">Legal</div>
            </div>
        </div>

        <div class="client-card">
            <h2 class="client-title">{{ $loan->client->first_name }} {{ $loan->client->last_name }}</h2>
            <div class="client-grid">
                <div class="client-item">
                    <span>Cedula</span>
                    <strong>{{ $loan->client->national_id }}</strong>
                </div>
                <div class="client-item">
                    <span>Telefono</span>
                    <strong>{{ $loan->client->phone ?? 'N/A' }}</strong>
                </div>
                <div class="client-item" style="grid-column: 1 / -1;">
                    <span>Direccion</span>
                    <strong>{{ $loan->client->address ?? 'N/A' }}</strong>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="metric">
                <span>Capital</span>
                <strong>RD$ {{ number_format($summary['principal'], 2) }}</strong>
            </div>
            <div class="metric">
                <span>Intereses</span>
                <strong>RD$ {{ number_format($summary['interest'], 2) }}</strong>
                @if ($isToDateMode)
                    <small class="info">
                        Interes al proximo corte: RD$ {{ number_format((float) ($summary['interest_at_cutoff'] ?? 0), 2) }}
                        @if (($summary['interest_next_cut_days'] ?? 0) > 0)
                            ({{ (int) $summary['interest_next_cut_days'] }} dias)
                        @endif
                    </small>
                @endif
            </div>
            <div class="metric">
                <span>Mora</span>
                <strong>RD$ {{ number_format($summary['late_fees'], 2) }}</strong>
                @if ($isToDateMode)
                    <small class="warning">
                        Mora al proximo corte: RD$ {{ number_format((float) ($summary['late_fees_pending_to_date'] ?? 0), 2) }}
                        @if (($summary['late_fees_pending_days'] ?? 0) > 0)
                            ({{ (int) $summary['late_fees_pending_days'] }} dias)
                        @endif
                    </small>
                @endif
            </div>
            <div class="metric">
                <span>Gastos legales</span>
                <strong>RD$ {{ number_format($summary['legal_fees'], 2) }}</strong>
            </div>
        </div>

        <div class="total">
            <div>
                <div style="text-transform: uppercase; font-size: 12px; letter-spacing: 1px; color: #94a3b8;">Total a pagar</div>
                <h2>RD$ {{ number_format($totalDueDisplay, 2) }}</h2>
            </div>
            <div>Fecha: {{ now()->format('d/m/Y') }}</div>
        </div>

        <div class="footer">
            Documento generado automaticamente. Para imprimir, utilice la opcion de impresion del navegador.
        </div>
    </div>
</body>
</html>

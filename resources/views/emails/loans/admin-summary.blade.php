@component('mail::message')
# Reporte diario de mora y legal

Este es el resumen diario de préstamos en mora y préstamos en legal.

@component('mail::panel')
**Préstamos en mora:** {{ count($overdueLoans) }}  
**Préstamos en legal:** {{ count($legalLoans) }}
@endcomponent

@if (count($overdueLoans))
## Préstamos en mora

@component('mail::table')
| Código | Cliente | Días Mora | Monto en Mora | Balance |
|:--|:--|--:|--:|--:|
@foreach ($overdueLoans as $loan)
| {{ $loan['code'] }} | {{ $loan['client'] }} | {{ $loan['days'] }} | RD$ {{ number_format($loan['amount'], 2) }} | RD$ {{ number_format($loan['balance'], 2) }} |
@endforeach
@endcomponent
@endif

@if (count($legalLoans))
## Préstamos en legal

@component('mail::table')
| Código | Cliente | Entrada Legal | Gastos Legales | Balance |
|:--|:--|:--|--:|--:|
@foreach ($legalLoans as $loan)
| {{ $loan['code'] }} | {{ $loan['client'] }} | {{ $loan['entered_at'] }} | RD$ {{ number_format($loan['legal_fees'], 2) }} | RD$ {{ number_format($loan['balance'], 2) }} |
@endforeach
@endcomponent
@endif

Gracias,  
{{ config('app.name') }}
@endcomponent

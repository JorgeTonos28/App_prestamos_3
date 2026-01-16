<x-mail::message>
# Aviso de Atraso

{!! $body !!}

**Detalles del Préstamo:**
* **Código:** {{ $loan->code }}
* **Monto Vencido:** RD$ {{ number_format($arrears['amount'], 2) }}
* **Días de Atraso:** {{ $arrears['days'] }}

<x-mail::button :url="route('login')">
Ver Estado de Cuenta
</x-mail::button>

Gracias,<br>
{{ config('app.name') }}
</x-mail::message>

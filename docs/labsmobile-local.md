# LabsMobile: configuración, prueba local y seguimiento

La integración usa la API JSON HTTP/POST de LabsMobile. La aplicación envía
un `POST` autenticado a `https://api.labsmobile.com/json/send` y normaliza los
teléfonos dominicanos al formato internacional (`1` seguido de 10 dígitos).

## Credenciales y configuración local

1. Entra a LabsMobile y abre **Configuración API**.
2. Genera un token API. El usuario de la API es el correo de la cuenta.
3. Guarda los datos únicamente en `.env`:

```dotenv
LABSMOBILE_ENABLED=true
LABSMOBILE_USERNAME=tu-correo-de-labsmobile@example.com
LABSMOBILE_TOKEN=tu-token-api
LABSMOBILE_ENDPOINT=https://api.labsmobile.com/json/send
LABSMOBILE_BALANCE_ENDPOINT=https://api.labsmobile.com/json/balance
LABSMOBILE_TEST_MODE=true
```

Después de editar `.env`, limpia la configuración:

```powershell
php artisan config:clear
```

El filtro por IP de LabsMobile es opcional. En desarrollo conviene dejarlo
vacío si la conexión no tiene una IP pública fija. En producción se puede
restringir a las IP de salida reales del servidor.

## Módulo SMS de la aplicación

Abre **Configuración > SMS**. Desde esa pestaña se puede:

- revisar si el proveedor y las credenciales están habilitados;
- consultar el saldo actual de LabsMobile;
- enviar un SMS individual seleccionando un cliente;
- configurar los recordatorios automáticos de mora;
- filtrar el historial por texto, fechas, estado y origen;
- revisar créditos consumidos y coste estimado.

También hay un botón **Enviar SMS** en el perfil de cada cliente y en el
detalle de cada préstamo. Los mensajes enviados desde un préstamo quedan
relacionados tanto con el cliente como con ese préstamo.

El coste mostrado es una estimación: configura en la misma pestaña el coste
efectivo por crédito y la moneda del paquete contratado. La aplicación calcula
los créditos por segmentos SMS; los envíos simulados registran cero créditos y
cero coste.

## Prueba sin consumo de saldo

Mantén `LABSMOBILE_TEST_MODE=true` y ejecuta:

```powershell
php artisan labsmobile:send-test 809-555-1234
```

Si LabsMobile responde con código `0`, mostrará una referencia (`subid`). La
petición quedó validada, pero no se entrega ningún SMS ni se descuentan
créditos. LabsMobile permite comprobar la referencia simulada en su historial.

También puedes cambiar el texto:

```powershell
php artisan labsmobile:send-test 809-555-1234 --message="Prueba SMS App Presto"
```

## Probar recordatorios de mora

Primero revisa destinatarios sin llamar al proveedor:

```powershell
php artisan loans:send-overdue-sms --force --dry-run
```

Después, todavía con `LABSMOBILE_TEST_MODE=true`, registra envíos simulados:

```powershell
php artisan loans:send-overdue-sms --force
```

Para ejecutar la programación mientras desarrollas, mantén abierto:

```powershell
php artisan schedule:work
```

## Confirmaciones de entrega

El envío funciona desde una URL local porque solo requiere una conexión HTTPS
saliente. Las confirmaciones de entrega sí necesitan que LabsMobile pueda
alcanzar una URL HTTPS pública. En producción, o mediante un túnel de
desarrollo, configura:

```dotenv
LABSMOBILE_WEBHOOK_TOKEN=una-cadena-aleatoria-larga
LABSMOBILE_ACK_URL=https://tu-dominio.example/webhooks/labsmobile/delivery?token=una-cadena-aleatoria-larga
```

El mismo valor debe aparecer en ambos lugares. Luego ejecuta
`php artisan config:clear`. La aplicación asociará cada callback con el
`subid` de LabsMobile y actualizará el estado del mensaje a entregado o fallido.

Los campos globales de URL de confirmaciones, clics y mensajes recibidos en el
panel de LabsMobile no son obligatorios para enviar. Esta integración envía su
`ackurl` por petición; las URLs de clics y SMS entrantes solo hacen falta si se
incorporan esas funciones.

Antes de pasar a envíos reales, verifica destinatarios, remitente permitido,
saldo y filtros de seguridad. Después cambia expresamente:

```dotenv
LABSMOBILE_TEST_MODE=false
```

Ese cambio habilita la entrega real y el consumo de saldo.

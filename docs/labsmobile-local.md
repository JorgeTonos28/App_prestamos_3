# LabsMobile SMS: configuración, pruebas, producción y diagnóstico

PRESTO integra LabsMobile mediante su API JSON HTTP/POST. La aplicación decide qué clientes deben recibir SMS, normaliza teléfonos dominicanos al formato internacional, registra cada intento y solicita confirmaciones de entrega (ACK) para distinguir entre un mensaje simplemente aceptado por LabsMobile y uno realmente entregado al dispositivo.

## 1. Credenciales

En LabsMobile abre **Configuración API**, genera un token y usa como usuario API el correo de la cuenta. Las credenciales solo deben existir en `.env`; nunca deben subirse al repositorio.

```dotenv
LABSMOBILE_ENABLED=true
LABSMOBILE_USERNAME=tu-correo@example.com
LABSMOBILE_TOKEN=tu-token-api
LABSMOBILE_ENDPOINT=https://api.labsmobile.com/json/send
LABSMOBILE_BALANCE_ENDPOINT=https://api.labsmobile.com/json/balance
LABSMOBILE_PRICES_ENDPOINT=https://api.labsmobile.com/json/prices
LABSMOBILE_TEST_MODE=true
```

La hora, periodicidad, cantidad máxima por día y plantilla de los recordatorios automáticos se configuran únicamente desde **Configuración > SMS > Configurar recordatorios automáticos**.

Después de cualquier cambio de `.env`:

```bash
php artisan config:clear
```

## 2. Migraciones

Después de desplegar una versión que incorpora el módulo SMS:

```bash
php artisan migrate --force
```

La tabla `sms_notifications` conserva destinatario, texto, origen, segmentos, créditos, costo estimado, referencia `subid`, respuesta inicial del proveedor, ACK, diagnóstico y fechas de envío/entrega.

## 3. Prueba sin consumir créditos

Mantén:

```dotenv
LABSMOBILE_TEST_MODE=true
```

Prueba directa desde terminal:

```bash
php artisan labsmobile:send-test 809-555-1234
```

Con texto personalizado:

```bash
php artisan labsmobile:send-test 809-555-1234 --message="Prueba SMS App Presto"
```

LabsMobile debe responder con código `0` y un `subid`. En modo prueba el envío aparece en el histórico de LabsMobile, pero no se entrega al teléfono ni consume saldo.

También puedes usar **Configuración > SMS > Enviar mensaje individual**. PRESTO muestra antes de enviar:

- cantidad de caracteres;
- codificación GSM o Unicode;
- cantidad de segmentos/SMS;
- créditos estimados para República Dominicana;
- costo aproximado en RD$ cuando esté configurado el valor monetario de un crédito.

## 4. Probar recordatorios automáticos

Primero calcula destinatarios y textos sin contactar LabsMobile:

```bash
php artisan loans:send-overdue-sms --force --dry-run
```

Luego, todavía con `LABSMOBILE_TEST_MODE=true`:

```bash
php artisan loans:send-overdue-sms --force
```

La periodicidad, hora, cantidad máxima por día y plantilla se administran desde **Configuración > SMS > Configurar recordatorios automáticos**.

## 5. Confirmaciones ACK y diagnóstico de entrega

Un estado inicial `accepted` significa que LabsMobile aceptó el envío; no confirma que el teléfono lo recibió. Para recibir el diagnóstico final, PRESTO envía un `ackurl` en cada mensaje real.

La URL debe ser pública y HTTPS:

```dotenv
LABSMOBILE_ACK_URL=https://prestamos.example.com/webhooks/labsmobile/delivery
LABSMOBILE_WEBHOOK_TOKEN=una-cadena-aleatoria-larga-y-secreta
```

No añadas manualmente `?token=` a `LABSMOBILE_ACK_URL`. PRESTO concatena automáticamente el token configurado en `LABSMOBILE_WEBHOOK_TOKEN`.

Puedes generar un token seguro con:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Después:

```bash
php artisan config:clear
```

La ruta pública es:

```text
GET /webhooks/labsmobile/delivery
```

No requiere sesión de usuario, pero rechaza cualquier callback cuyo token no coincida. En producción verifica además que Cloudflare, WAF, ModSecurity o reglas del hosting no bloqueen llamadas GET externas a esa ruta.

PRESTO conserva los datos técnicos enviados por LabsMobile y traduce los estados más importantes:

- `DELIVRD`: entregado y confirmado por el dispositivo;
- `UNDELIV`: no entregable; revisar teléfono, disponibilidad y cobertura;
- `REJECTD`: rechazado por operador/red;
- `BLOCKED`: bloqueado por filtros de seguridad o antispam;
- `EXPIRED`: expiró antes de poder entregarse;
- `UNKNOWN`: error sin causa más específica;
- `READ`: marcado como leído cuando el canal lo soporta.

En **Configuración > SMS > Historial > Detalles** se muestran `subid`, código API, `acklevel`, descripción ACK, fecha de aceptación, fecha de entrega y payload técnico. PRESTO conserva además la secuencia de eventos ACK recibidos para facilitar la depuración.

El webhook solo puede diagnosticar mensajes enviados después de que `LABSMOBILE_ACK_URL` esté activo, porque el `ackurl` viaja en la propia solicitud de envío.

## 6. Créditos, segmentos y costo en RD$

LabsMobile factura mediante créditos. PRESTO consulta automáticamente la tarifa vigente para República Dominicana en `/json/prices`; por ejemplo, un SMS estándar puede consumir una fracción de crédito, y un texto dividido en cuatro segmentos consume aproximadamente cuatro veces esa tarifa.

La tarifa de LabsMobile indica **créditos por SMS**, no el valor monetario que pagaste por cada crédito. Para traducir el consumo a pesos dominicanos, en **Configurar recordatorios automáticos** define:

**Costo efectivo de 1 crédito LabsMobile (RD$)**

Usa el costo real de tu paquete/factura. PRESTO calcula:

```text
costo estimado RD$ = segmentos SMS × créditos por SMS RD × RD$ por crédito
```

La moneda del módulo es siempre DOP/RD$.

Los textos Unicode tienen menor capacidad por segmento que los mensajes GSM. Antes de cualquier envío manual PRESTO muestra el número de SMS resultante. La plantilla automática también muestra una estimación, aunque el tamaño final puede variar al sustituir variables como nombre, monto y días de atraso.

La plantilla predeterminada de cobranza está intencionalmente redactada con caracteres GSM y de forma breve para reducir el riesgo de convertir un aviso sencillo en varios segmentos Unicode. Las plantillas personalizadas no se sobrescriben.

## 7. Saldo

PRESTO consulta el saldo automáticamente al abrir **Configuración > SMS** y vuelve a actualizarlo después de cada envío real realizado desde la aplicación. No es necesario pulsar un botón de actualización.

## 8. Pasar a producción

Antes del primer envío real:

1. Confirma que `APP_URL` usa el dominio HTTPS real.
2. Ejecuta `php artisan migrate --force`.
3. Configura usuario y token LabsMobile.
4. Configura `LABSMOBILE_ACK_URL` y `LABSMOBILE_WEBHOOK_TOKEN`.
5. Ejecuta `php artisan config:clear`.
6. Verifica que el callback público responde y no está bloqueado por el hosting.
7. Ejecuta pruebas simuladas y revisa el histórico.
8. Haz un primer envío real a un número propio/controlado.
9. Comprueba en PRESTO que el estado evoluciona de aceptado a entregado o muestra un diagnóstico de error.
10. Solo entonces activa los recordatorios automáticos.

Para habilitar entregas reales:

```dotenv
LABSMOBILE_TEST_MODE=false
```

## 9. Scheduler obligatorio en producción

Los recordatorios automáticos dependen del Laravel Scheduler. El servidor debe ejecutar cada minuto:

```cron
* * * * * /usr/local/bin/php /home/usuario/ruta_del_proyecto/artisan schedule:run >> /dev/null 2>&1
```

El comando SMS también se evalúa cada minuto, pero sale inmediatamente si la hora actual no coincide con la hora definida por el administrador.

Comprueba la programación con:

```bash
php artisan schedule:list
```

## 10. Diagnóstico rápido

Si el mensaje aparece como **Aceptado**, pero el cliente no lo recibe, espera el ACK final y revisa **Detalles**. Aceptado solo confirma que LabsMobile recibió/procesó la solicitud.

Si aparece **No entregable / UNDELIV**, verifica especialmente:

- que el teléfono corresponda realmente al cliente;
- que sea 809, 829 o 849 con siete dígitos adicionales;
- que el dispositivo esté activo y con cobertura;
- que el operador no esté rechazando el tráfico.

Si no llega ningún ACK:

- confirma que `LABSMOBILE_ACK_URL` sea HTTPS y público;
- confirma que `LABSMOBILE_WEBHOOK_TOKEN` no esté vacío;
- ejecuta `php artisan config:clear`;
- verifica que el mensaje haya sido enviado después de habilitar ACK;
- revisa reglas de firewall/WAF/ModSecurity;
- comprueba que el envío guardó un `subid`.

Para volver temporalmente a pruebas sin consumo:

```dotenv
LABSMOBILE_TEST_MODE=true
```

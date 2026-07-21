# IWS End-to-End Validation — Guía de uso

Este script ejecuta el flujo completo de integración IWS contra el ambiente **TEST** y genera un reporte
que puedes enviar a TI Intcomex como evidencia del go-live (Sección 6 de la guía IWS).

## Requisitos

1. WordPress con WooCommerce instalado y activo.
2. Plugin **VentressLabs Intcomex Integration** activado.
3. Credenciales TEST de IWS configuradas en `WP Admin → Intcomex → Settings`.
4. **Ambiente TEST** seleccionado como activo (no Producción).
5. WP-CLI instalado (recomendado) o acceso a PHP CLI que pueda hacer bootstrap de WordPress.
6. Opcional pero recomendado para testing de PlaceOrder: al menos un producto con stock > 0 ya sincronizado.

## Cómo ejecutar

### Opción A — WP-CLI (recomendada)

```bash
wp --user=1 eval-file wp-content/plugins/ventresslabs-intcomex-integration/bin/iws-e2e-test.php
```

`--user=1` otorga capacidades de administrador. Sin esto, algunas funciones de WC fallarán.

### Opción B — PHP CLI puro

```bash
WP_PATH=/var/www/html php wp-content/plugins/ventresslabs-intcomex-integration/bin/iws-e2e-test.php
```

`WP_PATH` debe apuntar al raíz de WordPress (donde está `wp-load.php`).
Si no se define ni se detecta, el script aborta con un error claro.

## Qué prueba el script

El script recorre **todas las APIs** y los puntos críticos del flujo:

| Sección IWS | Validación |
|---|---|
| 3 — Autenticación | Verifica que las credenciales TEST estén configuradas y la firma SHA-256 funcione (vía GetCatalog) |
| 4 — GetCatalog | Llama y cachea el catálogo |
| 4 — GetPriceList | Llama y cachea la lista de precios |
| 4 — GetInventory | Llama y cachea el inventario |
| 4 — DownloadExtendedCatalog | Descarga, parsea y cachea imágenes |
| 4/5.2-4 — GetProducts | Solicita los primeros 5 SKUs para validar formato de respuesta |
| 5.2-4 — Stock Validator | Prueba `fetch_stock()` con 3 SKUs sin caché (TTL=0) |
| 4/5.2-5 — PlaceOrder | Ejecuta un PlaceOrder mínimo con 1 unidad del primer SKU con stock disponible |
| 6 — Logs | Reporta el total de llamadas exitosas/fallidas almacenadas |

## Salida esperada

El script imprime un reporte con:
- Una línea por cada verificación `[OK]` o `[FAIL]`
- Resumen final con `Pasaron X/Y verificaciones`
- Si todo pasa: `🎉 El plugin cumple el checklist go-live`
- Si algo falla: lista de verificaciones fallidas con detalles

## PlaceOrder

**Por defecto, PlaceOrder está deshabilitado** después de la instalación del plugin (opt-in).

El script lo detecta y reporta:
```
[OK] PlaceOrder probado — Deshabilitado en settings (omitido)
```

Para ejecutar un PlaceOrder real como parte del test:

1. En `WP Admin → Intcomex → Settings`, sección "Envío de órdenes a IWS (PlaceOrder)":
   - Marca "Habilitar PlaceOrder"
   - Marca "Marcar orden WC fallida si IWS falla" (opcional)
2. Asegúrate de tener al menos un producto sincronizado con `InStock > 0`
   (revisa `WP Admin → Intcomex → Synchronization`)
3. Re-ejecuta el script.

El script entonces hará un PlaceOrder mínimo (un solo SKU, cantidad 1) con
`customerOrderNumber=E2E-TEST` para que TI Intcomex pueda trazarlo.

## Logs para TI Intcomex

Después de ejecutar el script:
1. Abre `WP Admin → Intcomex → Logs`
2. Verás las últimas llamadas con timestamps UTC, status code, request/response body, `Reference` de IWS
3. Captura una captura de pantalla o exporta la tabla. TI Intcomex validará:
   - GetCatalog → 200
   - GetPriceList → 200
   - GetInventory → 200
   - PlaceOrder → 200 con OrderNumber válido

## Checklist go-live (Sección 6 IWS)

Después de correr este script, confirma manualmente los criterios del checklist:

- [ ] GetCatalog ejecutado con respuesta exitosa en TEST
- [ ] GetPriceList ejecutado con respuesta exitosa en TEST
- [ ] GetInventory ejecutado con respuesta exitosa en TEST
- [ ] PlaceOrder ejecutado con respuesta exitosa en TEST
- [ ] TI Intcomex confirma validación de logs en TEST (envía captura de Intcomex → Logs)
- [ ] Cliente comparte su dirección IP pública fija
- [ ] TI Intcomex configura el perfil en Producción y entrega credenciales
- [ ] Cliente confirma acceso al portal de soporte: https://myservices.intcomex.com/es/XGT

## Solución de problemas

### "Faltan credenciales TEST"
Entra a Settings y completa `TEST API Key` y `TEST Access Key`. La guía IWS las entrega TI Intcomex.

### Error 401 de IWS
- `ErrorCode 12` → timestamp expirado (clock skew). El plugin reintena automáticamente una vez;
  si persiste, revisa la hora del servidor con `date -u`.
- `ErrorCode 13` → API Key inválida. Verifica que no haya espacios en blanco.
- `ErrorCode 14` → IP no registrada. En TEST no se requiere; en PROD sí (TI Intcomex la configura).
- `ErrorCode 10` → firma inválida. Revisa que Access Key corresponda a la API Key del mismo ambiente.

### "No se encontró SKU con stock > 0 para prueba"
Tu catálogo TEST probablemente está vacío o sin inventario. Verifica con TI Intcomex que tu perfil
TEST tenga productos asignados y stock simulationado.

### PlaceOrder falla con `ErrorCode 21` (InvalidProduct)
El SKU que elegiste no está disponible para PlaceOrder en TEST. Típicamente:
- Productos descontinuados
- Restricciones por Customer ID
- SKU mal formado

Pide a TI Intcomex un SKUconfirmed disponible en tu ambiente TEST.

### Logs no aparecen en Intcomex → Logs
El script escribe logs en `wp_options` vía `VentressLabs_Intcomex_Logger`. Si hay plugins de caché
de objects (Redis/Memcached) activos, puede haber lag. Refresca la página o purga la caché.

## Próximos pasos tras el go-live

1. En `WP Admin → Intcomex → Settings`, cambia el ambiente a **Producción**.
2. Ingresa las credenciales de Producción que entregue TI Intcomex.
3. Habilita PlaceOrder (si fue validado en TEST).
4. Ejecuta `Sincronizar ahora` (respeta el límite de 1/hora).
5. Verifica en `Intcomex → Logs` que las llamadas a Producción respondan 200.
6. Realiza una compra de prueba pequeña end-to-end y valida que el OrderNumber IWS aparezca en
   `Intcomex → Órdenes`.

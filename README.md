# VentressLabs Intcomex Integration

> Plugin de WordPress/WooCommerce que integra la tienda con la API de
> **Intcomex Web Services (IWS)** para sincronizar catálogo, precios,
> inventario, imágenes y enviar órdenes de compra de forma automática.

[![Plugin Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](#)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A57.2-8892BF.svg)](#)
[![WordPress](https://img.shields.io/badge/WordPress-%E2%89%A56.0-21759B.svg)](#)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-%E2%89%A57.0-96588A.svg)](#)
[![License: GPL-2.0+](https://img.shields.io/badge/license-GPL--2.0%2B-success.svg)](#)

## Tabla de contenidos

- [Descripción](#descripción)
- [Características](#características)
- [Requisitos](#requisitos)
- [Instalación](#instalación)
- [Configuración](#configuración)
- [Estructura del proyecto](#estructura-del-proyecto)
- [Endpoints IWS soportados](#endpoints-iws-soportados)
- [Flujos principales](#flujos-principales)
- [WP-CLI](#wp-cli)
- [Pruebas E2E (go-live)](#pruebas-e2e-go-live)
- [Tareas programadas (cron)](#tareas-programadas-cron)
- [Solución de problemas](#solución-de-problemas)
- [Licencia](#licencia)

## Descripción

`VentressLabs Intcomex Integration` conecta una tienda WooCommerce con
Intcomex a través de los **Intcomex Web Services (IWS)**, una API REST que
expone operaciones para obtener productos, precios e inventario, y para crear
órdenes programáticamente.

El plugin implementa los flujos recomendados por la guía oficial de integración
de IWS para productos físicos:

- **Sincronización periódica (Sección 5.1)**: GetCatalog → GetPriceList →
  GetInventory → actualización de productos WooCommerce.
- **Validación de stock en checkout (Sección 5.2 paso 4)**: GetProducts en
  tiempo real antes de procesar el pago.
- **PlaceOrder (Sección 5.2 paso 5)**: crea la orden en IWS cuando el pago se
  completa en WooCommerce.
- **DownloadExtendedCatalog (Sección 4)**: descarga imágenes y especificaciones
  (límite: 1 vez por mes).

## Características

- ✅ Autenticación SHA-256 según el esquema documentado por IWS
  (`apiKey,accessKey,utcTimestamp`).
- ✅ Multi-ambiente: **TEST** y **PROD**, con credenciales independientes.
- ✅ Sincronización **horaria** automática (cron) + sincronización **manual**.
- ✅ Sincronización **mensual** del catálogo extendido (imágenes + specs).
- ✅ Feature toggles por endpoint (activar/desactivar cada API IWS).
- ✅ Validación de stock en tiempo real durante el checkout, con caché
  transitorio configurable.
- ✅ Envío automático de `PlaceOrder` al completar el pago, con reintentos
  manuales (unidad + bulk).
- ✅ Columna **IWS** en el listado de pedidos de WooCommerce (estado + OrderNumber).
- ✅ Logger completo de cada llamada a IWS (Sección 6 — go-live).
- ✅ Comando **WP-CLI** para gestionar endpoints.
- ✅ Script **E2E** para validar el flujo completo contra TEST antes del
  go-live.
- ✅ Limpieza segura en `uninstall.php` (crons, options, transients).
- ✅ Reintento automático en errores 401 `InvalidTimeStamp` (clock skew).

## Requisitos

| Componente   | Versión mínima |
|--------------|----------------|
| PHP          | 7.2            |
| WordPress    | 6.0            |
| WooCommerce  | 7.0            |
| WP-CLI       | opcional (para `wp intcomex endpoints`) |

Extensiones PHP recomendadas: `curl`, `json`, `mbstring`.

## Instalación

1. Copia la carpeta `ventresslabs-intcomex-integration/` a
   `wp-content/plugins/`.
2. Entra a `WP Admin → Plugins` y activa **VentressLabs Intcomex Integration**.
3. Al activarse, el plugin programa:
   - El cron horario de sincronización (`ventresslabs_intcomex_cron_sync`).
   - El cron mensual del catálogo extendido
     (`ventresslabs_intcomex_cron_extended`).

> ℹ️ **PlaceOrder está deshabilitado por defecto** (opt-in). Habilítalo desde
> `Intcomex → Settings` o desde la página `Endpoints` tras validar en TEST.

## Configuración

En `WP Admin → Intcomex → Settings`:

### Environment & API Credentials

| Campo                          | Descripción                                            |
|--------------------------------|--------------------------------------------------------|
| Active Environment             | `test` (recomendado inicialmente) o `prod`             |
| TEST API Key / TEST Access Key | Credenciales TEST entregadas por TI Intcomex           |
| Production API Key / Access Key| Credenciales PROD (solo tras passar go-live)           |

> ⚠️ IWS exige iniciar la integración en **TEST** antes de pasar a Producción
> (Sección 2 de la guía).

### Product Categories to Sync

Cliquea **"Fetch Categories from Intcomex"** y marca las categorías que el
sync periódico traerá a WooCommerce.

### Validación de stock en tiempo real (Sección 5.2 paso 4)

| Campo                | Default | Descripción                                         |
|----------------------|---------|----------------------------------------------------|
| Modo fail-open       | No      | Permite checkout aunque la API IWS falle            |
| Bloquear si stock=0  | Sí      | Bloquea checkout cuando un SKU se agote             |
| Caché (segundos)     | 60      | TTL del transient de stock por carrito (0 = no cache) |

### Envío de órdenes a IWS (PlaceOrder)

| Campo                              | Default | Descripción                                  |
|------------------------------------|---------|---------------------------------------------|
| Enviar datos del cliente           | Sí      | Incluye Customer/Billing/Shipping en el payload |
| Marcar orden WC fallida si IWS falla | Sí    | Cambia estado WC → `failed`                 |
| Permitir reintentos manuales       | Sí      | Habilita botón "Reintentar" en pedidos      |
| Locale por defecto                 | es      | Locale del payload enviado a IWS            |
| StoreId                           | —       | Identificador de tienda (opcional)          |
| Tag                              | —       | Etiqueta adjunta al PlaceOrder              |

### Endpoints (Intcomex → Endpoints)

Toggles por endpoint. `GetCatalog` es **obligatorio** y siempre está activo.

## Estructura del proyecto

```
intocmex-api-woocommerce/
├── iws-openapi-en.yaml                     # Especificación OpenAPI de IWS
└── ventresslabs-intcomex-integration/      # Plugin de WordPress
    ├── ventresslabs-intcomex-integration.php  # Bootstrap + hooks activation/deactivation
    ├── uninstall.php                        # Limpieza completa al desinstalar
    ├── IWS Guia Integracion - FISICOS.md    # Guía oficial (referencia)
    ├── IWS Guia Integracion - FISICOS.pdf
    ├── admin/
    │   ├── class-ventresslabs-intcomex-admin.php  # Menú, settings, AJAX handlers
    │   ├── js/ventresslabs-intcomex-admin.js      # UI (sync, retry, fetch cats)
    │   └── partials/                               # Vistas admin (5 páginas)
    ├── includes/
    │   ├── class-ventresslabs-intcomex.php            # Clase core del plugin
    │   ├── class-ventresslabs-intcomex-loader.php     # Orquestador de hooks
    │   ├── class-intcomex-api-client.php             # Cliente HTTP IWS (auth + endpoints)
    │   ├── class-intcomex-sync-service.php           # Orquestación del sync periódico
    │   ├── class-intcomex-stock-validator.php        # Validación en checkout (5.2.4)
    │   ├── class-intcomex-order-service.php          # PlaceOrder desde WC (5.2.5)
    │   ├── class-intcomex-endpoint-manager.php       # Feature toggles + WP-CLI
    │   └── class-intcomex-logger.php                 # Logger (Sección 6)
    ├── bin/
    │   ├── iws-e2e-test.php                         # Script E2E para go-live
    │   └── README-E2E.md                            # Guía de uso del script
    └── docs/
        └── manual.md                               # (placeholder)
```

## Endpoints IWS soportados

| Endpoint ID              | IWS API                  | Default | Descripción                                   |
|--------------------------|--------------------------|---------|-----------------------------------------------|
| `get_catalog`            | `GetCatalog`             | ✅      | Catálogo base (obligatorio)                   |
| `get_price_list`         | `GetPriceList`           | ✅      | Lista de precios (sync periódica)            |
| `get_inventory`          | `GetInventory`           | ✅      | Inventario (sync periódica)                  |
| `download_extended_catalog` | `DownloadExtendedCatalog` | ✅   | Imágenes + specs (máx 1/mes)                 |
| `get_products`           | `GetProducts`            | ✅      | Validación real-time en checkout             |
| `place_order`            | `PlaceOrder`             | ❌      | Envío de órdenes (opt-in)                    |

Cada endpoint valida su toggle antes de ejecutarse y devuelve
`WP_Error('endpoint_disabled')` cuando está apagado.

## Flujos principales

### 1. Sincronización periódica (`run_full_sync`)

1. `GetCatalog` → SKU-indexed en `wp_options`.
2. `GetPriceList` (si endpoint habilitado).
3. `GetInventory` (si endpoint habilitado).
4. `update_products()` → crea/actualiza productos WC para las categorías seleccionadas:
   - SKU, nombre, precio (de PriceList), stock (de Inventory).
   - Categorías (jerárquicas).
   - Imágenes (de `DownloadExtendedCatalog` si está cacheado).

Throttle: **máximo 1 sincronización por hora** (transient
`ventresslabs_intcomex_sync_lock`), conforme a la Sección 5.1.

### 2. Checkout — Validación de stock (`VentressLabs_Intcomex_Stock_Validator`)

Hooks: `woocommerce_check_cart_items` + `woocommerce_after_checkout_validation`.

1. Recoge SKUs + cantidades del carrito.
2. `fetch_stock()` vía `GetProducts` con caché transitorio (`vl_intcomex_stock_*`).
3. Bloquea si:
   - SKU no existe en catálogo.
   - `stock=0` y `block_on_zero=yes`.
   - `cantidad solicitada > stock disponible`.
4. Si la API falla:
   - `fail_open=no` → bloquea checkout.
   - `fail_open=yes` → permite checkout con aviso.

### 3. Checkout — PlaceOrder (`VentressLabs_Intcomex_Order_Service`)

Hooks: `woocommerce_payment_complete` (+ `woocommerce_checkout_order_processed`
como fallback para gateways como COD/cheque).

1. Verifica toggle `place_order`.
2. Evita duplicados (meta `_intcomex_iws_order_number`).
3. Construye payload (simple `{Sku, Quantity}` o extendido con Customer/Billing/Shipping).
4. Llama `PlaceOrder` con query args (`locale`, `tag`, `customerOrderNumber`).
5. **Éxito**: persiste `OrderNumber` + respuesta completa + order note. Guarda
   tracking si llega en `Shipments`.
6. **Fallo**: persiste error, marca `_intcomex_iws_pending_retry`, opcionalmente
   cambia estado WC → `failed`.

### 4. DownloadExtendedCatalog (mensual)

- Descarga JSON binario a tmp, parsea, indexa por SKU con `images`, `title`,
  `description`, `specs`.
- Throttle: **máximo 1 vez por mes** (`ventresslabs_intcomex_extended_lock`).
- Las imágenes se adjuntan a productos WC durante `update_products()`.

## WP-CLI

```bash
# Listar endpoints y su estado
wp intcomex endpoints list

# Habilitar/deshabilitar un endpoint
wp intcomex endpoints enable place_order
wp intcomex endpoints disable download_extended_catalog

# Ver estado de uno
wp intcomex endpoints status place_order
```

## Pruebas E2E (go-live)

Script `bin/iws-e2e-test.php` recorre todas las APIs IWS contra TEST y
genera un reporte para enviar a TI Intcomex (Sección 6 de la guía).

```bash
# WP-CLI (recomendado)
wp --user=1 eval-file wp-content/plugins/ventresslabs-intcomex-integration/bin/iws-e2e-test.php

# PHP CLI puro
WP_PATH=/var/www/html php wp-content/plugins/ventresslabs-intcomex-integration/bin/iws-e2e-test.php
```

Ver [`bin/README-E2E.md`](ventresslabs-intcomex-integration/bin/README-E2E.md)
para el checklist completo del go-live.

## Tareas programadas (cron)

| Hook                                    | Schedule   | Acción                        |
|-----------------------------------------|------------|-------------------------------|
| `ventresslabs_intcomex_cron_sync`       | hourly     | `run_full_sync`               |
| `ventresslabs_intcomex_cron_extended`   | monthly    | `sync_extended_catalog`        |

Al desactivar el plugin se limpian ambos crons + los transients de throttle.
Al desinstalar (`uninstall.php`) se eliminan además todas las options y
transients del plugin. Los productos y órdenes WC creadas **se conservan**
(como datos del usuario).

## Solución de problemas

| Síntoma                            | Causa probable                                | Solución                                            |
|------------------------------------|-----------------------------------------------|----------------------------------------------------|
| 401 `ErrorCode 12` InvalidTimeStamp | Clock skew del servidor                       | El plugin reintenta automáticamente; verifica `date -u` |
| 401 `ErrorCode 13` InvalidApiKey    | API Key mal escrita / con espacios            | Revisa Settings → API Key                          |
| 401 `ErrorCode 14` InvalidIP        | IP no registrada en PROD                      | TI Intcomex debe configurar la IP en el perfil    |
| 401 `ErrorCode 10` InvalidSignature | API Key y Access Key de ambientes distintos   | Verifica que ambas correspondan al mismo ambiente |
| `sync_throttled` al sincronizar     | Ya sincronizaste en la última hora            | Espera ~60 min o usa `$force` desde código        |
| PlaceOrder falla con `ErrorCode 21` | SKU no disponible para PlaceOrder            | Pide a TI Intcomex un SKU confirmado en TEST      |
| Logs no aparecen                    | Cache de objetos (Redis/Memcached)            | Refresca la página o purga la caché               |

## Licencia

GPL-2.0 o superior — ver el header del plugin
(`ventresslabs-intcomex-integration.php`).

Autor: **VentressLabs** <contact@ventresslabs.com> · https://ventresslabs.com/

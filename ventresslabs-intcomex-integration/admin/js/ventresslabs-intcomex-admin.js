(function( $ ) {
    'use strict';

    $(function() {
        // Handle Fetch Categories button click
        $('#fetch-intcomex-categories').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $container = $('#intcomex-categories-list');

            $button.prop('disabled', true);
            $container.html('<p>Loading categories...</p>');

            var data = {
                'action': 'ventresslabs_fetch_categories',
                'nonce': vl_intcomex_admin.fetch_nonce
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    var categories = response.data.categories;
                    var saved_categories = response.data.saved_categories || [];
                    var html = '';

                    if (Object.keys(categories).length > 0) {
                        $.each(categories, function(id, name) {
                            var checked = saved_categories.includes(id) ? 'checked' : '';
                            html += '<div><label>';
                            html += '<input type="checkbox" name="ventresslabs_intcomex_selected_categories[]" value="' + id + '" ' + checked + '>';
                            html += ' ' + name;
                            html += '</label></div>';
                        });
                    } else {
                        html = '<p>No categories found or there was an error fetching them.</p>';
                    }
                    $container.html(html);
                } else {
                    $container.html('<p>Error: ' + response.data.message + '</p>');
                }
                $button.prop('disabled', false);
            });
        });

        // Handle Sync Products button click
        $('#ventresslabs-sync-now').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $logContainer = $('#sync-log');
            var $logContent = $('#sync-log-content');

            $button.prop('disabled', true);
            $logContainer.show();
            $logContent.html('<p>Starting synchronization... This may take a while.</p>');

            var data = {
                'action': 'ventresslabs_sync_products',
                'nonce': vl_intcomex_admin.sync_nonce
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    var results = response.data.results || {};
                    var html = '<strong>' + response.data.message + '</strong><br><br>';

                    if (results.catalog) {
                        html += 'GetCatalog: ' + results.catalog + ' productos<br>';
                    }
                    if (results.price_list) {
                        html += 'GetPriceList: ' + results.price_list + ' precios<br>';
                    }
                    if (results.inventory) {
                        html += 'GetInventory: ' + results.inventory + ' inventarios<br>';
                    }

                    if (Array.isArray(results.products)) {
                        html += '<br><strong>WooCommerce:</strong><br>';
                        results.products.forEach(function(result) {
                            if (typeof result === 'string') {
                                html += result + '<br>';
                            } else if (result && result.skipped) {
                                html += result.skipped + '<br>';
                            }
                        });
                    }
                    $logContent.html(html);
                } else {
                    $logContent.html('<p>Error: ' + response.data.message + '</p>');
                }
                $button.prop('disabled', false);
            });
        });

        // Handle Sync Extended Catalog button click
        $('#ventresslabs-sync-extended').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $logContainer = $('#sync-log');
            var $logContent = $('#sync-log-content');
            var force = $('#ventresslabs-sync-extended-force').is(':checked') ? '1' : '0';

            if (force === '1' && !window.confirm('¿Forzar la descarga ignorando el límite mensual?')) {
                return;
            }

            $button.prop('disabled', true);
            $logContainer.show();
            $logContent.html('<p>Descargando catálogo extendido... Esto puede tardar varios minutos.</p>');

            var data = {
                'action': 'ventresslabs_sync_extended',
                'nonce': vl_intcomex_admin.sync_extended_nonce,
                'force': force
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    $logContent.html('<p><strong>' + response.data.message + '</strong></p>');
                } else {
                    $logContent.html('<p>Error: ' + response.data.message + '</p>');
                }
                $button.prop('disabled', false);
            });
        });

        // Handle Retry Order button click (orders list & order detail)
        $(document).on('click', '.ventresslabs-retry-order', function(e) {
            e.preventDefault();

            var $button = $(this);
            var order_id = $button.data('order-id') || '';

            if (!order_id) {
                return;
            }

            if (!window.confirm(vl_intcomex_admin.i18n_retry_confirm || '¿Reintentar PlaceOrder en IWS para este pedido?')) {
                return;
            }

            $button.prop('disabled', true).text(vl_intcomex_admin.i18n_retrying || 'Reintentando…');

            var data = {
                'action': 'ventresslabs_retry_order',
                'nonce': vl_intcomex_admin.retry_order_nonce,
                'order_id': order_id
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    $button.replaceWith('<strong style="color:#46b450;">#' + (response.data.order_number || '') + '</strong>');
                    if (window.confirm(response.data.message + '\n\n¿Recargar la página para ver el detalle actualizado?')) {
                        window.location.reload();
                    }
                } else {
                    window.alert(response.data.message || 'Error');
                    $button.prop('disabled', false).text('Reintentar');
                }
            }).fail(function() {
                $button.prop('disabled', false).text('Reintentar');
            });
        });

        // Handle bulk retry of pending orders (Intcomex → Órdenes)
        $('#ventresslabs-retry-bulk').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $status = $('#ventresslabs-retry-bulk-status');

            if (!window.confirm(vl_intcomex_admin.i18n_bulk_confirm || '¿Reintentar todas las órdenes pendientes?')) {
                return;
            }

            $button.prop('disabled', true);
            $status.text(vl_intcomex_admin.i18n_retrying || 'Reintentando…').css('color', '#999');

            var data = {
                'action': 'ventresslabs_retry_orders_bulk',
                'nonce': vl_intcomex_admin.retry_order_nonce
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    $status.text(response.data.message).css('color', '#46b450');
                    // Optional: refresh table per-row to show new badges.
                    (response.data.results || []).forEach(function(r) {
                        var $row = $('tr[data-order-id="' + r.order_id + '"]');
                        if ($row.length && r.status === 'success') {
                            $row.find('.ventresslabs-retry-order').replaceWith('<strong style="color:#46b450;">#' + (r.order_number || '') + '</strong>');
                            $row.find('td').eq(4).html('<strong style="color:#46b450;">#' + (r.order_number || '') + '</strong>');
                            $row.find('td').eq(5).html('<span style="color:#46b450;">Creada</span>');
                        } else if ($row.length && r.status === 'error') {
                            $row.find('.ventresslabs-retry-order').prop('disabled', false).text('Reintentar');
                            $row.find('td').eq(6).html('<code>' + (r.error || '') + '</code>');
                        }
                    });
                    $button.prop('disabled', false);
                } else {
                    $status.text(response.data.message || 'Error').css('color', '#dc3232');
                    $button.prop('disabled', false);
                }
            }).fail(function() {
                $status.text('Error de red').css('color', '#dc3232');
                $button.prop('disabled', false);
            });
        });

        // Handle Clear Logs button click
        $('#ventresslabs-clear-logs').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);

            if (!window.confirm('¿Borrar todos los logs?')) {
                return;
            }

            $button.prop('disabled', true);

            var data = {
                'action': 'ventresslabs_clear_logs',
                'nonce': vl_intcomex_admin.clear_logs_nonce
            };

            $.post(vl_intcomex_admin.ajax_url, data, function(response) {
                if (response.success) {
                    $('#intcomex-logs-table tbody').html('<tr><td colspan="8">' + response.data.message + '</td></tr>');
                } else {
                    window.alert(response.data.message || 'Error');
                }
                $button.prop('disabled', false);
            });
        });
    });

})( jQuery );

<?php
/**
 * Sidebar de detalle reutilizable (clic en fila de tabla).
 *
 * Regla de seguridad: el panel se oculta automáticamente cuando la URL
 * lleva query string. Las vistas filtradas/paginadas o los enlaces
 * compartidos no deben exponer el panel de edición/detalle del registro.
 *
 * Uso en cada página:
 *   require_once __DIR__ . '/../includes/sidebar_detail.php';
 *   // en <head>:
 *   sidebarDetalleHeadLink();
 *   // al final de <body>:
 *   renderSidebarDetalle('Título — Detalle');
 */

function sidebarDetalleVisible(): bool
{
    return empty($_SERVER['QUERY_STRING']);
}

function sidebarDetalleHeadLink(): void
{
    if (!sidebarDetalleVisible()) {
        return;
    }
    echo '<link rel="stylesheet" href="../assets/css/sidebar.css">' . PHP_EOL;
}

function renderSidebarDetalle(string $titulo): void
{
    if (!sidebarDetalleVisible()) {
        return;
    }

    require_once __DIR__ . '/../components/sidebar.php';

    renderSidebar($titulo, function () {
        ?>
        <div class="sidebar-row-context" id="rowContext">
            <p class="sidebar-vacio">Selecciona un registro para ver su detalle.</p>
        </div>
        <?php
    });

    echo '<script src="../assets/js/sidebar.js"></script>' . PHP_EOL;
    ?>
    <script nonce="<?= cspStyleNonce() ?>">
    (function () {
        'use strict';
        var context = document.getElementById('rowContext');
        function esc(s) {
            return String(s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function attach() {
            var tables = document.querySelectorAll('table');
            tables.forEach(function (t) {
                var ths = t.querySelectorAll('thead th');
                if (ths.length === 0) return; // sin encabezados: no aplica
                var rows = t.querySelectorAll('tbody tr');
                rows.forEach(function (tr) {
                    if (tr.querySelector('.tabla-vacio')) return;
                    tr.style.cursor = 'pointer';
                    tr.addEventListener('click', function () {
                        var tds = tr.children;
                        var html = '';
                        ths.forEach(function (th, i) {
                            var td = tds[i];
                            if (!td) return;
                            html += '<div class="sidebar-context-row">' +
                                '<span class="sidebar-context-label">' + esc(th.textContent.trim()) + '</span>' +
                                '<span class="sidebar-context-value">' + esc(td.textContent.trim()) + '</span>' +
                                '</div>';
                        });
                        if (context) context.innerHTML = html;
                        if (window.openSidebar) window.openSidebar();
                    });
                });
            });
        }
        attach();
    })();
    </script>
    <?php
}

<?php
set_time_limit(120);
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/api_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logos.php';
require_once __DIR__ . '/../components/topbar.php';
require_once __DIR__ . '/../components/sidebar.php';

securityHeaders();
requireLogin();
ensureModulosCargados();
$usuario = currentUser();
$codigoCliente = $usuario['codigo_cliente'] ?? '';

// Filtros por sesión
$filterKey = 'refacciones_filters';

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code', 'page' => 'refacciones']);
    die('Acceso no permitido.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        csrfRefresh();
        logSecurityEvent('csrf_validation_failed', ['page' => 'refacciones', 'uri' => $_SERVER['REQUEST_URI']]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $filtros = $_SESSION[$filterKey] ?? [];

    $mesPost = sanitizeYearMonth($_POST['mes'] ?? '');
    if ($mesPost !== '') {
        $filtros['mes'] = $mesPost;
    }

    if (isset($_POST['refrescar'])) {
        $filtros['refrescar'] = true;
    } else {
        unset($filtros['refrescar']);
    }

    $_SESSION[$filterKey] = $filtros;
    csrfRefresh();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (!empty($_GET)) {
    $filtros = $_SESSION[$filterKey] ?? [];

    $mesGet = sanitizeYearMonth($_GET['mes'] ?? '');
    if ($mesGet !== '') {
        $filtros['mes'] = $mesGet;
    }

    if (isset($_GET['refrescar'])) {
        $filtros['refrescar'] = true;
    }

    $_SESSION[$filterKey] = $filtros;
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

$filtros = $_SESSION[$filterKey] ?? [];
$mesSeleccionado = $filtros['mes'] ?? '';
$forzarRefresco = !empty($filtros['refrescar']);

if ($forzarRefresco) {
    unset($_SESSION[$filterKey]['refrescar']);
}

$endpoint = '/api/one-page/reporte-refacciones?codigo_cliente=' . urlencode($codigoCliente);
if ($mesSeleccionado !== '') $endpoint .= '&mes=' . urlencode($mesSeleccionado);
if ($forzarRefresco) $endpoint .= '&refrescar=true';

$inicio = microtime(true);
$rep = apiGet($endpoint);
$tiempoCarga = round(microtime(true) - $inicio, 2);

function fm(mixed $v): string
{
    return '$' . number_format((float)$v, 0, '.', ',');
}
function raw(mixed $v): string
{
    return htmlspecialchars((string)(float)($v ?? 0));
}
function fp(mixed $v): string
{
    return $v !== null ? number_format($v, 0) . '%' : '—';
}
function fnum(mixed $v, int $dec = 0): string
{
    return number_format((float)($v ?? 0), $dec);
}
function nombreMes(?string $ym): string
{
    if (!$ym) return '—';
    $meses = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
    [$anio, $mes] = explode('-', $ym);
    return ($meses[$mes] ?? $mes) . ' ' . substr($anio, 2, 2);
}

function tablaInventarioRefacciones(array $inv, string $campo, string $titulo, bool $esMoneda): void
{
    $rangos = $inv['rangos'] ?? [];
    $udsLabel = $esMoneda ? 'Inventario $' : 'Uds Existencia';
    if (empty($inv['sucursales'])) {
        echo '<p class="padding-md">Sin datos de inventario de refacciones para este periodo.</p>';
        return;
    }
?>
    <div class="tarjeta">
        <div class="titulo"><?= htmlspecialchars($titulo) ?></div>
        <div class="fila-scroll">
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" data-col-id="sucursal">Sucursal</th>
                        <?php foreach ($rangos as $i => $r): ?><th colspan="2" data-col-id="rango_<?= $i ?>"><?= htmlspecialchars($r) ?></th><?php endforeach; ?>
                        <th colspan="2" data-col-id="total">Total</th>
                    </tr>
                    <tr>
                        <?php foreach ($rangos as $i => $r): ?>
                            <th data-col-id="<?= $campo ?>_uds_<?= $i ?>"><?= $udsLabel ?></th>
                            <th data-col-id="<?= $campo ?>_pct_<?= $i ?>">% Part.</th>
                        <?php endforeach; ?>
                        <th data-col-id="<?= $campo ?>_uds_total"><?= $udsLabel ?></th>
                        <th data-col-id="<?= $campo ?>_pct_total">% Part.</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (($inv['sucursales'] ?? []) as $f): ?>
                        <tr data-sucursal="<?= htmlspecialchars($f['sucursal']) ?>" data-compania="<?= htmlspecialchars($f['compania'] ?? '') ?>">
                            <td><?= htmlspecialchars($f['sucursal']) ?></td>
                            <?php foreach ($rangos as $r):
                                $d = $f['por_rango'][$r] ?? ['uds' => 0, 'valor' => 0, 'pct_part_uds' => 0, 'pct_part_valor' => 0]; ?>
                                <td class="<?= $esMoneda ? 'dinero' : '' ?>" <?= $esMoneda ? 'data-raw="' . raw($d['valor']) . '"' : '' ?>><?= $esMoneda ? fm($d['valor']) : fnum($d['uds']) ?></td>
                                <td class="pct-muted"><?= fp($esMoneda ? ($d['pct_part_valor'] ?? 0) : ($d['pct_part_uds'] ?? 0)) ?></td>
                            <?php endforeach; ?>
                            <td class="<?= $esMoneda ? 'dinero' : '' ?>" <?= $esMoneda ? 'data-raw="' . raw($f['total_valor']) . '"' : '' ?>><strong><?= $esMoneda ? fm($f['total_valor']) : fnum($f['total_uds']) ?></strong></td>
                            <td class="pct-muted"><?= fp($esMoneda ? ($f['pct_part_valor_total'] ?? 0) : ($f['pct_part_uds_total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <?php $t = $inv['totales'] ?? []; ?>
                    <tr class="fila-total">
                        <td>Total</td>
                        <?php foreach ($rangos as $r):
                            $d = $t['por_rango'][$r] ?? ['uds' => 0, 'valor' => 0, 'pct_part_uds' => 0, 'pct_part_valor' => 0]; ?>
                            <td class="<?= $esMoneda ? 'dinero' : '' ?>" <?= $esMoneda ? 'data-raw="' . raw($d['valor']) . '"' : '' ?>><?= $esMoneda ? fm($d['valor']) : fnum($d['uds']) ?></td>
                            <td class="pct-muted"><?= fp($esMoneda ? ($d['pct_part_valor'] ?? 0) : ($d['pct_part_uds'] ?? 0)) ?></td>
                        <?php endforeach; ?>
                        <td class="<?= $esMoneda ? 'dinero' : '' ?>" <?= $esMoneda ? 'data-raw="' . raw($t['valor'] ?? 0) . '"' : '' ?>><?= $esMoneda ? fm($t['valor'] ?? 0) : fnum($t['uds'] ?? 0) ?></td>
                        <td>100%</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
<?php
}

$hayError = !empty($rep['error']);
$todasCompanias = $hayError ? [] : array_unique(array_filter(array_column($rep['sucursales'] ?? [], 'compania')));
sort($todasCompanias);
$mesesDisponibles = $rep['meses_disponibles'] ?? [];
rsort($mesesDisponibles);
$mesActivo = $rep['mes'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>One Page — REFACCIONES · Breinit DCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/one_page_taller.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <style nonce="<?= cspStyleNonce() ?>">
        .push-right { margin-left: auto; }
        .btn-experto {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: #001b5d;
            color: #fff;
            border: 1px solid #001b5d;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
        }
        .btn-experto:hover {
            background: #092f87;
            border-color: #092f87;
        }
    </style>
</head>

<body>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="marca">DCA - Desarrollo</div>
            <nav>
                <a href="dashboard.php"><span class="material-symbols-outlined">dashboard</span> Dashboard</a>
                <div class="separador"></div>
                <form method="post" action="one_page.php" class="nav-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="seccion" value="nuevos">
                    <button type="submit"><span class="material-symbols-outlined">directions_car</span> Autos Nuevos</button>
                </form>
                <form method="post" action="one_page.php" class="nav-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="seccion" value="usados">
                    <button type="submit"><span class="material-symbols-outlined">time_to_leave</span> Autos Seminuevos</button>
                </form>
                <a href="one_page_taller.php"><span class="material-symbols-outlined">build</span> Taller</a>
                <a href="hp.php"><span class="material-symbols-outlined">brush</span> H&amp;P</a>
                <a href="refacciones.php" class="activo"><span class="material-symbols-outlined">inventory_2</span> REFACCIONES</a>
            </nav>
        </aside>
        <div class="content-wrap" id="contentWrap">
            <?php renderTopbar([
                'titulo'    => 'One Page — REFACCIONES',
                'subtitulo' => 'Información actualizada a: ' . nombreMes($mesActivo),
                'menu'      => true,
                'usuario'   => $usuario,
                'codigo_cliente' => $codigoCliente,
                'user_master'   => $usuario['user_master'] ?? '',
            ]); ?>
            <main>
                <form class="filtros" method="post" id="formFiltros">
                    <?= csrfField() ?>
                    <div>
                        <label for="compania">Compañía</label>
                        <select id="compania" name="compania">
                            <option value="todas">Todas</option>
                            <?php foreach ($todasCompanias as $comp): ?>
                                <option value="<?= htmlspecialchars($comp) ?>"><?= htmlspecialchars($comp) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="escala">Escala ($)</label>
                        <select id="escala" name="escala">
                            <option value="1">Ninguno</option>
                            <option value="100">Cientos</option>
                            <option value="1000">Miles</option>
                            <option value="1000000">Millones</option>
                        </select>
                    </div>
                    <div class="push-right">
                        <label for="mes">Periodo</label>
                        <select id="mes" name="mes">
                            <?php foreach ($mesesDisponibles as $ym): ?>
                                <option value="<?= htmlspecialchars($ym) ?>" <?= $ym === $mesActivo ? 'selected' : '' ?>><?= htmlspecialchars(nombreMes($ym)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="boton" type="submit" name="refrescar" value="1">&#8635; Actualizar datos</button>
                </form>

                <p class="nota-tiempo">Cargado en <?= $tiempoCarga ?>s</p>

                <?php if ($hayError): ?>
                    <div class="error"><strong>No se pudo cargar:</strong> <?= htmlspecialchars($rep['message'] ?? '') ?></div>
                <?php else: ?>

                    <?php if (!empty($rep['supuestos'])): ?>
                        <div class="aviso-warning">
                            <strong>⚠️ Supuestos pendientes de confirmar:</strong>
                            <ul class="aviso-list">
                                <?php foreach ($rep['supuestos'] as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($rep['advertencias'])): ?>
                        <div class="aviso-warning aviso-error">
                            <strong>⚠️ Posible desajuste de datos:</strong>
                            <ul class="aviso-list">
                                <?php foreach ($rep['advertencias'] as $a): ?><li><?= htmlspecialchars($a) ?></li><?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <div class="barra-exportar">
                        <button class="btn-exportar" id="btnExportar">
                            <span class="material-symbols-outlined icon-sm">download</span> Exportar HTML
                        </button>
                        <button class="btn-experto" id="btnExperto">
                            <span class="material-symbols-outlined icon-sm">psychology</span> Experto
                        </button>
                    </div>

                    <?php
                    tablaInventarioRefacciones($rep, 'uds', 'Inventario Refacciones (Uds)', false);
                    tablaInventarioRefacciones($rep, 'valor', 'Inventario Refacciones ($)', true);
                    ?>

                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="../assets/js/topbar.js"></script>
    <script nonce="<?= cspStyleNonce() ?>">
        document.getElementById('btnExportar')?.addEventListener('click', function() {
            var win = window.open('', '_blank');
            if (!win) { alert('Permite ventanas emergentes para exportar.'); return; }
            var clone = document.querySelector('main').cloneNode(true);
            clone.querySelectorAll('[style]').forEach(function(el) { el.removeAttribute('style'); });
            win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>REFACCIONES - Exportación</title>' +
                '<style>body{font-family:Inter,sans-serif;font-size:13px;padding:20px;color:#0b1c30;}' +
                'table{width:100%;border-collapse:collapse;margin-bottom:20px;}' +
                'th{background:#eff4ff;padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;border:1px solid #c5c5d4;}' +
                'td{padding:8px 12px;border:1px solid #c5c5d4;}' +
                '.num{text-align:right;}' +
                '</style></head><body>');
            win.document.write(clone.outerHTML);
            win.document.write('</body></html>');
            win.document.close();
        });

        document.getElementById('btnExperto')?.addEventListener('click', function() {
            alert('Modo experto: próximamente disponible.');
        });

        /* ---- Submit automático del selector de mes ---- */
        var mesSelect = document.getElementById('mes');
        if (mesSelect) {
            mesSelect.addEventListener('change', function () {
                var form = mesSelect.closest('form');
                if (form) form.submit();
            });
        }

        /* ---- Filtro por compañía ---- */
        function aplicarCompaniaRef() {
            var sel = document.getElementById('compania');
            if (!sel) return;
            var val = sel.value;
            try { sessionStorage.setItem('refacciones_compania', val); } catch(e) {}
            document.querySelectorAll('table tbody tr[data-sucursal]').forEach(function (tr) {
                var c = tr.getAttribute('data-compania') || '';
                tr.style.display = (val === 'todas' || c === val) ? '' : 'none';
            });
        }
        var companiaSelectRef = document.getElementById('compania');
        if (companiaSelectRef) {
            companiaSelectRef.addEventListener('change', aplicarCompaniaRef);
            try {
                var saved = sessionStorage.getItem('refacciones_compania');
                if (saved && companiaSelectRef.querySelector('option[value="' + saved.replace(/"/g, '&quot;') + '"]')) {
                    companiaSelectRef.value = saved;
                    aplicarCompaniaRef();
                }
            } catch(e) {}
        }

        /* ---- Escala de moneda ---- */
        function aplicarEscalaRef() {
            var sel = document.getElementById('escala');
            if (!sel) return;
            var escala = parseFloat(sel.value) || 1;
            var pref = escala === 100 ? 'C$' : escala === 1000 ? 'M$' : escala === 1000000 ? 'MM$' : '$';
            document.querySelectorAll('td.dinero[data-raw]').forEach(function (td) {
                var raw = parseFloat(td.getAttribute('data-raw')) || 0;
                if (escala === 1) {
                    td.textContent = '$' + raw.toLocaleString('es-MX', { maximumFractionDigits: 0 });
                } else {
                    td.textContent = pref + (raw / escala).toLocaleString('es-MX', { maximumFractionDigits: 1 });
                }
            });
        }
        var escalaSelectRef = document.getElementById('escala');
        if (escalaSelectRef) {
            escalaSelectRef.addEventListener('change', aplicarEscalaRef);
        }
    </script>

    <?php renderSidebar('One Page REFACCIONES — Detalle', function () { ?>
        <div class="sidebar-row-context" id="rowContext"></div>
        <form class="sidebar-form" id="sidebarForm">
            <div class="sidebar-form-group" id="grpModelo">
                <label for="fModelo">Modelo <span class="sidebar-nota">(campo backend)</span></label>
                <input type="text" id="fModelo" name="modelo" readonly>
            </div>
            <div class="sidebar-form-group" id="grpAtributo">
                <label for="fAtributo">Atributo <span class="sidebar-nota">(campo del modelo)</span></label>
                <input type="text" id="fAtributo" name="atributo" readonly>
            </div>
            <div class="sidebar-form-group">
                <label for="fTitulo">Titulo</label>
                <input type="text" id="fTitulo" name="titulo" placeholder="Nombre de la columna" required>
            </div>
            <div class="sidebar-form-group">
                <label for="fFormato">Formato</label>
                <input type="text" id="fFormato" name="formato" placeholder="Formato de visualizacion">
            </div>
            <button type="submit" class="sidebar-btn-guardar">Guardar</button>
        </form>
    <?php }); ?>
    <script src="../assets/js/sidebar.js"></script>
    <script nonce="<?= cspStyleNonce() ?>">
        (function () {
            'use strict';
            var context = document.getElementById('rowContext');
            var form = document.getElementById('sidebarForm');
            var fModelo = document.getElementById('fModelo');
            var fAtributo = document.getElementById('fAtributo');
            var fTitulo = document.getElementById('fTitulo');
            var fFormato = document.getElementById('fFormato');
            var grpModelo = document.getElementById('grpModelo');
            var grpAtributo = document.getElementById('grpAtributo');

            if (!context || !form) {
                console.error('[SIDEBAR] Faltan elementos del formulario');
                return;
            }

            var MODELO = 'InventarioRefaccion';

            function buscarMeta(colId) {
                if (colId.indexOf('uds_uds_') === 0) return { modelo: MODELO, atributo: 'Existencia' };
                if (colId.indexOf('uds_pct_') === 0) return { modelo: MODELO, atributo: 'Costo_Inventario_MN' };
                if (colId.indexOf('valor_uds_') === 0) return { modelo: MODELO, atributo: 'Existencia' };
                if (colId.indexOf('valor_pct_') === 0) return { modelo: MODELO, atributo: 'Costo_Inventario_MN' };
                return null;
            }

            function sinModelo(colId) {
                return colId === 'sucursal' || colId === 'total' || colId.indexOf('rango_') === 0;
            }

            function llenarSidebar(th) {
                var colId = th.dataset.colId || '';
                var colNombre = th.textContent.trim();

                context.innerHTML =
                    '<div class="sidebar-context-row">' +
                    '<span class="sidebar-context-label">Columna</span>' +
                    '<span class="sidebar-context-value">' + colNombre + '</span>' +
                    '</div>';

                if (sinModelo(colId)) {
                    if (grpModelo) grpModelo.style.display = 'none';
                    if (grpAtributo) grpAtributo.style.display = 'none';
                    if (fModelo) fModelo.value = '';
                    if (fAtributo) fAtributo.value = '';
                } else {
                    var meta = buscarMeta(colId);
                    if (grpModelo) grpModelo.style.display = 'block';
                    if (grpAtributo) grpAtributo.style.display = 'block';
                    if (fModelo) fModelo.value = meta ? meta.modelo : colId;
                    if (fAtributo) fAtributo.value = meta ? meta.atributo : '';
                }

                if (fTitulo) fTitulo.value = colNombre;
                if (fFormato) fFormato.value = '#,##0';

                if (window.openSidebar) window.openSidebar();
            }

            function asignarClicksATablas() {
                var tarjetas = document.querySelectorAll('.tarjeta');
                tarjetas.forEach(function (tarjeta) {
                    var tabla = tarjeta.querySelector('table');
                    if (!tabla) return;
                    var headers = tabla.querySelectorAll('thead th[data-col-id]');
                    headers.forEach(function (th) {
                        if (th.hasAttribute('colspan') && parseInt(th.getAttribute('colspan'), 10) > 1) return;
                        th.style.cursor = 'pointer';
                        th.title = 'Click para ver detalle';
                        th.addEventListener('click', function (e) {
                            if (e.target.closest('a') || e.target.closest('button')) return;
                            llenarSidebar(th);
                        });
                    });
                });
            }

            asignarClicksATablas();

            if (form) {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    if (!fTitulo || !fTitulo.value.trim()) return;
                    if (window.closeSidebar) window.closeSidebar();
                });
            }
        })();
    </script>
</body>

</html>

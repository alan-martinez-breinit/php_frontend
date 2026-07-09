<?php
set_time_limit(240);
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/api_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logos.php';
require_once __DIR__ . '/../components/topbar.php';
require_once __DIR__ . '/../includes/sidebar_detail.php';

securityHeaders();
requireLogin();
ensureModulosCargados();
$usuario = currentUser();
$codigoCliente = $usuario['codigo_cliente'] ?? '';

// Filtros por sesión
$filterKey = 'one_page_taller_filters';

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code', 'page' => 'one_page_taller']);
    die('Acceso no permitido.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        csrfRefresh();
        logSecurityEvent('csrf_validation_failed', ['page' => 'one_page_taller', 'uri' => $_SERVER['REQUEST_URI']]);
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

$endpoint = '/api/one-page/reporte-taller?codigo_cliente=' . urlencode($codigoCliente);
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
function fpColor(mixed $v): string
{
    if ($v === null) return '<span class="pct-muted">—</span>';
    if ($v >= 100) $class = 'pct-green';
    elseif ($v >= 70) $class = 'pct-orange';
    else $class = 'pct-red';
    return '<span class="' . $class . '">' . number_format($v, 0) . '%</span>';
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
    <title>One Page Taller · Breinit DCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/one_page_taller.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <?php sidebarDetalleHeadLink(); ?>
</head>

<body data-mes="<?= htmlspecialchars($mesActivo ?? '') ?>">
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
                <a href="one_page_taller.php" class="activo"><span class="material-symbols-outlined">build</span> Taller</a>
            </nav>
        </aside>
        <div class="content-wrap" id="contentWrap">
            <?php renderTopbar([
                'titulo'    => 'One Page — Taller',
                'subtitulo' => 'Información actualizada a: ' . nombreMes($mesActivo),
                'menu'      => true,
                'usuario'   => $usuario,
                'codigo_cliente' => $codigoCliente,
                'user_master'   => $usuario['user_master'] ?? '',
            ]); ?>
            <main>
                <form class="filtros" method="post">
                    <?= csrfField() ?>
                    <div>
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
                    <button class="btn-exportar" id="btn-exportar">
                        <span class="material-symbols-outlined icon-sm">download</span> Exportar HTML
                    </button>
                    </div>

                    <?php $od = $rep['ordenes_dia'] ?? [];
                    $dias = $od['dias'] ?? []; ?>
                    <div class="tarjeta">
                        <div class="titulo">Órdenes Recibidas (por día)</div>
                        <div class="fila-scroll">
                            <?php if (empty($dias)): ?>
                                <p class="padding-md">Sin órdenes recibidas capturadas para este periodo (o el criterio de folio/día no coincide — ver aviso arriba).</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sucursal</th>
                                            <?php foreach ($dias as $d): ?><th><?= str_pad($d, 2, '0', STR_PAD_LEFT) ?></th><?php endforeach; ?>
                                            <th>Total</th>
                                            <th>Interna</th>
                                            <th>Público</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($od['sucursales'] ?? []) as $f): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($f['sucursal']) ?></td>
                                                <?php foreach ($dias as $d): $v = $f['por_dia'][$d] ?? 0; ?>
                                                    <td><?= $v ? fnum($v) : '' ?></td>
                                                <?php endforeach; ?>
                                                <td><strong><?= fnum($f['total']) ?></strong></td>
                                                <td><?= fnum($f['interna']) ?></td>
                                                <td><?= fnum($f['publico']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>Total</td>
                                            <?php $t = $od['totales'] ?? [];
                                            foreach ($dias as $d): $v = $t['por_dia'][$d] ?? 0; ?>
                                                <td><?= $v ? fnum($v) : '' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= fnum($t['total'] ?? 0) ?></td>
                                            <td><?= fnum($t['interna'] ?? 0) ?></td>
                                            <td><?= fnum($t['publico'] ?? 0) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tarjeta">
                        <div class="titulo">Venta Servicio y Refacciones ($)</div>
                        <div class="fila-scroll">
                            <?php if (empty($rep['sucursales'])): ?>
                                <p class="padding-md">Sin datos de venta de servicio y refacciones para este periodo.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Sucursal</th>
                                            <th>Servicio - Venta</th>
                                            <th>Servicio - Margen</th>
                                            <th>Servicio - % Part.</th>
                                            <th>Refacciones - Venta</th>
                                            <th>Refacciones - Margen</th>
                                            <th>Refacciones - % Part.</th>
                                            <th>Total Venta</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($rep['sucursales'] ?? []) as $f): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($f['sucursal'] ?? '') ?></td>
                                                <td><?= fm($f['servicio_venta'] ?? 0) ?></td>
                                                <td><?= fm($f['servicio_margen'] ?? 0) ?></td>
                                                <td class="pct-muted"><?= fp($f['servicio_pct'] ?? null) ?></td>
                                                <td><?= fm($f['refacciones_venta'] ?? 0) ?></td>
                                                <td><?= fm($f['refacciones_margen'] ?? 0) ?></td>
                                                <td class="pct-muted"><?= fp($f['refacciones_pct'] ?? null) ?></td>
                                                <td><strong><?= fm($f['venta'] ?? 0) ?></strong></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <?php $t = $rep['totales'] ?? []; ?>
                                        <tr>
                                            <td>Total</td>
                                            <td><?= fm($t['servicio_venta'] ?? 0) ?></td>
                                            <td><?= fm($t['servicio_margen'] ?? 0) ?></td>
                                            <td class="pct-muted"><?= fp($t['servicio_pct'] ?? null) ?></td>
                                            <td><?= fm($t['refacciones_venta'] ?? 0) ?></td>
                                            <td><?= fm($t['refacciones_margen'] ?? 0) ?></td>
                                            <td class="pct-muted"><?= fp($t['refacciones_pct'] ?? null) ?></td>
                                            <td><strong><?= fm($t['venta'] ?? 0) ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tarjeta">
                        <div class="titulo">Venta Taller ($)</div>
                        <div class="fila-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th data-col-id="sucursal">Sucursal</th>
                                        <th data-col-id="venta">Venta</th>
                                        <th data-col-id="obj_venta_dia">Obj al Día</th>
                                        <th data-col-id="alcance_ritmo_pct">%Alcance Ritmo</th>
                                        <th data-col-id="obj_venta_mes">Obj Ventas</th>
                                        <th data-col-id="alcance_objetivo_pct">%Alcance Objetivo</th>
                                        <th data-col-id="margen">Margen Bruto</th>
                                        <th data-col-id="pct_margen">%Margen</th>
                                        <th data-col-id="obj_margen_dia">Obj Margen/Día</th>
                                        <th data-col-id="alcance_ritmo_margen_pct">%Alcance Ritmo</th>
                                        <th data-col-id="obj_margen_mes">Obj Margen</th>
                                        <th data-col-id="alcance_objetivo_margen_pct">%Alcance Objetivo</th>
                                        <th data-col-id="cartera">Cartera</th>
                                        <th data-col-id="ticket_prom_real">Ticket Prom. Real</th>
                                        <th data-col-id="ticket_prom_objetivo">Ticket Prom. Objetivo</th>
                                        <th data-col-id="variacion">Variación</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($rep['sucursales'] ?? []) as $f): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($f['sucursal']) ?></td>
                                            <td><?= fm($f['venta']) ?></td>
                                            <td><?= fm($f['obj_venta_dia']) ?></td>
                                            <td><?= fpColor($f['alcance_ritmo_pct']) ?></td>
                                            <td><?= fm($f['obj_venta_mes']) ?></td>
                                            <td><?= fpColor($f['alcance_objetivo_pct']) ?></td>
                                            <td class="<?= $f['margen'] < 0 ? 'neg' : '' ?>"><?= fm($f['margen']) ?></td>
                                            <td><?= fp($f['pct_margen']) ?></td>
                                            <td><?= fm($f['obj_margen_dia']) ?></td>
                                            <td><?= fpColor($f['alcance_ritmo_margen_pct']) ?></td>
                                            <td><?= fm($f['obj_margen_mes']) ?></td>
                                            <td><?= fpColor($f['alcance_objetivo_margen_pct']) ?></td>
                                            <td><?= fm($f['cartera']) ?></td>
                                            <td><?= $f['ticket_prom_real'] !== null ? fm($f['ticket_prom_real']) : '—' ?></td>
                                            <td><?= $f['ticket_prom_objetivo'] !== null ? fm($f['ticket_prom_objetivo']) : '—' ?></td>
                                            <td class="<?= ($f['variacion'] ?? 0) < 0 ? 'neg' : '' ?>">
                                                <?= $f['variacion'] !== null ? fm($f['variacion']) . ' (' . fp($f['variacion_pct']) . ')' : '—' ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($rep['sucursales'])): ?><tr>
                                            <td colspan="16">Sin registros.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <?php $t = $rep['totales'] ?? []; ?>
                                    <tr>
                                        <td>Total</td>
                                        <td><?= fm($t['venta'] ?? 0) ?></td>
                                        <td><?= fm($t['obj_venta_dia'] ?? 0) ?></td>
                                        <td><?= fpColor($t['alcance_ritmo_pct'] ?? null) ?></td>
                                        <td><?= fm($t['obj_venta_mes'] ?? 0) ?></td>
                                        <td><?= fpColor($t['alcance_objetivo_pct'] ?? null) ?></td>
                                        <td><?= fm($t['margen'] ?? 0) ?></td>
                                        <td><?= fp($t['pct_margen'] ?? null) ?></td>
                                        <td><?= fm($t['obj_margen_dia'] ?? 0) ?></td>
                                        <td><?= fpColor($t['alcance_ritmo_margen_pct'] ?? null) ?></td>
                                        <td><?= fm($t['obj_margen_mes'] ?? 0) ?></td>
                                        <td><?= fpColor($t['alcance_objetivo_margen_pct'] ?? null) ?></td>
                                        <td><?= fm($t['cartera'] ?? 0) ?></td>
                                        <td><?= isset($t['ticket_prom_real']) && $t['ticket_prom_real'] !== null ? fm($t['ticket_prom_real']) : '—' ?></td>
                                        <td>—</td>
                                        <td>—</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                <?php endif; ?>
            </main>
        </div>
    </div>
    <script src="../assets/js/one_page_taller.js"></script>
    <script src="../assets/js/topbar.js"></script>
<?php renderSidebarDetalle('One Page Taller — Detalle'); ?>
</body>

</html>
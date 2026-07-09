<?php
set_time_limit(240);
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
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
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
                <a href="hp.php"><span class="material-symbols-outlined">brush</span> H&amp;P</a>
                <a href="refacciones.php"><span class="material-symbols-outlined">inventory_2</span> REFACCIONES</a>
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
                        <button class="btn-experto" id="btn-experto">
                            <span class="material-symbols-outlined icon-sm">psychology</span> Experto
                        </button>
                    </div>

                    <?php $od = $rep['ordenes_recibidas'] ?? [];
                    $dias = $od['dias'] ?? []; ?>
                    <div class="tarjeta">
                        <div class="titulo">Órdenes Recibidas (por día)</div>
                        <div class="fila-scroll">
                            <?php if (empty($dias)): ?>
                                <p class="padding-md">Sin órdenes recibidas capturadas para este periodo.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th data-col-id="sucursal">Sucursal</th>
                                            <?php foreach ($dias as $d): $dd = str_pad($d, 2, '0', STR_PAD_LEFT); ?><th data-col-id="recibidas_dia_<?= $dd ?>"><?= $dd ?></th><?php endforeach; ?>
                                            <th data-col-id="recibidas_total">Total</th>
                                            <th data-col-id="recibidas_interna">Interna</th>
                                            <th data-col-id="recibidas_publico">Público</th>
                                            <th data-col-id="recibidas_garantia">Garantía</th>
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
                                                <td><?= fnum($f['garantia']) ?></td>
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
                                            <td><?= fnum($t['garantia'] ?? 0) ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php $estatus = $rep['ordenes_estatus'] ?? []; ?>
                    <div class="tarjetas-fila">
                        <?php
                        $cards = [
                            ['key' => 'abiertas', 'titulo' => 'Órdenes Abiertas', 'aged_label' => 'Más 15/10 Días', 'estimado' => true],
                            ['key' => 'en_proceso', 'titulo' => 'Órdenes en Proceso', 'aged_label' => 'Más 15 Días', 'estimado' => false],
                            ['key' => 'pendientes_facturar', 'titulo' => 'Órdenes Pendientes de Facturar', 'aged_label' => 'Más 10 Días', 'estimado' => true],
                        ];
                        foreach ($cards as $c):
                            $bloque = $estatus[$c['key']] ?? [];
                            $filas = $bloque['sucursales'] ?? [];
                            $tot = $bloque['totales'] ?? [];
                        ?>
                            <div class="tarjeta">
                                <div class="titulo"><?= htmlspecialchars($c['titulo']) ?></div>
                                <div class="fila-scroll">
                                    <table>
                                        <thead>
                                            <tr>
                                                <th data-col-id="sucursal">Sucursal</th>
                                                <th data-col-id="<?= $c['key'] ?>_ordenes">O.R.</th>
                                                <th data-col-id="<?= $c['key'] ?>_pct_mes">%</th>
                                                <th data-col-id="<?= $c['key'] ?>_importe">Importe</th>
                                                <th data-col-id="<?= $c['key'] ?>_aged_count"><?= htmlspecialchars($c['aged_label']) ?></th>
                                                <th data-col-id="<?= $c['key'] ?>_aged_pct">%</th>
                                                <th data-col-id="<?= $c['key'] ?>_aged_importe">Imp. <?= htmlspecialchars($c['aged_label']) ?><?= $c['estimado'] ? '*' : '' ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($filas)): ?>
                                                <tr>
                                                    <td colspan="7">Sin registros.</td>
                                                </tr>
                                            <?php endif; ?>
                                            <?php foreach ($filas as $f): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($f['sucursal']) ?></td>
                                                    <td><?= fnum($f['ordenes']) ?></td>
                                                    <td class="pct-muted"><?= fp($f['pct_mes']) ?></td>
                                                    <td><?= fm($f['importe']) ?></td>
                                                    <td><?= fnum($f['aged_count']) ?></td>
                                                    <td class="pct-muted"><?= fp($f['aged_pct']) ?></td>
                                                    <td><?= fm($f['aged_importe']) ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="fila-total">
                                                <td>Total</td>
                                                <td><?= fnum($tot['ordenes'] ?? 0) ?></td>
                                                <td class="pct-muted"><?= fp($tot['pct_mes'] ?? null) ?></td>
                                                <td><?= fm($tot['importe'] ?? 0) ?></td>
                                                <td><?= fnum($tot['aged_count'] ?? 0) ?></td>
                                                <td class="pct-muted"><?= fp($tot['aged_pct'] ?? null) ?></td>
                                                <td><?= fm($tot['aged_importe'] ?? 0) ?></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <?php if ($c['estimado']): ?>
                                        <p class="padding-md pct-muted">* Importe estimado proporcionalmente (no es una suma exacta) — ver aviso de supuestos arriba.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php $of = $rep['ordenes_facturadas'] ?? [];
                    $diasF = $of['dias'] ?? []; ?>
                    <div class="tarjeta">
                        <div class="titulo">Órdenes Facturadas (por día)</div>
                        <div class="fila-scroll">
                            <?php if (empty($diasF)): ?>
                                <p class="padding-md">Sin órdenes facturadas capturadas para este periodo.</p>
                            <?php else: ?>
                                <table>
                                    <thead>
                                        <tr>
                                            <th data-col-id="sucursal">Sucursal</th>
                                            <?php foreach ($diasF as $d): $ddf = str_pad($d, 2, '0', STR_PAD_LEFT); ?><th data-col-id="facturadas_dia_<?= $ddf ?>"><?= $ddf ?></th><?php endforeach; ?>
                                            <th data-col-id="facturadas_total">Total</th>
                                            <th data-col-id="facturadas_interna">Interna</th>
                                            <th data-col-id="facturadas_publico">Público</th>
                                            <th data-col-id="facturadas_garantia">Garantía</th>
                                            <th data-col-id="facturadas_obj_dia">Objetivo al Día</th>
                                            <th data-col-id="facturadas_alcance_ritmo_pct">%</th>
                                            <th data-col-id="facturadas_obj_mes">Objetivo</th>
                                            <th data-col-id="facturadas_alcance_objetivo_pct">%</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach (($of['sucursales'] ?? []) as $f): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($f['sucursal']) ?></td>
                                                <?php foreach ($diasF as $d): $v = $f['por_dia'][$d] ?? 0; ?>
                                                    <td><?= $v ? fnum($v) : '' ?></td>
                                                <?php endforeach; ?>
                                                <td><strong><?= fnum($f['total']) ?></strong></td>
                                                <td><?= fnum($f['interna']) ?></td>
                                                <td><?= fnum($f['publico']) ?></td>
                                                <td><?= fnum($f['garantia']) ?></td>
                                                <td><?= fnum($f['obj_dia']) ?></td>
                                                <td><?= fpColor($f['alcance_ritmo_pct']) ?></td>
                                                <td><?= fnum($f['obj_mes']) ?></td>
                                                <td><?= fpColor($f['alcance_objetivo_pct']) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td>Total</td>
                                            <?php $tf = $of['totales'] ?? [];
                                            foreach ($diasF as $d): $v = $tf['por_dia'][$d] ?? 0; ?>
                                                <td><?= $v ? fnum($v) : '' ?></td>
                                            <?php endforeach; ?>
                                            <td><?= fnum($tf['total'] ?? 0) ?></td>
                                            <td><?= fnum($tf['interna'] ?? 0) ?></td>
                                            <td><?= fnum($tf['publico'] ?? 0) ?></td>
                                            <td><?= fnum($tf['garantia'] ?? 0) ?></td>
                                            <td><?= fnum($tf['obj_dia'] ?? 0) ?></td>
                                            <td><?= fpColor($tf['alcance_ritmo_pct'] ?? null) ?></td>
                                            <td><?= fnum($tf['obj_mes'] ?? 0) ?></td>
                                            <td><?= fpColor($tf['alcance_objetivo_pct'] ?? null) ?></td>
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
                                            <th data-col-id="sucursal">Sucursal</th>
                                            <th data-col-id="servicio_venta">Servicio - Venta</th>
                                            <th data-col-id="servicio_margen">Servicio - Margen</th>
                                            <th data-col-id="servicio_pct">Servicio - % Part.</th>
                                            <th data-col-id="refacciones_venta">Refacciones - Venta</th>
                                            <th data-col-id="refacciones_margen">Refacciones - Margen</th>
                                            <th data-col-id="refacciones_pct">Refacciones - % Part.</th>
                                            <th data-col-id="venta">Total Venta</th>
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

    <?php renderSidebar('One Page Taller — Detalle', function () { ?>
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
        (function() {
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

            var MODELO_VSR = 'VentaServicioRefaccion';
            var MODELO_OBJ = 'ObjetivoServicio';

            var camposModelos = {
                'recibidas_total': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Recibidas'
                },
                'recibidas_interna': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Recibidas'
                },
                'recibidas_publico': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Recibidas'
                },
                'recibidas_garantia': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Recibidas'
                },
                'facturadas_total': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Facturadas'
                },
                'facturadas_interna': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Facturadas'
                },
                'facturadas_publico': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Facturadas'
                },
                'facturadas_garantia': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Facturadas'
                },
                'facturadas_obj_dia': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Ordenes_Reparacion_al_Dia'
                },
                'facturadas_obj_mes': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Ordenes_Reparacion'
                },
                'en_proceso_ordenes': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_en_Proceso'
                },
                'en_proceso_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_en_Proceso'
                },
                'en_proceso_aged_count': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_en_Proceso'
                },
                'en_proceso_aged_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_en_Proceso'
                },
                'pendientes_facturar_ordenes': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Pendientes_Fact'
                },
                'pendientes_facturar_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_x_Facturar'
                },
                'pendientes_facturar_aged_count': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_Pendientes_Fact'
                },
                'pendientes_facturar_aged_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_x_Facturar'
                },
                'abiertas_ordenes': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_en_Proceso + Cantidad_Ordenes_Reparacion_Pendientes_Fact'
                },
                'abiertas_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_en_Proceso + Venta_x_Facturar'
                },
                'abiertas_aged_count': {
                    modelo: MODELO_VSR,
                    atributo: 'Cantidad_Ordenes_Reparacion_en_Proceso + Cantidad_Ordenes_Reparacion_Pendientes_Fact'
                },
                'abiertas_aged_importe': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta_en_Proceso + Venta_x_Facturar'
                },
                'servicio_venta': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta'
                },
                'servicio_margen': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta - Costo_Neto'
                },
                'refacciones_venta': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta'
                },
                'refacciones_margen': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta - Costo_Neto'
                },
                'venta': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta'
                },
                'obj_venta_dia': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Vta_al_Dia'
                },
                'obj_venta_mes': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Vta_Mes'
                },
                'margen': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta - Costo_Neto'
                },
                'obj_margen_dia': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Utilidad_al_Dia'
                },
                'obj_margen_mes': {
                    modelo: MODELO_OBJ,
                    atributo: 'Obj_Utilidad_Mes'
                },
                'cartera': {
                    modelo: 'Cxc',
                    atributo: 'Saldo'
                },
                'ticket_prom_real': {
                    modelo: MODELO_VSR,
                    atributo: 'Venta / Cantidad_Ordenes_Reparacion_Recibidas'
                },
                'ticket_prom_objetivo': {
                    modelo: MODELO_OBJ,
                    atributo: 'Ticket_Prom_Objetivo_Taller'
                }
            };

            var SIN_MODELO_PREFIJOS = ['_pct_mes', '_aged_pct', '_alcance_ritmo_pct', '_alcance_objetivo_pct'];
            var SIN_MODELO_EXACTOS = ['alcance_ritmo_pct', 'alcance_objetivo_pct', 'pct_margen',
                'alcance_ritmo_margen_pct', 'alcance_objetivo_margen_pct', 'variacion'
            ];

            function buscarMeta(colId) {
                if (colId.indexOf('recibidas_dia_') === 0) {
                    return {
                        modelo: MODELO_VSR,
                        atributo: 'Cantidad_Ordenes_Reparacion_Recibidas'
                    };
                }
                if (colId.indexOf('facturadas_dia_') === 0) {
                    return {
                        modelo: MODELO_VSR,
                        atributo: 'Cantidad_Ordenes_Reparacion_Facturadas'
                    };
                }
                return camposModelos[colId] || null;
            }

            function sinModelo(colId) {
                if (SIN_MODELO_EXACTOS.indexOf(colId) !== -1) return true;
                for (var i = 0; i < SIN_MODELO_PREFIJOS.length; i++) {
                    if (colId.indexOf(SIN_MODELO_PREFIJOS[i]) !== -1) return true;
                }
                return false;
            }

            function llenarSidebar(th) {
                var colId = th.dataset.colId || '';
                var colNombre = th.textContent.trim();

                context.innerHTML =
                    '<div class="sidebar-context-row">' +
                    '<span class="sidebar-context-label">Columna</span>' +
                    '<span class="sidebar-context-value">' + colNombre + '</span>' +
                    '</div>';

                if (colId === 'sucursal' || sinModelo(colId)) {
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

                if (!window.openSidebar) {
                    console.error('[SIDEBAR] openSidebar no definida');
                    return;
                }
                window.openSidebar();
                console.log('[SIDEBAR] Sidebar abierto.');
            }

            function asignarClicksATablas() {
                var tarjetas = document.querySelectorAll('.tarjeta');
                console.log('[SIDEBAR] Tarjetas encontradas:', tarjetas.length);
                var totalHeaders = 0;
                tarjetas.forEach(function(tarjeta) {
                    var tabla = tarjeta.querySelector('table');
                    if (!tabla) return;
                    var headers = tabla.querySelectorAll('thead th[data-col-id]');
                    if (headers.length === 0) return;
                    var titulo = tarjeta.querySelector('.titulo');
                    var nombreTarjeta = titulo ? titulo.textContent.trim() : '(sin título)';
                    headers.forEach(function(th) {
                        if (th.hasAttribute('colspan') && parseInt(th.getAttribute('colspan'), 10) > 1) return;
                        th.style.cursor = 'pointer';
                        th.title = 'Click para ver detalle';
                        th.addEventListener('click', function(e) {
                            if (e.target.closest('a') || e.target.closest('button')) return;
                            console.log('[SIDEBAR] Click en encabezado:', JSON.stringify(th.textContent.trim()), 'de tabla "' + nombreTarjeta + '"');
                            llenarSidebar(th);
                        });
                        totalHeaders++;
                    });
                });
                console.log('[SIDEBAR] Total headers con click asignados:', totalHeaders);
            }

            asignarClicksATablas();

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    if (!fTitulo || !fTitulo.value.trim()) return;
                    window.closeSidebar();
                });
            }
        })();

        document.getElementById('btn-experto')?.addEventListener('click', function() {
            alert('Modo experto: próximamente disponible.');
        });
    </script>
</body>

</html>
<?php
// Forzar UTF-8 en la respuesta ANTES de cualquier output
header('Content-Type: text/html; charset=UTF-8');
set_time_limit(240);
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/api_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logos.php';
require_once __DIR__ . '/../components/tabla.php';
require_once __DIR__ . '/../components/topbar.php';

securityHeaders();
requireLogin();
ensureModulosCargados();
$usuario = currentUser();
$codigoCliente = $usuario['codigo_cliente'] ?? '';

// Filtros por sesión
$filterKey = 'one_page_filters';
$validSecciones = ['nuevos', 'usados'];

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code', 'page' => 'one_page']);
    die('Acceso no permitido.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrfValidate()) {
        csrfRefresh();
        logSecurityEvent('csrf_validation_failed', ['page' => 'one_page', 'uri' => $_SERVER['REQUEST_URI']]);
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    }

    $filtros = $_SESSION[$filterKey] ?? [];

    $mesPost = sanitizeYearMonth($_POST['mes'] ?? '');
    if ($mesPost !== '') {
        $filtros['mes'] = $mesPost;
    }

    $seccionPost = $_POST['seccion'] ?? '';
    if (in_array($seccionPost, $validSecciones, true)) {
        $filtros['seccion'] = $seccionPost;
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
    // Migrar parámetros GET a sesión y redirigir a URL limpia
    $filtros = $_SESSION[$filterKey] ?? [];

    $mesGet = sanitizeYearMonth($_GET['mes'] ?? '');
    if ($mesGet !== '') {
        $filtros['mes'] = $mesGet;
    }

    $seccionGet = $_GET['seccion'] ?? '';
    if (in_array($seccionGet, $validSecciones, true)) {
        $filtros['seccion'] = $seccionGet;
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
$seccionFiltro = $filtros['seccion'] ?? 'nuevos';
if (!in_array($seccionFiltro, $validSecciones, true)) $seccionFiltro = 'nuevos';

// Consumir flag de refresco (una vez)
if ($forzarRefresco) {
    unset($_SESSION[$filterKey]['refrescar']);
}

function ep(string $base, string $codigoCliente, string $tipo, string $mes, bool $refrescar): string
{
    $e = $base . '?codigo_cliente=' . urlencode($codigoCliente) . '&tipo=' . urlencode($tipo);
    if ($mes !== '') $e .= '&mes=' . urlencode($mes);
    if ($refrescar) $e .= '&refrescar=true';
    return $e;
}

$inicio = microtime(true);
$r = apiGetParalelo([
    'reporte'     => ep('/api/one-page/reporte-autos', $codigoCliente, $seccionFiltro, $mesSeleccionado, $forzarRefresco),
    'inventario'  => ep('/api/one-page/inventario-resumen', $codigoCliente, $seccionFiltro, $mesSeleccionado, $forzarRefresco),
]);
$reporte = $r['reporte'];
$inventario = $r['inventario'];
$tiempoCarga = round(microtime(true) - $inicio, 2);

// Siempre a escala 1; JS se encarga de reescalar en pantalla
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

$todasCompanias = array_unique(array_filter(array_column($reporte['sucursales'] ?? [], 'compania')));
sort($todasCompanias);

$mesesDisponibles = $reporte['meses_disponibles'] ?? [];
rsort($mesesDisponibles);
$mesActivo = $reporte['mes'] ?? null;
$tituloSeccion = $seccionFiltro === 'nuevos' ? 'Autos Nuevos' : 'Autos Seminuevos';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>One Page Autos · Breinit DCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700;800&amp;family=Inter:wght@400;500;600&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/one_page.css">
    <link rel="stylesheet" href="../assets/css/sidebar.css">
    <link rel="stylesheet" href="../assets/css/tabla.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
</head>

<body data-seccion="<?= htmlspecialchars($seccionFiltro) ?>" data-mes="<?= htmlspecialchars($mesActivo ?? '') ?>"
    data-codigo-cliente="<?= htmlspecialchars($codigoCliente) ?>"
    data-cxc-raw="<?= (int)(($reporte['diagnostico']['total_registros_cxc_raw'] ?? 0)) ?>"
    data-cxc-filtered="<?= (int)(($reporte['diagnostico']['total_registros_cxc'] ?? 0)) ?>">
    <script>
    // Mapa dinmico de modelo/atributo por cliente — viene del backend
    var CLIENTE_MODELO_ATRIBUTOS = <?= json_encode($reporte['modelo_atributos'] ?? $inventario['modelo_atributos'] ?? []) ?>;
    if (typeof window.CLIENTE_MODELO_ATRIBUTOS === 'undefined') {
        window.CLIENTE_MODELO_ATRIBUTOS = {};
    }
    </script>
    <div class="layout">
        <aside class="sidebar" id="sidebar">
            <div class="marca">DCA - Desarrollo</div>
            <nav>
                <a href="dashboard.php"><span class="material-symbols-outlined">dashboard</span> Dashboard</a>
                <div class="separador"></div>
                <form method="post" action="one_page.php" class="nav-form <?= $seccionFiltro === 'nuevos' ? 'activo' : '' ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="seccion" value="nuevos">
                    <button type="submit"><span class="material-symbols-outlined">directions_car</span> Autos Nuevos</button>
                </form>
                <form method="post" action="one_page.php" class="nav-form <?= $seccionFiltro === 'usados' ? 'activo' : '' ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="seccion" value="usados">
                    <button type="submit"><span class="material-symbols-outlined">time_to_leave</span> Autos Seminuevos</button>
                </form>
                <a href="one_page_taller.php"><span class="material-symbols-outlined">build</span> Taller</a>
            </nav>
        </aside>
        <div class="content-wrap" id="contentWrap">
            <?php renderTopbar([
                'titulo'    => 'One Page — ' . $tituloSeccion,
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
                    <button class="boton" type="submit" name="refrescar" value="1">&#8635; Actualizar datos</button>
                    <div class="push-right">
                        <label for="mes">Periodo</label>
                        <select id="mes" name="mes">
                            <?php foreach ($mesesDisponibles as $ym): ?>
                                <option value="<?= htmlspecialchars($ym) ?>" <?= $ym === $mesActivo ? 'selected' : '' ?>><?= htmlspecialchars(nombreMes($ym)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <input type="hidden" name="seccion" value="<?= htmlspecialchars($seccionFiltro) ?>">
                </form>
                <p class="nota-tiempo">
                    Cargado en <?= $tiempoCarga ?>s · *Se excluyen Tipos de venta Intercambio ·
                    Cartera = Saldo CxC filtrado por área de negocio según tipo de vehículo. MV = Inventario $ ÷ Venta $ del mes.
                    Filtros de Sucursal y Escala son instantáneos (sin recargar), incluso en el HTML exportado.
                </p>



                <div class="barra-exportar">
                    <button class="btn-exportar" id="btn-exportar">
                        <span class="material-symbols-outlined icon-sm">download</span> Exportar HTML
                    </button>
                </div>

                <?php
                function tablaVentaDia(array $rep, string $tipo = 'Autos'): void
                {
                    $vd = $rep['venta_dia'] ?? [];
                    $dias = $vd['dias'] ?? [];
                    if (empty($dias)) {
                        echo '<p class="nota">Sin ventas capturadas por día para este periodo.</p>';
                        return;
                    }
                ?>
                    <div class="tarjeta">
                        <div class="titulo">Venta Uds <?= htmlspecialchars($tipo) ?> x Día</div>
                        <div class="fila-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th data-col-id="sucursal" data-col-formato="texto">Sucursal</th>
                                        <?php foreach ($dias as $d):
                                            $dd = str_pad($d, 2, '0', STR_PAD_LEFT); ?>
                                            <th data-col-id="dia_<?= $dd ?>" data-col-formato="#,##0"><?= $dd ?></th>
                                        <?php endforeach; ?>
                                        <th data-col-id="total" data-col-formato="#,##0">Total</th>
                                        <th data-col-id="obj_uds_dia" data-col-formato="#,##0">Obj Uds/Día</th>
                                        <th data-col-id="pct_alcance_ritmo" data-col-formato="#,##0%" data-col-formula="{(38) Total} /{(39) Obj Vta Uds al Dia}">%Alcance Ritmo</th>
                                        <th data-col-id="obj_uds_mes" data-col-formato="#,##0">Obj Uds Mes</th>
                                        <th data-col-id="pct_alcance" data-col-formato="#,##0%" data-col-formula="{(38) Total} /{(41) Obj Vta Uds Mensual}">%Alcance</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($vd['sucursales'] ?? []) as $f): ?>
                                        <tr data-sucursal="<?= htmlspecialchars($f['sucursal']) ?>" data-compania="<?= htmlspecialchars($f['compania'] ?? '') ?>">
                                            <td data-campo="sucursal"><?= htmlspecialchars($f['sucursal']) ?></td>
                                            <?php foreach ($dias as $d): $v = $f['por_dia'][$d] ?? 0;
                                                $dd = str_pad($d, 2, '0', STR_PAD_LEFT); ?>
                                                <td data-dia="<?= $dd ?>" class="<?= $v < 0 ? 'neg' : '' ?>"><?= $v != 0 ? fnum($v) : '' ?></td>
                                            <?php endforeach; ?>
                                            <td data-campo="total"><strong><?= fnum($f['total']) ?></strong></td>
                                            <td data-campo="obj_uds_dia"><?= fnum($f['obj_uds_dia']) ?></td>
                                            <td data-campo="pct_alcance_ritmo"><?= fpColor($f['pct_alcance_ritmo']) ?></td>
                                            <td data-campo="obj_uds_mes"><?= fnum($f['obj_uds_mensual']) ?></td>
                                            <td data-campo="pct_alcance"><?= fpColor($f['pct_alcance']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fila-total">
                                        <td>Total</td>
                                        <?php $t = $vd['totales'] ?? [];
                                        foreach ($dias as $d): $v = $t['por_dia'][$d] ?? 0;
                                            $dd = str_pad($d, 2, '0', STR_PAD_LEFT); ?>
                                            <td data-dia="<?= $dd ?>"><?= $v != 0 ? fnum($v) : '' ?></td>
                                        <?php endforeach; ?>
                                        <td data-campo="total"><?= fnum($t['total'] ?? 0) ?></td>
                                        <td data-campo="obj_uds_dia"><?= fnum($t['obj_uds_dia'] ?? 0) ?></td>
                                        <td data-campo="pct_alcance_ritmo"><?= fpColor($t['pct_alcance_ritmo'] ?? null) ?></td>
                                        <td data-campo="obj_uds_mes"><?= fnum($t['obj_uds_mensual'] ?? 0) ?></td>
                                        <td data-campo="pct_alcance"><?= fpColor($t['pct_alcance'] ?? null) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php
                }

                function tablaVentaDinero(array $rep, string $tipo = 'Autos'): void
                {
                    // Cartera se marca como "FILTRADO" solo para el piloto puntual: cliente 0003,
                    // seccion Autos Nuevos, periodo Jun 2026. El backend aplica el filtro por
                    // Area_de_Negocio siempre (para cualquier cliente/mes/tipo), asi que NO sirve
                    // como senal aqui: por eso se usan datos de $rep/$tipo (parametros reales de esta
                    // funcion, no variables externas) para que el badge se autocorrija al cambiar de
                    // mes o de seccion en vez de depender de variables fuera de scope.
                    $carteraFiltrada = ($rep['codigo_cliente'] ?? null) === '0003'
                        && $tipo === 'Autos'
                        && ($rep['mes'] ?? null) === '2026-06';

                    $cols = [
                        ['id' => 'sucursal',   'label' => 'Sucursal',                     'formato' => 'texto'],
                        ['id' => 'venta',       'label' => 'Venta',        'clase' => 'dinero', 'raw_id' => 'venta_raw',            'formato' => '$#,##0'],
                        ['id' => 'obj_dia',     'label' => 'Obj al Día',   'clase' => 'dinero', 'raw_id' => 'obj_dia_raw',          'formato' => '$#,##0'],
                        ['id' => 'alc_ritmo',   'label' => '%Alcance Ritmo', 'html' => true,                                        'formato' => '#,##0%', 'formula' => '{(2) VENTA}/{(18) OBJETIVO AL DIA}'],
                        ['id' => 'obj_mes',     'label' => 'Obj Ventas',   'clase' => 'dinero', 'raw_id' => 'obj_mes_raw',          'formato' => '$#,##0'],
                        ['id' => 'alc_obj',     'label' => '%Alcance Objetivo', 'html' => true,                                     'formato' => '#,##0%', 'formula' => '{(2) VENTA} / {(20) OBJETIVO VENTAS}'],
                        ['id' => 'margen',      'label' => 'Margen Bruto', 'clase' => 'dinero', 'clase_campo' => 'margen_clase', 'raw_id' => 'margen_raw',      'formato' => '$#,##0'],
                        ['id' => 'pct_margen',  'label' => '%Margen',                                                               'formato' => '#,##0%', 'formula' => '{(22) MARGEN BRUTO} / {(2) VENTA}'],
                        ['id' => 'obj_margen_dia', 'label' => 'Obj Margen/Día', 'clase' => 'dinero', 'raw_id' => 'obj_margen_dia_raw', 'formato' => '$#,##0'],
                        ['id' => 'alc_ritmo_margen', 'label' => '%Alcance Ritmo', 'html' => true,                                   'formato' => '#,##0%', 'formula' => '{(22) MARGEN BRUTO} / {(26) OBJ UTILIDAD BRUTA DIA}'],
                        ['id' => 'obj_margen_mes', 'label' => 'Obj Margen', 'clase' => 'dinero', 'raw_id' => 'obj_margen_mes_raw', 'formato' => '$#,##0'],
                        ['id' => 'alc_obj_margen', 'label' => '%Alcance Objetivo', 'html' => true,                                  'formato' => '#,##0%', 'formula' => '{(22) MARGEN BRUTO} / {(24) OBJETIVO MARGEN}'],
                        ['id' => 'cartera',     'label' => 'Cartera',      'clase' => 'dinero col-cartera' . ($carteraFiltrada ? ' cartera-filtrada' : ''), 'raw_id' => 'cartera_raw', 'formato' => '$#,##0'],
                        ['id' => 'inv_uds',     'label' => 'Inventario',                                                           'formato' => '#,##0'],
                        ['id' => 'inv_valor',   'label' => 'Inventario $', 'clase' => 'dinero', 'raw_id' => 'inv_valor_raw',        'formato' => '$#,##0'],
                        ['id' => 'mv',          'label' => 'MV',                                                                   'formato' => '#,##0', 'formula' => '{(28) INVENTARIO} / {(34) Obj Vta Uds Mensual}'],
                    ];

                    $filas = [];
                    foreach (($rep['sucursales'] ?? []) as $f) {
                        // Usar el margen calculado por el backend; si no viene, 0
                        $margenValor = (float)($f['margen'] ?? 0);
                        $margenClase = $margenValor < 0 ? 'neg' : '';
                        $filas[] = [
                            'sucursal' => htmlspecialchars($f['sucursal']),
                            'venta' => fm($f['venta']),
                            'venta_raw' => $f['venta'],
                            'obj_dia' => fm($f['obj_venta_dia']),
                            'obj_dia_raw' => $f['obj_venta_dia'],
                            'alc_ritmo' => fpColor($f['pct_alcance_ritmo']),
                            'obj_mes' => fm($f['obj_venta_mes']),
                            'obj_mes_raw' => $f['obj_venta_mes'],
                            'alc_obj' => fpColor($f['pct_alcance_objetivo']),
                            'margen' => fm($margenValor),
                            'margen_raw' => $margenValor,
                            'margen_clase' => $margenClase,
                            'pct_margen' => fp($f['pct_margen']),
                            'obj_margen_dia' => fm($f['obj_margen_dia']),
                            'obj_margen_dia_raw' => $f['obj_margen_dia'],
                            'alc_ritmo_margen' => fpColor($f['pct_alcance_ritmo_margen']),
                            'obj_margen_mes' => fm($f['obj_margen_mes']),
                            'obj_margen_mes_raw' => $f['obj_margen_mes'],
                            'alc_obj_margen' => fpColor($f['pct_alcance_objetivo_margen']),
                            'cartera' => fm($f['cartera']),
                            'cartera_raw' => $f['cartera'],
                            'inv_uds' => fnum($f['inventario_uds']),
                            'inv_valor' => fm($f['inventario_valor']),
                            'inv_valor_raw' => $f['inventario_valor'],
                            'mv' => $f['mv'] !== null ? fnum($f['mv'], 2) : '—',
                        ];
                    }

                    $t = $rep['totales'] ?? [];
                    $margenTotal = (float)($t['margen'] ?? 0);

                    $totales = ($t ? [
                        'sucursal' => 'Total',
                        'venta' => fm($t['venta'] ?? 0),
                        'obj_dia' => fm($t['obj_venta_dia'] ?? 0),
                        'alc_ritmo' => fpColor($t['pct_alcance_ritmo'] ?? null),
                        'obj_mes' => fm($t['obj_venta_mes'] ?? 0),
                        'alc_obj' => fpColor($t['pct_alcance_objetivo'] ?? null),
                        'margen' => fm($margenTotal),
                        'pct_margen' => fp($t['pct_margen'] ?? null),
                        'obj_margen_dia' => fm($t['obj_margen_dia'] ?? 0),
                        'alc_ritmo_margen' => fpColor($t['pct_alcance_ritmo_margen'] ?? null),
                        'obj_margen_mes' => fm($t['obj_margen_mes'] ?? 0),
                        'alc_obj_margen' => fpColor($t['pct_alcance_objetivo_margen'] ?? null),
                        'cartera' => fm($t['cartera'] ?? 0),
                        'inv_uds' => fnum($t['inventario_uds'] ?? 0),
                        'inv_valor' => fm($t['inventario_valor'] ?? 0),
                        'mv' => isset($t['mv']) && $t['mv'] !== null ? fnum($t['mv'], 2) : '—',
                    ] : null);
                ?>
                    <div class="tarjeta">
                        <div class="titulo">Venta <?= htmlspecialchars($tipo) ?> ($)</div>
                        <div class="fila-scroll">
                            <?php renderTabla([
                                'columnas'  => $cols,
                                'filas'     => $filas,
                                'totales'   => $totales,
                                'clase_tfoot' => 'fila-total',
                                'vacio'     => 'Sin registros.',
                            ]); ?>
                        </div>
                    </div>
                <?php
                }

                function tablaInventario(array $inv, string $campo, string $titulo, bool $esMoneda): void
                {
                    $rangos = $inv['rangos'] ?? [];
                    $numRangos = count($rangos);
                    // %Part. fórmula: Uds del rango / Total Uds (siempre posición 19)
                    $udsLabel = $esMoneda ? 'Inventario $' : 'Uds Existencia';
                ?>
                    <div class="tarjeta">
                        <div class="titulo"><?= htmlspecialchars($titulo) ?></div>
                        <div class="fila-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th rowspan="2" data-col-id="sucursal" data-col-formato="texto">Sucursal</th>
                                        <?php foreach ($rangos as $i => $r): ?><th colspan="2" data-col-id="rango_<?= $i ?>" data-col-formato="texto"><?= htmlspecialchars($r) ?></th><?php endforeach; ?>
                                        <th colspan="2" data-col-id="total" data-col-formato="texto">Total</th>
                                    </tr>
                                    <tr>
                                        <?php foreach ($rangos as $i => $r):
                                            $udsPos = 2 * ($i + 1);
                                        ?>
                                            <th data-col-id="<?= $campo ?>_uds_<?= $i ?>" data-col-formato="#,##0"><?= $udsLabel ?></th>
                                            <th data-col-id="<?= $campo ?>_pct_<?= $i ?>" data-col-formato="#,##0%" data-col-formula="{(<?= $udsPos ?>) <?= $udsLabel ?>} / {(19) <?= $udsLabel ?>}">% Part.</th>
                                        <?php endforeach; ?>
                                        <th data-col-id="<?= $campo ?>_uds_total" data-col-formato="#,##0"><?= $udsLabel ?></th>
                                        <th data-col-id="<?= $campo ?>_pct_total" data-col-formato="#,##0%">% Part.</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (($inv['sucursales'] ?? []) as $f): ?>
                                        <tr data-sucursal="<?= htmlspecialchars($f['sucursal']) ?>" data-compania="<?= htmlspecialchars($f['compania'] ?? '') ?>">
                                            <td data-campo="sucursal"><?= htmlspecialchars($f['sucursal']) ?></td>
                                            <?php $rangoIndex = 0; foreach ($rangos as $r): $d = $f['por_rango'][$r] ?? ['uds' => 0, 'valor' => 0, 'pct_part_uds' => 0, 'pct_part_valor' => 0];
                                                $udsCampo = $campo . '_uds_' . $rangoIndex;
                                                $pctCampo = $campo . '_pct_' . $rangoIndex; ?>
                                                <td class="<?= $esMoneda ? 'dinero' : '' ?>" data-campo="<?= $udsCampo ?>" <?= $esMoneda ? 'data-raw="' . raw($d['valor']) . '"' : '' ?>><?= $esMoneda ? fm($d['valor']) : fnum($d['uds']) ?></td>
                                                <td class="pct-muted" data-campo="<?= $pctCampo ?>"><?= fp($esMoneda ? ($d['pct_part_valor'] ?? 0) : ($d['pct_part_uds'] ?? 0)) ?></td>
                                            <?php $rangoIndex++; endforeach; ?>
                                            <td class="<?= $esMoneda ? 'dinero' : '' ?>" data-campo="<?= $campo ?>_uds_total" <?= $esMoneda ? 'data-raw="' . raw($f['total_valor']) . '"' : '' ?>><strong><?= $esMoneda ? fm($f['total_valor']) : fnum($f['total_uds']) ?></strong></td>
                                            <td class="pct-muted" data-campo="<?= $campo ?>_pct_total"><?= fp($esMoneda ? ($f['pct_part_valor_total'] ?? 0) : ($f['pct_part_uds_total'] ?? 0)) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($inv['sucursales'])): ?><tr>
                                            <td colspan="<?= count($rangos) * 2 + 3 ?>">Sin registros.</td>
                                        </tr><?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="fila-total">
                                        <td data-campo="sucursal">Total</td>
                                        <?php $rangoIndex = 0; $tt = $inv['totales']['por_rango'] ?? [];
                                        foreach ($rangos as $r): $d = $tt[$r] ?? ['uds' => 0, 'valor' => 0, 'pct_part_uds' => 0, 'pct_part_valor' => 0];
                                            $udsCampo = $campo . '_uds_' . $rangoIndex;
                                            $pctCampo = $campo . '_pct_' . $rangoIndex; ?>
                                            <td class="<?= $esMoneda ? 'dinero' : '' ?>" data-campo="<?= $udsCampo ?>" <?= $esMoneda ? 'data-raw="' . raw($d['valor']) . '"' : '' ?>><?= $esMoneda ? fm($d['valor']) : fnum($d['uds']) ?></td>
                                            <td data-campo="<?= $pctCampo ?>"><?= fp($esMoneda ? ($d['pct_part_valor'] ?? 0) : ($d['pct_part_uds'] ?? 0)) ?></td>
                                        <?php $rangoIndex++; endforeach; ?>
                                        <td class="<?= $esMoneda ? 'dinero' : '' ?>" data-campo="<?= $campo ?>_uds_total" <?= $esMoneda ? 'data-raw="' . raw($inv['totales']['valor'] ?? 0) . '"' : '' ?>><?= $esMoneda ? fm($inv['totales']['valor'] ?? 0) : fnum($inv['totales']['uds'] ?? 0) ?></td>
                                        <td data-campo="<?= $campo ?>_pct_total">100%</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                <?php
                }

                function seccionCompleta(string $titulo, array $rep, array $inv, string $tipo = 'Autos'): void
                {
                    if (!empty($rep['error']) || !empty($inv['error'])) {
                        echo '<section class="seccion"><h2>' . htmlspecialchars($titulo) . '</h2>';
                        echo '<div class="error"><strong>No se pudo cargar:</strong> ' . htmlspecialchars($rep['message'] ?? $inv['message'] ?? '') . '</div></section>';
                        return;
                    }
                    echo '<section class="seccion"><h2>' . htmlspecialchars($titulo) . '</h2>';
                    if (!empty($rep['advertencias']) || !empty($inv['advertencias'])) {
                        $todas = array_merge($rep['advertencias'] ?? [], $inv['advertencias'] ?? []);
                        echo '<div class="aviso-warning">';
                        echo '<strong>âš ï¸ Posible desajuste de datos para este cliente:</strong><ul class="aviso-list">';
                        foreach ($todas as $a) {
                            echo '<li>' . htmlspecialchars($a) . '</li>';
                        }
                        echo '</ul></div>';
                    }
                    tablaVentaDia($rep, $tipo);
                    tablaVentaDinero($rep, $tipo);
                    tablaInventario($inv, 'uds', 'Inventario ' . $tipo . ' (Uds)', false);
                    tablaInventario($inv, 'valor', 'Inventario ' . $tipo . ' ($)', true);
                    echo '</section>';
                }

                if ($seccionFiltro === 'nuevos' || $seccionFiltro === 'usados') {
                    $tipoTabla = $seccionFiltro === 'nuevos' ? 'Autos' : 'SemiNuevos';
                    seccionCompleta($tituloSeccion, $reporte, $inventario, $tipoTabla);
                }
                ?>
            </main>
        </div>
    </div>
    <script src="../assets/js/one_page.js"></script>
    <script src="../assets/js/topbar.js"></script>

    <?php
    require_once __DIR__ . '/../components/sidebar.php';
    $tipoSidebar = $seccionFiltro === 'nuevos' ? 'Autos' : 'SemiNuevos';
    renderSidebar('Venta ' . $tipoSidebar . ' ($) — Detalle', function () {
    ?>
        <div class="sidebar-row-context" id="rowContext"></div>

        <!-- Formulario regular (visible para cualquier columna excepto Cartera) -->
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
            <div class="sidebar-form-group" id="gpoFormula" style="display:none">
                <label for="fFormula">Fórmula</label>
                <input type="text" id="fFormula" name="formula" placeholder="Fórmula del cálculo">
            </div>
            <button type="submit" class="sidebar-btn-guardar">Guardar</button>
        </form>

        <!-- Info específica de Cartera (visible solo al hacer clic en CARTERA) -->
        <div class="sidebar-cxc" id="sidebarCxc" style="display:none">
            <div class="sidebar-cxc-section">
                <div class="sidebar-cxc-title">Filtro aplicado a Cartera</div>
                <div class="sidebar-nota-info">Los valores de Cartera ahora se filtran por Area de negocio segun el tipo de vehiculo seleccionado.</div>
            </div>
            <div class="sidebar-cxc-section">
                <div class="sidebar-cxc-row">
                    <span class="sidebar-cxc-label">Registros totales CxC</span>
                    <span class="sidebar-cxc-value sidebar-cxc-raw" id="cxcRaw">0</span>
                </div>
                <div class="sidebar-cxc-row">
                    <span class="sidebar-cxc-label">Registros filtrados</span>
                    <span class="sidebar-cxc-value sidebar-cxc-filtered" id="cxcFiltered">0</span>
                </div>
                <div class="sidebar-cxc-row">
                    <span class="sidebar-cxc-label">Descartados</span>
                    <span class="sidebar-cxc-value sidebar-cxc-diff" id="cxcDiff">0</span>
                </div>
                <div class="sidebar-cxc-row">
                    <span class="sidebar-cxc-label">Filtro activo</span>
                    <span class="sidebar-cxc-badge" id="cxcBadge">Autos Nuevos</span>
                </div>
            </div>
            <div class="sidebar-cxc-section">
                <div class="sidebar-cxc-nota">La columna Cartera suma el Saldo de CxC solo de los registros cuya Area de negocio coincide con el tipo de vehiculo.</div>
            </div>

        </div>
    <?php }); ?>
    <script src="../assets/js/sidebar.js"></script>
    <script nonce="<?= cspStyleNonce() ?>">
        (function() {
            'use strict';
            // Seguridad: si el script del body no se ejecuto, inicializar vacio
            if (typeof window.CLIENTE_MODELO_ATRIBUTOS === 'undefined') {
                window.CLIENTE_MODELO_ATRIBUTOS = {};
            }

            console.log('[SIDEBAR] Iniciando script...');

            var context = document.getElementById('rowContext');
            var form = document.getElementById('sidebarForm');
            var fModelo = document.getElementById('fModelo');
            var fAtributo = document.getElementById('fAtributo');
            var grpModelo = document.getElementById('grpModelo');
            var grpAtributo = document.getElementById('grpAtributo');
            var fTitulo = document.getElementById('fTitulo');
            var fFormato = document.getElementById('fFormato');
            var fFormula = document.getElementById('fFormula');
            var gpoFormula = document.getElementById('gpoFormula');

            console.log('[SIDEBAR] DOM elements:', {
                context: !!context,
                form: !!form,
                fModelo: !!fModelo,
                fTitulo: !!fTitulo,
                fFormato: !!fFormato,
                fFormula: !!fFormula,
                openSidebar: typeof window.openSidebar
            });

            function asignarClicksATablas() {
                var seccion = document.body.dataset.seccion;
                console.log('[SIDEBAR] Sección actual: "' + seccion + '". Asignando clicks...');
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
                    console.log('[SIDEBAR] Tabla "' + nombreTarjeta + '" — ' + headers.length + ' headers con data-col-id');
                    headers.forEach(function(th) {
                        // Saltar th con colspan (encabezados agrupados, no clickeables)
                        if (th.hasAttribute('colspan') && parseInt(th.getAttribute('colspan')) > 1) return;
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
                if (totalHeaders === 0) {
                    console.warn('[SIDEBAR] No se encontraron headers con data-col-id.');
                }
            }

            var sidebarCxc = document.getElementById('sidebarCxc');

            function llenarSidebar(th) {
                var colId = th.dataset.colId || '';
                var colNombre = th.textContent.trim();
                var formato = th.dataset.colFormato || '';
                var formula = th.dataset.colFormula || '';

                if (!context || !fModelo || !fTitulo || !fFormato) {
                    console.error('[SIDEBAR] Faltan elementos del formulario');
                    return;
                }

                // Contexto visual
                context.innerHTML =
                    '<div class="sidebar-context-row">' +
                    '<span class="sidebar-context-label">Columna</span>' +
                    '<span class="sidebar-context-value">' + colNombre + '</span>' +
                    '</div>';

                // --- Cartera CxC filtrado: piloto puntual cliente 0003 + Autos Nuevos + Jun 2026.
                // Misma regla que el badge FILTRADO en PHP; via data-attrs para autocorregirse
                // al cambiar de mes o de seccion. ---
                var seccionActiva = document.body.dataset.seccion || '';
                var codigoClienteActivo = document.body.dataset.codigoCliente || '';
                var mesActivoBody = document.body.dataset.mes || '';
                var mostrarCxcFiltrado = (colId === 'cartera' && codigoClienteActivo === '0003' && seccionActiva === 'nuevos' && mesActivoBody === '2026-06');

                if (mostrarCxcFiltrado) {
                    if (form) form.style.display = 'none';
                    if (sidebarCxc) {
                        var raw = parseInt(document.body.dataset.cxcRaw) || 0;
                        var filtered = parseInt(document.body.dataset.cxcFiltered) || 0;
                        var diff = raw - filtered;
                        var pct = raw > 0 ? Math.round((diff / raw) * 100) : 0;
                        var badge = 'Autos Nuevos';

                        document.getElementById('cxcRaw').textContent = raw.toLocaleString() + ' registros';
                        document.getElementById('cxcFiltered').textContent = filtered.toLocaleString() + ' registros';
                        document.getElementById('cxcDiff').textContent = diff.toLocaleString() + ' (' + pct + '%)';
                        document.getElementById('cxcBadge').textContent = badge;

                        sidebarCxc.style.display = 'block';
                    }
                } else if (colId === 'cartera') {
                    // Cartera fuera del piloto (otro cliente/seccion/mes): mostrar como columna normal
                    if (sidebarCxc) sidebarCxc.style.display = 'none';
                    if (form) form.style.display = 'block';
                    if (grpModelo) grpModelo.style.display = 'block';
                    if (grpAtributo) grpAtributo.style.display = 'block';
                    var meta = (window.CLIENTE_MODELO_ATRIBUTOS || {})[colId];
                    if (meta) {
                        fModelo.value = meta.modelo || colId;
                        if (fAtributo) fAtributo.value = meta.label || meta.atributo || '';
                    } else {
                        fModelo.value = 'Cxc';
                        if (fAtributo) fAtributo.value = 'Saldo';
                    }
                    fTitulo.value = colNombre;
                    fFormato.value = formato;
                    if (gpoFormula) gpoFormula.style.display = 'none';
                    if (fFormula) fFormula.value = '';
                    if (!window.openSidebar) return;
                    window.openSidebar();
                    return;
                } else {
                    // --- Mostrar formulario regular ---
                    if (sidebarCxc) sidebarCxc.style.display = 'none';
                    if (form) form.style.display = 'block';

                    // Sucursal es dimensión, no métrica: ocultar Modelo/Atributo PARA TODAS LAS TABLAS
                    var esSucursalCol = (colId === 'sucursal');
                    if (esSucursalCol) {
                        if (grpModelo) grpModelo.style.display = 'none';
                        if (grpAtributo) grpAtributo.style.display = 'none';
                        fTitulo.value = colNombre;
                        fFormato.value = formato;
                        if (gpoFormula) gpoFormula.style.display = 'none';
                        if (fFormula) fFormula.value = '';
                    } else {
                        //Lookup dinmico desde el mapa del backend
                        var meta = (window.CLIENTE_MODELO_ATRIBUTOS || {})[colId];
                        if (grpModelo) grpModelo.style.display = 'block';
                        if (grpAtributo) grpAtributo.style.display = 'block';
                        if (meta) {
                            fModelo.value = meta.modelo || colId;
                            if (fAtributo) fAtributo.value = meta.label || meta.atributo || '';
                        } else {
                            //Fallback hardcodeado: mismo mapa que el backend
                            var fb = {
                                "sucursal":          {"modelo": "VentaAuto",    "atributo": "Sucursal"},
                                "dia_01":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_02":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_03":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_04":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_05":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_06":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_07":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_08":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_09":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_10":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_11":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_12":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_13":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_14":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_15":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_16":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_17":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_18":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_19":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_20":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_21":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_22":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_23":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_24":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_25":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_26":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_27":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_28":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_29":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_30":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "dia_31":            {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "total":             {"modelo": "VentaAuto",    "atributo": "Vta Uds"},
                                "obj_uds_dia":       {"modelo": "VentaAuto",    "atributo": "Obj_Vta_Uds_al_Dia"},
                                "pct_alcance_ritmo": {"modelo": "", "atributo": ""},
                                "obj_uds_mes":       {"modelo": "Objetivos Autos",    "atributo": "Obj_Vta_Uds_Mensual"},
                                "pct_alcance":       {"modelo": "", "atributo": ""},
                                "venta":             {"modelo": "VentaAuto",    "atributo": "Vta_Neta"},
                                "obj_dia":           {"modelo": "VentaAuto",    "atributo": "Obj_Venta_Dia"},
                                "obj_mes":           {"modelo": "VentaAuto",    "atributo": "Obj_Venta_Mes"},
                                "margen":            {"modelo": "", "atributo": ""},
                                "pct_margen":        {"modelo": "", "atributo": ""},
                                "obj_margen_dia":    {"modelo": "VentaAuto",    "atributo": "Obj_Utilidad_Bruta_Dia"},
                                "obj_margen_mes":    {"modelo": "VentaAuto",    "atributo": "Obj_Utilidad_Bruta_Mes"},
                                "alc_ritmo":         {"modelo": "", "atributo": ""},
                                "alc_obj":           {"modelo": "", "atributo": ""},
                                "alc_ritmo_margen":  {"modelo": "", "atributo": ""},
                                "alc_obj_margen":    {"modelo": "", "atributo": ""},
                                "cartera":           {"modelo": "Cxc",           "atributo": "Saldo"},
                                "inv_uds":           {"modelo": "InventarioAuto", "atributo": "Exist"},
                                "inv_valor":         {"modelo": "InventarioAuto", "atributo": "Inventario"},
                                "mv":                {"modelo": "", "atributo": ""},
                            };
                            var fbMeta = fb[colId];
                            if (fbMeta) {
                                fModelo.value = fbMeta.modelo;
                                if (fAtributo) fAtributo.value = fbMeta.atributo;
                            } else {
                                fModelo.value = colId;
                                if (fAtributo) fAtributo.value = '';
                            }
                        }
                        if (grpModelo) grpModelo.style.display = (fModelo.value && fModelo.value.trim()) ? 'block' : 'none';
                        if (grpAtributo) grpAtributo.style.display = (fAtributo && fAtributo.value && fAtributo.value.trim()) ? 'block' : 'none';
                        fTitulo.value = colNombre;
                        fFormato.value = formato;
                        if (gpoFormula) gpoFormula.style.display = formula ? 'block' : 'none';
                        if (fFormula) fFormula.value = formula;
                    }
                }

                if (!window.openSidebar) {
                    console.error('[SIDEBAR] openSidebar no definida');
                    return;
                }
                window.openSidebar();
                console.log('[SIDEBAR] Sidebar abierto.');
            }

            asignarClicksATablas();

            function asignarClicksACeldas() {
                var tarjetas = document.querySelectorAll('.tarjeta');
                tarjetas.forEach(function(tarjeta) {
                    var tabla = tarjeta.querySelector('table');
                    if (!tabla) return;
                    var titulo = tarjeta.querySelector('.titulo');
                    var nombreTarjeta = titulo ? titulo.textContent.trim() : '';
                    // Adjuntar clicks a TODAS las tablas (no solo Venta Uds)
                    var celdas = tabla.querySelectorAll('tbody td[data-dia], tbody td[data-campo], tfoot td[data-dia], tfoot td[data-campo]');
                    celdas.forEach(function(td) {
                        td.style.cursor = 'pointer';
                        td.title = 'Click para ver detalle';
                        td.addEventListener('click', function(e) {
                            if (e.target.closest('a') || e.target.closest('button')) return;
                            td.dataset.tabla = nombreTarjeta;
                            llenarSidebarCelda(td);
                        });
                    });
                });
            }

            function llenarSidebarCelda(td) {
                var tr = td.closest('tr');
                var sucursal = tr ? (tr.dataset.sucursal || '') : '';
                var dia = td.dataset.dia || '';
                var campo = td.dataset.campo || '';
                var titulo = dia || campo;

                if (!context || !fModelo || !fTitulo || !fFormato) {
                    console.error('[SIDEBAR] Faltan elementos del formulario');
                    return;
                }

                context.innerHTML =
                    '<div class="sidebar-context-row">' +
                    '<span class="sidebar-context-label">Sucursal</span>' +
                    '<span class="sidebar-context-value">' + sucursal + '</span>' +
                    '</div>' +
                    '<div class="sidebar-context-row">' +
                    '<span class="sidebar-context-label">Columna</span>' +
                    '<span class="sidebar-context-value">' + titulo + '</span>' +
                    '</div>';

                // --- Cartera CxC filtrado: piloto puntual cliente 0003 + Autos Nuevos + Jun 2026.
                // Misma regla que el badge FILTRADO en PHP; via data-attrs para autocorregirse
                // al cambiar de mes o de seccion. ---
                var seccionActiva = document.body.dataset.seccion || '';
                var codigoClienteActivo = document.body.dataset.codigoCliente || '';
                var mesActivoBody = document.body.dataset.mes || '';
                var mostrarCxcFiltrado = (campo === 'cartera' && codigoClienteActivo === '0003' && seccionActiva === 'nuevos' && mesActivoBody === '2026-06');

                if (mostrarCxcFiltrado) {
                    if (form) form.style.display = 'none';
                    if (sidebarCxc) {
                        var raw = parseInt(document.body.dataset.cxcRaw) || 0;
                        var filtered = parseInt(document.body.dataset.cxcFiltered) || 0;
                        var diff = raw - filtered;
                        var pct = raw > 0 ? Math.round((diff / raw) * 100) : 0;

                        document.getElementById('cxcRaw').textContent = raw.toLocaleString() + ' registros';
                        document.getElementById('cxcFiltered').textContent = filtered.toLocaleString() + ' registros';
                        document.getElementById('cxcDiff').textContent = diff.toLocaleString() + ' (' + pct + '%)';
                        document.getElementById('cxcBadge').textContent = 'Autos Nuevos';

                        sidebarCxc.style.display = 'block';
                    }
                    if (!window.openSidebar) return;
                    window.openSidebar();
                    return;
                }

                if (sidebarCxc) sidebarCxc.style.display = 'none';
                if (form) form.style.display = 'block';

                var esSucursalCelda = (campo === 'sucursal');
                if (esSucursalCelda) {
                    // Sucursal es dimensión, no métrica: ocultar Modelo/Atributo PARA TODAS LAS TABLAS
                    if (grpModelo) grpModelo.style.display = 'none';
                    if (grpAtributo) grpAtributo.style.display = 'none';
                } else {
                    if (grpModelo) grpModelo.style.display = 'block';
                    if (grpAtributo) grpAtributo.style.display = 'block';
                    //Lookup dinmico desde el mapa del backend
                    var meta = (window.CLIENTE_MODELO_ATRIBUTOS || {})[campo];
                    if (meta) {
                        fModelo.value = meta.modelo || campo;
                        if (fAtributo) fAtributo.value = meta.label || meta.atributo || '';
                    } else {
                        //Fallback hardcodeado: mismo mapa que el backend
                        var fb = {
                            "sucursal":          {"modelo": "VentaAuto",       "atributo": "Sucursal"},
                            "dia_01":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_02":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_03":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_04":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_05":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_06":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_07":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_08":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_09":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_10":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_11":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_12":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_13":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_14":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_15":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_16":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_17":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_18":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_19":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_20":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_21":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_22":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_23":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_24":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_25":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_26":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_27":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_28":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_29":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_30":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "dia_31":            {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "total":             {"modelo": "VentaAuto",       "atributo": "Vta Uds"},
                            "obj_uds_dia":       {"modelo": "VentaAuto",       "atributo": "Obj_Vta_Uds_al_Dia"},
                            "pct_alcance_ritmo": {"modelo": "", "atributo": ""},
                            "obj_uds_mes":       {"modelo": "Objetivos Autos",       "atributo": "Obj_Vta_Uds_Mensual"},
                            "pct_alcance":       {"modelo": "", "atributo": ""},
                            "venta":             {"modelo": "VentaAuto",       "atributo": "Vta_Neta"},
                            "obj_dia":           {"modelo": "VentaAuto",       "atributo": "Obj_Venta_Dia"},
                            "obj_mes":           {"modelo": "VentaAuto",       "atributo": "Obj_Venta_Mes"},
                            "margen":            {"modelo": "", "atributo": ""},
                            "pct_margen":        {"modelo": "", "atributo": ""},
                            "obj_margen_dia":    {"modelo": "VentaAuto",       "atributo": "Obj_Utilidad_Bruta_Dia"},
                            "obj_margen_mes":    {"modelo": "VentaAuto",       "atributo": "Obj_Utilidad_Bruta_Mes"},
                            "alc_ritmo":         {"modelo": "", "atributo": ""},
                            "alc_obj":           {"modelo": "", "atributo": ""},
                            "alc_ritmo_margen":  {"modelo": "", "atributo": ""},
                            "alc_obj_margen":    {"modelo": "", "atributo": ""},
                            "cartera":           {"modelo": "Cxc",              "atributo": "Saldo"},
                            "inv_uds":           {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "inv_valor":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "mv":                {"modelo": "", "atributo": ""},
                            "valor_uds_0":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_1":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_2":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_3":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_4":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_5":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_6":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_7":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_8":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_9":       {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_10":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_11":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_12":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_13":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_14":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_15":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_16":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_17":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_18":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_uds_19":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_pct_0":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_1":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_2":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_3":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_4":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_5":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_6":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_7":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_8":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_9":        {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_10":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_11":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_12":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_13":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_14":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_15":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_16":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_17":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_18":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_pct_19":       {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "valor_uds_total":    {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "valor_pct_total":    {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_uds_0":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_1":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_2":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_3":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_4":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_5":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_6":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_7":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_8":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_9":          {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_10":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_11":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_12":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_13":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_14":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_15":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_16":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_17":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_18":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_uds_19":         {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_pct_0":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_1":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_2":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_3":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_4":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_5":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_6":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_7":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_8":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_9":          {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_10":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_11":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_12":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_13":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_14":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_15":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_16":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_17":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_18":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_pct_19":         {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                            "uds_uds_total":      {"modelo": "InventarioAuto",   "atributo": "Exist"},
                            "uds_pct_total":      {"modelo": "InventarioAuto",   "atributo": "Inventario"},
                        };
                        var fbMeta = fb[campo];
                        if (fbMeta) {
                            fModelo.value = fbMeta.modelo;
                            if (fAtributo) fAtributo.value = fbMeta.atributo;
                        } else {
                            fModelo.value = campo || 'Venta';
                            if (fAtributo) fAtributo.value = '';
                        }
                    }
                if (grpModelo) grpModelo.style.display = (fModelo.value && fModelo.value.trim()) ? 'block' : 'none';
                if (grpAtributo) grpAtributo.style.display = (fAtributo && fAtributo.value && fAtributo.value.trim()) ? 'block' : 'none';
                }
                fTitulo.value = titulo;
                fFormato.value = '#,##0';

                var formula = '';
                var thEl = td.closest('table') ? td.closest('table').querySelector('th[data-col-id="' + campo + '"]') : null;
                if (thEl && thEl.dataset.colFormula) {
                    formula = thEl.dataset.colFormula;
                }
                if (gpoFormula) gpoFormula.style.display = formula ? 'block' : 'none';
                if (fFormula) fFormula.value = formula;

                if (!window.openSidebar) {
                    console.error('[SIDEBAR] openSidebar no definida');
                    return;
                }
                window.openSidebar();
            }

            asignarClicksACeldas();

            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault();
                    console.log('[SIDEBAR] Submit:', {
                        modelo: fModelo ? fModelo.value : '(n/a)',
                        titulo: fTitulo ? fTitulo.value : '(n/a)',
                        formato: fFormato ? fFormato.value : '(n/a)',
                        formula: fFormula ? fFormula.value : '(n/a)'
                    });
                    if (!fTitulo || !fTitulo.value.trim()) {
                        console.warn('[SIDEBAR] Título vacío, no se cierra');
                        return;
                    }
                    window.closeSidebar();
                });
            }
        })();
    </script>
</body>

</html>
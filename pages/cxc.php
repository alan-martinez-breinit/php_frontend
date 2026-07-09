<?php
/**
 * Vista para el módulo CXC (Cuentas por Cobrar).
 * Columnas whitelist, filtros, paginación y exportación HTML.
 */
set_time_limit(120);
require_once __DIR__ . '/../lib/config.php';
require_once __DIR__ . '/../lib/api_client.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/logos.php';
require_once __DIR__ . '/../components/topbar.php';
require_once __DIR__ . '/../components/tabla.php';
require_once __DIR__ . '/../includes/sidebar_detail.php';

securityHeaders();
requireLogin();
ensureModulosCargados();
$usuario = currentUser();
$codigoCliente = $usuario['codigo_cliente'] ?? '';

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code', 'page' => 'cxc']);
    die('Acceso no permitido.');
}

// Columnas permitidas (clave=campo API, valor=etiqueta)
$COLUMNAS_CXC = [
    'date_key'                  => 'Fecha',
    'Compania'                  => 'Compañía',
    'Companias'                 => 'Compañía',
    'Servidor'                  => 'Servidor',
    'Sucursal'                  => 'Sucursal',
    'Clientes'                  => 'Cliente',
    'Clientes_Descripcion'      => 'Cliente',
    'Folio'                     => 'Folio',
    'Fecha'                     => 'Fecha Doc',
    'Fecha_Venc'                => 'Vencimiento',
    'Monto_Original'            => 'Monto Original',
    'Saldo'                     => 'Saldo',
    'Saldo_Vencido'             => 'Saldo Vencido',
    'Saldo_por_Vencer'          => 'Saldo x Vencer',
    'Dias_Vencido'              => 'Días Venc.',
    'Dias_Antiguedad'           => 'Días Antig.',
    'Rango_Vencido'             => 'Rango',
    'Rango_Antiguedad'          => 'Rango',
    'Tipo_de_Movimiento'        => 'Tipo Mov.',
    'Clasificacion_de_Cartera'  => 'Cartera',
    'Monto_Pagado'              => 'Monto Pagado',
    'VIN'                       => 'VIN',
    'Area_de_Negocio'           => 'Área Negocio',
    'Vendedores'                => 'Vendedor',
    'Condiciones_Pago'          => 'Cond. Pago',
];

// Filtros guardados en sesión para no exponerlos en la URL
$hoy = new DateTime();
$inicioMes = (new DateTime())->modify('first day of this month')->format('Y-m-d');
$finMes = $hoy->format('Y-m-d');

$filterKey = 'cxc_filters';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!csrfValidate()) {
    csrfRefresh();
    logSecurityEvent('csrf_validation_failed', ['page' => 'cxc', 'uri' => $_SERVER['REQUEST_URI']]);
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
  }

  $filtros = $_SESSION[$filterKey] ?? [];

  $sd = sanitizeDate($_POST['start_date'] ?? '');
  if ($sd !== '') $filtros['start_date'] = $sd;

  $ed = sanitizeDate($_POST['end_date'] ?? '');
  if ($ed !== '') $filtros['end_date'] = $ed;

  $q = sanitizeString($_POST['q'] ?? '', 100);
  $filtros['q'] = $q !== '' ? $q : '';

  $s = sanitizeString($_POST['sucursal'] ?? '', 64);
  $filtros['sucursal'] = $s !== '' ? $s : '';

  if (isset($_POST['refrescar'])) {
    $filtros['refrescar'] = true;
  } else {
    unset($filtros['refrescar']);
  }

  $_SESSION[$filterKey] = $filtros;
  csrfRefresh();
  header('Location: cxc.php');
  exit;
}

  if (!empty($_GET) && isset($_GET['offset'])) {
  // Migrar offset de paginación a sesión para URL limpia
  $offsetGet = sanitizeInt($_GET['offset'] ?? 0, 0, PHP_INT_MAX);
  if ($offsetGet > 0) {
    $_SESSION[$filterKey]['offset'] = $offsetGet;
  } else {
    unset($_SESSION[$filterKey]['offset']);
  }
  header('Location: cxc.php');
  exit;
}

$filtros = $_SESSION[$filterKey] ?? [];
$startDate = $filtros['start_date'] ?? $inicioMes;
$endDate = $filtros['end_date'] ?? $finMes;
$busqueda = $filtros['q'] ?? '';
$sucursal = $filtros['sucursal'] ?? '';
$forzarRefresco = !empty($filtros['refrescar']);
$limit = 50;
$offset = sanitizeInt($_SESSION[$filterKey]['offset'] ?? 0, 0, PHP_INT_MAX);

// Consumir flag de refresco (una vez)
if ($forzarRefresco) {
  unset($_SESSION[$filterKey]['refrescar']);
}

if ($startDate === '') $startDate = $inicioMes;
if ($endDate === '') $endDate = $finMes;
if ($startDate > $endDate) {
  $tmp = $startDate;
  $startDate = $endDate;
  $endDate = $tmp;
}

// Consulta al API
// Usamos /range SIEMPRE para que el backend respete limit/offset
// Default: mes actual si no hay filtro explícito
$finMes = (new DateTime())->modify('last day of this month')->format('Y-m-d');

$inicio = microtime(true);

$params = http_build_query([
  'start_date' => $startDate,
  'end_date' => $endDate,
  'limit' => $limit,
  'offset' => $offset,
]);
$endpoint = "/api/{$codigoCliente}/cxc/range?{$params}";

$meta = ['total' => null, 'has_more' => false, 'next_offset' => null];
$datos = apiGet($endpoint, 60, $meta);

$tiempoCarga = round(microtime(true) - $inicio, 2);
$totalRegistros = $meta['total'] ?? null;
if ($totalRegistros === null) {
  $conteo = apiGet("/api/{$codigoCliente}/cxc/range/count?start_date=" . urlencode($startDate) . "&end_date=" . urlencode($endDate));
  $totalRegistros = $conteo['total'] ?? $conteo['count'] ?? 0;
}
$totalRegistros = (int)$totalRegistros;
$items = $datos['value'] ?? $datos['items'] ?? $datos['results'] ?? (is_array($datos) && empty($datos['error']) ? $datos : []);

// Sucursales disponibles desde los datos
$todasSucursales = [];
if (!empty($items) && is_array($items)) {
    $nombres = array_unique(array_filter(array_column($items, 'Sucursal')));
    sort($nombres);
    $todasSucursales = $nombres;
}
$hayError = !empty($datos['error']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CXC · Cuentas por Cobrar · Breinit DCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/modulo.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <link rel="stylesheet" href="../assets/css/tabla.css">
    <?php sidebarDetalleHeadLink(); ?>
    <style nonce="<?= cspStyleNonce() ?>">
        main { padding: 24px 40px; }
    </style>
</head>

<body>

<?php
$metaCxc = $codigoCliente . ' · ' . number_format((int)$totalRegistros) . ' registros · ' . $tiempoCarga . 's';
if ($busqueda !== '') $metaCxc .= ' · Búsqueda: "' . $busqueda . '"';
renderTopbar([
    'titulo'  => 'CXC · Cuentas por Cobrar',
    'volver'  => 'dashboard.php',
    'meta'    => $metaCxc,
    'usuario' => $usuario,
    'codigo_cliente' => $codigoCliente,
    'user_master'   => $usuario['user_master'] ?? '',
]);
?>

<main>

<form class="filter-bar" method="post">
  <?= csrfField() ?>
  <div>
    <label for="start_date">Desde</label>
    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
  </div>
  <div>
    <label for="end_date">Hasta</label>
    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
  </div>
  <input type="hidden" name="refrescar" value="1">
  <button type="submit">Filtrar</button>
  <a href="cxc.php" class="filter-clean">Limpiar filtros</a>
</form>

    <div class="barra-exportar">
        <button class="btn-exportar" id="btnExportar">
            <span class="material-symbols-outlined icon-btn">download</span> Exportar HTML
        </button>
    </div>

    <?php if ($hayError): ?>
        <div class="error">
            <strong>Error:</strong>
            <p><?= htmlspecialchars($datos['message'] ?? 'No se pudieron cargar los datos') ?></p>
        </div>
    <?php elseif (empty($items)): ?>
        <p class="empty-state">No hay registros de cuentas por cobrar para los filtros seleccionados.</p>
    <?php else:
        // Tipo por nombre de campo
        $tipoColumna = function ($campo) {
            if (preg_match('/(saldo|monto|importe|cargo|credito|costo|precio|venta)/i', $campo)) return 'moneda';
            if (preg_match('/^(dias_|dias_venc|exist|uds_)/i', $campo)) return 'numero';
            if (preg_match('/^date_key$|fecha_venc|fecha$/i', $campo) || $campo === 'Fecha') return 'fecha';
            return 'texto';
        };
        $formatear = function ($raw, $tipo) {
            if ($raw === null || $raw === '') return '—';
            switch ($tipo) {
                case 'moneda': return '$' . number_format((float)$raw, 2);
                case 'numero': return number_format((int)$raw, 0);
                case 'fecha':  return htmlspecialchars(implode('/', array_reverse(explode('-', (string)$raw))));
                default:       return htmlspecialchars((string)$raw);
            }
        };

        // Armar definiciones de columnas
        $colDefs = [];
        foreach ($COLUMNAS_CXC as $campo => $etiqueta) {
            $tipo = $tipoColumna($campo);
            $col = ['id' => $campo, 'label' => $etiqueta];
            if ($tipo === 'moneda') {
                $col['clase'] = 'num';
                $col['clase_campo'] = $campo . '_clase';
                $col['raw_id'] = $campo . '_raw';
            } elseif ($tipo === 'numero') {
                $col['clase'] = 'num';
            }
            $colDefs[] = $col;
        }

        // Pre-formatear filas
        $filasTabla = [];
        foreach ($items as $fila) {
            if (!is_array($fila)) continue;
            $ft = [];
            foreach ($COLUMNAS_CXC as $campo => $etiqueta) {
                $raw = $fila[$campo] ?? null;
                $tipo = $tipoColumna($campo);
                $ft[$campo] = $formatear($raw, $tipo);
                if ($tipo === 'moneda') {
                    if ($raw !== null && $raw !== '' && (float)$raw < 0) {
                        $ft[$campo . '_clase'] = 'monto-neg';
                    }
                    $ft[$campo . '_raw'] = $raw !== null && $raw !== '' ? (float)$raw : 0;
                }
            }
            $filasTabla[] = $ft;
        }

        renderTabla([
            'columnas' => $colDefs,
            'filas'    => $filasTabla,
            'vacio'    => 'No hay registros de cuentas por cobrar para los filtros seleccionados.',
        ]);
        ?>

<?php
$hasMore = $meta['has_more'] ?? ($offset + $limit < $totalRegistros);
$nextOffset = $meta['next_offset'] ?? ($offset + $limit);
$prevOffset = max(0, $offset - $limit);
$mostradosHasta = $hasMore ? $offset + $limit : max($totalRegistros, $offset + $limit);
?>
<div class="pagination">
<?php if ($offset > 0): ?>
<a href="cxc.php?offset=0">&laquo; Primera</a>
<a href="cxc.php?offset=<?= $prevOffset ?>">&larr; Anterior</a>
<?php endif; ?>
<span>Mostrando <?= $offset + 1 ?>–<?= number_format($mostradosHasta) ?> de <?= number_format($totalRegistros) ?></span>
<?php if ($hasMore): ?>
<a href="cxc.php?offset=<?= $nextOffset ?>">Siguiente &rarr;</a>
<?php endif; ?>
</div>
    <?php endif; ?>

</main>

    <script src="../assets/js/topbar.js"></script>
    <script nonce="<?= cspStyleNonce() ?>">
document.getElementById('btnExportar')?.addEventListener('click', function() {
    var win = window.open('', '_blank');
    if (!win) { alert('Permite ventanas emergentes para exportar.'); return; }
    var tables = document.querySelectorAll('table');
    if (!tables.length) return;
    var clone = tables[0].cloneNode(true);
    clone.querySelectorAll('[style]').forEach(function(el) { el.removeAttribute('style'); });
    win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>CXC - Exportaci\u00F3n</title>' +
        '<style>body{font-family:Inter,sans-serif;font-size:13px;padding:20px;color:#0b1c30;}' +
        'table{width:100%;border-collapse:collapse;}' +
        'th{background:#eff4ff;padding:8px 12px;text-align:left;font-size:11px;text-transform:uppercase;border:1px solid #c5c5d4;}' +
        'td{padding:8px 12px;border:1px solid #c5c5d4;}' +
        '.num{text-align:right;}' +
        '</style></head><body>');
    win.document.write(clone.outerHTML);
    win.document.write('</body></html>');
    win.document.close();
});
</script>
<?php renderSidebarDetalle('CXC — Detalle de registro'); ?>
</body>
</html>

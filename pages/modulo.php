<?php
/**
 * Visor genérico de módulos con whitelist de columnas.
 * Los módulos sin whitelist muestran solo conteo + aviso.
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
$codigoCliente = $usuario['codigo_cliente'] ?? null;

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code']);
    die('Acceso no permitido.');
}

$modulosSesion = $_SESSION['modulos'] ?? [];
$modulosPermitidos = [];
foreach ($modulosSesion as $m) {
    if (is_array($m) && !empty($m['slug']) && is_string($m['slug'])) {
        $modulosPermitidos[$m['slug']] = $m['label'] ?? $m['slug'];
    }
}

$modulo = sanitizeString($_GET['modulo'] ?? '', 64);

if ($modulo === '' || !array_key_exists($modulo, $modulosPermitidos)) {
    http_response_code(404);
    logSecurityEvent('module_not_found_or_unauthorized', ['module' => $modulo]);
    die('Módulo no válido para tu cliente. Vuelve al <a href="dashboard.php">inicio</a>.');
}

$tituloModulo = $modulosPermitidos[$modulo];

// ─── Redirigir a páginas dedicadas si existen ──────────────────
$paginasDedicadas = [
    'cxc'                => 'cxc.php',
    'objetivos-servicio' => 'objetivos_servicio.php',
];
if (isset($paginasDedicadas[$modulo])) {
    header('Location: ' . $paginasDedicadas[$modulo]);
    exit;
}

// Columnas permitidas por módulo (clave=campo API, valor=etiqueta)
$MODULOS_COLUMNAS = [

    'industria' => [
        'date_key'                => 'Fecha',
        'Company'                 => 'Compañía',
        'Compania'                => 'Compañía',
        'Servidor'                => 'Servidor',
        'Marca'                   => 'Marca',
        'Tipo_de_Vehiculo'        => 'Tipo Vehículo',
        'Origen'                  => 'Origen',
        'Unidades'                => 'Unidades',
        'Unidades_Totales'        => 'Unidades Totales',
        'Segmento_INEGI'          => 'Segmento INEGI',
    ],

    'inventario-autos' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Compania'                => 'Compañía',
        'Companias'               => 'Compañía',
        'Servidor'                => 'Servidor',
        'Marca'                   => 'Marca',
        'Version'                 => 'Modelo',
        'Ano_Modelo'              => 'Año',
        'Color'                   => 'Color',
        'Color_Exterior'          => 'Color',
        'VIN'                     => 'VIN',
        'Num_Inventario'          => 'No. Inventario',
        'Nuevo_Usado'             => 'Nuevo/Usado',
        'NuevoUsado'              => 'Nuevo/Usado',
        'Tipo_de_Vehiculo'        => 'Tipo',
        'Precio_Venta'            => 'Precio Venta',
        'Precio_de_Venta'         => 'Precio Venta',
        'IM_Precio_Vta_Prom'      => 'Precio Prom',
        'Inventario'              => 'Inventario',
        'Exist'                   => 'Existencia',
        'Dias_en_Inv'             => 'Días Inventario',
        'Kilometraje'             => 'Kilometraje',
        'Ubicacion_Veh'           => 'Ubicación',
        'Ubicacion'               => 'Ubicación',
        'Separado'                => 'Separado',
    ],

    'inventario-refacciones' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Compania'                => 'Compañía',
        'Companias'               => 'Compañía',
        'Servidor'                => 'Servidor',
        'Almacen'                 => 'Almacén',
        'Refaccion'               => 'Código',
        'Refaccion_Descripcion'   => 'Descripción',
        'Clasificacion_Codigo'    => 'Clasificación',
        'Clasificacion_de_Inventario' => 'Clasificación',
        'Clasificacion_Descripcion'   => 'Clasificación',
        'Existencia'              => 'Existencia',
        'Costo_Unitario'          => 'Costo Unitario',
        'Costo_Inventario'        => 'Costo Total',
        'Costo_Inventario_MN'     => 'Costo Total',
        'Ubicacion_Almacen'       => 'Ubicación',
        'Meses_Antiguedad'        => 'Antigüedad (meses)',
        'Grupo_Inventario'        => 'Grupo',
    ],

    'objetivo-autos' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Compania'                => 'Compañía',
        'Companias'               => 'Compañía',
        'Company'                 => 'Compañía',
        'Servidor'                => 'Servidor',
        'Asesor'                  => 'Asesor',
        'Marca'                   => 'Marca',
        'Nuevo_Usado'             => 'Nuevo/Usado',
        'NuevoUsado'              => 'Nuevo/Usado',
        'Tipo_de_Vehiculo'        => 'Tipo',
        'Tipo_de_Venta'           => 'Tipo Venta',
        'Obj_Vta_Uds_al_Dia'      => 'Obj. Uds/Día',
        'Obj_Vta_Uds_Mensual'     => 'Obj. Uds/Mes',
        'Obj_Venta_Dia'           => 'Obj. Venta/Día',
        'Obj_Venta_Mes'           => 'Obj. Venta/Mes',
        'Obj_Utilidad_Bruta_Dia'  => 'Obj. Utilidad/Día',
        'Obj_Utilidad_Bruta_Mes'  => 'Obj. Utilidad/Mes',
        'Obj_Precio_Promedio_x_Unidad' => 'Obj. Precio Prom.',
        'Obj_Utilidad_Bruta_pct_sa'    => 'Obj. Utilidad %',
        'Obj_Meses_Vta_en_Inv'    => 'Obj. Meses Vta/Inv',
    ],

    'ventas-autos' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Compania'                => 'Compañía',
        'Companias'               => 'Compañía',
        'Company'                 => 'Compañía',
        'Servidor'                => 'Servidor',
        'Clientes'                => 'Cliente',
        'Clientes_Descripcion'    => 'Cliente',
        'Usuario'                 => 'Vendedor',
        'Marca'                   => 'Marca',
        'Version'                 => 'Modelo',
        'Ano_Modelo'              => 'Año',
        'Color'                   => 'Color',
        'Color_Exterior'          => 'Color',
        'VIN'                     => 'VIN',
        'Nuevo_Usado'             => 'Nuevo/Usado',
        'NuevoUsado'              => 'Nuevo/Usado',
        'Tipo_de_Vehiculo'        => 'Tipo',
        'Tipo_de_Venta'           => 'Tipo Venta',
        'Credito_Contado'         => 'Créd/Contado',
        'Importe_Factura'         => 'Importe Factura',
        'Vta_Neta'                => 'Venta Neta',
        'Costo_Bruto'             => 'Costo Bruto',
        'Uds_Vendidas'            => 'Uds Vendidas',
        'Uds_Canceladas'          => 'Uds Canceladas',
        'Banco_Financiera'        => 'Banco/Financiera',
        'Fecha_Venta'             => 'Fecha Venta',
        'Dias_de_Inventario'      => 'Días Inventario',
    ],

    'venta-servicio-refacciones' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Compania'                => 'Compañía',
        'Companias'               => 'Compañía',
        'Company'                 => 'Compañía',
        'Servidor'                => 'Servidor',
        'Clientes'                => 'Cliente',
        'Clientes_Descripcion'    => 'Cliente',
        'Asesor'                  => 'Asesor',
        'Marca'                   => 'Marca',
        'VIN'                     => 'VIN',
        'Refaccion'               => 'Refacción',
        'Refaccion_Descripcion'   => 'Refacción Desc.',
        'Tipo_Venta_Serv_Most'    => 'Tipo Venta',
        'Tipo_Orden'              => 'Tipo Orden',
        'Venta'                   => 'Venta',
        'Importe_Bruto'           => 'Importe Bruto',
        'Costo_Neto'              => 'Costo Neto',
        'Venta_Unidades'          => 'Uds Venta',
        'Venta_x_Facturar'        => 'Vta x Facturar',
        'Folio_Factura'           => 'Folio Factura',
        'Folio_Orden_Reparacion'  => 'Folio OR',
        'Kilometraje'             => 'Kilometraje',
        'Dias_en_Taller'          => 'Días Taller',
        'ROI_Real'                => 'ROI Real',
    ],

    'finanzas' => [
        'date_key'                => 'Fecha',
        'Sucursal'                => 'Sucursal',
        'Companias'               => 'Compañía',
        'Servidor'                => 'Servidor',
        'Concepto'                => 'Concepto',
        'Subconcepto'             => 'Subconcepto',
        'Area_de_Negocio'         => 'Área Negocio',
        'Departamento'            => 'Departamento',
        'Cargos_Mes'              => 'Cargos del Mes',
        'Creditos_Mes'            => 'Créditos del Mes',
        'Saldo_Inicial'           => 'Saldo Inicial',
        'Saldo_Final'             => 'Saldo Final',
        'Gasto'                   => 'Gasto',
        'Reporte'                 => 'Reporte',
        'Reporte_Descripcion'     => 'Reporte Desc.',
    ],
];

$columnasPermitidas = $MODULOS_COLUMNAS[$modulo] ?? [];

// Sin whitelist → solo conteo
if (empty($columnasPermitidas)) {
    $conteo = apiGet("/api/{$codigoCliente}/{$modulo}/count");
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars($tituloModulo) ?> · Breinit DCA</title>
        <link rel="stylesheet" href="../assets/css/modulo.css">
        <link rel="stylesheet" href="../assets/css/topbar.css">
    </head>
    <body>
        <?php renderTopbar([
            'titulo'  => $tituloModulo,
            'volver'  => 'dashboard.php',
            'codigo_cliente' => $codigoCliente,
        ]); ?>
        <main>
            <?php if (!empty($conteo['error'])): ?>
                <div class="error">
                    <strong>No se pudo cargar el módulo.</strong>
                    <p>Por favor intenta de nuevo más tarde o contacta a soporte.</p>
                </div>
            <?php else: ?>
                <p>Total de registros: <span class="badge"><?= htmlspecialchars((string)($conteo['count'] ?? $conteo['total'] ?? '—')) ?></span></p>
                <p class="aviso">La vista detallada del módulo está siendo adecuada y estará disponible próximamente.</p>
            <?php endif; ?>
        </main>
    </body>
    </html>
    <?php
    exit;
}

// Paginación
$limit     = sanitizeInt($_GET['limit'] ?? 100, 1, 500);
$offset    = sanitizeInt($_GET['offset'] ?? 0, 0, PHP_INT_MAX);
$page      = sanitizeInt($_GET['page'] ?? 1, 1, PHP_INT_MAX);
$noAutoDemo = ($modulo === 'inventario-autos' && !empty($_GET['no_auto_demo']));

// Consulta al API
$inicio = microtime(true);

if ($noAutoDemo) {
    // Endpoint filtrado: sin autos demo, paginación por página
    $endpointDatos = "/api/{$codigoCliente}/{$modulo}/no-auto-demo?page={$page}&page_size={$limit}";
    $datos = apiGet($endpointDatos, 60);
    $tiempoCarga = round(microtime(true) - $inicio, 2);
    $items = $datos['data'] ?? [];
    $totalRegistros = (int)($datos['total'] ?? 0);
    $totalPages = (int)($datos['total_pages'] ?? 0);
    $meta = ['total' => $totalRegistros, 'has_more' => $page < $totalPages, 'next_offset' => null];
} else {
    $endpointDatos = "/api/{$codigoCliente}/{$modulo}/?limit={$limit}&offset={$offset}";
    $meta = ['total' => null, 'has_more' => false, 'next_offset' => null];
    $datos = apiGet($endpointDatos, 60, $meta);
    $tiempoCarga = round(microtime(true) - $inicio, 2);
    $totalRegistros = $meta['total'] ?? null;
    if ($totalRegistros === null) {
        $conteo = apiGet("/api/{$codigoCliente}/{$modulo}/count");
        $totalRegistros = $conteo['total'] ?? $conteo['count'] ?? 0;
    }
    $totalRegistros = (int)$totalRegistros;
    $items = $datos['value'] ?? $datos['items'] ?? $datos['results'] ?? (is_array($datos) && empty($datos['error']) ? $datos : []);
    $totalPages = 1;
}

$hayError = !empty($datos['error']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloModulo) ?> · Breinit DCA</title>
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
    <?php renderTopbar([
        'titulo'  => $tituloModulo,
        'volver'  => 'dashboard.php',
        'meta'    => $codigoCliente . ' · ' . number_format((int)$totalRegistros) . ' registros · ' . $tiempoCarga . 's',
        'usuario' => $usuario,
        'codigo_cliente' => $codigoCliente,
        'user_master'   => $usuario['user_master'] ?? '',
    ]); ?>

    <main>
        <div class="meta-info meta-info-lg">
            <span class="material-symbols-outlined icon-inline">info</span>
            Mostrando hasta <?= $limit ?> registros por página.
            <?php if ($modulo === 'inventario-autos'): ?>
                <span class="toggle-demo">
                    <?php if ($noAutoDemo): ?>
                        <a href="?modulo=<?= urlencode($modulo) ?>&limit=<?= $limit ?>" class="toggle-demo-link">Mostrar todos (con autos demo)</a>
                        <span class="toggle-demo-active">· Filtro: Sin autos demo activo</span>
                    <?php else: ?>
                        <a href="?modulo=<?= urlencode($modulo) ?>&limit=<?= $limit ?>&no_auto_demo=1" class="toggle-demo-link">Filtrar sin autos demo</a>
                    <?php endif; ?>
                </span>
            <?php endif; ?>
        </div>

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
        <?php elseif (empty($items) || !is_array($items)): ?>
            <p class="empty-state">No hay registros para los filtros seleccionados.</p>
        <?php else:
            // Columnas existentes en los datos
            $primerItem = is_array($items[0] ?? null) ? $items[0] : [];
            $columnasDisponibles = [];
            $etiquetas = [];
            foreach ($columnasPermitidas as $campo => $etiqueta) {
                if (array_key_exists($campo, $primerItem)) {
                    $columnasDisponibles[] = $campo;
                    $etiquetas[$campo] = $etiqueta;
                }
            }

            if (empty($columnasDisponibles)): ?>
                <p class="empty-state">No se encontraron columnas configuradas para este módulo. Contacta al administrador para ajustar la whitelist.</p>
            <?php else:
                // Tipo por nombre de campo
                $tipoColumna = function ($campo) {
                    $cl = strtolower($campo);
                    if (preg_match('/(precio|importe|venta|vta|costo|cargo|credito|saldo|ingreso|egreso|monto|utilidad)/i', $campo)
                        && !preg_match('/^ud|^cantidad|^exist|unidades?$/i', $campo)
                        && !str_contains($cl, 'uds_')
                        && !str_contains($cl, 'pct')
                        && !str_contains($cl, 'porc')) return 'moneda';
                    if (preg_match('/(pct|porc|roi_real|margen_pct|alcance_pct)/i', $campo)
                        || preg_match('/^alcance_ritmo|^alcance_objetivo/', $campo)
                        || preg_match('/(_pct$|_porcentaje$)/i', $campo)) return 'pct';
                    if (preg_match('/^(unidades?|exist|stock|cantidad|uds_vendidas|uds_canceladas|venta_unidades|inventario|dias_|obj_uds|obj_vta_uds|obj_utilidad_bruta_pct)/i', $campo)
                        || preg_match('/^(unidades?|existencia|exist|stock|cantidad|inventario|dias_)/i', $campo)
                        || preg_match('/^uds_/', $campo)) return 'numero';
                    return 'texto';
                };
                $formatear = function ($raw, $tipo) {
                    if ($raw === null || $raw === '') return '—';
                    switch ($tipo) {
                        case 'moneda': return '$' . number_format((float)$raw, 2);
                        case 'pct':    return number_format((float)$raw, 1) . '%';
                        case 'numero': return number_format((int)$raw, 0);
                        case 'fecha':  return htmlspecialchars(implode('/', array_reverse(explode('-', (string)$raw))));
                        default:       return htmlspecialchars((string)$raw);
                    }
                };

                // Armar definiciones de columnas
                $colDefs = [];
                foreach ($columnasDisponibles as $campo) {
                    $tipo = $tipoColumna($campo);
                    $col = ['id' => $campo, 'label' => $etiquetas[$campo]];
                    if ($tipo === 'moneda') {
                        $col['clase'] = 'num';
                        $col['clase_campo'] = $campo . '_clase';
                        $col['raw_id'] = $campo . '_raw';
                    } elseif ($tipo === 'numero' || $tipo === 'pct') {
                        $col['clase'] = 'num';
                    }
                    $colDefs[] = $col;
                }

                // Pre-formatear filas
                $filasTabla = [];
                foreach ($items as $fila) {
                    if (!is_array($fila)) continue;
                    $ft = [];
                    foreach ($columnasDisponibles as $campo) {
                        $raw = $fila[$campo] ?? null;
                        $tipo = $tipoColumna($campo);
                        // Fecha: detectar por valor si no se detectó por nombre
                        if ($tipo === 'texto' && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$raw)) {
                            $tipo = 'fecha';
                        }
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
                    'vacio'    => 'No hay registros para los filtros seleccionados.',
                ]);
                ?>

            <?php if ($noAutoDemo): ?>
            <?php
                $qsBase = http_build_query([
                    'modulo' => $modulo,
                    'limit'  => $limit,
                    'no_auto_demo' => 1,
                ]);
                $from = ($page - 1) * $limit + 1;
                $to = min($page * $limit, $totalRegistros);
            ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&page=1">&laquo; Primera</a>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&page=<?= $page - 1 ?>">&larr; Anterior</a>
                <?php endif; ?>
                <span>Página <?= $page ?> de <?= max($totalPages, 1) ?> · Mostrando <?= number_format($from) ?>–<?= number_format($to) ?> de <?= number_format($totalRegistros) ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&page=<?= $page + 1 ?>">Siguiente &rarr;</a>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&page=<?= $totalPages ?>">&Uacute;ltima &raquo;</a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <?php
                $hasMore = $meta['has_more'] ?? ($offset + $limit < $totalRegistros);
                $nextOffset = $meta['next_offset'] ?? ($offset + $limit);
                $prevOffset = max(0, $offset - $limit);
                $qsBase = http_build_query([
                    'modulo' => $modulo,
                    'limit'  => $limit,
                ]);
                $mostradosHasta = $hasMore ? $offset + $limit : max($totalRegistros, $offset + $limit);
            ?>
            <div class="pagination">
                <?php if ($offset > 0): ?>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&offset=0">&laquo; Primera</a>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&offset=<?= $prevOffset ?>">&larr; Anterior</a>
                <?php endif; ?>
                <span>Mostrando <?= $offset + 1 ?>–<?= number_format($mostradosHasta) ?> de <?= number_format($totalRegistros) ?></span>
                <?php if ($hasMore): ?>
                    <a href="?<?= htmlspecialchars($qsBase) ?>&offset=<?= $nextOffset ?>">Siguiente &rarr;</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <script nonce="<?= cspStyleNonce() ?>">
    document.getElementById('btnExportar')?.addEventListener('click', function() {
        var win = window.open('', '_blank');
        if (!win) { alert('Permite ventanas emergentes para exportar.'); return; }
        var tables = document.querySelectorAll('table');
        if (!tables.length) return;
        var clone = tables[0].cloneNode(true);
        clone.querySelectorAll('[style]').forEach(function(el) { el.removeAttribute('style'); });
        win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Exportaci\u00F3n</title>' +
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
<?php renderSidebarDetalle($tituloModulo . ' — Detalle'); ?>
</body>
</html>

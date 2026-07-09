<?php
set_time_limit(120);
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

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code']);
    die('Acceso no permitido.');
}

$hoy = new DateTime();
$inicioMes = (new DateTime())->modify('first day of last month')->format('Y-m-d');
$finMes = $hoy->format('Y-m-d');

$startDate = sanitizeDate($_GET['start_date'] ?? $inicioMes);
$endDate   = sanitizeDate($_GET['end_date'] ?? $finMes);
$limit     = sanitizeInt($_GET['limit'] ?? 50, 1, 200);
$offset    = sanitizeInt($_GET['offset'] ?? 0, 0, PHP_INT_MAX);

// Fecha inválida → default
if ($startDate === '') $startDate = $inicioMes;
if ($endDate === '') $endDate = $finMes;

$inicio = microtime(true);

$endpoint = "/api/{$codigoCliente}/objetivos-servicio/range"
    . "?start_date=" . urlencode($startDate)
    . "&end_date=" . urlencode($endDate)
    . "&limit={$limit}&offset={$offset}";
$datos = apiGet($endpoint);

$conteo = apiGet("/api/{$codigoCliente}/objetivos-servicio/range/count"
    . "?start_date=" . urlencode($startDate)
    . "&end_date=" . urlencode($endDate));

$tiempoCarga = round(microtime(true) - $inicio, 2);
$totalRegistros = $conteo['total'] ?? $conteo['count'] ?? 0;
$items = $datos['value'] ?? $datos['items'] ?? $datos['results'] ?? (is_array($datos) && empty($datos['error']) ? $datos : []);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objetivos Servicio · Breinit DCA</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/objetivos_servicio.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <?php sidebarDetalleHeadLink(); ?>
</head>

<body>

    <?php renderTopbar([
        'titulo'  => 'Objetivos Servicio',
        'volver'  => 'dashboard.php',
        'meta'    => $codigoCliente . ' · ' . number_format((int)$totalRegistros) . ' registros · ' . $tiempoCarga . 's',
        'usuario' => $usuario,
        'codigo_cliente' => $codigoCliente,
        'user_master'   => $usuario['user_master'] ?? '',
    ]); ?>

    <div class="main">

        <form class="filter-bar" method="get">
            <label>Desde</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            <label>Hasta</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            <input type="hidden" name="limit" value="<?= $limit ?>">
            <button type="submit">Filtrar</button>
            <span class="badge"><?= number_format((int)$totalRegistros) ?> registros</span>
        </form>

        <?php if (!empty($datos['error'])): ?>
            <div class="error-box">
                <strong>Error:</strong> <?= htmlspecialchars($datos['message'] ?? 'No se pudieron cargar los datos') ?>
            </div>
        <?php elseif (empty($items)): ?>
            <p class="empty-state">No hay registros para el rango seleccionado.</p>
        <?php else: ?>
            <div class="scroll-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Compañía</th>
                            <th>Sucursal</th>
                            <th>Asesor</th>
                            <th>Tipo Venta</th>
                            <th>Sub Tipo</th>
                            <th>Órdenes Reparación</th>
                            <th>Vta del Día</th>
                            <th>Utilidad %</th>
                            <th>Utilidad Día</th>
                            <th>Utilidad Mes</th>
                            <th>Vta Mes</th>
                            <th>Órd. Rep. al Día</th>
                            <th>Ticket Prom Taller</th>
                            <th>Ticket Prom HyP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $fila): ?>
                            <tr>
                                <td><?= htmlspecialchars($fila['date_key'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($fila['Compania'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($fila['Sucursal'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($fila['Asesor'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($fila['Tipo_Venta_Serv_Most'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($fila['Sub_Tipo_Venta'] ?? '—') ?></td>
                                <td class="num"><?= htmlspecialchars($fila['Obj_Ordenes_Reparacion'] ?? '—') ?></td>
                                <td class="num"><?= isset($fila['Obj_Vta_al_Dia']) ? '$' . number_format((float)$fila['Obj_Vta_al_Dia'], 0) : '—' ?></td>
                                <td class="num"><?= isset($fila['Obj_Utilidad_pct']) ? number_format((float)$fila['Obj_Utilidad_pct'], 1) . '%' : '—' ?></td>
                                <td class="num"><?= isset($fila['Obj_Utilidad_al_Dia']) ? '$' . number_format((float)$fila['Obj_Utilidad_al_Dia'], 0) : '—' ?></td>
                                <td class="num"><?= isset($fila['Obj_Utilidad_Mes']) ? '$' . number_format((float)$fila['Obj_Utilidad_Mes'], 0) : '—' ?></td>
                                <td class="num"><?= isset($fila['Obj_Vta_Mes']) ? '$' . number_format((float)$fila['Obj_Vta_Mes'], 0) : '—' ?></td>
                                <td class="num"><?= htmlspecialchars($fila['Obj_Ordenes_Reparacion_al_Dia'] ?? '—') ?></td>
                                <td class="num"><?= isset($fila['Ticket_Prom_Objetivo_Taller']) ? '$' . number_format((float)$fila['Ticket_Prom_Objetivo_Taller'], 0) : '—' ?></td>
                                <td class="num"><?= isset($fila['Ticket_Prom_Objetivo_HyP']) ? '$' . number_format((float)$fila['Ticket_Prom_Objetivo_HyP'], 0) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php
            $prevOffset = max(0, $offset - $limit);
            $nextOffset = $offset + $limit;
            $qs = http_build_query(['start_date' => $startDate, 'end_date' => $endDate, 'limit' => $limit]);
            ?>
            <div class="pagination">
                <?php if ($offset > 0): ?>
                    <a href="?<?= htmlspecialchars($qs) ?>&offset=0">&laquo; Primera</a>
                    <a href="?<?= htmlspecialchars($qs) ?>&offset=<?= $prevOffset ?>">&larr; Anterior</a>
                <?php endif; ?>
                <span>Mostrando <?= $offset + 1 ?>–<?= min($offset + $limit, (int)$totalRegistros) ?> de <?= number_format((int)$totalRegistros) ?></span>
                <?php if ($offset + $limit < (int)$totalRegistros): ?>
                    <a href="?<?= htmlspecialchars($qs) ?>&offset=<?= $nextOffset ?>">Siguiente &rarr;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
    <script src="../assets/js/topbar.js"></script>
<?php renderSidebarDetalle('Objetivos de Servicio — Detalle'); ?>
</body>

</html>

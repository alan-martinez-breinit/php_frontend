<?php
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
$codigoCliente = $usuario['codigo_cliente'] ?? null;

if (!$codigoCliente || !preg_match('/^[A-Za-z0-9_-]+$/', $codigoCliente)) {
    http_response_code(403);
    logSecurityEvent('access_denied', ['reason' => 'missing_or_invalid_client_code', 'page' => 'dashboard']);
    die('Acceso no permitido.');
}

$modulos = $_SESSION['modulos'] ?? [];
$modulosPorSlug = [];

/**
 * Devuelve la URL de un módulo según su slug.
 * Los módulos con página propia (cxc, objetivos-servicio) tienen URL fija;
 * el resto usa modulo.php con el slug como parámetro.
 */
function moduloUrl(string $slug): string
{
    return match ($slug) {
        'objetivos-servicio' => 'objetivos_servicio.php',
        'cxc'                => 'cxc.php',
        default              => 'modulo.php?modulo=' . urlencode($slug),
    };
}
foreach ($modulos as $m) {
    $modulosPorSlug[$m['slug']] = $m;
}

$preferidos = ['cxc', 'ventas-autos', 'inventario-autos'];
$kpiSlugs = array_values(array_intersect($preferidos, array_keys($modulosPorSlug)));
foreach ($modulosPorSlug as $slug => $info) {
    if (count($kpiSlugs) >= 3) break;
    if (!in_array($slug, $kpiSlugs, true)) $kpiSlugs[] = $slug;
}

$conteos = [];
foreach ($kpiSlugs as $slug) {
    $conteos[$slug] = $codigoCliente ? apiGet("/api/{$codigoCliente}/{$slug}/count") : ['error' => true];
}

$getCount = function ($resp) {
    if (is_array($resp) && !empty($resp['error'])) return null;
    if (is_int($resp) || is_float($resp)) return (int)$resp;
    if (is_array($resp)) {
        if (isset($resp['count'])) return (int)$resp['count'];
        if (isset($resp['total'])) return (int)$resp['total'];
        if (isset($resp['data']) && (is_int($resp['data']) || is_float($resp['data']))) return (int)$resp['data'];
    }
    return null;
};

$valoresKpi = [];
foreach ($kpiSlugs as $slug) {
    $valoresKpi[$slug] = $getCount($conteos[$slug] ?? null);
}

$moduloReciente = in_array('ventas-autos', $kpiSlugs, true) ? 'ventas-autos' : ($kpiSlugs[0] ?? null);
$filasRecientes = [];
if ($codigoCliente && $moduloReciente) {
    $recientes = apiGet("/api/{$codigoCliente}/{$moduloReciente}/?limit=5");
    $filasRecientes = $recientes['value'] ?? $recientes['items'] ?? $recientes['results'] ?? (is_array($recientes) && empty($recientes['error']) ? $recientes : []);
}

$valores = array_values($valoresKpi);
$total = max(1, array_sum(array_map(fn($v) => (int)($v ?? 0), $valores)));
$p1 = round((($valores[0] ?? 0) / $total) * 100);
$p2 = round((($valores[1] ?? 0) / $total) * 100);
$p3 = max(0, 100 - $p1 - $p2);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>DCA - Desarrollo | Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Hanken+Grotesk:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/topbar.css">
    <?php sidebarDetalleHeadLink(); ?>
    <?php
    $donutP1 = $p1;
    $donutP2 = $p1 + $p2;
    $donutBg = ".donut-chart{background:conic-gradient(var(--primary-container) 0% {$donutP1}%,var(--secondary) {$donutP1}% {$donutP2}%,#cbdbf5 {$donutP2}% 100%)}";
    ?>
    <style nonce="<?= cspStyleNonce() ?>">
        <?= $donutBg ?>
    </style>
</head>

<body>
    <aside class="sidebar">
        <div class="sidebar-brand">DCA - Desarrollo</div>
        <nav class="sidebar-nav">
            <a class="nav-link nav-active" href="dashboard.php">
                <span class="material-symbols-outlined nav-icon icon-filled">dashboard</span>
                <span>Dashboard</span>
            </a>
            <a class="nav-link" href="one_page.php">
                <span class="material-symbols-outlined nav-icon">insights</span>
                <span>One Page</span>
            </a>
            <a class="nav-link" href="one_page_taller.php">
                <span class="material-symbols-outlined nav-icon">build</span>
                <span>Taller</span>
            </a>
            <a class="nav-link" href="hp.php">
                <span class="material-symbols-outlined nav-icon">brush</span>
                <span>H&amp;P</span>
            </a>
            <a class="nav-link" href="refacciones.php">
                <span class="material-symbols-outlined nav-icon">inventory_2</span>
                <span>REFACCIONES</span>
            </a>
            <?php foreach ($modulos as $m):
                $url = moduloUrl($m['slug']);
            ?>
                <a class="nav-link" href="<?= htmlspecialchars($url) ?>">
                    <span class="material-symbols-outlined nav-icon"><?= htmlspecialchars($m['icon']) ?></span>
                    <span><?= htmlspecialchars($m['label']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-footer">
            <a class="nav-link nav-logout" href="../auth/logout.php">
                <span class="material-symbols-outlined nav-icon">logout</span>
                <span>Cerrar sesión</span>
            </a>
        </div>
    </aside>

    <?php renderTopbar([
        'modo'     => 'dashboard',
        'clase'    => 'topbar-con-sidebar',
        'buscar'   => 'Buscar en los módulos...',
        'usuario'  => $usuario,
        'codigo_cliente' => $codigoCliente,
        'user_master'    => $usuario['user_master'] ?? '',
    ]); ?>

    <main class="main-content">
        <div class="main-inner">
            <div class="page-header">
                <h2 class="page-title">Vista General</h2>
                <p class="page-subtitle">Bienvenido de nuevo, <?= htmlspecialchars($usuario['name']) ?>. </p>
            </div>

            <div class="dashboard-grid">
                <!-- KPIs -->
                <div class="kpi-row">
                    <?php foreach ($kpiSlugs as $slug): $info = $modulosPorSlug[$slug];
                        $valor = $valoresKpi[$slug]; ?>
                        <div class="kpi-card">
                            <div class="kpi-icon-wrap">
                                <span class="material-symbols-outlined kpi-icon"><?= htmlspecialchars($info['icon']) ?></span>
                            </div>
                            <p class="kpi-label"><?= htmlspecialchars($info['label']) ?></p>
                            <h3 class="kpi-value"><?= $valor !== null ? htmlspecialchars((string)$valor) : '—' ?></h3>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Backend Info Card -->
                <div class="backend-card">
                    <div class="backend-body">
                        <span class="backend-badge">Backend</span>
                        <h4 class="backend-title">Breinit DCA API</h4>
                        <p class="backend-text">
                            Consumiendo <?= htmlspecialchars(parse_url(FASTAPI_BASE_URL, PHP_URL_HOST) ?? '') ?> vía el gateway FastAPI local.
                            Cliente <?= htmlspecialchars($codigoCliente ?? '—') ?> · <?= count($modulos) ?> módulos disponibles.
                        </p>
                    </div>
                    <span class="material-symbols-outlined backend-deco icon-filled">cloud_done</span>
                </div>

                <!-- Tabla Recientes -->
                <div class="recent-card">
                    <div class="recent-header">
                        <h3 class="recent-title"><?= htmlspecialchars($modulosPorSlug[$moduloReciente]['label'] ?? 'Actividad') ?> Recientes</h3>
                        <?php if ($moduloReciente): ?>
                            <a class="recent-link" href="<?= htmlspecialchars(moduloUrl($moduloReciente)) ?>">Ver Todo</a>
                        <?php endif; ?>
                    </div>
                    <div class="table-wrap">
                        <?php if (empty($filasRecientes) || !is_array($filasRecientes) || !is_array(reset($filasRecientes) ?: null)): ?>
                            <p class="empty-msg">No hay datos disponibles todavía (revisa que el token en fastapi_app/.env sea válido).</p>
                        <?php else: $columnas = array_slice(array_keys(reset($filasRecientes)), 0, 4); ?>
                            <table class="data-table">
                                <thead>
                                    <tr><?php foreach ($columnas as $col): ?><th><?= htmlspecialchars($col) ?></th><?php endforeach; ?></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filasRecientes as $fila): ?>
                                        <tr>
                                            <?php foreach ($columnas as $col): ?>
                                                <td><?= htmlspecialchars(is_scalar($fila[$col] ?? '') ? (string)$fila[$col] : json_encode($fila[$col])) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Donut Distribución -->
                <div class="donut-card">
                    <div class="donut-header">
                        <h3 class="donut-title">Distribución de Registros</h3>
                        <p class="donut-subtitle"><?= htmlspecialchars(implode(' / ', array_map(fn($s) => $modulosPorSlug[$s]['label'] ?? $s, $kpiSlugs))) ?></p>
                    </div>
                    <div class="donut-wrap">
                        <div class="donut-chart" data-p1="<?= $p1 ?>" data-p2="<?= $p2 ?>">
                            <div class="donut-inner">
                                <span class="donut-total"><?= $total ?></span>
                                <span class="donut-label">Total</span>
                            </div>
                        </div>
                    </div>
                    <div class="donut-legend">
                        <?php foreach ($kpiSlugs as $s): ?>
                            <div class="legend-item">
                                <div class="legend-dot"></div>
                                <span><?= htmlspecialchars($modulosPorSlug[$s]['label'] ?? $s) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="../assets/js/topbar.js"></script>
    <?php renderSidebarDetalle('Panel — Detalle'); ?>
</body>

</html>
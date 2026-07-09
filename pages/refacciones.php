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

$mesesDisponibles = [];
$mesActivo = null;
$todasCompanias = [];
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
                'subtitulo' => '',
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
                                <option value="<?= htmlspecialchars($ym) ?>" <?= $ym === $mesActivo ? 'selected' : '' ?>><?= htmlspecialchars($ym) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="boton" type="submit" name="refrescar" value="1">&#8635; Actualizar datos</button>
                </form>

                <div class="barra-exportar">
                    <button class="btn-exportar" id="btnExportar">
                        <span class="material-symbols-outlined icon-sm">download</span> Exportar HTML
                    </button>
                    <button class="btn-experto" id="btnExperto">
                        <span class="material-symbols-outlined icon-sm">psychology</span> Experto
                    </button>
                </div>

                <div class="empty-state">
                    <span class="material-symbols-outlined" style="font-size:48px;color:#c5c5d4;margin-bottom:16px;">inventory_2</span>
                    <h2 style="font-family:'Hanken Grotesk',sans-serif;font-size:22px;color:#444652;margin-bottom:8px;">REFACCIONES</h2>
                    <p style="color:#757683;font-size:14px;">Módulo en preparación. Próximamente estará disponible.</p>
                </div>
            </main>
        </div>
    </div>

    <script src="../assets/js/topbar.js"></script>
    <script nonce="<?= cspStyleNonce() ?>">
        document.getElementById('btnExportar')?.addEventListener('click', function() {
            var win = window.open('', '_blank');
            if (!win) { alert('Permite ventanas emergentes para exportar.'); return; }
            var tables = document.querySelectorAll('table');
            if (!tables.length) return;
            var clone = tables[0].cloneNode(true);
            clone.querySelectorAll('[style]').forEach(function(el) { el.removeAttribute('style'); });
            win.document.write('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>REFACCIONES - Exportaci\u00F3n</title>' +
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

        document.getElementById('btnExperto')?.addEventListener('click', function() {
            alert('Modo experto: próximamente disponible.');
        });
    </script>
</body>

</html>

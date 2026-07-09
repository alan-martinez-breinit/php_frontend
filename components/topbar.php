<?php
function renderTopbar(array $params): void
{
    $titulo     = $params['titulo'] ?? '';
    $subtitulo  = $params['subtitulo'] ?? '';
    $meta       = $params['meta'] ?? '';
    $menu       = !empty($params['menu']);
    $menuId     = $params['menu_id'] ?? 'btn-hamburguesa';
    $volver     = $params['volver'] ?? '';
    $busqueda   = $params['busqueda'] ?? false;
    $usuario    = $params['usuario'] ?? null;
    $codigoCliente = $params['codigo_cliente'] ?? '';
    $userMaster = $params['user_master'] ?? '';
    $logo       = $params['logo'] ?? true;
    $slotIzq    = $params['slot_izquierda'] ?? null;
    $slotDer    = $params['slot_derecha'] ?? null;
    $clase      = $params['clase'] ?? '';

    // Resolver logo
    $rutaLogo = '';
    if ($logo === true && $codigoCliente !== '') {
        $rutaLogo = logoClienteRuta($codigoCliente);
    } elseif (is_string($logo) && $logo !== '') {
        $rutaLogo = $logo;
    }

    $tieneMenu   = $menu;
    $tieneVolver = ($volver !== '');
    $tieneBusqueda = is_array($busqueda) || $busqueda === true;
    $busquedaPlaceholder = is_array($busqueda) ? ($busqueda['placeholder'] ?? 'Buscar...') : 'Buscar...';
    $busquedaId = is_array($busqueda) ? ($busqueda['id'] ?? 'search-input') : 'search-input';

    $tieneIzquierda = $slotIzq !== null || $tieneMenu || $tieneVolver || $titulo !== false || $tieneBusqueda;
    $tieneDerecha   = $slotDer !== null || $rutaLogo !== '' || $usuario !== null;

    // Determinar modo para clase CSS
    $modo = 'topbar-completo';
    if ($tieneBusqueda) $modo = 'topbar-dashboard';
    if ($tieneVolver && !$menu && !$tieneBusqueda) $modo = 'topbar-sencillo';
?>
    <header class="topbar-comp <?= htmlspecialchars($modo) ?> <?= htmlspecialchars($clase) ?>">
        <div class="topbar-izq">
            <?php if ($slotIzq !== null): ?>
                <?php $slotIzq(); ?>
            <?php else: ?>
                <?php if ($tieneMenu): ?>
                    <button class="topbar-btn-menu" id="<?= htmlspecialchars($menuId) ?>" title="Mostrar/ocultar menú">
                        <span class="material-symbols-outlined">menu</span>
                    </button>
                <?php endif; ?>

                <?php if ($tieneVolver): ?>
                    <a href="<?= htmlspecialchars($volver) ?>" class="topbar-volver">
                        <span class="material-symbols-outlined">arrow_back</span>
                        <span>Inicio</span>
                    </a>
                <?php endif; ?>

                <?php if ($tieneBusqueda): ?>
                    <div class="topbar-busqueda">
                        <span class="material-symbols-outlined topbar-busqueda-icono">search</span>
                        <input type="text" id="<?= htmlspecialchars($busquedaId) ?>"
                            class="topbar-busqueda-input"
                            placeholder="<?= htmlspecialchars($busquedaPlaceholder) ?>">
                    </div>
                <?php endif; ?>

                <?php if ($titulo !== false && $titulo !== ''): ?>
                    <div class="topbar-titulo-wrap">
                        <?php if ($tieneVolver): ?>
                            <h1 class="topbar-titulo"><?= htmlspecialchars($titulo) ?></h1>
                        <?php else: ?>
                            <h1 class="topbar-titulo"><?= htmlspecialchars($titulo) ?></h1>
                            <?php if ($subtitulo !== ''): ?>
                                <div class="topbar-subtitulo"><?= htmlspecialchars($subtitulo) ?></div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($meta !== ''): ?>
                            <div class="topbar-meta"><?= htmlspecialchars($meta) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($tieneDerecha): ?>
            <div class="topbar-der">
                <?php if ($slotDer !== null): ?>
                    <?php $slotDer(); ?>
                <?php else: ?>
                    <?php if ($rutaLogo !== ''): ?>
                        <img src="<?= htmlspecialchars($rutaLogo) ?>" alt="Logo" class="topbar-logo">
                    <?php endif; ?>

                    <?php if ($usuario !== null && is_array($usuario)): ?>
                        <div class="topbar-perfil" id="topbarPerfil">
                            <button class="topbar-perfil-btn" id="topbarPerfilToggle">
                                <span class="topbar-perfil-nombre"><?= htmlspecialchars($usuario['name'] ?? '') ?></span>
                                <span class="topbar-perfil-avatar">
                                    <span class="material-symbols-outlined">account_circle</span>
                                </span>
                            </button>
                            <div class="topbar-perfil-dropdown" id="topbarPerfilDropdown">
                                <div class="topbar-perfil-cab">
                                    <div class="topbar-perfil-nom"><?= htmlspecialchars($usuario['name'] ?? '') ?></div>
                                    <div class="topbar-perfil-sub"><?= htmlspecialchars($usuario['email'] ?? '') ?></div>
                                    <div class="topbar-perfil-sub">
                                        <?php if ($userMaster !== ''): ?>
                                            Cuenta: <?= htmlspecialchars($userMaster) ?>
                                        <?php elseif ($codigoCliente !== ''): ?>
                                            Cliente: <?= htmlspecialchars($codigoCliente) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <a href="../auth/logout.php" class="topbar-perfil-logout">
                                    <span class="material-symbols-outlined">logout</span>
                                    Cerrar sesión
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>
<?php
}

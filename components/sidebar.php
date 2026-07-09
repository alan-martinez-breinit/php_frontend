<?php
function renderSidebar(string $titulo, callable $contentCallback): void
{
?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="sidebar-panel" id="sidebarPanel">
        <div class="sidebar-panel-header">
            <h3><?= htmlspecialchars($titulo) ?></h3>
            <button class="sidebar-panel-close" id="sidebarPanelClose" aria-label="Cerrar">&times;</button>
        </div>
        <div class="sidebar-panel-body">
            <?php $contentCallback(); ?>
        </div>
    </div>
<?php
}

/**
 * Control reutilizable del Sidebar.
 * Expone openSidebar() / closeSidebar() globalmente.
 */
(function () {
    var overlay = document.getElementById('sidebarOverlay');
    var panel = document.getElementById('sidebarPanel');
    var closeBtn = document.getElementById('sidebarPanelClose');

    if (!overlay || !panel) return;

    window.openSidebar = function () {
        panel.classList.add('open');
        overlay.classList.add('open');
    };

    window.closeSidebar = function () {
        panel.classList.remove('open');
        overlay.classList.remove('open');
    };

    if (closeBtn) {
        closeBtn.addEventListener('click', window.closeSidebar);
    }
    if (overlay) {
        overlay.addEventListener('click', window.closeSidebar);
    }
})();

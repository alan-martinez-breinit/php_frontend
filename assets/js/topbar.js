/**
 * topbar.js — Dropdown del perfil del componente Topbar.
 * Se activa automáticamente si existe el marcado generado por renderTopbar().
 */
(function () {
    'use strict';

    var toggle   = document.getElementById('topbarPerfilToggle');
    var dropdown = document.getElementById('topbarPerfilDropdown');
    var perfil   = document.getElementById('topbarPerfil');

    if (!toggle || !dropdown || !perfil) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    document.addEventListener('click', function (e) {
        if (!perfil.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });
})();

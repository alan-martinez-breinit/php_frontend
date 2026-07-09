/* one_page_taller.js — sidebar + perfil + exportar HTML (Taller) */

/* ---- Variables globales desde data attributes (evita inline scripts) ---- */
(function () {
    var body = document.body;
    if (body) {
        window._onePageTallerMes = encodeURIComponent(body.dataset.mes || '');
        window._onePageTallerMesNombre = body.dataset.mes || 'reporte';
    }
})();

/* ---- Sidebar ---- */
function toggleSidebar() {
    var sb = document.getElementById('sidebar');
    var cw = document.getElementById('contentWrap');
    if (sb) sb.classList.toggle('hidden');
    if (cw) cw.classList.toggle('full');
}

var btnHamburguesa = document.getElementById('btn-hamburguesa');
if (btnHamburguesa) {
    btnHamburguesa.addEventListener('click', toggleSidebar);
}

/* ---- Perfil dropdown ---- */
function togglePerfil() {
    var dd = document.getElementById('perfilDropdown');
    if (dd) dd.classList.toggle('show');
}

var perfilToggle = document.getElementById('perfilToggle');
var perfilMenu = document.getElementById('perfilMenu');
var perfilDropdown = document.getElementById('perfilDropdown');

if (perfilToggle) {
    perfilToggle.addEventListener('click', function (e) {
        e.stopPropagation();
        togglePerfil();
    });
}

if (perfilMenu && perfilDropdown) {
    document.addEventListener('click', function (e) {
        if (!perfilMenu.contains(e.target)) {
            perfilDropdown.classList.remove('show');
        }
    });
}

/* ---- Submit automático del selector de mes ---- */
var mesSelect = document.getElementById('mes');
if (mesSelect) {
    mesSelect.addEventListener('change', function () {
        var form = mesSelect.closest('form');
        if (form) form.submit();
    });
}

/* ---- Exportar HTML ---- */
var btnExportar = document.getElementById('btn-exportar');
if (btnExportar) {
    btnExportar.addEventListener('click', exportarHTML);
}

function exportarHTML() {
    var mesNombre = window._onePageTallerMesNombre || 'reporte';
    var ts = new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-');

    var clone = document.documentElement.cloneNode(true);
    clone.querySelectorAll('script').forEach(function (s) { s.remove(); });
    var body = clone.querySelector('body');
    if (body) body.setAttribute('data-exported', 'true');

    var html = '<!DOCTYPE html>\n' + clone.outerHTML;
    var blob = new Blob([html], { type: 'text/html;charset=utf-8' });
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'one_page_taller_' + mesNombre + '_' + ts + '.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

/* one_page.js — sidebar, perfil, filtros compañía/escala, exportar HTML */

/* ---- Variables globales desde data attributes (evita inline scripts) ---- */
(function () {
    var body = document.body;
    if (body) {
        window._onePageSeccion = body.dataset.seccion || 'reporte';
        window._onePageMes = encodeURIComponent(body.dataset.mes || '');
        window._onePageMesNombre = body.dataset.mes || 'reporte';
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

/* ---- Filtro por compañía ---- */
function aplicarCompania() {
    var sel = document.getElementById('compania');
    if (!sel) return;
    var val = sel.value;
    document.querySelectorAll('table tbody tr[data-sucursal]').forEach(function (tr) {
        var c = tr.getAttribute('data-compania') || '';
        if (val === 'todas' || c === val) {
            tr.classList.remove('fila-oculta');
        } else {
            tr.classList.add('fila-oculta');
        }
    });
}

var companiaSelect = document.getElementById('compania');
if (companiaSelect) {
    companiaSelect.addEventListener('change', aplicarCompania);
}

/* ---- Escala de moneda ---- */
function aplicarEscala() {
    var sel = document.getElementById('escala');
    if (!sel) return;
    var escala = parseFloat(sel.value) || 1;
    var pref = escala === 100 ? 'C$' : escala === 1000 ? 'M$' : escala === 1000000 ? 'MM$' : '$';
    document.querySelectorAll('td.dinero[data-raw]').forEach(function (td) {
        var raw = parseFloat(td.getAttribute('data-raw')) || 0;
        if (escala === 1) {
            td.textContent = '$' + raw.toLocaleString('es-MX', { maximumFractionDigits: 0 });
        } else {
            td.textContent = pref + (raw / escala).toLocaleString('es-MX', { maximumFractionDigits: 1 });
        }
    });
}

var escalaSelect = document.getElementById('escala');
if (escalaSelect) {
    escalaSelect.addEventListener('change', aplicarEscala);
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
    var seccion = window._onePageSeccion || 'reporte';
    var mes = decodeURIComponent(window._onePageMes || '');
    var nombreMes = window._onePageMesNombre || 'reporte';
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
    a.download = 'one_page_' + seccion + '_' + nombreMes + '_' + ts + '.html';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

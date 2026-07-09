/* one_page_taller.js — sidebar + perfil + exportar HTML (Taller) */

/* ---- Variables globales desde data attributes (evita inline scripts) ---- */
(function () {
    var body = document.body;
    if (body) {
        window._onePageTallerMes = encodeURIComponent(body.dataset.mes || '');
        window._onePageTallerMesNombre = body.dataset.mes || 'reporte';
    }
})();

/* ---- Sidebar Detail - Asignar clicks solo a headers ---- */
(function() {
    var context = document.getElementById('rowContext');
    var form = document.getElementById('sidebarForm');
    var fModelo = document.getElementById('fModelo');
    var fTitulo = document.getElementById('fTitulo');
    var fAtributo = document.getElementById('fAtributo');
    var fFormato = document.getElementById('fFormato');
    var grpModelo = document.getElementById('grpModelo');
    var grpAtributo = document.getElementById('grpAtributo');
    var gpoFormula = document.getElementById('gpoFormula');
    var fFormula = document.getElementById('fFormula');

    if (!context || !form) return;

    function llenarSidebar(th) {
        var colId = th.dataset.colId || '';
        var colNombre = th.textContent.trim();

        if (!context || !fModelo || !fTitulo || !fFormato) {
            console.error('[SIDEBAR] Faltan elementos del formulario');
            return;
        }

        context.innerHTML =
            '<div class="sidebar-context-row">' +
            '<span class="sidebar-context-label">Columna</span>' +
            '<span class="sidebar-context-value">' + colNombre + '</span>' +
            '</div>';

        if (grpModelo) grpModelo.style.display = 'block';
        if (grpAtributo) grpAtributo.style.display = 'block';

        // Mapeo básico de campos Taller
        var camposModelos = {
            'sucursal': { modelo: 'Taller', atributo: 'Sucursal' },
            'servicio_venta': { modelo: 'VentaServicioRefacciones', atributo: 'Servicio - Venta' },
            'servicio_obj_dia': { modelo: 'VentaServicioRefacciones', atributo: 'Servicio - Obj Día' },
            'servicio_alcance_ritmo': { modelo: '', atributo: '' },
            'refacciones_venta': { modelo: 'VentaServicioRefacciones', atributo: 'Refacciones - Venta' },
            'refacciones_obj_dia': { modelo: 'VentaServicioRefacciones', atributo: 'Refacciones - Obj Día' },
            'refacciones_alcance_ritmo': { modelo: '', atributo: '' },
            'total_venta': { modelo: 'VentaServicioRefacciones', atributo: 'Total Venta' },
            'total_obj_dia': { modelo: 'VentaServicioRefacciones', atributo: 'Total Obj Día' },
            'total_alcance_ritmo': { modelo: '', atributo: '' },
            'venta': { modelo: 'VentaTaller', atributo: 'Venta' },
            'obj_venta_dia': { modelo: 'VentaTaller', atributo: 'Obj Venta Día' },
            'alcance_ritmo_pct': { modelo: '', atributo: '' },
            'obj_venta_mes': { modelo: 'VentaTaller', atributo: 'Obj Ventas' },
            'alcance_objetivo_pct': { modelo: '', atributo: '' },
            'margen': { modelo: 'VentaTaller', atributo: 'Margen Bruto' },
            'pct_margen': { modelo: '', atributo: '' },
            'obj_margen_dia': { modelo: 'VentaTaller', atributo: 'Obj Margen Día' },
            'alcance_ritmo_margen_pct': { modelo: '', atributo: '' },
            'obj_margen_mes': { modelo: 'VentaTaller', atributo: 'Obj Margen' },
            'alcance_objetivo_margen_pct': { modelo: '', atributo: '' },
            'cartera': { modelo: 'Cxc', atributo: 'Saldo' },
            'ticket_prom_real': { modelo: 'VentaTaller', atributo: 'Ticket Prom Real' },
            'ticket_prom_objetivo': { modelo: 'VentaTaller', atributo: 'Ticket Prom Objetivo' },
            'variacion': { modelo: '', atributo: '' }
        };

        var meta = camposModelos[colId];
        if (meta && meta.modelo) {
            fModelo.value = meta.modelo;
            if (fAtributo) fAtributo.value = meta.atributo || '';
        } else {
            fModelo.value = colId || 'Taller';
            if (fAtributo) fAtributo.value = '';
        }

        if (grpModelo) grpModelo.style.display = (fModelo.value && fModelo.value.trim()) ? 'block' : 'none';
        if (grpAtributo) grpAtributo.style.display = (fAtributo && fAtributo.value && fAtributo.value.trim()) ? 'block' : 'none';

        fTitulo.value = colNombre;
        fFormato.value = '#,##0';
        if (gpoFormula) gpoFormula.style.display = 'none';
        if (fFormula) fFormula.value = '';

        if (!window.openSidebar) {
            console.error('[SIDEBAR] openSidebar no definida');
            return;
        }
        window.openSidebar();
        console.log('[SIDEBAR] Sidebar abierto.');
    }

    // Asignar clicks SOLO a headers con data-col-id (no a datos)
    var tarjetas = document.querySelectorAll('.tarjeta');
    console.log('[SIDEBAR] Tarjetas encontradas:', tarjetas.length);
    var totalHeaders = 0;

    tarjetas.forEach(function(tarjeta) {
        var tabla = tarjeta.querySelector('table');
        if (!tabla) return;
        var headers = tabla.querySelectorAll('thead th[data-col-id]');
        if (headers.length === 0) return;
        var titulo = tarjeta.querySelector('.titulo');
        var nombreTarjeta = titulo ? titulo.textContent.trim() : '(sin título)';

        console.log('[SIDEBAR] Tabla "' + nombreTarjeta + '" — ' + headers.length + ' headers con data-col-id');
        headers.forEach(function(th) {
            // Saltar th con colspan (encabezados agrupados, no clickeables)
            if (th.hasAttribute('colspan') && parseInt(th.getAttribute('colspan')) > 1) return;
            th.style.cursor = 'pointer';
            th.title = 'Click para ver detalle';
            th.addEventListener('click', function(e) {
                if (e.target.closest('a') || e.target.closest('button')) return;
                console.log('[SIDEBAR] Click en encabezado:', JSON.stringify(th.textContent.trim()), 'de tabla "' + nombreTarjeta + '"');
                llenarSidebar(th);
            });
            totalHeaders++;
        });
    });
    console.log('[SIDEBAR] Total headers con click asignados:', totalHeaders);
    if (totalHeaders === 0) {
        console.warn('[SIDEBAR] No se encontraron headers con data-col-id.');
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

# Breinit DCA — Documentación

## Arquitectura de Componentes

El frontend usa 3 componentes reutilizables (sidebar, tabla, topbar). Cada uno sigue el mismo patrón:

| Componente | PHP                      | CSS                      | JS                     |
| ---------- | ------------------------ | ------------------------ | ---------------------- |
| Sidebar    | `components/sidebar.php` | `assets/css/sidebar.css` | `assets/js/sidebar.js` |
| Tabla      | `components/tabla.php`   | `assets/css/tabla.css`   | —                      |
| Topbar     | `components/topbar.php`  | `assets/css/topbar.css`  | `assets/js/topbar.js`  |

**Reglas:**

- Incluir con `require_once __DIR__ . '/../components/...'`
- Cargar CSS en `<head>` y JS antes de `</body>`
- El prefijo de clases CSS coincide con el nombre del componente (`sidebar-*`, `tabla-*`, `topbar-*`)
- La clase base del contenedor es el nombre del componente con sufijo `-comp` (`.topbar-comp`) para evitar conflictos con estilos legacy

---

## Sidebar

Panel lateral deslizable (derecha → izquierda). Se usa para edición rápida de registros.

### Inclusión

```php
require_once __DIR__ . '/../components/sidebar.php';
```

### Render

```php
renderSidebar('Título del Panel', function () {
    // HTML o PHP aquí
});
```

### JS API

```js
openSidebar(); // abre
closeSidebar(); // cierra
```

### IDs del DOM

| Elemento     | ID                  |
| ------------ | ------------------- |
| Overlay      | `sidebarOverlay`    |
| Panel        | `sidebarPanel`      |
| Botón cerrar | `sidebarPanelClose` |

### CSS clases principales

| Clase                   | Propósito                                |
| ----------------------- | ---------------------------------------- |
| `.sidebar-overlay`      | Fondo semitransparente (toggle `.open`)  |
| `.sidebar-panel`        | Panel fijo a la derecha (toggle `.open`) |
| `.sidebar-panel-header` | Cabecera con título + cerrar             |
| `.sidebar-panel-body`   | Área de contenido scrollable             |
| `.sidebar-row-context`  | Contexto de la fila seleccionada         |
| `.sidebar-context-row`  | Fila de detalle (label + value)          |
| `.sidebar-form-group`   | Grupo de formulario                      |

---

## Tabla

Tabla HTML con `<thead>`, `<tbody>`, `<tfoot>` opcional, clases dinámicas por celda y `data-raw`.

### Inclusión

```php
require_once __DIR__ . '/../components/tabla.php';
```

### Render

```php
renderTabla([
    'columnas' => [
        ['id' => 'sucursal', 'label' => 'Sucursal'],
        ['id' => 'venta',    'label' => 'Venta',
         'clase' => 'num', 'clase_campo' => 'venta_clase', 'raw_id' => 'venta_raw'],
        ['id' => 'alcance',  'label' => '% Alcance', 'html' => true],
    ],
    'filas'    => $filasTabla,
    'totales'  => $filaTotales,   // opcional
    'vacio'    => 'Sin datos.',
    'clase_tabla' => 'mi-extra',
    'clase_tfoot' => 'tabla-tfoot', // default
    'click'    => true,            // cursor pointer en tbody tr
]);
```

### Opciones por columna

| Parámetro     | Tipo   | Descripción                                         |
| ------------- | ------ | --------------------------------------------------- |
| `id`          | string | **Requerido.** Campo del array de datos             |
| `label`       | string | Texto del `<th>`                                    |
| `clase`       | string | Clase CSS estática en `<td>`                        |
| `clase_campo` | string | Campo de la fila con clase CSS dinámica             |
| `html`        | bool   | `true` = no escapar (para badges, fpColor)          |
| `raw_id`      | string | Campo de la fila con valor numérico para `data-raw` |

### CSS clases principales

| Clase                          | Propósito                                |
| ------------------------------ | ---------------------------------------- |
| `.tabla`                       | `<table>` principal                      |
| `.tabla-wrap`                  | Contenedor scrollable                    |
| `.tabla td.num`, `.tabla .num` | Alineación derecha + tabular-nums        |
| `.tabla .monto-neg`            | Texto rojo para valores negativos        |
| `.tabla-vacio`                 | Celda de "sin datos" (centrada, itálica) |
| `.tabla-tfoot`                 | Fila de totales (negrita, borde azul)    |

### data-raw

Cuando una columna define `raw_id`, el `<td>` incluye `data-raw="<valor>"` con el valor numérico original. Útil para tooltips, gráficos inline o cálculos JS.

---

## Topbar

Barra superior con 3 modos automáticos y slots personalizables.

### Inclusión

```php
require_once __DIR__ . '/../components/topbar.php';
```

### Modos

El modo se determina automáticamente según los parámetros:

| Modo        | Disparo                             | Contenido izquierdo            | Contenido derecho |
| ----------- | ----------------------------------- | ------------------------------ | ----------------- |
| `completo`  | `menu=true`                         | hamburger + título + subtítulo | logo + perfil     |
| `sencillo`  | `volver='...'` (sin menu ni search) | link volver + título + meta    | logo              |
| `dashboard` | `busqueda=[...]`                    | búsqueda                       | logo + perfil     |

Ejemplos:

```php
// Completo (one_page)
renderTopbar(['titulo'=>'One Page', 'subtitulo'=>'Ene 26', 'menu'=>true, 'usuario'=>$usuario, 'codigo_cliente'=>$codigoCliente]);

// Sencillo (modulo, cxc)
renderTopbar(['titulo'=>'CXC', 'volver'=>'dashboard.php', 'meta'=>'0003 · 150 registros', 'codigo_cliente'=>$codigoCliente]);

// Dashboard
renderTopbar(['busqueda'=>['placeholder'=>'Buscar módulos...'], 'usuario'=>$usuario, 'codigo_cliente'=>$codigoCliente]);
```

### Parámetros

| Parámetro        | Tipo          | Default             | Descripción                           |
| ---------------- | ------------- | ------------------- | ------------------------------------- |
| `titulo`         | string\|false | `''`                | Título principal (false para ocultar) |
| `subtitulo`      | string        | `''`                | Texto secundario (modo completo)      |
| `meta`           | string        | `''`                | Metadata (modo sencillo)              |
| `menu`           | bool          | `false`             | Botón hamburger                       |
| `menu_id`        | string        | `'btn-hamburguesa'` | ID del botón hamburger                |
| `volver`         | string        | `''`                | Href para link de volver              |
| `busqueda`       | array\|bool   | `false`             | `['placeholder'=>'...']` o `true`     |
| `usuario`        | array\|null   | `null`              | Con keys: `name`, `email`             |
| `codigo_cliente` | string        | `''`                | Para logo automático                  |
| `user_master`    | string        | `''`                | Para mostrar en dropdown              |
| `logo`           | string\|bool  | `true`              | Ruta, `true`=auto, `false`=ocultar    |
| `slot_izquierda` | callable      | `null`              | HTML personalizado lado izquierdo     |
| `slot_derecha`   | callable      | `null`              | HTML personalizado lado derecho       |
| `clase`          | string        | `''`                | Clase CSS extra en `<header>`         |

### Logo automático

Si `logo=true` y `codigo_cliente` está definido, llama a `logoClienteRuta()` de `includes/logos.php`.

### CSS clases

| Clase                         | Propósito                                        |
| ----------------------------- | ------------------------------------------------ |
| `.topbar-comp`                | Base (flex, sticky, z-40)                        |
| `.topbar-completo`            | Modo completo (fondo azul)                       |
| `.topbar-sencillo`            | Modo sencillo (fondo azul)                       |
| `.topbar-dashboard`           | Modo dashboard (fondo azul)                      |
| `.topbar-con-sidebar`         | Variante con margen para sidebar (dashboard.php) |
| `.topbar-izq` / `.topbar-der` | Contenedores flex                                |
| `.topbar-btn-menu`            | Botón hamburger                                  |
| `.topbar-volver`              | Link de regreso                                  |
| `.topbar-busqueda`            | Contenedor buscador                              |
| `.topbar-busqueda-input`      | Input de búsqueda                                |
| `.topbar-titulo-wrap`         | Contenedor del título                            |
| `.topbar-titulo`              | Título `h1`                                      |
| `.topbar-subtitulo`           | Subtítulo                                        |
| `.topbar-meta`                | Metadata                                         |
| `.topbar-logo`                | Imagen del logo                                  |
| `.topbar-perfil`              | Contenedor del perfil                            |
| `.topbar-perfil-btn`          | Botón perfil                                     |
| `.topbar-perfil-dropdown`     | Dropdown (toggle `.show`)                        |
| `.topbar-perfil-logout`       | Link de cerrar sesión                            |

---

## Convenciones de formato de datos

### Detección de tipo por nombre de campo (módulos dinámicos)

En `modulo.php` y `cxc.php` las tablas formatean valores automáticamente según el nombre del campo:

| Tipo     | Regex                                                                                         | Formato             |
| -------- | --------------------------------------------------------------------------------------------- | ------------------- |
| `moneda` | `precio, importe, venta, vta, costo, cargo, credito, saldo, ingreso, egreso, monto, utilidad` | `$1,234.56`         |
| `pct`    | `pct, porc, roi_real, margen_pct, alcance_pct`                                                | `12.3%`             |
| `numero` | `unidades?, exist, stock, cantidad, uds_vendidas, inventario, dias_`                          | `1,234`             |
| `fecha`  | `date_key, fecha_venc` o valor `YYYY-MM-DD`                                                   | `dd/mm/yyyy`        |
| `texto`  | todo lo demás                                                                                 | escape HTML directo |

Las columnas de tipo `moneda` reciben clase `num` (derecha, monoespacio) y `monto-neg` si el valor es negativo.

### Helper functions (en `lib/`)

- `fm($n)` — formatea como moneda
- `fpColor($val)` — badge con color dinámico
- `fp($val)` — porcentaje
- `fnum($n)` — número con comas
- `raw($val)` — raw numérico para atributos
- `nombreMes($n)` — nombre del mes
- `logoClienteRuta($codigo)` — ruta del logo del cliente

---

## Páginas y componentes aplicados

| Página                   | Sidebar     | Tabla                   | Topbar       |
| ------------------------ | ----------- | ----------------------- | ------------ |
| `one_page.php`           | ✅          | ✅ (Venta Autos $)      | ✅ completo  |
| `one_page_taller.php`    | ❌          | ❌                      | ✅ completo  |
| `modulo.php`             | ❌          | ✅ genérica (whitelist) | ✅ sencillo  |
| `cxc.php`                | ❌          | ✅ CXC                  | ✅ sencillo  |
| `objetivos_servicio.php` | ❌          | ❌                      | ✅ sencillo  |
| `dashboard.php`          | ❌ (nativa) | ❌                      | ✅ dashboard |

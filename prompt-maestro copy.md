Aquí tienes el **Prompt Maestro General** diseñado con todos los estándares técnicos, arquitectónicos y de diseño logrados en **Reportes Financieros** y **Transacciones Financieras**. Puedes copiarlo y usarlo para modernizar cualquier módulo o vista del plugin:

---

```markdown
# PROMPT MAESTRO DE MODERNIZACIÓN GLOBAL UX/UI, RESPONSIVE DESIGN Y DARK MODE

Actúa como Diseñador UI/UX Senior y Desarrollador Full-Stack WordPress. Tu objetivo es modernizar y estandarizar la página/módulo actual de **Aura Business Suite**, adaptándola a los mismos patrones de diseño, interactividad, responsividad y compatibilidad con Modo Oscuro que ya están implementados y consolidados en **Transacciones Financieras** y **Reportes Financieros**.

Sigue estrictamente las siguientes directrices en todos los componentes:

---

## 1. 🎨 SISTEMA DE DISEÑO, COLORES Y DARK MODE
- **Modo Claro (Light Mode)**:
  - Fondos de tarjetas y paneles: `#ffffff` con borde `1px solid #e2e8f0` y sombra suave `box-shadow: 0 1px 3px rgba(0,0,0,0.05)`.
  - Tipografías principales: Títulos `#1e293b`, subtítulos y etiquetas `#64748b` (mayúsculas de 10-11px con `letter-spacing: 0.5px`).
  - Botones primarios: Gradiente o sólido azul `#2563eb` con hover `#1d4ed8` y altura de 36-38px.
  - Inputs y Selects: Fondo `#ffffff`, borde `#cbd5e1`, radio `6px`, altura `36px`, y foco `box-shadow: 0 0 0 3px rgba(37,99,235,0.15)`.

- **Modo Oscuro (Dark Mode)**:
  - Aplica selectores universales:
    `html[data-wp-dark-mode-scheme="dark"]`, `html.wp-dark-mode-active`, `body.wp-dark-mode-active`, `body.aura-dark-mode`, `body[data-theme="dark"]`, `[data-theme="dark"]`.
  - Fondos de tarjetas y tablas: `#1e293b` con bordes `#334155`.
  - Tipografías: Texto principal en blanco nítido **`#f8fafc` / `#ffffff`** (NUNCA texto negro o gris oscuro sobre fondo oscuro).
  - Textos secundarios y etiquetas: `#94a3b8`.
  - Inputs y Selects: Fondo `#1e293b`, bordes `#334155`, texto `#f8fafc`.
  - Badges de estado / pills: Tonos saturados sobre fondos oscuros (ej. `#064e3b` con texto `#6ee7b7` para éxito; `#7f1d1d` con `#fca5a5` para error; `#312e81` con `#c7d2fe` para badges primarios).

---

## 2. 🎛️ BARRA LATERAL DE FILTROS (SIDEBAR) Y BARRA SUPERIOR
- **Estructura idéntica a Transacciones**:
  - Contenedor `.aura-filters-card` con cabecera `.aura-filters-header` (título en mayúsculas `FILTROS Y CONFIGURACIÓN`, icono azul `#3b82f6` y botón colapsar `#toggle-filters` con flecha Dashicon).
  - Cuerpo `.aura-filters-body` dividido en secciones tipo tarjeta `.filter-section` con títulos `h4` y dashicons temáticos.
  - **Selector de Fechas y Período**: Usar grilla `.aura-date-range-row` con `.aura-date-input-wrap` elásticos (`width: 100%; min-width: 0; box-sizing: border-box;`) y botonera de chips rápidos `.aura-quick-date-chips` (`Este Mes`, `Mes Ant.`, `Trimestre`, `Este Año`). **Cero desbordamiento horizontal**.
  - **Barra Superior Inteligente**: Al ocultar filtros, el botón **"Mostrar Filtros"** aparece a la izquierda y los botones de acción rápida/exportación (`CSV`, `Excel`, `Imprimir`) aparecen automáticamente alineados a la derecha.

---

## 3. 📊 GRÁFICOS, ESTADÍSTICAS Y KPI CARDS
- **Tarjetas KPI (`.aura-report-kpis` / `.aura-kpi-grid`)**:
  - 4 tarjetas con bordes coloreados temáticos (Ingreso, Egreso, Balance, Pendientes).
  - Cifras en negrita destacadas (`1.4rem` a `1.75rem`) con formato monetario o conteos claros.
- **Gráficos Donut / Barras / Líneas (Chart.js)**:
  - **Layout en 2 Columnas (`.aura-donut-report-layout`)**: Gráfico centrado a la izquierda y **Leyenda HTML Interactiva con Scroll** a la derecha (`max-height: 260px; overflow-y: auto;`).
  - **Interacción Bidireccional**: Al pasar el ratón (hover) sobre un item de la leyenda, resalta el segmento del gráfico; al hacer clic, abre el drilldown de desglose de esa categoría.
  - **Leyenda Inteligente**: Cada fila incluye viñeta de color, nombre completo con `title` tooltip, badge de subcategorías/estado, importe monetario y porcentaje destacado.
  - **Adaptabilidad Dark Mode**: El borde del Donut (`borderColor`) y las fuentes cambian automáticamente para fundirse con el fondo en modo oscuro.

---

## 4. 📋 TABLAS Y RESULTADOS JERÁRQUICOS
- **Contenedor Responsivo con Scroll Táctil**:
  - Todo elemento de tabla debe estar dentro de `.aura-report-table-wrap` con `overflow-x: auto; -webkit-overflow-scrolling: touch;` y `min-width: 650px;` en la tabla para asegurar que los números y badges nunca se compriman.
- **Cabeceras**: Fondo azul marino `#1e3a5f` con tipografía blanca, o `#0f172a` en dark mode.
- **Filas**: Fondos alternados (`#ffffff` y `#f8fafc` en claro, `#1e293b` y `#0f172a` en oscuro).
- **Tablas Jerárquicas**: Soporte para árboles expandibles con botones `[+]`/`[-]`, sangría `└─` con viñetas de colores y botones globales `Expandir Todas` y `Colapsar Todas`.

---

## 5. 📱 ADAPTABILIDAD RESPONSIVE TOTAL (BREAKPOINTS)
- **Laptops (<= 1200px)**: Sidebar fluido de 290px y KPIs en 4 columnas compactas.
- **Tablets (<= 1024px)**: Layout en 1 columna, KPIs en 2 columnas, gráfico y leyenda apilados verticalmente con scroll fluido.
- **Móviles Horizontales (<= 768px)**: Botones de exportación en cuadrícula touch de 3 columnas, toolbars en columna y tabla con scroll táctil suave.
- **Móviles Verticales (<= 480px)**: KPIs en 1 columna, chips de fechas en 2 columnas y gráfico compacto de 220px.

---

## 6. 🖨️ MOTOR DE IMPRESIÓN Y EXPORTACIÓN A PDF (@media print)
- **Membrete Corporativo Oficial (`.aura-print-header`)**:
  - Logotipo del sitio + Razón social en mayúsculas (`#1e3a5f`) + subtítulo *"MÓDULO FINANCIERO — REPORTE OFICIAL"*.
  - Tarjeta central con el **Nombre del Reporte** y badge del período consultado.
  - Bloque derecho con: *Generado por*, *Fecha de Emisión* e insignia de *"Documento Oficial"*.
- **Gráfica y Leyenda Completa**:
  - Eliminar scrollbar y límite de altura (`max-height: none !important; overflow: visible !important;`).
  - Leyenda desplegada en 2 columnas con todas las categorías y cifras visibles.
- **Limpieza de Impresión**:
  - Ocultar botones interactivos (`[+]`, `[-]`, `Gráfico`, `Expandir/Colapsar`, sidebars y menús de WordPress).
  - Reglas de color forzado `-webkit-print-color-adjust: exact; print-color-adjust: exact;`.
  - Pie de página institucional de confidencialidad (`.aura-print-footer`) con aviso legal y número de página.

---

Aplica estas directrices en el archivo PHP de la vista, en el CSS del módulo y en el JavaScript correspondiente, asegurando que no haya errores de sintaxis (`php -l`), y finaliza sincronizando a `C:\laragon\www\aura-business-suite\` y compilando el ZIP con `build-zip-sin-vendor.php`.
```
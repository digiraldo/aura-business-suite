# 📋 PROMPT MAESTRO GLOBAL DE MODERNIZACIÓN UX/UI, RESPONSIVE DESIGN, CHILD ROWS Y DARK MODE — AURA BUSINESS SUITE

> **Uso:** Copia este prompt maestro y utilízalo para modernizar, corregir y estandarizar cualquier pantalla, módulo o vista de **Aura Business Suite** (Finanzas, Inventario, Usuarios, Estudiantes, Vehículos, Biblioteca, Certificados, etc.).

---

```markdown
# PROMPT MAESTRO DE MODERNIZACIÓN GLOBAL UX/UI, RESPONSIVE DESIGN Y DARK MODE

Actúa como **Arquitecto Frontend Senior y Desarrollador Full-Stack WordPress** para el plugin **Aura Business Suite**. Tu objetivo es analizar, modernizar y estandarizar la página o módulo actual `[NOMBRE_O_URL_DE_LA_PAGINA]`, adaptándola rigurosamente a los mismos patrones de diseño, interactividad, responsividad, sistema de filas expandibles (Child Rows), tooltips contextuales y compatibilidad con Modo Oscuro que ya están implementados y consolidados en **Transacciones Financieras**, **Reportes Financieros** y **Mi Dashboard Financiero**.

Sigue estrictamente las siguientes directrices arquitectónicas y visuales:

---

## 1. 🎨 SISTEMA DE DISEÑO, COLORES Y DARK MODE
- **Modo Claro (Light Mode)**:
  - Fondos de tarjetas y paneles: `#ffffff` con borde `1px solid #e2e8f0` y sombra suave `box-shadow: 0 1px 3px rgba(0,0,0,0.05)`.
  - Tipografías principales: Títulos `#0f172a` / `#1e293b`, subtítulos y etiquetas `#64748b` (mayúsculas de 10-11px con `letter-spacing: 0.5px`).
  - Botones primarios: Gradiente o sólido azul `#2563eb` con hover `#1d4ed8` y altura de 36-38px.
  - Inputs y Selects: Fondo `#ffffff`, borde `#cbd5e1`, radio `6px`, altura `36px`, y foco `box-shadow: 0 0 0 3px rgba(37,99,235,0.15)`.

- **Modo Oscuro (Dark Mode)**:
  - Aplica selectores universales con máxima especificidad:
    `html[data-wp-dark-mode-scheme="dark"]`, `html.wp-dark-mode-active`, `body.wp-dark-mode-active`, `body.aura-dark-mode`, `body[data-theme="dark"]`, `[data-theme="dark"]`, `body.admin-color-midnight`, `body.admin-color-coffee`.
  - Fondos de tarjetas y tablas: `#1e293b` con bordes `#334155` y encabezados `#0f172a`.
  - Tipografías: Texto principal en blanco nítido **`#f8fafc` / `#ffffff`** (NUNCA texto negro o gris oscuro sobre fondo oscuro).
  - Textos secundarios y etiquetas: `#94a3b8`.
  - Inputs y Selects: Fondo `#0f172a` o `#1e293b`, bordes `#334155`, texto `#f8fafc` y `color-scheme: dark`.
  - Badges de estado / pills: Tonos saturados sobre fondos oscuros (ej. `#064e3b` con texto `#6ee7b7` para éxito; `#7f1d1d` con `#fca5a5` para error; `#312e81` con `#c7d2fe` para badges primarios).

---

## 2. 💡 AYUDAS CONTEXTUALES Y TOOLTIPS AUTOMÁTICOS
- **Análisis e Inclusión Automática**:
  - Analiza la página y detecta automáticamente métricas financieras, fórmulas de cálculo, estados, filtros complejos o términos técnicos.
  - Agrega en cada tarjeta KPI, columna de tabla o etiqueta de filtro el icono de ayuda `.aura-help-icon` (`dashicons dashicons-editor-help`) o contenedor `.aura-tooltip-wrap`.
  - Al pasar el cursor (hover) o pulsar en móviles, debe mostrarse un **Tooltip flotante interactivo** con fondo oscuro `#0f172a`, texto blanco `#ffffff`, radio de `6px`, flecha indicadora y sombra suave `box-shadow: 0 4px 12px rgba(0,0,0,0.15)`.
  - En Dark Mode, el tooltip mantiene fondo `#0f172a` con borde `#334155` y z-index alto (`10000`) para no quedar tapado por encabezados fijos o modales.

---

## 3. 🎛️ BARRA LATERAL DE FILTROS (SIDEBAR) Y BARRA SUPERIOR
- **Estructura Estándar**:
  - Contenedor `.aura-filters-card` con cabecera `.aura-filters-header` (título en mayúsculas `FILTROS Y CONFIGURACIÓN`, icono azul `#3b82f6` y botón colapsar `#toggle-filters` con flecha Dashicon).
  - Cuerpo `.aura-filters-body` dividido en secciones tipo tarjeta `.filter-section` con títulos `h4` y dashicons temáticos.
  - **Selector de Fechas y Período**: Usar grilla `.aura-date-range-row` con `.aura-date-input-wrap` elásticos (`width: 100%; min-width: 0; box-sizing: border-box;`) y botonera de chips rápidos `.aura-quick-date-chips` (`Este Mes`, `Mes Ant.`, `Trimestre`, `Este Año`). **Cero desbordamiento horizontal**.
  - **Barra Superior Inteligente**: Al ocultar filtros, el botón **"Mostrar Filtros"** aparece a la izquierda y los botones de acción rápida/exportación (`CSV`, `Excel`, `Imprimir`) aparecen automáticamente alineados a la derecha.

---

## 4. 📊 GRÁFICOS, ESTADÍSTICAS Y KPI CARDS
- **Tarjetas KPI (`.aura-report-kpis` / `.aura-kpi-grid` / `.aura-ud-stats-grid`)**:
  - 4 tarjetas con bordes coloreados temáticos (Ingreso, Egreso, Balance, Pendientes/Alertas).
  - Cifras en negrita destacadas (`1.4rem` a `1.75rem`) con formato monetario o conteos claros.
  - Iconos en cajas redondeadas suaves (`.aura-ud-stat-icon-wrap`).
- **Gráficos Donut / Barras / Líneas (Chart.js)**:
  - **Layout en 2 Columnas (`.aura-donut-report-layout`)**: Gráfico centrado a la izquierda y **Leyenda HTML Interactiva con Scroll** a la derecha (`max-height: 260px; overflow-y: auto;`).
  - **Interacción Bidireccional**: Al pasar el ratón (hover) sobre un item de la leyenda, resalta el segmento del gráfico; al hacer clic, abre el drilldown de desglose de esa categoría.
  - **Leyenda Inteligente**: Cada fila incluye viñeta de color, nombre completo con tooltip, badge de subcategorías/estado, importe monetario y porcentaje destacado.
  - **Adaptabilidad Dark Mode**: El borde del Donut (`borderColor`) y las fuentes cambian automáticamente para fundirse con el fondo en modo oscuro.

---

## 5. 📋 TABLAS, RESPONSIVE DESIGN Y SISTEMA UNIVERSAL DE FILAS EXPANDIBLES (CHILD ROWS)
- **Ajuste Fluido al 100% del Ancho y Cero Scroll Horizontal (Zero Horizontal Scroll)**:
  - En pantallas de escritorio / laptops (>= 1025px), las tablas muestran todas las columnas de forma fluida y clara.
  - En pantallas móviles (< 782px / 768px) y tablets (< 1024px), la tabla **NUNCA DEBE FORZAR un `min-width` rígido** (como 650px, 780px u 820px) en `.wp-list-table` o en sus contenedores envolventes (`.aura-table-responsive-wrap`, `.aura-table-inner-scroll`). La tabla debe fluir al 100% del ancho del dispositivo (`width: 100% !important; min-width: 0 !important; max-width: 100% !important; table-layout: auto !important; box-sizing: border-box !important;`).
  - **Manejo Estricto de `white-space` y `word-break` (Regla Anti-Desbordamiento)**:
    - **PROHIBIDO** aplicar `white-space: nowrap !important;` de forma genérica a todos los `th` y `td` de la tabla.
    - Las columnas de texto descriptivo y **todo el contenido de la Fila Hija (`td.aura-child-row-cell`, `.aura-child-card`, `.aura-child-card *`)** deben tener **explícitamente**:
      `white-space: normal !important; word-break: break-word !important; overflow-wrap: break-word !important; box-sizing: border-box !important;`.
    - `white-space: nowrap !important;` queda reservado **única y exclusivamente** para celdas compactas de identificación, badges o montos numéricos (`.column-id`, `.column-transaction_date`, `.column-status`, `.column-amount`).
  - Cabeceras thead: Fondo azul marino `#1e3a5f` con tipografía blanca `#ffffff` (en modo claro) y `#0f172a` (en modo oscuro).
  - Filas tbody: Fondos alternados (`#ffffff` y `#f8fafc` en claro, `#1e293b` y `#0f172a` en oscuro).

- **🌟 Sistema Universal de Filas Expandibles (Child Rows) con Diseño WOW**:
  - **Estrategia Maestra de 3 a 4 Columnas Compactas en Fila Principal Móvil**:
    - En pantallas móviles (<= 768px), la fila principal de la tabla solo debe mostrar **3 o 4 columnas compactas** de alto valor y lectura rápida:
      1. `#` (Columna ID con el Toggle Chevron `[v]` + Pill `.aura-txn-id-pill` con icono y tipo integrado + **Tooltip Enriquecido Interactivo**).
      2. `Estado` o `Fecha` (`.column-status` / `.column-transaction_date`).
      3. `Monto` o `Métrica Clave` (`.column-amount` con cifra destacada alineada a la derecha).
    - **Tooltip Descriptivo Enriquecido en Columna `#ID` y `Tipo` (Zero-Clipping & Maximum Z-Index)**:
      - El Pill `#ID` (`.aura-txn-id-pill`) y la celda de `Tipo` (`.aura-type-wrap`) deben incluir tanto el atributo `data-tooltip` como su tarjeta flotante enriquecida `.aura-txn-id-tooltip-box` / `.aura-type-tooltip-box` (activada por hover/tap/focus) que revele el `#ID`, tipo de operación, descripción completa del concepto y categoría contable.
      - **REGLA DE ORO DE VISIBILIDAD (SIEMPRE POR ENCIMA DE TODO)**:
        1. **Portal Flotante Global**: Todo tooltip dinámico debe registrarse con `z-index: 999999999 !important;` (gestionado mediante el portal global `#aura-global-tooltip` anclado a `document.body` o CSS con elevación forzada).
        2. **Cero Recortes por Overflow**: Ningún contenedor con `overflow: hidden`, `overflow-x: auto` o celda `td` debe recortar el tooltip.
        3. **Elevación de Filas en Hover**: La fila activa (`tr:hover`, `tr:focus-within`) pasa automáticamente a `position: relative !important; z-index: 9999 !important;` para que ninguna fila adyacente ni cabecera oculte la tarjeta informativa.
        4. **Detección de Filas Superiores (Orientación Inteligente)**: En las primeras filas (`tr:first-child`, `tr:nth-child(2)`), el tooltip se despliega automáticamente hacia abajo (`top: calc(100% + 8px) !important;`) con flecha invertida, evitando colisionar con el `thead` o el borde superior de la tarjeta.
    - **PROHIBIDO mostrar columnas de textos extensos o botones de acción en la fila principal móvil**:
      - Columnas como `.column-description`, `.column-concept`, `.column-cb`, `.column-created_by`, `.column-created_at`, `.column-acciones` / `.column-actions` **DEBEN OCULTARSE en móvil (`display: none !important;`)**.
      - *Motivo crítico*: El espacio horizontal en smartphones (360px-420px) es insuficiente para acomodar descripciones junto a otras columnas, provocando que el texto se aplaste verticalmente letra por letra ("D-E-S-C-R-I-P-C-I-Ó-N") o ensanche forzadamente la tabla generando scroll horizontal inferior.
  - **Control de Tablenav y Paginación en Móvil (Cero Superposición)**:
    - Las barras de navegación `.tablenav` (superior e inferior) deben tener `height: auto !important; min-height: unset !important; overflow: visible !important;` y flujo flexbox (`display: flex; flex-direction: column; align-items: stretch; gap: 12px;`).
    - Las acciones en lote (`.bulkactions`) ocupan el 100% de ancho con `select` flexible y botón `Aplicar` adyacente.
    - La paginación (`.tablenav-pages`) distribuye el conteo de elementos y los botones («, ‹, 1 de 1, ›, ») sin salirse ni montarse sobre los bordes de la tarjeta.
  - **Fila Hija Expandible (Child Row) 100% Adaptada al Viewport**:
    1. Incluir botón toggle `.aura-row-toggle` (`[+]` / `[-]` o chevron animado) en la columna principal `#`.
    2. Al pulsar el toggle, desplegar una fila hija (`.aura-child-row` / `.aura-child-card-wrapper`) con animación fluida `cubic-bezier(0.34, 1.56, 0.64, 1)`.
    3. La celda `td.aura-child-row-cell`, su contenedor `.aura-child-card-wrapper` y la tarjeta `.aura-child-card` deben ocupar exactamente el **100% del ancho visible de la pantalla** (`width: 100% !important; max-width: 100% !important; box-sizing: border-box !important; margin: 0 !important; overflow: hidden !important;`).
    4. **Estructura Interna de la Fila Hija (`.aura-child-card`)**:
       - **Hero Bar Superior (`.aura-child-hero-bar`)**: Icono representativo, título/descripción completa del registro, badges tipo píldora (`.aura-pill-badge`) y tarjeta destacada de importe/estado (`.aura-child-amount-card`). En móvil se apila verticalmente al 100% de ancho.
       - **Grid de Sub-Tarjetas (`.aura-child-sections-grid`)**:
         - *Datos Principales (`.aura-child-subcard`)*: Metadatos clave organizados en pares etiqueta-valor.
         - *Responsables/Usuarios*: Avatar circular, nombre y rol (`.aura-user-badge-card`).
         - *Notas/Detalle Completo (`.aura-subcard-full`)*: Bloque de observaciones con borde de acento.
         - **CRUCIAL**: Toda `.aura-child-subcard` DEBE tener `min-width: 0 !important;` para permitir que el CSS Grid se reduzca fluidamente sin expandir el ancho de la tabla.
         - En tablets (`<= 1024px`): Pasar a 2 columnas (`grid-template-columns: 1fr 1fr !important;`).
         - En móviles (`<= 768px`): El grid se transforma a 1 sola columna vertical (`grid-template-columns: 1fr !important;`).
       - **Dock de Acciones Rápidas (`.aura-child-action-dock`)**:
         - **PROHIBIDO** el uso de estilos inline con colores o anchos rígidos (`<button style="...">`).
         - Utilizar clases semánticas normalizadas (`.aura-btn-dock--view`, `.aura-btn-dock--approve`, `.aura-btn-dock--reject`, `.aura-btn-dock--edit`) con altura estándar táctil (36-40px), `display: inline-flex; align-items: center; justify-content: center;` y compatibilidad total con Modo Oscuro.
         - En móvil, apilar los botones al 100% de ancho con distribución vertical clara.
    - **Resultado esperado**: Cero scroll horizontal inferior en la pantalla móvil; lectura, revisión y acciones táctiles cómodas e inmediatas sin tener que deslizar la pantalla a la derecha.

- **✨ Control del Estado Vacío ("¡Todo al día!" / Empty States Limpios)**:
  - Cuando una consulta o filtro devuelva **0 registros** (`$total_items == 0` o `empty($items)`):
    1. **NO** renderizar el contenedor de la tabla vacío con headers huérfanos o doble mensaje superpuesto.
    2. Renderizar de forma limpia y exclusiva una tarjeta de Estado Vacío `.aura-empty-state.aura-glass-card` con icono temático grande (ej. `dashicons-yes-alt` verde o `dashicons-info`), título amigable (ej. *"¡Todo al día!"*) y mensaje explicativo (ej. *"No hay registros o transacciones pendientes para los filtros actuales"*).
    3. **Mostrar la tabla única y exclusivamente cuando existan datos reales para mostrar**, manteniendo la interfaz limpia, profesional y libre de ruido visual.

---

## 6. 📱 ADAPTABILIDAD RESPONSIVE TOTAL (BREAKPOINTS)
- **Laptops y Pantallas Medianas (<= 1360px / <= 1200px)**: Sidebar fluido de 290px, KPIs en 4 columnas compactas, ocultamiento selectivo de columnas de baja prioridad (`.column-created_at`, `.column-created_by`) y Child Rows en 2 columnas.
- **Tablets (<= 1024px)**: Layout en 1 columna, KPIs en 2 columnas, sidebar colapsable o drawer, ocultamiento de columnas secundarias (`.aura-col-tablet-hidden`, `.column-category`, `.column-created_at`, `.column-created_by`), Child Rows en 2 columnas (`grid-template-columns: 1fr 1fr !important;`), tabla adaptada al ancho sin scroll forzado.
- **Móviles Horizontales y Smartphones (<= 768px)**:
  - Tabla 100% fluida sin `min-width` rígido (cero scrollbar inferior).
  - **Fila principal de 3 columnas compactas**: `#ID` (con toggle y pill de tipo), `Fecha`, `Monto`.
  - **Ocultamiento estricto** de columnas secundarias y extensas (`.column-cb`, `.column-type`, `.column-description`, `.column-category`, `.column-created_by`, `.column-created_at`, `.column-acciones`).
  - Fila Hija (Child Row) ocupando el 100% del viewport visible con sub-tarjetas y dock de acciones apilados verticalmente en 1 sola columna (`grid-template-columns: 1fr !important;`).
  - Textos descriptivos con salto de línea automático (`white-space: normal !important; word-break: break-word !important;`).
  - Toolbar de filtros y acciones apilada al 100% de ancho con botones e inputs touch-friendly (altura 40px).
- **Móviles Verticales (<= 480px)**: KPIs en 1 columna, chips de fechas en 2 columnas, child subcards compactas con padding de 10px y gráfico compacto de 220px.

---

## 7. 🪟 SISTEMA UNIVERSAL DE MODALES UX/UI (MODAL DESIGN SYSTEM)
- **Desenfoque de Fondo Obligatorio (Backdrop Blur)**:
  - Al abrir cualquier modal del sistema (Detalle de Transacción, Rechazo, Autorización, Confirmación, etc.), el fondo detrás del modal DEBE desenfocarse intensamente para centrar la atención del usuario:
    - Overlay `.aura-modal-overlay`: `position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.75) !important; backdrop-filter: blur(10px) saturate(180%) !important; -webkit-backdrop-filter: blur(10px) saturate(180%) !important; z-index: 1 !important;`.
- **Estructura Flexbox Inquebrantable contra Cortes Inferiores**:
  - Para evitar que la acumulación de campos de datos desborde el modal y empuje el pie de botones fuera de la pantalla (corte inferior):
    1. **Contenedor Raíz (`.aura-modal`)**: `position: fixed; width: 100vw; height: 100vh; height: 100dvh; display: flex; align-items: center; justify-content: center; padding: 16px; box-sizing: border-box;`.
    2. **Caja Modal (`.aura-modal-container` / `.aura-modal-content`)**: `height: auto !important; max-height: min(86vh, calc(100dvh - 36px)) !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; border-radius: 14px !important;`.
    3. **Cabecera y Tabs (`.aura-modal-header`, `.modal-tabs-nav`)**: `flex: 0 0 auto !important; flex-shrink: 0 !important;`.
    4. **Cuerpo del Modal (`.aura-modal-body`) con Scroll Independiente**:
       - `flex: 1 1 auto !important; min-height: 0 !important; overflow-y: auto !important; overflow-x: hidden !important; -webkit-overflow-scrolling: touch !important;`.
       - **REGLA TÉCNICA CLAVE**: `min-height: 0 !important;` es OBLIGATORIO en Flexbox para permitir que el contenedor restrinja la altura y active el scroll interno, impidiendo que los datos expandan el modal verticalmente.
    5. **Pie de Acciones (`.aura-modal-footer`) Rígido y Visible**:
       - `flex: 0 0 auto !important; flex-shrink: 0 !important; position: relative !important; z-index: 5 !important;` (siempre anclado y 100% visible en la base del modal flotante).
- **Botón de Cierre Superior Nítido y Táctil (`.aura-modal-close`)**:
  - **PROHIBIDO** el uso de X pequeñas, tenues o difíciles de tocar en pantallas táctiles.
  - El botón superior debe ser circular (mínimo 36x36px o 38x38px), con fondo contrastante (`rgba(15, 23, 42, 0.65)` o `rgba(255, 255, 255, 0.15)`), borde sutil, icono centrado blanco `#ffffff`, `aria-label="Cerrar"` y animación hover/focus a color rojo (`#dc2626`).
  - En móviles (`<= 782px`): Ubicado con espacio seguro (`top: 8px; right: 8px;`) y margen derecho en el título para evitar que textos o montos se solapen con el botón.
- **Dock de Acciones Ultra-Compacto en el Pie (`.aura-modal-footer`)**:
  - **PROHIBIDO** apilar botones gigantes al 100% de ancho en columna o permitir `flex-wrap: wrap` que duplique o triplique la altura del footer cortándolo por abajo en móviles.
  - **OBLIGATORIO en Móvil (`<= 782px`)**: Dock horizontal deslizable táctil con altura fija estricta (`display: flex; flex-direction: row; flex-wrap: nowrap !important; overflow-x: auto !important; -webkit-overflow-scrolling: touch !important; min-height: 46-48px !important; max-height: 48px !important;`).
  - **Botones con Ancho Auto-Ajustado**: (`flex: 0 0 auto !important; width: auto !important; height: 32px !important; padding: 0 10px !important; font-size: 11.5px !important; white-space: nowrap !important;`).
  - **Distribución Horizontal Eficiente y Limpia**:
    - *Izquierda (`.modal-actions-left`)*: Botón secundario de escape `✕ Cerrar` (`#btn-close-modal-footer` / `.aura-modal-btn-close`).
    - *Derecha (`.modal-actions-right`)*: Botones operativos de decisión (`Editar`, `Aprobar`, `Rechazar`, `Eliminar`).
    - *PROHIBICIÓN DE BOTONES ACCESORIOS*: No saturar el modal con botones secundarios como "Duplicar" o "Exportar PDF" que compiten con las acciones principales de auditoría/revisión y consumen espacio horizontal innecesario.
  - La altura total del footer en móvil se mantiene en **46-48px exactos**, liberando el **80% del viewport útil para los datos de la transacción y garantizando que ningún botón se corte**.
- **Modales Secundarios (Rechazo, Autorización, Confirmación)**:
  - Todo modal secundario (ej. `#aura-rejection-modal` / `.aura-small-modal`) debe heredar la misma estructura: fondo con desenfoque, botón de cierre 'X', botón de Cancelar en footer, textarea con scroll y contenedor con `max-height: min(86vh, calc(100dvh - 36px))` y `overflow: hidden`.
- **Accesibilidad y Cierre Universal**:
  - El modal debe cerrarse de forma inmediata mediante:
    1. Clic en botón superior `.aura-modal-close`.
    2. Clic en botón inferior `.aura-modal-btn-close` / *"Cerrar"* / *"Cancelar"*.
    3. Clic fuera del modal en `.aura-modal-overlay`.
    4. Pulsación de la tecla `Escape` (`Esc`).
- **Compatibilidad Dark Mode Total**:
  - Fondo de tarjeta `#1e293b`, cabeceras degradadas azul/marino `#1e3a5f` a `#0f172a`, inputs y bloques descriptivos `#0f172a` con bordes `#334155` y footer oscuro `#0f172a`.

---

## 8. 🖨️ MOTOR DE IMPRESIÓN Y EXPORTACIÓN A PDF (@media print)
- **Membrete Corporativo Oficial (`.aura-print-header`)**:
  - Logotipo oficial del sitio + Razón social en mayúsculas (`#1e3a5f`) + subtítulo *"MÓDULO FINANCIERO — REPORTE OFICIAL"*.
  - Tarjeta central con el **Nombre del Reporte** y badge del período consultado (`01/01/2026 al 31/12/2026`).
  - Bloque derecho con: *Titular/Generado por*, *Fecha de Emisión* e insignia de *"Documento Oficial"*.
- **Ajuste al 100% de la Hoja A4**:
  - `@page { size: A4 portrait; margin: 10mm 12mm 12mm 12mm; }`.
  - Anulación de márgenes y flotaciones de WordPress (`#wpcontent`, `#wpbody-content`, etc.) con `width: 100% !important; margin: 0 !important; float: none !important;`.
- **Textos en Negro Nítido (`#0f172a` y `#000000`)**:
  - Anulación total de estilos Dark Mode durante la impresión, asegurando legibilidad máxima sobre papel blanco.
- **Gráfica y Leyenda Completa Sin Cortes**:
  - `max-height: none !important; overflow: visible !important;` en leyendas de gráficos con distribución en 2 columnas.
- **Limpieza de Impresión**:
  - Ocultar botones interactivos (`[+]`, `[-]`, `Gráfico`, `Expandir/Colapsar`, sidebars y menús de WordPress).
  - Reglas de color forzado `-webkit-print-color-adjust: exact; print-color-adjust: exact;`.
  - Pie institucional de confidencialidad (`.aura-print-footer`) con aviso legal y número de página.

---

## 9. 🛡️ REGLAS TÉCNICAS DE NO REGRESIÓN
1. **Reutilizar primero, crear después**: Usa las clases existentes en `assets/css/admin-styles.css`, `assets/css/aura-design-system.css` y controladores en `assets/js/aura-ui.js` (`AuraUI.initResponsiveTables()`, `AuraUI.initMobileTableLabels()`).
2. **No bloquear el scroll de la página**: No uses `overflow: hidden` en contenedores raíz o tarjetas que contengan tablas grandes o paginaciones.
3. **No usar `$(this).css(...)` con colores fijos en JavaScript**: Las interacciones hover deben manejarse con clases CSS para no romper el Modo Oscuro.
4. **PROHIBIDO usar `display: flex !important;` incondicional en `.aura-modal`**:
   - Usar `display: flex;` sin `!important` y agregar siempre `.aura-modal[style*="display: none"], .aura-modal.is-hidden { display: none !important; }` para que los modales respeten el estado oculto inicial y respondan correctamente a los métodos `.hide()`, `.fadeOut()` y al cierre por JavaScript.
5. **Anti-Caché Obligatorio en Assets de Admin**: Encolar siempre los archivos CSS y JS usando versionado dinámico basado en `filemtime` (`AURA_VERSION . '.' . @filemtime(AURA_PLUGIN_DIR . 'assets/css/archivo.css')`) para garantizar que el navegador cargue inmediatamente cualquier ajuste visual o responsivo.
6. **Validación y Compilación**:
   - Validar sintaxis PHP con `php -l`.
   - Sincronizar con `robocopy "c:\laragon\www\diserwp\wp-content\plugins\aura-business-suite" "C:\laragon\www\aura-business-suite" /MIR /XD .git .agents vendor node_modules /XF *.zip`.
   - Compilar el archivo ZIP con `php "c:\laragon\www\aura-business-suite\build-zip-sin-vendor.php"`.
```
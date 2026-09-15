# 📋 PROMPT MAESTRO GLOBAL DE MODERNIZACIÓN UX/UI, RESPONSIVE DESIGN, INPUT GROUPS, CHILD ROWS Y DARK MODE — AURA BUSINESS SUITE

> **Uso:** Copia este prompt maestro y utilízalo para modernizar, corregir y estandarizar cualquier pantalla, módulo o vista de **Aura Business Suite** (Finanzas, Inventario, Usuarios, Estudiantes, Vehículos, Biblioteca, Certificados, etc.).

---

```markdown
# PROMPT MAESTRO DE MODERNIZACIÓN GLOBAL UX/UI, RESPONSIVE DESIGN, INPUT GROUPS Y DARK MODE

Actúa como **Arquitecto Frontend Senior y Desarrollador Full-Stack WordPress** para el plugin **Aura Business Suite**. Tu objetivo es analizar, modernizar y estandarizar la página o módulo actual `[NOMBRE_O_URL_DE_LA_PAGINA]`, adaptándola rigurosamente a los mismos patrones de diseño, interactividad, responsividad, **Cabecera Principal (Hero Glass Card)**, **Barra de Navegación de Pestañas (Tabs Navbar)**, sistema de filas expandibles (Child Rows), **Input Groups integrados con iconos/símbolos estilo Bootstrap**, tooltips contextuales y compatibilidad con Modo Oscuro que ya están implementados y consolidados en **Áreas y Tipos de Área (`page=aura-areas`)**, **Transacciones Financieras**, **Reportes Financieros** y **Mi Dashboard Financiero**.

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

## 2. 🏛️ CABECERA PRINCIPAL (HERO GLASS CARD) Y BARRA DE NAVEGACIÓN DE PESTAÑAS (INCLUSIÓN Y ESTANDARIZACIÓN GLOBAL OBLIGATORIA)
- **Mandato de Estandarización Global**:
  - Toda pantalla, módulo o subsección de **Aura Business Suite** **DEBE implementar obligatoriamente la Cabecera Principal Hero** y, en caso de presentar múltiples submódulos, clasificaciones o vistas, **la Barra de Navegación de Pestañas (Tabs Navbar)** utilizando con exactitud las clases semánticas universales aquí especificadas.
  - **PROHIBIDO** inventar layouts de cabecera planos tipo WordPress estándar (`<h1>Título</h1><hr>`) o botones de navegación sueltos sin contenedor unificado.

### A. Cabecera Principal Hero Glass Card (`<!-- CABECERA PRINCIPAL -->`)
- **Estructura HTML Estándar Obligatoria**:
  ```html
  <!-- CABECERA PRINCIPAL -->
  <header class="aura-glass-card aura-page-header">
      <div class="aura-page-header__icon">
          <span class="dashicons dashicons-[icono-tematico-representativo]"></span>
      </div>
      <div class="aura-page-header__content">
          <h1><?php esc_html_e( 'Título Principal de la Pantalla', 'aura-suite' ); ?></h1>
          <p><?php esc_html_e( 'Descripción clara, concisa y orientada a la acción sobre el propósito del módulo.', 'aura-suite' ); ?></p>
      </div>
      <div class="aura-page-header__badges">
          <span class="aura-badge aura-badge-blue" data-tooltip="<?php esc_attr_e( 'Descripción contextual del indicador', 'aura-suite' ); ?>">
              <?php echo esc_html( $contador_principal ); ?> <?php esc_html_e( 'elementos', 'aura-suite' ); ?>
          </span>
          <span class="aura-badge aura-badge-gray" data-tooltip="<?php esc_attr_e( 'Descripción contextual del segundo indicador', 'aura-suite' ); ?>">
              <?php echo esc_html( $contador_secundario ); ?> <?php esc_html_e( 'clasificaciones', 'aura-suite' ); ?>
          </span>
      </div>
  </header>
  ```

- **Especificaciones Visuales y CSS**:
  1. **Contenedor Principal (`.aura-page-header`)**:
     - `display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 20px 24px; margin-bottom: 20px; border-radius: 14px; background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 100%); box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.25); color: #ffffff; box-sizing: border-box;`.
  2. **Caja del Icono Hero (`.aura-page-header__icon`)**:
     - `width: 52px; height: 52px; border-radius: 14px; background: rgba(255, 255, 255, 0.18); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid rgba(255, 255, 255, 0.25);`.
     - Dashicon interior: `font-size: 28px; width: 28px; height: 28px; color: #ffffff;`.
  3. **Contenido de Texto (`.aura-page-header__content`)**:
     - `flex: 1; min-width: 0;`.
     - `h1`: `margin: 0; color: #ffffff !important; font-size: 1.4rem; font-weight: 700; line-height: 1.25;`.
     - `p`: `margin: 6px 0 0; color: rgba(255, 255, 255, 0.88) !important; font-size: 0.88rem; line-height: 1.5;`.
  4. **Píldoras y Badges de Conteo (`.aura-page-header__badges`)**:
     - `display: flex; gap: 8px; flex-wrap: wrap; align-items: center;`.
     - Píldora individual (`.aura-badge`): `padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; background: rgba(255, 255, 255, 0.2); color: #ffffff; border: 1px solid rgba(255, 255, 255, 0.3); backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); white-space: nowrap;`.
  5. **Comportamiento Responsivo (Móviles <= 768px)**:
     - En pantallas móviles la cabecera se apila verticalmente (`flex-direction: column; align-items: flex-start; padding: 16px; gap: 14px;`).
     - `.aura-page-header__badges` pasa a ocupar `width: 100%;` con distribución fluida.

---

### B. Barra de Navegación de Pestañas (`<!-- BARRA DE NAVEGACIÓN DE PESTAÑAS -->`)
- **Estructura HTML Estándar Obligatoria**:
  ```html
  <!-- BARRA DE NAVEGACIÓN DE PESTAÑAS -->
  <nav class="aura-navbar aura-nav aura-glass-card">
      <button type="button" class="aura-navbar-item aura-tab-btn active" data-tab="tab-principal" data-tab-key="principal">
          <span class="dashicons dashicons-[icono-pestaña-1]"></span>
          <?php esc_html_e( 'Pestaña Principal', 'aura-suite' ); ?>
      </button>
      <button type="button" class="aura-navbar-item aura-tab-btn" data-tab="tab-secundaria" data-tab-key="secundaria">
          <span class="dashicons dashicons-[icono-pestaña-2]"></span>
          <?php esc_html_e( 'Pestaña Secundaria', 'aura-suite' ); ?>
      </button>
  </nav>

  <!-- PANELES DE CONTENIDO -->
  <section id="tab-principal" class="aura-tab-panel active">
      <!-- Contenido pestaña 1 -->
  </section>
  <section id="tab-secundaria" class="aura-tab-panel">
      <!-- Contenido pestaña 2 -->
  </section>
  ```

- **Especificaciones Visuales y CSS**:
  1. **Contenedor Navbar (`.aura-navbar` / `.aura-nav`)**:
     - `display: flex; gap: 8px; padding: 8px 12px; margin-bottom: 20px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05); overflow-x: auto; -webkit-overflow-scrolling: touch;`.
  2. **Botón de Pestaña (`.aura-tab-btn` / `.aura-navbar-item`)**:
     - `display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; border: none; background: transparent; color: #64748b; font-size: 13.5px; font-weight: 600; cursor: pointer; transition: all 0.2s ease; white-space: nowrap; outline: none;`.
     - Icono interior: `font-size: 18px; width: 18px; height: 18px; color: #94a3b8; transition: color 0.2s ease;`.
     - Hover: `background: #f1f5f9; color: #1e293b;`.
  3. **Estado Activo (`.aura-tab-btn.active`)**:
     - `background: #2563eb !important; color: #ffffff !important; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);`.
     - Icono en estado activo: `color: #ffffff !important;`.
  4. **Paneles con Transición Suave (`.aura-tab-panel`)**:
     - `display: none; animation: auraFadeIn 0.25s ease-out forwards;`.
     - Panel activo (`.aura-tab-panel.active`): `display: block;`.
     - `@keyframes auraFadeIn { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: translateY(0); } }`.
  5. **Compatibilidad Total con Modo Oscuro**:
     - `.aura-navbar`: `background: #1e293b !important; border-color: #334155 !important;`.
     - `.aura-tab-btn`: `color: #94a3b8;`.
     - `.aura-tab-btn:hover`: `background: #334155; color: #f8fafc;`.
     - `.aura-tab-btn.active`: `background: #2563eb !important; color: #ffffff !important;`.
  6. **Comportamiento JavaScript y Persistencia de URL (`?tab=...`)**:
     - Al hacer clic en un `.aura-tab-btn`, alternar la clase `.active` entre botones y entre paneles `.aura-tab-panel`.
     - Sincronizar dinámicamente la URL mediante `history.replaceState(null, '', newUrl)` con el parámetro `?tab=tabKey` para que al recargar la página o enviar formularios AJAX se mantenga la pestaña activa seleccionada.
     - Ajustar DataTables responsivos al cambiar de pestaña mediante `$.fn.dataTable.tables({ visible: true, api: true }).columns.adjust().responsive.recalc();`.

---

## 3. 💡 AYUDAS CONTEXTUALES, TOOLTIPS ENRIQUECIDOS TIPO TARJETA Y SOPORTE EMOJIS/DASHICONS (INCLUSIÓN OBLIGATORIA)
- **Análisis e Inclusión Automática Exigida**:
  - Al analizar y optimizar cualquier pantalla o módulo, el desarrollador **DEBE detectar y agregar automáticamente ayudas contextuales e iconos de ayuda** (`.aura-help-icon` con `dashicons dashicons-editor-help` o `.aura-tooltip-wrap`) en:
    1. **Tarjetas KPI / Métricas**: En cada tarjeta de resumen o indicador, explicando qué calcula o representa el valor.
    2. **Cabeceras de Tablas (`<th>`)**: En cada columna de la tabla, explicando el significado del dato.
    3. **Filtros y Selectores**: En cada etiqueta o selector de filtro, explicando el alcance del filtrado.
    4. **Campos de Formulario (`<label>`)**: En cada campo del formulario o modal, indicando su propósito o formato requerido.
    5. **Celdas de Datos Clave en Tablas**: En `#ID`, `Estado`, `Fecha`, `Categoría`, `Área/Programa`, `Monto`, `Método de Pago`, `Usuario/Tercero Vinculado` y `Creado por`.

- **🌟 Tarjetas Flotantes Enriquecidas (`.aura-tip-card`)**:
  - Para datos que contengan información multidimensional (usuarios, terceros, categorías, métodos de pago, estados contables), el tooltip se estructura como una **Tarjeta Visual de Alta Densidad Informativa**:
    ```html
    <div class="aura-tip-card">
        <div class="aura-tip-card-header">
            <!-- Avatar / Logo / Icono / Emoji Ampliado (48x48px) -->
            <div class="aura-tip-avatar-large">...</div>
            <div class="aura-tip-info">
                <div class="aura-tip-title">Título Principal / Nombre Comercial</div>
                <div class="aura-tip-subtitle">Subtítulo / @usuario / Razón Social</div>
                <div class="aura-tip-badges">
                    <span class="aura-tip-badge aura-tip-badge--company">🏢 Empresa</span>
                    <span class="aura-tip-badge aura-tip-badge--role">Proveedor</span>
                </div>
            </div>
        </div>
        <div class="aura-tip-card-body">
            <div class="aura-tip-row"><span>📄 Documento:</span> <strong>NIT 900.123.456-7</strong></div>
            <div class="aura-tip-row"><span>✉️ Correo:</span> <strong>contacto@empresa.com</strong></div>
            <div class="aura-tip-row"><span>📞 Teléfono:</span> <strong>+57 300 123 4567</strong></div>
            <div class="aura-tip-row"><span>📌 Concepto:</span> <strong>Pago a Proveedor</strong></div>
        </div>
    </div>
    ```

- **🎨 Regla Estricta para Iconos, Dashicons y Emojis (Anti-Fallback WordPress "W")**:
  - **Detección Dinámica**: Las categorías, áreas y conceptos pueden configurarse con Dashicons de WordPress (ej. `dashicons-cart`) **O** con Emojis / caracteres Unicode (ej. `🍕`, `💼`, `💰`, `🏥`, `📚`).
  - **PROHIBICIÓN CRÍTICA**: NUNCA colocar un emoji directamente dentro de la clase `.dashicons` (`<span class="dashicons 🍕"></span>`), ya que la fuente Dashicons no reconoce el emoji y hace fallback renderizando el icono de la **W de WordPress**.
  - **Patrón Obligatorio de Renderizado**:
    - Si el valor inicia con `dashicons-` o contiene `dashicons`: `<span class="dashicons dashicons-tag"></span>`.
    - Si es un Emoji o símbolo Unicode: `<span class="aura-cat-emoji" style="font-size: 22px; line-height: 1;">🍕</span>`.
    - Utilizar siempre el helper estandarizado `Aura_Financial_Categories::render_icon_html( $icon, $color, $extra_class )`.

- **🖼️ PREVISUALIZACIÓN Y AMPLIACIÓN FLOTANTE DE LOGOS, AVATARES Y FOTOGRAFÍAS (HOVER & TOUCH PREVIEW)**:
  - **Columnas de Logo / Imagen / Avatar**: En cualquier tabla o vista que incluya una columna de LOGO, imagen de perfil, carátula, avatar o fotografía (ej. `.column-logo`, `.column-avatar`, `.column-image`, `.column-photo`):
    - **Miniatura Compacta en Celda**: Mostrar la imagen en tamaño compacto estilizado (32×32px a 40×40px) con bordes suaves (`border-radius: 8px`), borde sutil `1px solid #e2e8f0` y cursor interactivo (`cursor: pointer;` o `cursor: zoom-in;`), con `user-select: none; touch-action: manipulation;`.
    - **Ampliación Flotante al Pasar el Cursor o Tocar (Hover & Touch)**: Al pasar el puntero por encima (en escritorio) o al tocar la miniatura (en pantallas táctiles / móviles / tablets), se debe desplegar una **Previsualización Flotante Ampliada en Alta Resolución** (120×120px a 240×240px o tarjeta emergente `.aura-floating-img-card` con la imagen nítida, título/nombre y `object-fit: contain;` / `cover;` con sombra profunda `box-shadow: 0 20px 40px -8px rgba(0,0,0,0.35)`).
    - **Estructura HTML Estándar Obligatoria en PHP**:
      ```html
      <td class="aura-table-logo-cell" style="text-align:center;">
          <div class="aura-tooltip-wrap aura-table-thumb-wrap">
              <div class="aura-table-thumb-preview aura-img-preview-trigger" data-img-url="<?php echo esc_url($img_url); ?>" data-img-title="<?php echo esc_attr($title); ?>">
                  <img src="<?php echo esc_url($img_url); ?>" width="36" height="36" alt="<?php echo esc_attr($title); ?>">
              </div>
              <div class="aura-tooltip-content">
                  <div class="aura-floating-img-card">
                      <img src="<?php echo esc_url($img_url); ?>" alt="<?php echo esc_attr($title); ?>">
                      <span class="aura-floating-img-title"><?php echo esc_html($title); ?></span>
                  </div>
              </div>
          </div>
      </td>
      ```
    - **REGLA DE ORO DE ESTABILIDAD Y CERO PARPADEO (ANTI-FLICKERING & STABLE HOVER)**:
      1. **Validación de `relatedTarget` en Salida**: NUNCA usar `mouseleave` en modo de captura (`true`) que cierre el tooltip al moverse entre elementos hijos (`img`, `div`, `span`). Se debe verificar siempre `e.relatedTarget`; si el puntero sigue dentro del contenedor o se mueve hacia el propio portal flotante `#aura-global-tooltip`, **el tooltip DEBE mantenerse abierto**.
      2. **Grace Period (Retardo de Gracia de 100-120ms)**: Al salir del área interactiva, programar el cierre con un retardo de ~120ms (`setTimeout`), cancelando de inmediato el cierre si el puntero vuelve a ingresar al disparador o al tooltip flotante.
      3. **Interacción con el Tooltip**: `#aura-global-tooltip` debe tener `pointer-events: auto !important;` para permitir posar el cursor sobre la tarjeta o imagen sin que se cierre abruptamente.
    - **REGLA DE ORO PARA DISPOSITIVOS TÁCTILES (TOUCH & TAP PINNING)**:
      1. **Fijado Táctil Estable**: En dispositivos móviles, tablets o pantallas táctiles, un toque sobre la miniatura abre y **fija (pin)** la vista flotante HD en pantalla sin parpadear ni cerrarse por eventos sintéticos de hover.
      2. **Anti-Colisión con Filas Hijas (StopPropagation)**: El evento de toque/clic sobre la miniatura debe detener la propagación (`e.stopPropagation()`) para **evitar que DataTables abra o cierre accidentalmente la Fila Hija (Child Row)** al intentar ver la foto.
      3. **Cierre Cómodo**: La vista se cierra suavemente al dar un segundo toque sobre la misma miniatura, al tocar en cualquier otra parte de la pantalla, al desplazarse con scroll (>30px) o al pulsar <kbd>Escape</kbd>.
    - **REGLA DE ORO DE ELEVACIÓN Y CERO RECORTES (SIEMPRE POR ENCIMA DE TODA LA INFORMACIÓN)**:
      1. **Portal Flotante Global Obligatorio**: Esta imagen ampliada/tooltip debe inyectarse directamente en el portal `#aura-global-tooltip` anclado a `document.body` con `position: fixed !important; z-index: 9999999999 !important; pointer-events: auto !important;`.
      2. **Cero Ocultamiento**: NUNCA debe quedar tapada por cabeceras `thead`, filas adyacentes, barras de paginación, scroll horizontal de la tabla (`.aura-table-inner-scroll`), overflow de modales o contenedores circundantes.
      3. **Fallback Elegante Anti-Rotura**: Si no existe imagen o falla la carga (`onerror`), mostrar un avatar de reemplazo con la inicial coloreada o icono Dashicon representativo (`dashicons-building`, `dashicons-admin-users`, `dashicons-format-image`), manteniendo siempre la misma altura de fila y la coherencia visual.

- **🖼️ Imágenes de Perfil y Logos Ampliados en Tooltips Compuestos (48×48px)**:
  - En tooltips de usuarios o entidades comerciales (`.aura-tip-avatar-large`), utilizar imágenes o avatares de **48x48px** con esquinas redondeadas (`border-radius: 12px; object-fit: cover;`), borde sutil y fallback a inicial coloreada si no existe avatar o logo.

- **REGLA DE ORO DE ELEVACIÓN Y CERO CORTES (PORTAL GLOBAL ANCLADO AL BODY)**:
  - **Portal Flotante Global Obligatorio**: Todo tooltip debe crearse e inyectarse directamente como hijo de `document.body` (`#aura-global-tooltip`) con `position: fixed !important; z-index: 9999999999 !important; pointer-events: auto !important;`.
  - **Soporte HTML y Tarjetas sin Escapar**: Cuando `data-tooltip` contenga etiquetas HTML (`<div class="aura-tip-card">...</div>`), el motor JS no debe escapar el código con `.text().html()`; debe inyectar el HTML directamente y agregar la clase `#aura-global-tooltip.has-card-content` para ajustar padding cero (`padding: 0 !important; max-width: 360px !important; border-radius: 14px; overflow: hidden;`).
  - **Cero Recortes por Overflow**: Ningún contenedor con `overflow: hidden`, `overflow-y: auto`, modal o celda de tabla puede recortar o desbordar el tooltip.
  - **Detección Dinámica de Coordenadas (`getBoundingClientRect()`)**: Si no hay espacio superior en el viewport, se orienta automáticamente hacia abajo (`.is-bottom`), y si colisiona con los bordes laterales, se auto-ajusta manteniendo la flecha alineada al disparador.
  - **Anti-Flicker en Eventos**: Usar delegación limpia con `mouseover`/`mouseout` y validación de `relatedTarget` (o `AuraUI.initTooltips()`).
  - En Modo Oscuro, el fondo permanece en `#0f172a` con borde `#334155`, sombras intensas `0 18px 40px -8px rgba(0,0,0,0.5)` y texto `#f8fafc`.

---

## 4. 🔤 INPUTS CON ICONOS Y SÍMBOLOS: INPUT GROUPS INTEGRADOS TIPO BOOTSTRAP (ANTI-DESBORDE Y ALINEACIÓN PERFECTA)
- **Problema Crítico y Anti-Patrón Prohibido**:
  - **PROHIBIDO** posicionar iconos descriptivos, Dashicons o símbolos dentro de inputs usando `position: absolute; right: 12px; top: 38px;` o coordenadas fijas sobre `.aura-form-field`.
  - *Causa del fallo*: Al variar la altura de las etiquetas `<label>` (por textos largos, tooltips `.aura-help-icon`, subtítulos explicativos o saltos de línea responsivos), las coordenadas fijas causan que el icono se salga del campo de entrada, quede flotando afuera en el fondo o solape bordes y textos.

- **Patrón Obligatorio Estilo Bootstrap (`.aura-input-group` + `.aura-input-group-text`)**:
  - Todo campo de entrada (`<input>`, `<select>`) que requiera un icono temático o símbolo identificativo (fechas, monedas `$`, categorías, áreas/sedes, métodos de pago, cuentas bancarias, terceros, usuarios, teléfonos, emails, referencias, etc.) **DEBE encapsularse en un contenedor flex unificado**:
    ```html
    <!-- Input con Icono Descriptivo Prepend -->
    <div class="aura-input-group">
        <span class="aura-input-group-text" id="addon-date">
            <span class="dashicons dashicons-calendar-alt"></span>
        </span>
        <input type="text" class="aura-datepicker" placeholder="dd/mm/yyyy" aria-label="Fecha" aria-describedby="addon-date" required>
    </div>

    <!-- Select con Icono Descriptivo Prepend -->
    <div class="aura-input-group">
        <span class="aura-input-group-text" id="addon-category">
            <span class="dashicons dashicons-tag"></span>
        </span>
        <select id="expense_category_id" name="expense_category_id" aria-describedby="addon-category" required>
            <option value="">Seleccionar categoría...</option>
        </select>
    </div>

    <!-- Campo de Monto con Símbolo Monetario ($) -->
    <div class="aura-input-group aura-amount-group">
        <span class="aura-input-group-text currency-symbol" id="addon-amount">$</span>
        <input type="number" id="amount" name="amount" step="0.01" min="0.01" placeholder="0.00" aria-describedby="addon-amount" required>
    </div>
    ```

- **Especificaciones Visuales y CSS del Componente**:
  1. **Contenedor Flex (`.aura-input-group`)**:
     - `position: relative; display: flex; flex-wrap: nowrap; align-items: stretch; width: 100%; border-radius: 8px; box-sizing: border-box; transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);`.
  2. **Bloque Prepend Adherido (`.aura-input-group-text`)**:
     - `display: inline-flex; align-items: center; justify-content: center; padding: 0 12px; font-size: 14px; font-weight: 500; line-height: 1; color: #64748b; background-color: #f1f5f9; border: 1px solid #cbd5e1; border-right: none; border-radius: 8px 0 0 8px; flex-shrink: 0; min-width: 42px; box-sizing: border-box;`.
     - Dashicon interior: `font-size: 18px; width: 18px; height: 18px; color: #64748b;`.
     - Símbolo monetario (`.currency-symbol`): `font-size: 16px; font-weight: 700; color: #0f766e; background-color: #f0fdf4; border-left: 3px solid #10b981; min-width: 44px;`.
  3. **Control Integrado (`input`, `select`)**:
     - `position: relative; flex: 1 1 auto; width: 1% !important; min-width: 0; margin: 0 !important; height: 42px !important; line-height: 40px !important; padding: 0 12px !important; border-radius: 0 8px 8px 0 !important; border: 1px solid #cbd5e1 !important; background: #ffffff !important; color: #1e293b !important; box-sizing: border-box !important;`.
  4. **Estado de Foco Unificado (`:focus-within`)**:
     - `.aura-input-group:focus-within`: `box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15) !important; border-radius: 8px;`.
     - `.aura-input-group:focus-within .aura-input-group-text`: `background-color: #eff6ff !important; border-color: #3b82f6 !important; color: #2563eb !important;`.
     - `.aura-input-group:focus-within .aura-input-group-text .dashicons`: `color: #2563eb !important;`.
     - `.aura-input-group:focus-within > input, .aura-input-group:focus-within > select`: `border-color: #3b82f6 !important; outline: none !important; box-shadow: none !important; z-index: 2 !important;`.
  5. **Validación de Errores (`.has-error`)**:
     - En error, todo el grupo resalta en rojo con `.aura-input-group-text` (`border-color: #ef4444; background: #fef2f2; color: #ef4444;`) e `input/select` (`border-color: #ef4444;`).
  6. **Compatibilidad Total con Modo Oscuro**:
     - `.aura-input-group-text`: `background-color: #0f172a !important; border-color: #334155 !important; color: #94a3b8 !important;`.
     - `input, select`: `background-color: #0f172a !important; border-color: #334155 !important; color: #f8fafc !important;`.
     - `:focus-within .aura-input-group-text`: `background-color: #1e293b !important; border-color: #3b82f6 !important; color: #60a5fa !important;`.

---

## 5. 🎛️ BARRA LATERAL DE FILTROS (SIDEBAR) Y BARRA SUPERIOR
- **Estructura Estándar**:
  - Contenedor `.aura-filters-card` con cabecera `.aura-filters-header` (título en mayúsculas `FILTROS Y CONFIGURACIÓN`, icono azul `#3b82f6` y botón colapsar `#toggle-filters` con flecha Dashicon).
  - Cuerpo `.aura-filters-body` dividido en secciones tipo tarjeta `.filter-section` con títulos `h4` y dashicons temáticos.
  - **Selector de Fechas y Período**: Usar grilla `.aura-date-range-row` con `.aura-date-input-wrap` elásticos (`width: 100%; min-width: 0; box-sizing: border-box;`) y botonera de chips rápidos `.aura-quick-date-chips` (`Este Mes`, `Mes Ant.`, `Trimestre`, `Este Año`). **Cero desbordamiento horizontal**.
  - **Barra Superior Inteligente**: Al ocultar filtros, el botón **"Mostrar Filtros"** aparece a la izquierda y los botones de acción rápida/exportación (`CSV`, `Excel`, `Imprimir`) aparecen automáticamente alineados a la derecha.

---

## 6. 📊 GRÁFICOS, ESTADÍSTICAS Y KPI CARDS
- **Tarjetas KPI (`.aura-report-kpis` / `.aura-kpi-grid` / `.aura-ud-stats-grid` / `.aura-areas-summary-grid`)**:
  - Grid auto-fit (`display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 20px;`).
  - Tarjeta individual (`.aura-stat-card` / `.aura-areas-stat-card`): fondo blanco `#ffffff`, borde `1px solid #e2e8f0`, radio `14px`, sombra suave `0 1px 3px rgba(0,0,0,0.05)`.
  - Cifras en negrita destacadas (`1.4rem` a `1.75rem`) con formato monetario o conteos claros.
  - Iconos en cajas redondeadas suaves (`.aura-ud-stat-icon-wrap`).
  - Tooltips de ayuda `.aura-help-icon` en cada etiqueta superior.
- **Gráficos Donut / Barras / Líneas (Chart.js)**:
  - **Layout en 2 Columnas (`.aura-donut-report-layout`)**: Gráfico centrado a la izquierda y **Leyenda HTML Interactiva con Scroll** a la derecha (`max-height: 260px; overflow-y: auto;`).
  - **Interacción Bidireccional**: Al pasar el ratón (hover) sobre un item de la leyenda, resalta el segmento del gráfico; al hacer clic, abre el drilldown de desglose de esa categoría.
  - **Leyenda Inteligente**: Cada fila incluye viñeta de color, nombre completo con tooltip, badge de subcategorías/estado, importe monetario y porcentaje destacado.
  - **Adaptabilidad Dark Mode**: El borde del Donut (`borderColor`) y las fuentes cambian automáticamente para fundirse con el fondo en modo oscuro.

---

## 7. 📋 TABLAS, RESPONSIVE DESIGN, BARRAS DE CONTROL (DATATABLES/TABLENAV) Y FILAS EXPANDIBLES (CHILD ROWS)
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

- **🎛️ Estandarización de Barras de Control y Paginación (DataTables / WP Tablenav)**:
  - **Barra Superior / Inferior (`.bottom`, `.top`, `.dt-layout-row`, `.tablenav`)**:
    - Disposición Flexbox fluida (`display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 14px; margin-top: 18px; padding-top: 14px; border-top: 1px solid #f1f5f9;`).
    - En Modo Oscuro, el borde superior pasa a `border-top: 1px solid #334155 !important;`.
  - **Selector de Registros (`.dt-length`, `.dataTables_length`)**:
    - Etiquetas en tipografía limpia `#475569` (12px, font-weight: 600; `#94a3b8` en Modo Oscuro).
    - Selector `<select>` estilizado con altura de 34px, borde `#cbd5e1`, radio de 6px, fondo `#ffffff` (en oscuro: fondo `#0f172a`, borde `#334155`, texto `#f8fafc` y `color-scheme: dark`).
  - **Contador Informativo (`.dt-info`, `.dataTables_info`)**:
    - Texto descriptivo legible `#64748b` (12px, font-weight: 500; `#94a3b8` en Modo Oscuro).
  - **Botonera de Paginación (`.dt-paging`, `.dt-paging-button`, `.paginate_button`)**:
    - Botones táctiles de 32x32px (o padding `0 10px`), radio de 6px, fondo `#ffffff`, borde `#cbd5e1` y texto `#334155` (en hover: fondo `#f1f5f9`, borde `#94a3b8`).
    - Botón de página activa (`.current`, `.active`): Fondo `#2563eb !important; border-color: #2563eb !important; color: #ffffff !important; box-shadow: 0 2px 6px rgba(37,99,235,0.25);`.
    - Botones deshabilitados (`.disabled`, `[aria-disabled="true"]`): Opacidad reducida a 0.45 con `pointer-events: none; cursor: not-allowed;`.
    - En Modo Oscuro: Botones en fondo `#0f172a`, borde `#334155`, texto `#cbd5e1` (hover `#1e293b`).
  - **Comportamiento Móvil (<= 768px)**:
    - La barra `.bottom` y `.tablenav` se apilan verticalmente (`flex-direction: column; align-items: center; text-align: center; gap: 12px;`).
    - El selector de longitud, contador y botones de paginación ocupan el 100% de ancho con distribución centrada sin desbordar el contenedor.

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

- **🖼️ Estandarización de Columnas de Logo, Avatar o Fotografía en Tablas**:
  - En cualquier tabla o vista que liste entidades con imagen (Empresas/Terceros, Vehículos, Libros, Usuarios, Estudiantes, Certificados, Áreas):
    - **Celda con Miniatura Interactiva**: La miniatura debe tener la clase `.aura-table-thumb-preview.aura-has-tooltip` o `.aura-table-logo-img`, tamaño compacto (32×32px a 40×40px), bordes redondeados y `cursor: pointer;` / `cursor: zoom-in;`.
    - **Tooltip / Popover con Imagen HD Ampliada**: El atributo `data-tooltip` debe contener la tarjeta de previsualización con la imagen ampliada (120×120px a 240×240px) y nombre identificador.
    - **Interacción Universal (Hover & Touch)**:
      - En computadoras de escritorio se activa al pasar el puntero (`hover`).
      - En dispositivos móviles o tablets se activa al tocar (`tap/touch`), permitiendo previsualizar la imagen con nitidez sin tapar la pantalla.
    - **Z-Index y Cero Ocultamiento**: El tooltip se renderiza a través del portal flotante anclado a `document.body` (`z-index: 9999999999 !important;`), asegurando que jamás quede oculto o cortado por bordes de celda ni scrolls internos.

- **✨ Control del Estado Vacío ("¡Todo al día!" / Empty States Limpios)**:
  - Cuando una consulta o filtro devuelva **0 registros** (`$total_items == 0` o `empty($items)`):
    1. **NO** renderizar el contenedor de la tabla vacío con headers huérfanos o doble mensaje superpuesto.
    2. Renderizar de forma limpia y exclusiva una tarjeta de Estado Vacío `.aura-empty-state.aura-glass-card` con icono temático grande (ej. `dashicons-yes-alt` verde o `dashicons-info`), título amigable (ej. *"¡Todo al día!"*) y mensaje explicativo (ej. *"No hay registros o transacciones pendientes para los filtros actuales"*).
    3. **Mostrar la tabla única y exclusivamente cuando existan datos reales para mostrar**, manteniendo la interfaz limpia, profesional y libre de ruido visual.

---

## 8. 📱 ADAPTABILIDAD RESPONSIVE TOTAL (BREAKPOINTS)
- **Laptops y Pantallas Medianas (<= 1360px / <= 1200px)**: Sidebar fluido de 290px, KPIs en 4 columnas compactas, ocultamiento selectivo de columnas de baja prioridad (`.column-created_at`, `.column-created_by`) y Child Rows en 2 columnas.
- **Tablets (<= 1024px)**: Layout en 1 columna, KPIs en 2 columnas, sidebar colapsable o drawer, ocultamiento de columnas secundarias (`.aura-col-tablet-hidden`, `.column-category`, `.column-created_at`, `.column-created_by`), Child Rows en 2 columnas (`grid-template-columns: 1fr 1fr !important;`), tabla adaptada al ancho sin scroll forzado.
- **Móviles Horizontales y Smartphones (<= 768px)**:
  - Cabecera `.aura-page-header` en disposición vertical fluida (`flex-direction: column; align-items: flex-start;`).
  - Barra de pestañas `.aura-navbar` con scroll táctil horizontal suave (`overflow-x: auto; -webkit-overflow-scrolling: touch;`).
  - Tabla 100% fluida sin `min-width` rígido (cero scrollbar inferior).
  - **Fila principal de 3 columnas compactas**: `#ID` (con toggle y pill de tipo), `Fecha`, `Monto`.
  - **Ocultamiento estricto** de columnas secundarias y extensas (`.column-cb`, `.column-type`, `.column-description`, `.column-category`, `.column-created_by`, `.column-created_at`, `.column-acciones`).
  - Fila Hija (Child Row) ocupando el 100% del viewport visible con sub-tarjetas y dock de acciones apilados verticalmente en 1 sola columna (`grid-template-columns: 1fr !important;`).
  - Textos descriptivos con salto de línea automático (`white-space: normal !important; word-break: break-word !important;`).
  - Toolbar de filtros y acciones apilada al 100% de ancho con botones e inputs touch-friendly (altura 40px).

---

## 9. 🪟 SISTEMA UNIVERSAL DE MODALES UX/UI Y BOTONERA PROFESIONAL
- **Arquitectura DOM y Centrado Perfecto (Zero Collision)**:
  - Para evitar colisiones de CSS globales, todo modal debe estructurarse con dos niveles claros:
    1. **Capa Overlay Exterior (`.aura-modal-overlay`)**: `position: fixed !important; top: 0; left: 0; width: 100vw; height: 100vh; height: 100dvh; display: flex !important; align-items: center !important; justify-content: center !important; padding: 16px; box-sizing: border-box; background: rgba(15, 23, 42, 0.75) !important; backdrop-filter: blur(10px) saturate(180%) !important; -webkit-backdrop-filter: blur(10px) saturate(180%) !important; z-index: 999999 !important;`.
    2. **Caja Modal Interior (`.aura-modal-container` / `.aura-modal-content`)**: `position: relative !important; margin: auto !important; width: 94% !important; max-width: 680px; height: auto !important; max-height: min(88vh, calc(100dvh - 32px)) !important; display: flex !important; flex-direction: column !important; overflow: hidden !important; border-radius: 14px !important; background: #ffffff !important; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25) !important;`.
    3. **PROHIBICIÓN CRÍTICA**: NUNCA aplicar la clase `.aura-modal` a la caja interna cuando `.aura-modal-overlay` ya esté actuando como contenedor raíz, para evitar que reglas globales `position: fixed; width: 100vw; height: 100vh` conviertan la tarjeta interna en un viewport fijo descentrado o incompleto.
- **Regla Estricta contra Iconos Duplicados en Botones**:
  - **PROHIBIDO** incluir textos que repitan símbolos que ya están presentes en el icono del botón (ej. un botón con `<span class="dashicons dashicons-plus-alt2"></span>` que además tenga el texto `+ Nuevo Registro`, produciendo un antiestético `+ + Nuevo Registro`).
  - Cada botón debe tener una sola representación clara del icono (bien mediante su Dashicon HTML o bien mediante el texto del botón, nunca ambos duplicados).
- **Estructura Flexbox Inquebrantable contra Cortes Inferiores**:
  - Para evitar que la acumulación de campos desborde el modal y corte el pie de botones:
    1. **Cabecera (`.aura-modal-header`)**: `flex: 0 0 auto !important;` con título claro y botón de cierre táctil.
    2. **Cuerpo del Formulario (`.aura-modal-body` / `.aura-form-grid`)**: `flex: 1 1 auto !important; min-height: 0 !important; overflow-y: auto !important; -webkit-overflow-scrolling: touch !important;`.
    3. **Pie de Acciones (`.aura-modal-footer`)**: `flex: 0 0 auto !important; position: relative !important; z-index: 5 !important;` (siempre anclado, limpio y 100% visible).
- **Botón de Cierre Superior Nítido y Táctil (`.aura-modal-close`)**:
  - Botón circular de 36x36px a 38x38px, centrado, con fondo suave, icono `✕`, `aria-label="Cerrar"` y animación hover a rojo `#dc2626`.
- **Accesibilidad y Cierre Universal**:
  - Cierre inmediato con: botón `✕`, botón `Cancelar`, clic en overlay exterior y tecla <kbd>Escape</kbd>.
- **Compatibilidad Dark Mode Total**:
  - Fondo de tarjeta `#1e293b`, bordes `#334155`, cabecera `#1e293b`, footer `#0f172a`, inputs `#0f172a` con bordes `#334155` y texto `#f8fafc`.

---

## 10. 🖨️ MOTOR DE IMPRESIÓN Y EXPORTACIÓN A PDF (@media print)
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

## 11. 🛡️ REGLAS TÉCNICAS DE NO REGRESIÓN
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

---

## 12. ✅ LISTA DE VERIFICACIÓN GLOBAL OBLIGATORIA (MANDATORY MODERNIZATION CHECKLIST)

> **Al modernizar o revisar cualquier pantalla de Aura Suite, verifica y cumple sin excepción cada uno de los siguientes puntos:**

- [ ] **1. Cabecera Principal Hero (`.aura-page-header`)**: Presenta icono en caja 52x52px (`.aura-page-header__icon`), título `h1` blanco, subtítulo `p` blanco translúcido, gradiente azul `#1e3a5f` a `#2563eb` y badges de conteo `.aura-badge` con `data-tooltip`.
- [ ] **2. Barra de Navegación de Pestañas (`.aura-navbar`)**: Si la pantalla tiene subsecciones, utiliza `.aura-navbar.aura-nav.aura-glass-card` con botones `.aura-tab-btn` (icono Dashicon 18px + texto), paneles `.aura-tab-panel` con animación fade-in y persistencia automática en URL (`?tab=...` o `?type=...`).
- [ ] **3. Inputs Groups Bootstrap (`.aura-input-group`)**: Todo input/select con icono descriptivo o moneda está encapsulado con `.aura-input-group` y `.aura-input-group-text` adherido. Cero iconos con `position: absolute; top: ...;` fijo.
- [ ] **4. Tooltips y Ayudas Contextuales**: Cada tarjeta KPI, columna `<th>`, filtro y label de modal incluye su `.aura-help-icon` o `.aura-tooltip-wrap`, renderizado a través del portal flotante `#aura-global-tooltip` en `document.body` sin cortes por overflow.
- [ ] **5. Tooltips Enriquecidos con HTML y Zoom HD de Imágenes en Tablas (Anti-Flicker & Touch Pinning)**: En toda tabla que contenga imágenes, logos, avatares o fotografías, ES OBLIGATORIO implementar la miniatura con hover zoom y la tarjeta de previsualización flotante HD (`.aura-floating-img-card` de 160×160px a 240×240px o `.aura-tip-card` con avatar 52×52px, badges y datos clave). El motor JS debe inyectar el HTML real mediante `#aura-global-tooltip.has-card-content` sin convertirlo a texto plano, utilizar verificación de `relatedTarget` y retardo de gracia (100-120ms) para cero parpadeo al mover el cursor, detener propagación (`e.stopPropagation()`) para que el toque en móvil/tablet no active accidentalmente la Child Row, y fijar la imagen en pantalla hasta tocar fuera o presionar Escape.
- [ ] **6. Tabla Responsive y Child Rows**: Cero scroll horizontal forzado en móvil; la fila principal móvil muestra 3-4 columnas compactas (`#ID`, `Estado/Fecha`, `Monto/Acción`), y al expandir el toggle `[+]` o pulsar la fila, la Fila Hija (`.aura-child-card`) se despliega ocupando el 100% del ancho con sus sub-tarjetas y dock de acciones apilados.
- [ ] **7. Modales Blindados**: Estructurados con `.aura-modal-overlay` (overlay exterior con blur) y `.aura-modal-content` (caja interior centrada con flexbox vertical, cuerpo scrollable y footer de botones 100% visible).
- [ ] **8. Modo Oscuro 100% Nativo**: Toda tarjeta, cabecera, navbar, input group, tabla, modal y tooltip cuenta con selectores de máxima especificidad para fondo oscuro (`#0f172a` / `#1e293b`), bordes (`#334155`) y texto blanco nítido (`#f8fafc`).
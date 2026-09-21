# Registro de Correcciones y Tareas

⏳ A veces al cargar un modal se demora en mostrarse, requiero modernizar esto mostrando Skeleton Loaders dentro del modal para esto y no tener la sensacion de que no carga nada, pon esto en el archivo `documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md`

X Cambia todas las versiones de este plugin de wordpress a la version 1.8.1 y que no aparezca nada de 1.7.9

⏳ El sistema de Aura Suite es muy grande ya que tiene varios modulos con diferentes funciones, hay alguna manera de implementar documentacion de uso que sirvan como tutoriales para que los que operen cualquier funcionallidad y tengan dudas sigan estos pasos?

✅ En La pagina del Calemndario `https://diserwp.test/wp-admin/admin.php?page=aura-calendar`, y en el frontend del calendario que en este caso lo vi en `https://diserwp.test/portal-del-instructor/`, no se si aparece en otras partes, quiero que adapte perfectamente el modo oscuro, parecido al modo oscuro del Calendario de Google, donde se pueden apreciar la divicion de los cuadros o cuadriculas de los dias en el mes, Semana, Dia, Agenda, tambien anliza en cualquiera de estas vistas que los textos se adapten a modo claro / oscuro, junto con los badge, tooltip, boton, tarjeta, modal, etc.

- **Resuelto**:
  1. **Alineación con Google Calendar Dark Mode**: Se implementó una paleta completa de modo oscuro inspirada en Google Calendar (`#181b21` fondo, `#20242c` superficie, bordes de cuadrícula nítidos en `#3c4043` y líneas menores punteadas).
  2. **Vistas de Mes, Semana, Día y Agenda**: En todas las vistas de FullCalendar tanto en WP-Admin (`class-calendar-admin.php`) como en el frontend (`class-calendar-frontend.php` / Portal del Instructor), las divisiones de las celdas y casillas horarias son claramente visibles con bordes nítidos. El día de hoy se resalta con píldora circular azul `#6366f1` y los días de otros meses se atenúan suavemente.
  3. **Controles, Modales y Componentes**: Se adaptaron al modo oscuro las pestañas de navegación (`.adp-nav-tabs` y `.adp-tab-btn`), la barra de filtros superior, todos los modales (Agendar clase, detalle rápido, asistencia, tareas, materias), chips de usuario, zonas drag & drop y el tooltip flotante enriquecido (`#aura-cal-event-tooltip`), garantizando contraste y legibilidad óptimos tanto en Modo Oscuro como en Modo Claro sin regresiones.

✅ En Calendario, al lado derecho del sidebar de wordpress creo que eseste `id="toplevel_page_aura-calendar"`, no muestra cuando navega entra cada una de las paginas, ya que no queda resaltado con blanco negrita en que pagina esta, al igual que el navbar `class="adp-nav-tabs"`, analiza y adapta las paginas de acuerdo al archivo `documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md`

- **Resuelto**:
  1. **Sincronización del Sidebar de WordPress (`#toplevel_page_aura-calendar`)**:
     - Se implementaron los filtros nativos de WordPress `parent_file` y `submenu_file` en `Aura_Calendar_Admin` (`modules/calendar/class-calendar-admin.php`). Esto garantiza que al navegar a cualquiera de las subpáginas o tabs (`aura-calendar`, `aura-calendar-programs`, `aura-calendar-grades`, `aura-calendar-tasks`, `aura-calendar-settings`), WordPress mantenga abierto el menú del Calendario y aplique automáticamente la clase `.current` al submenú específico.
     - Se añadieron reglas CSS específicas en `assets/css/calendar-admin.css` para que el submenú activo en `#adminmenu #toplevel_page_aura-calendar .wp-submenu li.current a` se resalte con texto **blanco en negrita** (`color: #ffffff !important; font-weight: 700 !important;`), fondo tintado (`rgba(99, 102, 241, 0.22)`) y acento visual izquierdo (`border-left: 3px solid #6366f1`), cumpliendo con el estándar visual en modo claro y modo oscuro.
  2. **Sincronización de Pestañas Superiores (`.adp-nav-tabs`)**:
     - En `templates/calendar/main.php`, se actualizaron los enlaces de las pestañas a las URLs canónicas de WordPress (`admin.php?page=aura-calendar-...`) en lugar de basarse únicamente en query params `&tab=`.
     - Se incorporó soporte bidireccional que detecta tanto el parámetro `page` como el parámetro retrocompatible `tab`, garantizando que la pestaña correspondiente reciba `.active` con `color: #ffffff` y `font-weight: 700`.
  3. **Alineación con el Design System (`TRACKER-MIGRACION-DESIGN-SYSTEM.md`)**:
     - Se reemplazaron estilos inline hardcodeados con `#fff` en `templates/calendar/tab-settings.php` por las variables CSS institucionales (`var(--aura-surface-alt)`, `var(--aura-border)`, `var(--aura-primary)`).

✅ En el modal de Añadir Materia en `https://diserwp.test/wp-admin/admin.php?page=aura-calendar&tab=programs`, no funcionan o no abre lo correspondiente para Subir Archivo o Añadir Enlace de Material Docente y Material para Alumnos, tambien en el programa creado y en cada una de las tarjetas de materias, los tooltips enriquecidos de las imagenes de perfil su texto no se adapta en modo claro y oscuro.

- **Resuelto**:
  1. **Desbloqueo de Subida de Archivos y Enlaces en Creación de Materias**:
     - Se eliminó la validación bloqueante en `assets/js/calendar-admin.js` (`if (!subjId)`) que impedía abrir el selector de archivos o el modal de enlaces al crear una nueva materia (`id = 0`).
     - En `modules/calendar/class-calendar-subjects.php` (`ajax_upload_material()`), se flexibilizó la lectura de parámetros (`audience`/`material_type`, `external_url`/`drive_link`, `title`/`file_name`) y se habilitó la creación y retorno inmediato de entidades de material en memoria cuando `$subject_id === 0`.
     - Al guardar la materia (`#form-subject-editor`), los arrays `teacher_materials` y `student_materials` se serializan en JSON y se persisten directamente en la base de datos a través de `Aura_Calendar_Subjects::save()`.
     - Se dotó al botón de eliminación de materiales (`.btn-remove-material`) de capacidad para descartar elementos tanto en base de datos como en memoria.
  2. **Micro-Modal Moderno para Enlaces de Material (`#modal-add-material-link`)**:
     - Se implementó un micro-modal específico en `templates/calendar/tab-programs.php` con campos para URL compartida (Google Drive, Dropbox, Web) y Título descriptivo, eliminando el uso de `prompt()` nativo del navegador.
  3. **Adaptación de Tooltips Enriquecidos (`#aura-av-tooltip`) en Modo Claro y Oscuro**:
     - Se rediseñó la tarjeta flotante de avatar (`#aura-av-tooltip`) utilizando variables semánticas:
       - **Modo Claro**: Fondo blanco de superficie (`var(--aura-surface)`), borde suave (`#e2e8f0`), sombra flotante multicapa, nombre en `#0f172a`, rol en `#4f46e5` y correo en `#64748b`.
       - **Modo Oscuro**: Fondo `#181b21`, borde `#3c4043`, nombre en `#f8fafc`, rol en `#38bdf8` y correo en `#94a3b8`.
     - Se ajustaron los bordes de los avatares apilados (`.aura-avatar-stack-item` y `.aura-av-more`) para eliminar halos desentonados.

⏳ En el frontend del `Portal Docente y Académico — Aura Business Suite`, las tareas que se asignan a los estudiantes, deben porder asignarles tareas grupales o igual para todos o Tareas, Controles de Lectura y Ensayos por estudiante ya que por ejemplo a cada estudiante que tenga asignado el Docente, esta leyendo un libro diferente y este docente puede pedir un resumen de cada libro diferente asignado a cada estudiante, analiza y corrige o crea de acuerdo a los estandares empresariales.

✅ Analiza que de acuerdo a los permisos en Gestión de Permisos y Roles (CBAC), se sincronice bien con el login al iniciar seccion en el frontend, aparezca los botones correspondientes al usuario o que tienen permiso el usuario como: Acceder al Panel Administrativo, Ir a mi Portal de Instructor, Ir a mi Portal de Estudiante y para todos el Cerrar Seccion y demas botones que un usuario tenga permiso en el sistemas de AURA SUITE

✅ Al desplegar una fila en la tabla de Caja Chica, los botones de Acciones Rápidas, los muestre de manera horizontal, solo ponerlos de manera vertical cuando la pantalla sea pequeña.

- **Resuelto**: Se reestructuró `.aura-child-card--sidebar-layout` y `.aura-child-actions-sidebar` en `assets/css/financial-accounts.css`. Ahora en escritorio las Acciones Rápidas se posicionan como una barra horizontal superior con botones alineados horizontalmente (`flex-direction: row; flex-wrap: wrap; width: auto;`). En pantallas pequeñas (`@media (max-width: 768px)`), los botones se apilan de manera vertical (`flex-direction: column; width: 100%;`).

✅ Tooltips Enriquecidos en Cuentas y Tablas en Bancos y Cuentas en Todas las tablas, analiza tambien que todo se ajuste a modo claro/oscuro y tambien que en Reembolsos a Personas Muestre el Icono predeterminado o Imagen de perfil.

- **Resuelto**:
  1. En `modules/financial/class-financial-accounts.php` (`ajax_list_reimbursements()`), se enriqueció la respuesta con avatar de WordPress (`get_avatar_url`), logo del tercero (`wp_get_attachment_image_url`), iniciales e icono predeterminado (`dashicons-businessman` o `dashicons-building`).
  2. En `assets/js/financial-accounts.js`, la tabla de Reembolsos a Personas ahora renderiza el avatar con efecto hover zoom y tarjeta `.aura-tip-card` del tercero con datos de contacto, saldo remanente y barra de avance de amortización.
  3. En `#aura-accounts-table`, `#aura-petty-cash-table`, `#aura-reimbursements-table` y `#aura-exchanges-table`, todos los tooltips fueron estandarizados a `.aura-tip-card` con tokens contrastantes eliminando colores oscuros inline duros, garantizando legibilidad óptima tanto en Modo Claro como en Modo Oscuro.

✅ Caja Chica y Reembolsos: Corrección de Publicación Automática a Transacciones Financieras y Trazabilidad

- **Resuelto**:
  1. En `modules/financial/class-financial-accounts.php`, se corrigió la inserción contable de transacciones para liquidaciones de caja chica (`ajax_update_petty_cash_status`) y pagos de reembolsos (`ajax_pay_reimbursement`), mapeando a las columnas reales del esquema (`related_user_id`, `third_party_id`, `expense_category_id`, `category_id`, `receipt_file`).
  2. En `modules/financial/class-financial-transactions-list.php`, se implementaron Badges interactivos de origen (`[📦 Caja #ID]` y `[👤 Reemb #ID]`) que enlazan directamente a las pestañas y registros correspondientes con tooltips informativos.
  3. En `assets/js/financial-accounts.js`:
     - Se incorporó prompt de confirmación y captura de motivo obligatorio al rechazar rendiciones y nota opcional de auditoría al aprobar.
     - Se añadió botón de acción rápida para "Liquidar Excedente" en la tabla y tarjeta de entrega de Caja Chica cuando los gastos comprobados superan lo entregado.

✅ En Bancos y Cuentas en Reembolsos, este si esta haciendo el Registro de la Transaccion en transacciones como ya lo hace Caja Chica?

- **Resuelto**:
  - **Sí, 100% comprobado y verificado**: En Reembolsos existen dos fases:
    1. **Registrar Deuda** (Botón *Registrar deuda*): Es un compromiso contable (cuenta por pagar/pasivo a favor del custodio o tercero), por lo que aún no hay desembolso de dinero.
    2. **Registrar Pago** (Botón *Pagar* en la fila o *Registrar pago*): Al pagarse el reembolso, el modal tiene marcada por defecto la casilla *"Registrar Transacción de Egreso en Libro Mayor"*. Con la corrección en `ajax_pay_reimbursement()`, el sistema genera inmediatamente la transacción de egreso en `wp_aura_finance_transactions` (con tipo `expense`, categoría, beneficiario y comprobante) y el movimiento bancario de débito en `wp_aura_finance_account_movements`.
    3. **Trazabilidad**: En la tabla de Transacciones Financieras aparece con el badge interactivo **`[👤 Reemb #ID]`** que te lleva directamente al registro de reembolso correspondiente.
    - Se comprobó mediante prueba de liquidación generando exitosamente la transacción **#378** vinculada al Reembolso **#2**.


✅ Analiza lo creado en `documentacion\Tutoriales` y corrige o actualiza todo lo nuevo o implemendado de Tutorial para Caja Chica y Reembolsos


✅ En el Módulo de Usuarios, no veo como asignar un estudiante que esta como usuario en wordpress, analiza el modulo de usuarios e integralo como debe ser a todo lo que se ha realizado en Calendario, Biblioteca, Cursos, Cuentas, Formularios, Finanzas, etc. Integra esto de forma correcta con el AURA Suite, y sus modulos a que corresponda o aplique, analiza porque no muestra nada en el dashboard:

- **Resuelto**:
  1. **Fix del Error Crítico 500 (`/wp-admin/admin.php?page=aura-students`)**:
     - Causa 1: En `modules/common/class-aura-ui.php` se llamaba a `Aura_Roles_Manager::get_role_label()`, método inexistente que generaba un `Fatal error: Call to undefined method`. Se implementó dicho método con mapeo completo de roles (`academic_student`, etc.) y se blindó `class-aura-ui.php` con fallback de seguridad `method_exists()`.
     - Causa 2: En `templates/students/list.php` línea 17 había un tag PHP plano sin escapar que filtraba código PHP al render HTML. Fue corregido.
     - Causa 3: Se verificó con PHP CLI que tanto la lista de estudiantes como el formulario renderizan con HTTP 200 OK y 0 errores fatales.
  2. **Integración Bidireccional Estudiantes ↔ Usuarios WordPress (`wp_users`)**:
     - **En Estudiantes (`modules/students/class-students-crud.php`, `templates/students/student-form.php` y `templates/students/list.php`)**:
       - Se agregaron endpoints AJAX `aura_students_search_wp_users`, `aura_students_link_wp_user` y `aura_students_sync_from_wp_user`.
       - Se añadió tarjeta interactiva de vinculación con WordPress con autocompletado en 1 clic (`⚡ Rellenar datos`), selector de usuarios WP existentes y opción de creación automática de cuenta WP con asignación automática del rol `academic_student`.
       - En la tabla de estudiantes se muestra el badge `@usuario` con enlace directo a su configuración CBAC si ya tiene cuenta, o `Sin cuenta WP`.
       - En el modal de detalle del estudiante se muestra la sección de Cuenta WordPress con enlace directo a CBAC.
  3. **En Módulo de Usuarios / CBAC (`templates/permissions-page.php` y `assets/js/permissions-page.js`)**:
     - Consulta indexada de vinculación a `wp_aura_students`.
     - En las tablas de Usuarios Activos y Usuarios WP Disponibles, se añadió el chip `[🎓 Estudiante #CODIGO]` en la identidad del usuario y datos de expediente en los tooltips enriquecidos `.aura-tip-card` y filas hijas desplegables con enlace directo a su ficha académica en Estudiantes.
     - En la Tarjeta Hero del usuario seleccionado (`$selected_user`), si ya es estudiante se muestra su expediente y botón directo `[Ver expediente académico]`. Si aún no es estudiante, se muestra el botón interactivo de acción rápida `[⚡ Registrar / Vincular como Estudiante]`, que crea/vincula su expediente en 1 solo clic mediante AJAX.
  4. **Convergencia con Calendario, Biblioteca, Cursos y Finanzas**:
     - Al estar sincronizado `wp_user_id` en `wp_aura_students` y tener el rol `academic_student`, el usuario tiene acceso inmediato a:
       - **Calendario Frontend**: detección por `Aura_Students_Frontend::get_student_by_wp_user()`.
       - **Biblioteca**: asignación y auditoría de préstamos de libros por `wp_user_id`.
       - **Cursos**: acceso autenticado al portal del estudiante `[aura_student_portal]`.
       - **Finanzas**: vinculación directa de pagos y transacciones mediante `related_user_id`.


⏳ Corrige el archivo `assets\css\aura-design-system.css`, con los que tenga de referencia para evitar esto y anotalo en `documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md`, para que no vuelva a suceder.

⏳ AGREGAR MODULO DE CONTACTOS con CRUD, sincronizado con los contactos de una cuenta de Google, quiero porde tener todos los contactos importantes de la organización en un modulo nuevo y que se sincronice con los terceros, proveedores, Personas Naturales, etc, todo sincronizado al 100% en los contactos de la cuenta de google, quiero tener todos los contactos de wordpress y terceros sincronizados en contactos de google y que pueda administrarlos desde el modulo.
⏳ Crear todo lo correspondiente de este modulo en:
    https://diserwp.test/wp-admin/admin.php?page=aura-suite
    https://diserwp.test/wp-admin/admin.php?page=aura-permissions&tab=profile-templates
    documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md
- Tambien sincroniza si registro un contacto en la cuenta de google sincronizada, aparezca en los contactos de este nuevo modulo.

✅ A un usuario Suscriptor le di todos los permisos en Gestión de Permisos y Roles (CBAC) y al iniciar sesión no me muestra Auditoría, Las Integraciones Contables:

- **Resuelto**: Se ajustaron los submenús en `aura-business-suite.php` (`render_audit_page` e `render_integrations_page`) para aceptar las capabilities `aura_finance_audit` y `aura_finance_integrations` con nivel de capability `'read'` en `add_submenu_page()`. Se verificó que ahora cualquier usuario con dichas capabilities asignadas en CBAC puede ver y acceder a Auditoría e Integraciones Contables sin ser bloqueado por WordPress.

✅ Tengo este usuario @sandram con estos permisos:
aura_finance_view_own, aura_finance_create, aura_finance_edit_own, aura_finance_delete_own
Dejo captura de pantalla de lo que deja ver o muestra, analiza todos los permisos y corrige que es lo que debe mostrar ya que veo que muestra el módulo de Vehículos y no lo tiene en sus permisos, también verifica como dar permiso a un usuario para que vea el botón de Auditoría y Integraciones Contables en Finanzas:

- **Resuelto**:
  1. En `modules/vehicles/admin/class-vehicle-admin.php`, se eliminó el filtro automático que concedía `aura_vehicles_access` a cualquier usuario por el mero hecho de pertenecer a un área en `wp_aura_area_users`. Ahora `@sandram` ya NO tiene acceso al módulo de Vehículos porque no tiene capabilities de vehículos en CBAC.
  2. En `aura-business-suite.php`, se condicionó el submenú de **Etiquetas** a `aura_finance_tags` y se habilitó el acceso a **Auditoría** (`aura_finance_audit`) e **Integraciones Contables** (`aura_finance_integrations`).
  3. En el login del frontend (`[aura_login]`), `@sandram` ve únicamente el botón de "Acceder al Panel Administrativo" (para gestionar sus finanzas) y "Cerrar Sesión", sin ver portales de estudiante ni de instructor.

⏳ Hay alguna manera de exportar las imagenes de perfil de usuarios y de todo lo correspondiente a areas y terceros, para luego ser importados, que me sugiere, enviar todo a la unidad compartida de drive o existe algo mejor usando lo nativo de wordpress














```bash
php build-zip.php
php build-zip-sin-vendor.php
```
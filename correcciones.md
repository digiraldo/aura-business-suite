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

✅ En el frontend del `Portal Docente y Académico — Aura Business Suite`, las tareas que se asignan a los estudiantes, deben poder asignarles tareas grupales o igual para todos o Tareas, Controles de Lectura y Ensayos por estudiante ya que por ejemplo a cada estudiante que tenga asignado el Docente, esta leyendo un libro diferente y este docente puede pedir un resumen de cada libro diferente asignado a cada estudiante, analiza y corrige o crea de acuerdo a los estandares empresariales.
- Analiza al final si ha creado o crea nuevas funciones o datos en la base de datos y que a la hora de actualizar en wordpress no presente errores.
- Los estudiantes se les asignara el nombre del curso o capacitacion o programa y asi poder asignar todos los alumnos a una tarea, profesor, directivo pero tambien se debe asignar de forma individual para tareas o responsabilidades individuales.
- Tambien sincroniza todo lo correspondiente con los Estudiantes en su Portal de estudiantes para que este vea que libro tiene asignado, que fecha de entrega, que tareas o actividades tiene, si se le asigno una herramienta del inventario, si ha llenado un formulario o encuesta y fecha de caducidad de encuesta, y que responsabilidad o actividades tiene y a que area, programa, materia, etc pertenece junto a que instructor, directivo o usuario debe entregarle la actividad o responsabilidad o tarea, o libro, o herramiente, etc.
- Recuerda tambien integrar al estudiante si tiene una deuda del costo del programa, y que se le puede adjuntar a este Portal del Estudiante, el Certificado, del modulo de certificados, integrar estudiantes con el programa al modulo de certificados.

- **Resuelto**:
  1. **Asignación Multimodal en Portal Docente (`[aura_teacher_portal]`)**:
     - **Idempotencia DDL Cero-Errores**: En `modules/calendar/class-calendar-setup.php`, se implementó `maybe_add_task_targeting_columns()` usando verificaciones defensivas con `SHOW COLUMNS FROM` y `suppress_errors(true)` para añadir las columnas `target_type` (`VARCHAR(30)`), `target_student_ids` (`TEXT`), y `student_assignments` (`LONGTEXT`) en la tabla `wp_aura_cal_tasks`.
     - **Tres Modos de Asignación**:
       - 👥 **Grupal**: Tarea y libro común para toda la cohorte matriculada en el programa.
       - 👤 **Individual**: Selección puntual de uno o más estudiantes matriculados específicos mediante checkboxes interactivos.
       - 🎲 **Diferenciada**: Cada estudiante matriculado recibe un libro o tema diferente de la Biblioteca y sus notas pedagógicas. Incluye el botón inteligente **"🎲 Repartir Libros Aleatoriamente"**, que distribuye equitativamente y sin esfuerzo todo el acervo bibliográfico entre los alumnos del programa con un solo clic.
     - **Gestión Docente de Estudiantes por Programa**: En `modules/calendar/class-calendar-tasks.php`, se implementó el método `get_program_students()` y el endpoint seguro `aura_cal_get_program_students` para consultar en tiempo real a los matriculados desde `wp_aura_student_enrollments` enlazando por `course_id` y por nombre de cohorte en `wp_aura_cal_programs`.
     - **Revisión de Entregas 360°**: En `get_submissions()`, el docente visualiza la lista completa de todos los estudiantes asignados a la tarea (tanto los que ya entregaron como los que están `⏳ Sin Entrega Aún`), con el chip del libro que le correspondió a cada uno y formularios directos para retroalimentación y calificación.
  2. **Sincronización Integral en el Portal del Estudiante (`[aura_student_portal]`)**:
     - **Tareas y Controles de Lectura Personalizados**: En `templates/students/frontend/tasks.php`, cada estudiante solo visualiza las tareas grupales o las que le fueron asignadas individualmente. En tareas diferenciadas, el sistema sobreescribe automáticamente los datos del libro mostrando su título, autor, portada en biblioteca e instrucciones específicas para él.
     - **Destinatario de Entrega y Pertenencia Académica**: Cada tarjeta de tarea exhibe claramente a qué Profesor, Directivo o Titular debe entregarse la actividad (`👤 Entregar a: ...`), junto con el Área académica (`🏛️ Área`) y el Programa (`🎓 Programa`).
     - **Estado Financiero y Paz y Salvo**: En `templates/students/frontend/portal.php`, se integró una tarjeta superior que calcula en tiempo real el saldo deudor (`balance_due`) de las inscripciones del estudiante en `wp_aura_student_enrollments`. Si tiene saldo pendiente, muestra el monto adeudado y acceso rápido a "Mis Pagos"; si se encuentra al 100% pago, le otorga la insignia oficial de **"🎖️ Paz y Salvo Académico y Financiero"**.
     - **Herramientas y Equipos de Inventario**: Se creó la nueva plantilla `templates/students/frontend/equipment.php` y su pestaña correspondiente en el portal ("Mis Herramientas"), consultando `wp_aura_inventory_loans` y `wp_aura_inventory_equipment` para que el alumno audite qué activos tiene a su cargo, fecha de retiro, límite de devolución, estado físico y quién se los entregó.
     - **Encuestas con Caducidad y Formularios**: Integrado con `wp_aura_form_assignments` y `wp_aura_forms`, mostrando la fecha de caducidad (`expires_at`), estado y formulario directo para contestar.
     - **Módulo de Certificados Integrado**: Corregido bug de consulta en `class-certificates-issuer.php`, `class-certificates-reports.php` y `bulk-issue.php` vinculando a `wp_aura_student_enrollments`. El estudiante visualiza y descarga sus diplomas en PDF con código QR y folio desde la pestaña "Mis Certificados".
  3. **Estilos y Dark Mode**: Se agregaron en `assets/css/aura-frontend-dark-mode.css` las reglas para radio-cards de targeting, filas diferenciadas, submission cards y tarjetas de inventario con contraste impecable en modo claro y modo oscuro.

✅ En el Portal Docente y Académico — Aura Business Suite, donde el Docente o profesor ve el listado de sus estudiantes de acuerdo a cada programa y materia y como este les asigna tareas o responsabilidades?

- **Resuelto**:
  1. **Ubicación y Visualización de la Nómina**:
     - **Pestaña "Mis Materias y Grupos" (`#tab-teacher-subjects`)**: En cada tarjeta de materia a cargo del docente, se implementó el botón destacado `👥 Estudiantes`. Al hacer clic, se abre una ventana modal interactiva (`#modal-teacher-students`) titulada con el nombre de la Materia y el Programa, que consulta en tiempo real mediante AJAX (`aura_cal_get_program_students`) la nómina completa de los alumnos matriculados en dicho programa académico.
     - **Pestaña "Tareas y Evaluaciones" (`#tab-teacher-tasks`)**: Junto al botón de creación de tareas, se integró el acceso directo `👥 Nómina de Estudiantes` para consultar a los estudiantes de cualquier cátedra sin salir de la gestión de entregas.
     - **Ficha Integral de cada Estudiante**: El docente visualiza para cada alumno su avatar, nombre completo, código institucional (`student_code`), enlace directo a correo (`mailto:`) y enlace directo a WhatsApp (`📞 wa.me/...`). Además cuenta con una **Barra de Búsqueda en Vivo** para filtrar al instante por nombre, apellido, código o email.
  2. **Asignación de Tareas y Responsabilidades**:
     - **Asignación Individual y Directa**: En la misma nómina, cada estudiante cuenta con el botón **`📝 Asignar Tarea / Responsabilidad`**. Al hacer clic, se abre de forma automática el modal de creación de tareas con el Programa y la Materia preseleccionados, la modalidad en **"👤 Individual"** y dicho estudiante marcado y seleccionado de manera exclusiva, colocando el foco listo para redactar el título, instrucciones y fecha de entrega.
     - **Asignación Grupal**: Botón superior **`➕ Asignar Tarea Grupal`** en la cabecera del modal para publicar actividades dirigidas a toda la cohorte simultáneamente.
     - **Asignación Diferenciada (Por Libros)**: Botón superior **`🎲 Repartir Libros`** en la cabecera del modal para abrir la asignación diferenciada, permitiendo asignar o barajar aleatoriamente un libro distinto de la biblioteca a cada uno de los alumnos matriculados para sus controles de lectura individuales.

✅ Donde el profesor y director o Coordinador de programa puede ver el listado de los estudiantes y ver sus estadisticas, que deben, que responsabilidades tienen y demas acciones necesarias de acuerdo a su gerarquia como director o coordinador de programa.

- **Resuelto**:
  1. **Jerarquía del Profesor / Docente Titular (Portal Frontend `[aura_teacher_portal]`)**:
     - **Listado por Cátedra**: En la pestaña *"Mis Materias y Grupos"* > Botón **`👥 Estudiantes`** en cada asignatura, el profesor consulta la nómina de los alumnos de su cohorte, con avatar, código, email y WhatsApp directo.
     - **Estadísticas de Entregas y Rendimiento**: En la pestaña *"Tareas y Evaluaciones"* > Botón **`📥 X Entregas`** de cada tarea, audita quién entregó y quién está pendiente (`⏳ Sin Entrega Aún`), lee resúmenes escritos (con conteo de palabras), descarga adjuntos y califica con nota (0-100) y retroalimentación pedagógica.
     - **Responsabilidades Asignadas**: En el calendario de clases (`#aura-teacher-fullcalendar`), las sesiones con estrellas (`⭐`) indican qué estudiantes tienen asignados roles de liderazgo o responsabilidades de monitoría de sesión.
     - **Acciones Permitidas**: Asignar tareas individuales directas, publicar tareas grupales, repartir libros diferenciados de la biblioteca y subir material pedagógico a Google Drive / Nube.
  2. **Jerarquía del Director o Coordinador de Programa (Panel wp-admin `aura-students`)**:
     - **¿Qué deben los Estudiantes? (Auditoría Financiera y Paz y Salvo)**: En `wp-admin/admin.php?page=aura-students-paz-salvo`, el director audita en tiempo real el **Costo Neto**, **Total Pagado**, **Saldo Pendiente (Deuda)** y **Cuotas Vencidas** de todos los estudiantes, con filtro por cohorte/programa y filtros rápidos (`🔴 Solo morosos` / `✅ Solo al día`), permitiendo enviar recordatorios de cobro a un clic por Email o WhatsApp y exportar el listado a CSV.
     - **Estadísticas Globales y de Rendimiento**: En `wp-admin/admin.php?page=aura-students` (Dashboard) y `page=aura-students-reports` (Reportes), dispone de métricas de retención, tasa de graduados, nuevos postulantes, gráficos comparativos de pagos recaudados vs. ingresos proyectados y distribución de perfiles/becas.
     - **Inscripciones y Becas por Cohorte**: En `wp-admin/admin.php?page=aura-students-enrollments`, aprueba postulantes asignando becas porcentuales (0% a 100%) y gestiona los estados de matrícula (`active`, `completed`, `withdrawn`, `suspended`).
     - **Ficha Integral 360° del Alumno**: En `wp-admin/admin.php?page=aura-students-list`, audita el historial académico, áreas de interés, datos personales y vinculación con su usuario de WordPress.
     - **Asignación de Materias y Docentes**: En `wp-admin/admin.php?page=aura-calendar&tab=programs`, define la malla curricular de cada programa, matricula cohortes y nombra a los profesores titulares.
  3. **Tutorial Institucional Creado**:
     - Se documentó el flujo operativo completo, matriz comparativa y diagramas de arquitectura en [`documentacion/Tutoriales/TUTORIAL-PORTAL-DOCENTE-Y-COORDINACION-ESTUDIANTES.md`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/documentacion/Tutoriales/TUTORIAL-PORTAL-DOCENTE-Y-COORDINACION-ESTUDIANTES.md).


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

✅ Al crear un usuario en "Asignar o Crear Usuario" (`admin.php?page=aura-permissions`), salía en el modal "Error de conexión. Inténtalo de nuevo.", con errores en consola:
- `[DOM] Password field is not contained in a form: <input type="password" id="aura_cu_password"...>`
- `/wp-admin/admin-ajax.php:1 Failed to load resource: the server responded with a status of 422 ()`

- **Resuelto**:
  1. **Envoltura en `<form>` Estándar y Accesibilidad DOM (`templates/permissions-page.php`)**:
     - Se envolvieron todos los campos de la pestaña "+ Crear Nuevo Usuario" en un elemento `<form id="aura-form-create-user" autocomplete="on" onsubmit="return false;">`.
     - Se asignaron los atributos estándar `name`, `autocomplete` (`given-name`, `family-name`, `email`, `username`, `tel`, `new-password`) y `minlength="8"`.
     - Esto eliminó por completo la advertencia del navegador `[DOM] Password field is not contained in a form` y permite a los gestores de contraseñas reconocer el formulario, además de permitir el envío del formulario al presionar Enter.
  2. **Validación Frontend Previa y Experiencia de Usuario (`assets/js/permissions-page.js`)**:
     - Se añadió validación en tiempo real en el cliente para verificar el formato de correo electrónico y exigir una contraseña de al menos 8 caracteres antes de enviar la petición al servidor.
     - Si falta alguno o no cumple el mínimo, se muestra inmediatamente el mensaje de error correspondiente y se enfoca el campo defectuoso sin realizar llamadas AJAX infructuosas.
     - Se vinculó el evento `submit` de `#aura-form-create-user` directamente al botón de acción `#aura-modal-btn-action-create`.
  3. **Corrección de Códigos HTTP y Manejo de Errores AJAX (`aura-business-suite.php` y `assets/js/permissions-page.js`)**:
     - En `ajax_create_user()` de `aura-business-suite.php`, se eliminaron los códigos de estado HTTP de error (422, 409, 403, 500) en `wp_send_json_error()`, adoptando el estándar nativo de WordPress AJAX donde las validaciones de negocio retornan JSON con `success: false`. Esto erradica el mensaje rojo en consola `Failed to load resource: the server responded with a status of 422`.
     - Se ampliaron las verificaciones de permisos en el backend para permitir a usuarios con `aura_admin_users_create`, `manage_options` o `create_users`.
     - En `assets/js/permissions-page.js`, se mejoró la función `.fail(function(xhr) { ... })` para que, en caso de cualquier eventual fallo de transporte o respuesta HTTP no-200, inspeccione `xhr.responseJSON?.data?.message` o analice `xhr.responseText` antes de recurrir al mensaje genérico de "Error de conexión".



Eliminación de la regla agresiva background-color: #6366f1 !important; en modo oscuro para respetar el color de cada evento con contraste legible estilo Google Calendar.


⏳ Hay alguna manera de exportar las imagenes de perfil de usuarios y de todo lo correspondiente a areas y terceros, para luego ser importados, que me sugiere, enviar todo a la unidad compartida de drive o existe algo mejor usando lo nativo de wordpress



⏳ Falta aplicar los Permisos de todo lo relacionado al calendario, por ejemplo, a un usuario le di todos los permisos Capacidades de:
di_finance_view_dashboard, di_finance_create, di_finance_manage_accounts, di_finance_manage_counterparties, aura_admin_users_manage, aura_admin_users_create, aura_admin_permissions_assign, aura_admin_settings, aura_admin_gdrive_config, aura_admin_notifications_view, aura_admin_modules_enable, aura_admin_backup, aura_admin_logs, aura_areas_view_own, aura_areas_view_all, aura_areas_budget_view, aura_areas_forms_manage, aura_areas_enrollment_manage, aura_areas_assign_user, aura_areas_budget_manage, aura_areas_types_manage, aura_areas_manage, aura_third_parties_view, aura_third_parties_create, aura_third_parties_edit, aura_third_parties_delete, aura_third_parties_create_wp_user, aura_cal_view_calendar, aura_cal_view_own, aura_cal_manage_calendar, aura_cal_delete_events, aura_cal_manage_programs, aura_cal_delete_programs, aura_cal_view_attendance, aura_cal_take_attendance, aura_cal_view_grades, aura_cal_manage_grades, aura_cal_delete_grades, aura_cal_view_tasks, aura_cal_manage_tasks, aura_cal_delete_tasks, aura_cal_submit_tasks, aura_cal_grade_tasks, aura_cal_sync_gcal, aura_cal_manage_settings
Y no le sale el boton de Agregar Clase
⏳ Cuando doy click en uno de los cuadros de un dia en el calendario, abre modal para crear evento, pero no asigna fecha ni hora de la zona de calendario donde se dio clic, deberia aparecer el dia mes y año junto conla hora si se selecciono en modo semana o dia en Inicio (Fecha y Hora) * y tambien la misma fecha pero media hora despues en: Fin (Fecha y Hora) *
⏳ Que en Descripción y Temario de Agendar Clase o Actividad, pueda guardar texto con este formato:
- Texto 1
- Texto 2
- Texto 3
- etc
⏳ Cuando se edite un evento, que cargue todo lo correspondiente de las bases de datos, no aparecen o cargan bien las fechas con horas. 
⏳ Pantalla Completa del calendario del portal de Profesores y Estudiantes, habilita esto.
⏳ Tooltips enriquecidos en los eventos del calendario del portal de Profesores y Estudiantes, que la imagen de perfil de profesores se vea mas grande en el tooltip
⏳ Sincronización exacta de las horas de eventos del calendario del portal de Profesores y Estudiantes y del backend con la zona horaria del instituto de forma local o Aura Suite, no con la zona horaria del navegador web o dispositivo.
⏳ Cada Evento Creado en Agendar Clase o Actividad, tiene un Color Distintivo de la Clase, que aparezca en el calendario ese color y que este correctamente adaptado a modo claro y oscuro.
⏳ Analiza de nuevo todo, por favor cambia todo lo correspondiente a Agendar Clase o Actividad o Agendar clase en el calendario, mas bien pon todo generico tipo calendario de google por ejemplo Crear Evento.


En el calendario de google esta sincronizando de esta manera que dejo como ejemplo:
[HAADIME-RA-27] Disciplinas Espirituales: Meditación
Código Corto del Programa: [HAADIME-RA-27]
Nombre de la Materia: Disciplinas Espirituales
Nombre del Evento: Meditación

- Quiero que el titulo del evento en el Calendario de Google se vea asi:
Meditación: Disciplinas Espirituales [HAADIME-RA-27]


```bash
php build-zip.php
php build-zip-sin-vendor.php
```
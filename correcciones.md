php build-zip.php



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
     - **Listado por Cátedra**: En la pestaña *"Mis Materias y Grupos"* Botón **`👥 Estudiantes`** en cada asignatura, el profesor consulta la nómina de los alumnos de su cohorte, con avatar, código, email y WhatsApp directo.
     - **Estadísticas de Entregas y Rendimiento**: En la pestaña *"Tareas y Evaluaciones"* Botón **`📥 X Entregas`** de cada tarea, audita quién entregó y quién está pendiente (`⏳ Sin Entrega Aún`), lee resúmenes escritos (con conteo de palabras), descarga adjuntos y califica con nota (0-100) y retroalimentación pedagógica.
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


✅ ⏳ Hay alguna manera de exportar las imagenes de perfil de usuarios y de todo lo correspondiente a areas y terceros, para luego ser importados, que me sugiere, enviar todo a la unidad compartida de drive o existe algo mejor usando lo nativo de wordpress
[!TIP]
**Sobre la exportación de imágenes de perfil de usuarios, áreas y terceros (consulta de la línea 203 de `correcciones.md`):**
Lo más robusto, portable y nativo en WordPress es **aprovechar la biblioteca de medios (`wp_posts` de tipo `attachment`) o generar un archivo ZIP empaquetado descargable**:
- En WordPress, las fotos de perfil y logos ya se guardan en la carpeta `/wp-content/uploads/` y se vinculan por ID o metadatos (`aura_profile_photo_id`, `aura_area_logo`, etc.).
- Para respaldar o migrar entre entornos: la mejor solución nativa es una herramienta de **Exportar/Importar Medios** en ZIP (o sincronización directa con Google Drive mediante la integración ya disponible en Aura Suite). Con Drive es ideal como copia de seguridad en la nube, mientras que para restauración local un paquete ZIP con su respectivo manifiesto JSON o CSV es 100% confiable y no depende de APIs externas.


✅ Falta aplicar los Permisos de todo lo relacionado al calendario, por ejemplo, a un usuario le di todos los permisos Capacidades de:
di_finance_view_dashboard, di_finance_create, di_finance_manage_accounts, di_finance_manage_counterparties, aura_admin_users_manage, aura_admin_users_create, aura_admin_permissions_assign, aura_admin_settings, aura_admin_gdrive_config, aura_admin_notifications_view, aura_admin_modules_enable, aura_admin_backup, aura_admin_logs, aura_areas_view_own, aura_areas_view_all, aura_areas_budget_view, aura_areas_forms_manage, aura_areas_enrollment_manage, aura_areas_assign_user, aura_areas_budget_manage, aura_areas_types_manage, aura_areas_manage, aura_third_parties_view, aura_third_parties_create, aura_third_parties_edit, aura_third_parties_delete, aura_third_parties_create_wp_user, aura_cal_view_calendar, aura_cal_view_own, aura_cal_manage_calendar, aura_cal_delete_events, aura_cal_manage_programs, aura_cal_delete_programs, aura_cal_view_attendance, aura_cal_take_attendance, aura_cal_view_grades, aura_cal_manage_grades, aura_cal_delete_grades, aura_cal_view_tasks, aura_cal_manage_tasks, aura_cal_delete_tasks, aura_cal_submit_tasks, aura_cal_grade_tasks, aura_cal_sync_gcal, aura_cal_manage_settings
Y no le sale el boton de Agregar Clase
✅ Cuando doy click en uno de los cuadros de un dia en el calendario, abre modal para crear evento, pero no asigna fecha ni hora de la zona de calendario donde se dio clic, deberia aparecer el dia mes y año junto conla hora si se selecciono en modo semana o dia en Inicio (Fecha y Hora) * y tambien la misma fecha pero media hora despues en: Fin (Fecha y Hora) *
✅ Que en Descripción y Temario de Agendar Clase o Actividad, pueda guardar texto con este formato:
- Texto 1
- Texto 2
- Texto 3
- etc
✅ Cuando se edite un evento, que cargue todo lo correspondiente de las bases de datos, no aparecen o cargan bien las fechas con horas. 
✅ Pantalla Completa del calendario del portal de Profesores y Estudiantes, habilita esto.
✅ Tooltips enriquecidos en los eventos del calendario del portal de Profesores y Estudiantes, que la imagen de perfil de profesores se vea mas grande en el tooltip
✅ Sincronización exacta de las horas de eventos del calendario del portal de Profesores y Estudiantes y del backend con la zona horaria del instituto de forma local o Aura Suite, no con la zona horaria del navegador web o dispositivo.
✅ Cada Evento Creado en Agendar Clase o Actividad, tiene un Color Distintivo de la Clase, que aparezca en el calendario ese color y que este correctamente adaptado a modo claro y oscuro.
✅ Analiza de nuevo todo, por favor cambia todo lo correspondiente a Agendar Clase o Actividad o Agendar clase en el calendario, mas bien pon todo generico tipo calendario de google por ejemplo Crear Evento.
- **Resuelto**:
Se han completado todas las correcciones y mejoras del módulo de Calendario de acuerdo con el plan aprobado:

### 🛠️ Resumen de lo implementado:
1. **Permisos y Botón de Creación:**
   - Corregida la verificación de permisos en las vistas del calendario ([`templates/calendar/main.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/main.php) y [`templates/calendar/tab-calendar.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/tab-calendar.php)) y agregado el alias de capacidad `aura_cal_create_events` en [`modules/common/class-roles-manager.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/common/class-roles-manager.php).
   - Ahora los usuarios con capacidades de calendario (`aura_cal_manage_calendar`, etc.) ven y pueden usar el botón para crear eventos.

2. **Clic en Celda: Hora Inicial y Fin Automático (+30 min):**
   - Se añadió `dateClick` y se optimizó el cálculo en [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js).
   - Al hacer clic en un día o bloque horario, se asigna la fecha y hora de inicio y se calcula automáticamente la hora de **Fin con 30 minutos después** en formato compatible `YYYY-MM-DDTHH:mm`.

3. **Formato Multilínea y Viñetas en Descripción:**
   - Modificado en [`modules/calendar/class-calendar-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php) con `wp_unslash()`.
   - Se añadió `white-space: pre-wrap; line-height: 1.5;` en [`templates/calendar/modal-partials.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/modal-partials.php) y [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css).
   - Se preservan las listas con viñetas tanto al guardar como al consultar el detalle y en el tooltip.

4. **Edición sin Desfase Horario y Preselección de Materias:**
   - Se eliminó la conversión desfasada a UTC (`toISOString()`), sustituyéndola por fechas locales generadas por el servidor (`start_local_iso` / `end_local_iso`).
   - Se implementó la carga y selección dinámica de materias (`loadSubjectsForProgram`) al abrir la edición de un evento.

5. **Sincronización con WordPress (Semana y Formatos de Fecha/Hora):**
   - El día de inicio de semana de la cuadrícula (`firstDay`) ahora toma el valor configurado en WordPress (`get_option('start_of_week')`).
   - Los formatos de fecha y hora se alinean con `get_option('date_format')`, `get_option('time_format')` y la zona horaria institucional de WordPress (`wp_timezone_string()`).

6. **Pantalla Completa en Portales Frontend:**
   - Se agregó el botón `⛶ Pantalla Completa` en el Portal Docente y en el Portal Estudiantil ([`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php)).
   - Redimensionamiento reactivo con `updateSize()` y soporte para salir con la tecla `Escape`.

7. **Tooltips Enriquecidos con Avatar Grande (44px) en formato Stack + Ring Animado:**
   - El tooltip flotante ahora muestra el avatar destacado a `44px` con un **Ring Animado** (`@keyframes aura-avatar-ring-pulse`), halo de luz concéntrico y gap oscuro tipo offset.
   - En caso de múltiples docentes o co-instructores, se agrupan en formato **Avatar Stack** (apilados horizontalmente con solapamiento, borde delimitador `#0f172a`, efecto hover flotante y badge `+N`).
   - Los líderes estudiantiles del evento también se visualizan en un stack compacto de avatares con indicador de roles.

8. **Colores Propios en Modo Oscuro:**
   - Eliminada la sobrescritura `!important` en [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css). Cada evento mantiene su color asignado en modo claro y oscuro.

9. **Terminología Genérica:**
   - Estandarizado a estilo Google Calendar: **"Crear Evento"**, **"Editar Evento"** y **"Guardar Evento"**.

Para más detalles, puedes consultar la guía completa en [walkthrough.md](file:///C:/Users/disan/.gemini/antigravity-ide/brain/a456aa39-3414-4a5f-a597-48172dac2d36/walkthrough.md).

Edited correcciones.md
Viewed correcciones.md:212-236


Se ha implementado el formato **Stack + Ring Animado** para los **Avatares Grandes (44px)** en los **Tooltips Enriquecidos** del calendario.

---

### 🎨 Detalle de las mejoras visuales implementadas

1. **Ring Animado (Pulsing Ring + Ring-Offset Gap):**
   - **Avatar Principal (44px):** El avatar principal cuenta con un borde índigo institucional (`#6366f1`), un separador de contraste perimetral oscuro de `2px` (`#0f172a`), y una animación `@keyframes aura-avatar-ring-pulse` que genera ondas y un halo de luz pulsante continuo con interpolación suave (`cubic-bezier(0.4, 0, 0.6, 1)`).

2. **Avatar Stack (Solapamiento Horizontal y Elevación):**
   - **Múltiples Instructores / Co-docentes:** Cuando una clase o evento cuenta con más de un instructor, se muestran apilados horizontalmente (`.aura-avatar-stack`) con solapamiento negativo (`margin-left: -14px`), borde protector de 2.5px (`#0f172a`) y sombra con efecto flotante al pasar el cursor (`transform: scale(1.18) translateY(-3px)` con elevación `z-index: 10`).
   - **Contador `+N`:** Si existen más de 2 co-docentes adicionales, se añade automáticamente el badge circular apilado (`.aura-avatar-more`) con el conteo de instructores restantes y su lista de nombres en el `title`.

3. **Stack de Liderazgo Estudiantil:**
   - La fila de estudiantes con responsabilidades/liderazgo en el evento también adopta el formato Stack (avatares de 24px solapados con borde perimetral, indicador de iniciales en caso de no tener foto, y badge `+N`).

4. **Soporte Global (Backend + Portales Frontend):**
   - Se expusieron las funciones `window.showEventTooltip` y `window.hideEventTooltip` en [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js), garantizando que tanto en el panel de administración como en el **Portal de Profesores** (`[aura_teacher_portal]`) y el **Portal de Estudiantes** (`[aura_student_schedule]`) el tooltip muestre exactamente el mismo diseño premium con Ring Animado y Stack.

---

### 📁 Archivos Actualizados

- [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js): Lógica de construcción del Stack de avatares, fallback con iniciales, anillo animado y exportación de funciones globales.
- [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css): Reglas y keyframes del Ring Animado, solapamiento horizontal, bordes y micro-interacciones hover.
- [`correcciones.md`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/correcciones.md) y [`walkthrough.md`](file:///C:/Users/disan/.gemini/antigravity-ide/brain/a456aa39-3414-4a5f-a597-48172dac2d36/walkthrough.md): Documentación técnica del cambio.



✅ En el calendario de google esta sincronizando de esta manera que dejo como ejemplo:
[HAADIME-RA-27] Disciplinas Espirituales: Meditación
Código Corto del Programa: [HAADIME-RA-27]
Nombre de la Materia: Disciplinas Espirituales
Nombre del Evento: Meditación
✅ Quiero que el titulo del evento en el Calendario de Google se vea asi:
Meditación: Disciplinas Espirituales [HAADIME-RA-27]

- **Resuelto**:

Se ha actualizado el formato del título para la sincronización con **Google Calendar**:

### 📅 Archivo modificado:
- [`modules/calendar/class-calendar-google-sync.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-google-sync.php)

### 📌 Formato implementado:
```
{Nombre del Evento}: {Nombre de la Materia} [{Código Corto del Programa}]
```

- **Ejemplo resultante en Google Calendar:**
  `Meditación: Disciplinas Espirituales [HAADIME-RA-27]`

*(En caso de que un evento no tenga materia asignada o no cuente con código corto, se ajusta elegantemente sin dejar dos puntos ni corchetes vacíos).*

La verificación de sintaxis PHP ha concluido sin errores.



✅ Al actualizar un evento, agrego o modifico los Profesor(es) o Instructor(es) a Cargo y no me aparecen los seleccionados correspondiente, solo me muestra uno y en este caso seleccione varios para probar el formato Stack + Ring Animado recien implementado.

- **Causa Raíz Identificada:**
  1. En la base de datos, la tabla `wp_aura_cal_event_instructors` conservaba un índice único heredado (`UNIQUE KEY event_instructor (event_id, instructor_id)`). Al intentar insertar un segundo o tercer docente sin suministrar explícitamente `instructor_id`, MySQL asignaba el valor predeterminado `0` para `instructor_id`, disparando un error de clave duplicada silencioso `Duplicate entry '{event_id}-0' for key 'event_instructor'`.
  2. En [`modules/calendar/class-calendar-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php), la consulta de instructores en `get()` no mapeaba correctamente `id = teacher_id` y `name = display_name`, causando que al editar solo se precargara el primer identificador.

- **Solución Implementada:**
  1. **Migración y Limpieza de Índices:** Se eliminó el índice duplicado obsoleto `event_instructor` y se añadió la migración automática en [`modules/calendar/class-calendar-setup.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-setup.php) para preservar la integridad de `UNIQUE KEY event_teacher (event_id, teacher_id)`.
  2. **Persistencia Dual Resiliente en PHP:** En `Aura_Calendar_Events::save()`, se detecta si la columna legacy `instructor_id` existe y se sincroniza en conjunto con `teacher_id`, permitiendo almacenar múltiples docentes simultáneamente sin colisiones.
  3. **Carga Enriquecida de Avatares:** Se actualizó `get_events()` y `get()` para consultar las fotos de perfil reales subidas en `wp_aura_students.photo_url` para cada instructor, con fallback a Gravatar.
  4. **Selector Visual de Profesores:** En [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js), las píldoras de selección `.aura-user-chip` ahora incorporan miniatura de avatar y mapean de manera bidireccional todos los IDs de profesores seleccionados tanto al abrir el modal de edición como al guardar.


✅ El boton de Pantalla completa, su texto no esta en blanco en modo oscuro y no se puede leer, el boton de Cambio de Modo Claro, Oscuro, su icono no se ve n modo Claro.

- **Solución Implementada:**
  1. **Icono de Tema en Modo Claro / Oscuro (Sin Duplicación):** WordPress no incluye de forma nativa el glifo `dashicons-moon`. Se añadió en [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css) la regla `.dashicons-moon:before { content: "🌙" !important; }` y `.aura-theme-toggle .dashicons-lightbulb:before { content: "☀️" !important; }`. Además, en [`assets/js/aura-frontend-theme.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/aura-frontend-theme.js) se mantuvo el contenido interior del span vacío (`icon.textContent = ''`) para evitar que el navegador renderice dos iconos superpuestos (uno del pseudo-elemento `:before` y otro del `innerHTML`), logrando una visualización limpia de un único icono perfectamente centrado.
  2. **Texto de Pantalla Completa en Modo Oscuro:** Se forzó la especificidad de color `#ffffff !important;` en modo oscuro para los textos `.fs-text` y los contenedores de los botones `#btn-toggle-teacher-fullscreen`, `#btn-toggle-student-fullscreen` y `#btn-toggle-fullscreen`, eliminando la ilegibilidad y asegurando un alto contraste estético.

✅ En el modal de Crear Evento, al final, al hacer clic en un toggle y que al seleccionarlo, me aparezcan unos eventos rapidos de agregar al calendario que al tener la fecha y hora de inicio ya definida me agregue automaticamente la duracion de 30 minutos como son:
Descanso, Introducción, Reflexión, Deportes, Lectura, Trabajo, Refrigerio, Desayuno, Almuerzo, Comida, Cena.
Que estos una vez en el calendario agregados, sean editables para poder cambiarles la duracion por ejemplo o la hora de finalización, profesores o instructores a cargo, etc.
CRUD de Eventos Genéricos en https://centromateo.org/wp-admin/admin.php?page=aura-calendar-settings adaptable y funcional.

- **Solución Implementada:**
  1. **Catálogo y Modelo Backend (`Aura_Calendar_Generic_Events`):**
     - Creada la clase [`modules/calendar/class-calendar-generic-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-generic-events.php) que gestiona los 11 eventos iniciales con sus iconos emoji, duraciones (30 min), colores y tipos (break, activity, class, workshop, exam, other).
     - Endpoints AJAX registrados con seguridad (nonce + capability check): `aura_cal_get_generic_events`, `aura_cal_save_generic_event`, `aura_cal_delete_generic_event`, `aura_cal_reset_generic_events`.
  2. **Interfaz de Administración (CRUD en Ajustes):**
     - En [`templates/calendar/tab-settings.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/tab-settings.php), se implementó la tabla completa del Catálogo con visualización de icono, nombre, tipo, duración en minutos, píldora de color, estado y acciones de Editar y Eliminar.
     - Modal dedicado `#modal-generic-event-editor` con inputs para Nombre, Icono emoji, Duración en minutos, Tipo de evento, Color Picker interactivo sincronizado en hexadecimal y Switch de Activo/Inactivo.
     - Botón para restablecer el catálogo a los 11 eventos iniciales recomendados.
  3. **Selector Rápido en Modal de Creación/Edición de Eventos:**
     - En [`templates/calendar/modal-partials.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/modal-partials.php), al final del formulario del editor se añadió el switch interactivo "⚡ Eventos Genéricos y Rápidos".
     - Al activarlo, despliega las píldoras de eventos rápidos (chips).
     - Al hacer clic en un chip: asigna el título, selecciona el tipo y color distintivo, auto-selecciona el primer programa disponible si no se había elegido uno, y **calcula automáticamente la fecha y hora de finalización sumando los 30 minutos** (o la duración asignada al evento) respecto a la hora de inicio definida.
     - **100% Editable:** El evento se vuelca a los campos estándar del formulario, permitiendo que el usuario modifique la duración, hora de inicio/fin, instructores a cargo, salón, enlaces y notas antes y después de agendar.

✅ Sincronización en Google Calendar para eventos multi-día con barra horizontal continua y formato de título estricto `{Nombre del Evento}: {Nombre de la Materia} [{Código Corto del Programa}]`.

- **Solución Implementada:**
  1. **Barra Larga Continua de Días en Google Calendar:**
     - En [`modules/calendar/class-calendar-google-sync.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-google-sync.php), se detecta si la fecha de inicio y la fecha de fin son diferentes (`$is_multi_day = ($start_date !== $end_date)`).
     - Para eventos multi-día, Google Calendar API v3 exige enviar el objeto de fecha `date` (formato `YYYY-MM-DD`) sin horas. Como la propiedad `end.date` en Google Calendar API es **exclusiva**, se calcula `$end_date_exclusive = date('Y-m-d', strtotime($end_date . ' +1 day'))`. De esta forma, Google Calendar renderiza de inmediato la **barra horizontal larga continua** a lo largo de todos los días comprendidos.
     - En la descripción se preserva el desglose del horario exacto: `⏰ Horario programado: DD/MM/YYYY HH:MM a DD/MM/YYYY HH:MM`.
     - Para eventos dentro del mismo día, se mantiene la precisión de hora con `dateTime` y zona horaria RFC3339.
  2. **Formato Estricto del Título (Summary):**
     - Se fijó la estructura requerida:
       `{Nombre del Evento}: {Nombre de la Materia} [{Código Corto del Programa}]`
     - Manejo de fallbacks limpio si no tiene materia o código para evitar caracteres sobrantes.






### Implementación Completada: Pantalla Completa Resiliente, Modo Claro/Oscuro Integral y Tooltips Enriquecidos (Portal Docente y Estudiante)

1. **Pantalla Completa Resiliente en Frontend:**
   - **Causa identificada:** Los botones `#btn-toggle-teacher-fullscreen` y `#btn-toggle-student-fullscreen` se registraban dentro de un bloque condicional `if (typeof FullCalendar !== 'undefined')` que se ejecutaba en `DOMContentLoaded`, momento en el cual el bundle externo de FullCalendar en el footer muchas veces aún no terminaba de cargar.
   - **Solución implementada:** Se desacopló la lógica a delegación global con `$(document).on('click', ...)`, se incorporó soporte dual (clase CSS fija al 100% de la ventana + API nativa `requestFullscreen()` del navegador), bloqueo de scroll del fondo con `body.aura-cal-fullscreen-active`, soporte para tecla `Escape` y evento `fullscreenchange`.
   - **Expansión y redibujado:** Se configuró `#aura-teacher-fullcalendar` y `#aura-student-calendar` con `flex: 1 1 auto` y `height: calc(100vh - 80px)`, disparando `calendar.updateSize()` reactivo inmediato y con retardo de 220ms.

2. **Adaptación Integral a Modo Claro y Modo Oscuro:**
   - En [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css), se definieron las variables CSS base en `:root` y `html[data-theme="light"]`, además de mapear los tokens `--aura-surface-card`, `--aura-surface-alt`, `--aura-border` y `--aura-text-...` para eliminar cualquier fallback blanco no deseado en modales y tarjetas en modo oscuro.
   - Estilización de alta legibilidad para botones secundarios (`.btn-secondary`), botones fantasma (`.btn-ghost`), badges institucionales (`.badge-indigo`, `.badge-emerald`, `.badge-amber`), modales (`#modal-student-event-detail`, modales de asistencia, tareas y nómina), chips de líderes y barra de herramientas de FullCalendar.

3. **Tooltips Enriquecidos con Avatar Grande (44px) en Stack + Ring Animado:**
   - Se conectaron de forma segura en ambos portales con `(window.showEventTooltip || showEventTooltip)`.
   - Se diseñó la adaptación de `#aura-cal-event-tooltip` en modo claro (tarjeta limpia con sombra suave y texto oscuro) y en modo oscuro (estilo glassmorphism slate oscuro con texto blanco).
   - Se incluyó el Avatar Grande de 44px con Ring Animado pulsante (`aura-avatar-ring-pulse`), stack de avatares de instructores secundarios solapados con elevación en `:hover` e indicador `+N`.




- En el Portal Docente y Académico — Aura Business Suite y de estudiantes, activa o realiza los tooltips enriquecidos, igual como se muestran en el calendario en el backend, y cuando doy clic en uno de ellos, no me abra la informacion en una alert si no en un modal moderno y cuando cierre el modal no me salga de pantalla completa si esta activado este modo en Portal Docente y Académico — Aura Business Suite y de estudiantes, tambien realiza este modal moderon en el calendario del backend.

### Solución Implementada: Tooltips Enriquecidos, Modal Moderno Unificado y Preservación de Pantalla Completa

1. **Tooltips Enriquecidos Idénticos al Backend en Ambos Portales:**
   - Se unificó el generador de tooltips flotantes (`#aura-cal-event-tooltip`) exponiendo `window.showEventTooltip` y `window.hideEventTooltip` desde [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js).
   - Se activó el tooltip en tiempo real tanto en el Portal Docente (`[aura_teacher_portal]`) como en el Portal de Estudiantes (`[aura_student_schedule]`) al pasar el cursor sobre las sesiones (`eventMouseEnter` y `eventMouseLeave`).
   - El tooltip detecta si la pantalla completa está activa (vía `document.fullscreenElement` o la clase `.aura-calendar-is-fullscreen`) y se anexa automáticamente dentro del contenedor activo en pantalla completa para ser visible sobre la capa superior (*top-layer*) del navegador.
   - Cuenta con el Avatar Grande de 44px con **Ring Animado Pulsante** (`aura-avatar-ring-animated`), stack de avatares con solapamiento y elevación en hover, indicador `+N` docentes, chips de programa, materia, horario, aula/virtual y badge de estudiante líder.

2. **Modal Moderno Unificado de Detalle de Evento (`modal-event-detail.php`):**
   - Se extrajo y modernizó el modal en un template independiente y reutilizable: [`templates/calendar/modal-event-detail.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/modal-event-detail.php), eliminando código duplicado.
   - **Diseño Ultra-Moderno:** Glassmorphism (`backdrop-filter: blur(6px)`), cabecera con badges de estado y tipo de sesión, avatar de 44px con ring animado y stack de instructores, banner personalizado de responsabilidad para el estudiante líder autenticado, grid 2x2 para Programa, Materia, Horario y Salón/Aula, caja de videoconferencia con botón de acceso directo («Unirse a la Sesión»), chips de liderazgo y área de descripción/temario.
   - **Acciones y Permisos:** Incluye botones de «Control de Asistencia», «Editar Evento» y «Eliminar» con verificación estricta de capabilities de WordPress (`aura_cal_take_attendance`, `aura_cal_manage_calendar`, `aura_cal_delete_events`, `manage_options`).
   - Se integró unificadamente en:
     * Backend: [`templates/calendar/modal-partials.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/modal-partials.php)
     * Portal Docente: Contenedor `.adp-card` de `#tab-teacher-schedule` en [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php).
     * Portal de Estudiantes: Contenedor `.adp-card` de `.aura-student-schedule-wrap` en [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php).

3. **Cero `alert(...)` y Apertura Dinámica:**
   - Se eliminó por completo el `alert(msg)` que existía en el Portal Docente al hacer clic en un evento.
   - Se conectó `eventClick` en el backend, en el portal docente y en el de estudiantes para que invoque la función unificada `window.openEventDetail(info.event)`.

4. **Preservación Total de la Pantalla Completa al Cerrar el Modal:**
   - **Causa raíz:** Anteriormente el `alert()` forzaba la salida de pantalla completa en los navegadores por políticas del SO, y los listeners globales de la tecla `Escape` ejecutaban la salida de pantalla completa sin verificar si había un modal abierto.
   - **Solución implementada:**
     * Al abrir el modal mientras la pantalla completa está activa, `#modal-event-detail` se anexa dinámicamente al contenedor que tiene el foco de fullscreen para garantizar visibilidad sin interrumpir el modo pantalla completa.
     * Al hacer clic en la «X», en el botón «Cerrar» (`.btn-close-evt-detail` o `[data-close-modal]`) o en el overlay, se ejecuta el cierre suave del modal sin alterar las clases ni la API nativa de pantalla completa.
     * En todos los listeners de la tecla `Escape` (backend, portal docente y portal estudiante), se verifica primero si existe algún modal abierto (`#modal-event-detail:visible, .aura-modal-overlay:visible, [id^="modal-"]:visible`). Si hay un modal visible, se cierra el modal con `fadeOut(150)`, se remueve la clase `aura-modal-open` del body y se cancela la propagación del evento (`e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation(); return false;`), **impidiendo de manera absoluta que el calendario salga de pantalla completa**.

- En wordpress o en Divi, que codigo escribo para que la sección, Fila o modulo tambien cambie de modo claro y oscuro en el frontend.

### Tutorial Completo Creado en `documentacion/Tutoriales`:
Se creó el tutorial detallado paso a paso en:
[`documentacion/Tutoriales/TUTORIAL-CALENDARIO-TOOLTIPS-MODALES-Y-MODO-OSCURO-DIVI.md`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/documentacion/Tutoriales/TUTORIAL-CALENDARIO-TOOLTIPS-MODALES-Y-MODO-OSCURO-DIVI.md)

El tutorial contiene:
1. **Calendario Académico:**
   - Explicación y funcionamiento de los tooltips enriquecidos (Avatar 44px + Ring animado + Stack de docentes secundarios).
   - Modal moderno unificado (`modal-event-detail.php`) con glassmorphism, videollamadas, chips de líderes y RBAC.
   - Preservación inteligente de pantalla completa al cerrar el modal (por "X", botón "Cerrar", overlay o tecla `Escape`).
2. **Adaptación en WordPress y Divi Builder:**
   - Fundamento técnico de las etiquetas `html[data-theme="dark"]` y `body.aura-dark-theme`.
   - Método 1: Uso directo de variables CSS nativas de Aura en el elemento principal del módulo de Divi.
   - Método 2: Uso de clase personalizada (`.seccion-adaptable-aura`) con reglas de modo claro y oscuro.
   - Método 3: Estilización global para todo el sitio en Divi.
   - Tabla comparativa de tokens y variables CSS disponibles.


### ✅ Selección y Determinación del Profesor/Usuario Principal (Titular de la Clase)

#### 1. ¿Cómo funciona el Usuario Principal en el Calendario?
- **En la Celda del Calendario (Vista Mes / Semana / Día):** Se proyecta el micro-avatar (18px) del **Profesor Titular** (`role = 'lead'`).
- **En el Tooltip Enriquecido (Hover):**
  - **Avatar Principal Grande (44px):** Ostenta el **Ring Animado Pulsante** (`@keyframes aura-avatar-ring-pulse`) con resplandor índigo/dorado y su nombre completo en el encabezado.
  - **Stack de Co-tutores / Asistentes (28px):** Los demás profesores asignados (`role = 'assistant'`) se apilan en cascada a la derecha con borde perimetral.
- **En el Modal Moderno de Detalles:** Se muestra el avatar titular destacado junto con los co-instructores y sus roles correspondientes.

#### 2. ¿Cómo se selecciona el Usuario Principal al agregar varios?
Se incorporó un selector interactivo visual con estrella ⭐ en los chips de selección de docentes del modal:
1. **Selector de Estrella ⭐ en cada Chip:**
   - En la lista de chips de profesores (`#evt-teachers-container`), cada docente cuenta con un botón interactivo de estrella (⭐ para el titular activo con etiqueta dorada `TITULAR`, y ☆ para los demás).
   - **Basta con hacer clic en la estrella ☆ del profesor deseado** para convertirlo al instante en el **Profesor Titular** de la sesión.
2. **Asignación Automática Inteligente:**
   - Si no has marcado ninguno y seleccionas el primer profesor, este se designa automáticamente como Titular principal.
   - Si desmarcas al profesor que era el titular, el sistema promueve de inmediato al siguiente profesor marcado como nuevo titular.
3. **Persistencia en Base de Datos y Backend:**
   - El formulario envía `primary_teacher_id` mediante un campo oculto.
   - En [`modules/calendar/class-calendar-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php), la inserción en la tabla de asignaciones (`aura_cal_event_instructors`) guarda al docente seleccionado con `role = 'lead'` y a los acompañantes con `role = 'assistant'`.
   - Las consultas SQL ordenan siempre con `ORDER BY CASE WHEN ei.role = 'lead' THEN 0 ELSE 1 END, ei.id ASC`, asegurando coherencia absoluta en FullCalendar, tooltips, modales y sincronización con Google Calendar.



### ✅ Adaptación de Eventos Rápidos a Modo Claro y Registro Detallado de Sincronización con Google Calendar

#### 1. Botones de Eventos Rápidos Adaptados a Modo Claro y Oscuro
- **Problema previo:** En el modal de Crear/Editar Evento, los botones de "Eventos Rápidos y Genéricos" tenían fondos blancos fijos (`background: #ffffff`) y tipografía gris clara inyectada por código inline, provocando que en modo claro no contrastaran adecuadamente o lucieran desalineados con el sistema de diseño.
- **Solución implementada:**
  - En [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css), se declararon clases de tokens dinámicos:
    - `.box-quick-generic-section`: fondo adaptable (`var(--aura-surface-alt)`).
    - `.btn-quick-generic-chip`: fondo interactivo adaptable a modo claro (`#ffffff`, texto `#1e293b`, borde `#cbd5e1`) y a modo oscuro (`#1e293b`, texto `#f1f5f9`, borde `#334155`).
    - `.btn-quick-generic-dur`: microbadge de duración estilizado con fondo sutil translúcido.
  - En [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js), la función `renderQuickGenericChips()` ahora asigna el color distintivo mediante variable CSS local `--chip-accent` y un borde izquierdo coloreado de 3.5px, garantizando contraste óptimo y fluidez al alternar entre temas.

#### 2. Dónde Ver los Errores y Causa Raíz de la Sincronización (Total: 9, Sincronizados: 3, Errores: 6)
- **Causa Raíz del Error:**
  - Al revisar la respuesta de la Google Calendar API se descubrió:
    `HTTP 403 Forbidden: "Service accounts cannot invite attendees without Domain-Wide Delegation of Authority."`
  - **Explicación:** Las Cuentas de Servicio (Service Accounts de Google Cloud) no tienen permitido invitar asistentes (`attendees`) a menos que cuenten con delegación de autoridad en Google Workspace.
  - Al sincronizar eventos que tenían profesores asignados, el plugin enviaba el arreglo `attendees`, lo que provocaba que Google Calendar rechazara los 6 eventos con profesores asignados (incluyendo diplomados y eventos multi-día de barra continua horizontal) y solo aceptara los 3 eventos genéricos sin docentes.
- **Solución:**
  - En [`modules/calendar/class-calendar-google-sync.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-google-sync.php), se retiró el campo restrictivo `attendees` del payload (los nombres y roles de los instructores ya se proyectan automáticamente de forma legible en la descripción del evento y en el título).
  - En eventos multi-día (`is_multi_day`), se envían fechas sin hora (`start.date` y `end.date` exclusivo +1 día), lo cual le indica a Google Calendar que dibuje la **barra continua horizontal superior (all-day span)** a lo largo de todos los días de duración.
  - En [`modules/common/class-google-calendar.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/common/class-google-calendar.php), se añadió la captura del error exacto de la API (`Aura_Google_Calendar::get_last_error()`) para evitar errores genéricos silenciosos.
- **¿Dónde ver los errores a partir de ahora?**
  - Se implementó un panel visual interactivo permanente: **📋 Registro de la Última Sincronización** en la pestaña **Ajustes** (debajo de "Herramientas de Sincronización Manual").
  - Muestra una tabla con cada evento procesado:
    - ID y Nombre del evento.
    - Rango y Horario programado.
    - Insignia de estado (✅ Sincronizado / ❌ Falló).
    - Detalle exacto o motivo reportado por la API de Google.
  - Los datos se guardan en la base de datos (`aura_cal_last_sync_log`) para consulta inmediata y se actualizan dinámicamente vía AJAX al pulsar "Sincronizar Todas las Clases Futuras".






### 26. Corrección de Carga de Avatares, Auto-Reparación de Base de Datos, Horas 06:00-24:00 y Sincronización de Fechas/Horas

#### A. Avatares de Profesores que No Cargaban (Backend y Frontend)
- **Causa Raíz:**
  1. Al incorporar la designación de **Profesor Titular ⭐**, se agregó la columna `role` a la tabla `wp_aura_cal_event_instructors` en el código PHP, y la consulta de eventos realizaba:
     `ORDER BY CASE WHEN ei.role = 'lead' THEN 0 ELSE 1 END, ei.id ASC` y `JOIN wp_users u ON u.ID = COALESCE(NULLIF(ei.teacher_id, 0), ei.instructor_id)`
  2. En Hostinger (y en instalaciones existentes), al actualizar el plugin vía archivo ZIP, WordPress **NO** ejecuta automáticamente el hook `register_activation_hook`. Además, como `DB_VERSION` estaba fijado en `1.5.0`, el instalador dbDelta (`create_tables`) nunca se activaba en Hostinger.
  3. Si la tabla no tenía la columna `role` o la columna `instructor_id`, MySQL generaba un error fatal de consulta:
     `Unknown column 'ei.role' in 'order clause'` o `Unknown column 'ei.instructor_id'`
     Al fallar la consulta, `$inst_rows` retornaba vacío (`[]`), por lo que `$primary_avatar` quedaba en blanco y los calendarios, tarjetas de eventos, tooltips y modales no mostraban ningún avatar.
  4. En el Portal del Instructor (`class-calendar-frontend.php`), la consulta a materias y tareas docentes ejecutaba `WHERE (s.teacher_id = %d OR s.teacher_ids LIKE %s)`. Sin embargo, las columnas reales en `wp_aura_cal_subjects` son `default_teacher_id` y `teachers`, arrojando otro error de MySQL `Unknown column 's.teacher_id'`.
- **Solución Implementada:**
  - **Auto-Migración y Reparación de Base de Datos:**
    - Se implementó `Aura_Calendar_Setup::maybe_add_event_instructors_columns()`, que se ejecuta automáticamente en cada inicio (`init`), asegurando que las columnas `teacher_id`, `instructor_id`, `role` (con default `'lead'`) y `notes` existan siempre.
    - Sincroniza bidireccionalmente los IDs (`teacher_id <-> instructor_id`) y asigna `role = 'lead'` a registros con rol nulo.
    - Se implementó `Aura_Calendar_Setup::repair_all_calendar_tables()`, que repara y verifica todas las 8 tablas del calendario (`wp_aura_cal_event_instructors`, `wp_aura_cal_subjects`, `wp_aura_cal_programs`, `wp_aura_cal_events`, `wp_aura_cal_tasks`).
    - Se incrementó `DB_VERSION = '1.7.3'`. Al subir el ZIP a Hostinger, en la primera carga se detecta la versión anterior y se auto-ejecuta la migración y dbDelta de forma transparente.
    - Se añadió un botón manual interactivo en **Calendario > Ajustes**: **🛠️ Sincronizar y Reparar BD**, permitiendo al administrador forzar la verificación y reparación en vivo con feedback inmediato.
  - **Consultas SQL Blindadas y Tolerantes:**
    - En [`modules/calendar/class-calendar-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php), en `get_events()` y `get()`, se inspeccionan dinámicamente las columnas de la tabla para construir un JOIN y ORDER BY seguros, evitando cualquier caída si falta alguna columna.
    - En [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php), se corrigieron las consultas de materias y tareas para buscar en `s.default_teacher_id` y `s.teachers`.

---

#### B. Rango de Horas en Vistas Semana y Día (06:00 a 24:00)
- **Causa Raíz:**
  - En [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js) y [`class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php), las vistas semanales y diarias estaban configuradas con:
    - `slotMinTime: '06:00:00'` / `'07:00:00'`
    - `slotMaxTime: '21:00:00'` / `'22:00:00'`
    - `allDaySlot: false`
  - Clases o actividades que terminaban a las 10:00 p.m. (22:00) o más tarde quedaban ocultas o recortadas al final del calendario. Además, al tener `allDaySlot: false`, no existía la franja superior para eventos continuos de varios días.
- **Solución Implementada:**
  - Se unificó la configuración en todos los calendarios (Backend, Portal Docente y Portal de Estudiantes):
    - `slotMinTime: '06:00:00'`
    - `slotMaxTime: '24:00:00'` (abarca toda la jornada hasta la medianoche).
    - `scrollTime: '07:00:00'` (desplaza la vista suavemente a las 07:00 am por defecto).
    - `allDaySlot: true` y `allDayText: 'Todo el día'` (permite la barra superior continua para diplomados y eventos multi-día).
    - `timeZone: 'local'` (asegura que las horas sigan la zona horaria del navegador sin desfases).

---

#### C. Sincronización de Fechas y Horas en Hostinger
- **Causa Raíz:**
  1. WordPress en `centromateo.org` está configurado en zona horaria **Ciudad de México** (UTC-6) con formato de hora `H:i`.
  2. Al enviar fechas a FullCalendar, se enviaban como string con espacio (`YYYY-MM-DD HH:mm:ss`), lo que en algunos navegadores causaba que se interpretaran como UTC en lugar de hora local, produciendo un desfase de 6 horas.
  3. En `Aura_Calendar_Events::save()`, los valores provenientes de inputs `<input type="datetime-local">` contienen una `T` (`YYYY-MM-DDTHH:mm`). Si se insertaban sin normalizar en columnas `DATETIME` de MySQL, generaban discrepancias o formatos heterogéneos.
- **Solución Implementada:**
  - **Normalización DATETIME:** En `save()` y `update_dates()`, se reemplaza cualquier `T` por espacio y se asegura formato canónico `YYYY-MM-DD HH:mm:ss`.
  - **Formato ISO para FullCalendar:** En `get_events()`, `start` y `end` se entregan con `T` (`str_replace(' ', 'T', $row->start_datetime)`), y con la opción `timeZone: 'local'` en el frontend y backend, FullCalendar renderiza exactamente las 07:30 a. m. a 10:00 p. m. tal como están guardadas en la base de datos de México.
  - **Auto-reparación tras actualización:** Al subir la nueva versión a Hostinger, el script de auto-migración detecta los eventos existentes, asegura las columnas faltantes y los campos `gcal_sync_status`, `student_leaders`, etc.

---

#### D. Instrucciones para Hostinger

1. Subir y reemplazar el plugin con el nuevo archivo generado `aura-business-suite.zip`.
2. En WordPress en Hostinger, simplemente recargar cualquier página del panel de administración (`https://centromateo.org/wp-admin/admin.php?page=aura-calendar`).
   - La base de datos se auto-migrará a la versión `1.7.3` en milisegundos en la primera carga.
   - Si se desea verificar o forzar manualmente, en **Calendario > Ajustes** se puede hacer clic en el nuevo botón **🛠️ Sincronizar y Reparar BD**.
3. Los avatares cargarán de inmediato tanto en las celdas del calendario como en los tooltips, modales y portales de profesores y estudiantes.
4. Las vistas de semana y día mostrarán desde las 06:00 hasta las 24:00 horas.

---

### 27. Navegación y URLs al Estilo Google Calendar (Deep Linking) en Backend y Frontend

Se implementó el enrutamiento reactivo idéntico al estándar de Google Calendar para todas las vistas y calendarios del sistema:

#### Formato de URLs Soportado
- **Mes:** `#/u/0/r/month/YYYY/M/D` (ejemplo: `#/u/0/r/month/2027/1/1`)
- **Semana:** `#/u/0/r/week/YYYY/M/D` (ejemplo: `#/u/0/r/week/2027/1/1`)
- **Día:** `#/u/0/r/day/YYYY/M/D` (ejemplo: `#/u/0/r/day/2027/1/1`)
- **Agenda / Lista:** `#/u/0/r/agenda/YYYY/M/D` (ejemplo: `#/u/0/r/agenda/2027/1/1`)

#### Alcance y Funcionamiento en Backend y Frontend
1. **Sincronización Reactiva en Tiempo Real:**
   - Cada vez que el usuario cambia de vista (Mes, Semana, Día, Agenda) o navega en las fechas (Siguiente, Anterior, Hoy), el evento `datesSet` actualiza automáticamente la barra de direcciones del navegador utilizando `window.history.replaceState` con el hash `#/u/0/r/{vista}/{año}/{mes}/{día}` sin recargar la página.
2. **Carga y Enlaces Compartibles (Deep Linking):**
   - Si un usuario ingresa directamente o comparte un enlace con la ruta (por ejemplo `https://centromateo.org/portal-del-instructor/#/u/0/r/month/2027/1/1` o en el wp-admin `admin.php?page=aura-calendar#/u/0/r/day/2026/10/15`), la función `parseCalendarUrlRoute()` detecta los parámetros de la URL e inicializa FullCalendar directamente en esa fecha y vista.
3. **Navegación Nativa del Navegador (Atrás / Adelante):**
   - Se conectó un listener para el evento `hashchange` en Backend, Portal del Docente y Horario del Estudiante. Al pulsar los botones Atrás o Adelante del navegador, el calendario cambia de vista o fecha instantáneamente sin recarga de página.
4. **Botón Directo a Google Calendar en Barra de Acciones:**
   - Se añadió un botón en la barra superior de acciones:
     - **Backend:** `#btn-open-gcal` en `templates/calendar/tab-calendar.php`.
     - **Frontend Instructor:** `#btn-open-teacher-gcal` en el Portal del Docente.
     - **Frontend Estudiante:** `#btn-open-student-gcal` en el Horario del Estudiante.
   - Su enlace (`href`) se sincroniza en vivo para abrir exactamente la misma vista y fecha en la aplicación web real de Google Calendar (`https://calendar.google.com/calendar/u/0/r/{vista}/{año}/{mes}/{día}`).
5. **Compatibilidad Visual y Modo Oscuro:**
   - Se diseñaron estilos dedicados para `.aura-btn-gcal-link` en `calendar-admin.css` y `aura-frontend-dark-mode.css`, asegurando legibilidad, bordes, estados hover e integración total con el modo oscuro.

---

### 28. Solución para Divi (CSS Libre vs Elementos) y Compactación de las Dos Primeras Filas del Calendario (Cabecera y Todo el Día)

#### A. Solución al Error de Divi (`Expected a 'FUNCTION' or 'IDENT' after colon`)
- **Causa Raíz:** En la captura enviada, la ventana de configuración de Divi se encuentra activa en la subpestaña **"CSS de formato libre"** (dentro de *Avanzado ➔ CSS personalizado*). Este editor exige que cualquier código CSS esté dentro de un bloque con la palabra clave `selector { ... }`. Al pegar propiedades CSS sueltas sin un selector que las contenga, el analizador de Divi genera el error de sintaxis en rojo.
- **Solución Implementada:**
  1. **Si usas "CSS de formato libre":** Se debe envolver con `selector { ... }`:
     ```css
     selector {
         background-color: var(--aura-surface-card, #ffffff) !important;
         color: var(--aura-text-primary, #0f172a) !important;
         border: 1px solid var(--aura-border, #e2e8f0) !important;
         transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
     }
     ```
  2. **Si usas "Elementos del módulo":** Solo debes hacer clic en el botón contiguo **"Elementos del módulo"** (a la derecha de "CSS de formato libre"). Allí aparece la casilla **"Elemento principal"** donde sí se pueden pegar las propiedades sueltas directamente sin `selector`.
  - Se actualizó el tutorial en [`documentacion/Tutoriales/TUTORIAL-CALENDARIO-TOOLTIPS-MODALES-Y-MODO-OSCURO-DIVI.md`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/documentacion/Tutoriales/TUTORIAL-CALENDARIO-TOOLTIPS-MODALES-Y-MODO-OSCURO-DIVI.md) detallando ambos caminos con capturas explicadas.

---

#### B. Compactación Visual de las Dos Primeras Filas y Eliminación de Scrollbars Secundarias
- **Causa Raíz:**
  1. En la vista semanal (`timeGridWeek`), la fila 1 (cabecera con los días de la semana) y la fila 2 (franja de "Todo el día") tenían múltiples fuentes de padding superior e inferior (en celdas `th`, `td`, y estilos heredados de Divi).
  2. El badge "🌅 Todo el día" se dividía en dos líneas de texto (`🌅 Todo` / `el día`), duplicando la altura requerida para la fila.
  3. En Windows, FullCalendar coloca un elemento con `overflow-y: scroll` en la celda espaciadora derecha (`.fc-scrollgrid-shrink`) de ambas filas para reservar el ancho de la barra de desplazamiento del cuerpo. Al tener alturas pequeñas (~30px), Windows dibuja dos cajas de scrollbar completas con flechas arriba/abajo en la cabecera y en all-day.
- **Solución Implementada:**
  1. **Eliminación Total de Padding Vertical Excesivo:**
     - `padding-top: 0 !important; padding-bottom: 0 !important;` en `.fc-scrollgrid-section-header` y `.fc-timegrid-all-day`.
     - Padding de `.fc-col-header-cell` ajustado a `3px 0 !important` y altura mínima de `.fc-daygrid-day-frame` reducida a `24px`. Ambas filas quedan inmediatamente juntas y sin espacios muertos.
  2. **Badge Compacto en Una Sola Línea:**
     - Se fijó `white-space: nowrap !important;` en `.fc-timegrid-axis-cushion` con padding `2px 7px !important` y bordes píldora redondeados `9999px`, manteniendo "🌅 Todo el día" en una sola línea pulcra.
  3. **Alineación 100% de Columnas (Eje Horario Sincronizado):**
     - Se unificó el ancho de la columna de hora `.fc-timegrid-axis` en exactamente `82px !important` en la cabecera, en la fila de Todo el día y en todas las franjas horarias inferiores, garantizando que LUN, MAR, MIÉ, JUE, VIE, SÁB y DOM mantengan su cuadrícula perfectamente recta y alineada.
  4. **Eliminación de Flechas de Scrollbar en Windows:**
     - Se aplicó `scrollbar-width: none !important;` y `::-webkit-scrollbar { display: none !important; width: 0 !important; }` en `.fc-scrollgrid-shrink .fc-scroller`. La celda preserva su ancho de reserva para alinear las columnas con el cuerpo inferior pero las flechas y la barra gris desaparecen por completo.
  5. **Neutralización de Tablas de Divi:**
     - Se forzó `border-collapse: collapse !important; margin-bottom: 0 !important;` sobre las tablas del calendario en el frontend y backend para anular cualquier espaciado externo introducido por Divi.




---

### 29. Corrección de Desfase Horario en Tooltip Enriquecido y Estabilidad de Arrastre (Drag & Drop / Resize)

#### A. Desfase de Horarios en Tooltip Enriquecido y Modal de Detalles (04:00 — 12:00 vs 10:00 — 01:30)
- **Causa Raíz:**
  1. En WordPress, `wp-settings.php` establece globalmente la zona horaria del motor PHP a UTC (`date_default_timezone_set('UTC')`).
  2. Las columnas `start_datetime` y `end_datetime` en MySQL almacenan la fecha y hora local del evento (ej: `2027-01-25 10:00:00`).
  3. Al consultar eventos en [`modules/calendar/class-calendar-events.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php), el código ejecutaba `wp_date(..., strtotime($row->start_datetime))`. Debido a que PHP corre en UTC, `strtotime()` interpretaba la cadena local como UTC, y posteriormente `wp_date()` aplicaba la compensación de zona horaria de WordPress (ej: `-6 horas` para la zona horaria de Ciudad de México / UTC-6), convirtiendo erróneamente las **10:00 am** en **04:00 am**, distorsionando las etiquetas generadas en el tooltip.
- **Solución Implementada:**
  1. Se implementó el método estático centralizado `format_local_datetime(string $datetime_str, string $format)` en `Aura_Calendar_Events`, el cual crea la fecha asociando explícitamente la zona horaria configurada en WordPress (`wp_timezone()`), preservando exactamente la hora almacenada en la base de datos sin desplazamientos.
  2. En [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js), las funciones `showEventTooltip` y `openEventDetail` ahora leen preferentemente los objetos de fecha de FullCalendar (`event.start` y `event.end`) formateados dinámicamente con `toLocaleTimeString()` respetando el formato de 12 horas o 24 horas del sitio WordPress.

---

#### B. Evento "Salta a Otro Lugar" al Estirar o Arrastrar (Drag & Drop / Resize)
- **Causa Raíz:**
  1. En `assets/js/calendar-admin.js`, la función `updateEventDates(event)` utilizaba `event.start.toISOString()` y `event.end.toISOString()`.
  2. El método estándar de JavaScript `.toISOString()` convierte **siempre** la fecha a UTC (Zulu time). Por ejemplo, un evento programado a las 10:00 am en una máquina en UTC-6 era convertido a `16:00:00Z` (+6 horas).
  3. Cuando el servidor recibía la petición AJAX `aura_cal_update_event_dates`, limpiaba la cadena y guardaba `2027-01-25 16:00:00` en MySQL como si fuera hora local.
  4. En consecuencia, cada vez que el usuario estiraba con el mouse o arrastraba un evento para moverlo, FullCalendar guardaba la hora desfasada +6 horas hacia adelante, provocando que el evento saltara a otra posición inesperada en el grid.
- **Solución Implementada:**
  1. Se creó la función utilitaria `formatLocalDateTime(d, includeSeconds)` en `calendar-admin.js`, la cual construye una cadena limpia `YYYY-MM-DD HH:mm:ss` en la hora local del navegador, sin conversión a UTC.
  2. Se actualizó `updateEventDates(event, revertFunc)` para enviar las fechas en formato local exacto y procesar la respuesta AJAX con las nuevas etiquetas de horario y fecha (`start_time_label`, `end_time_label`, `date_label`), reflejándolas de inmediato en `event.extendedProps`.
  3. Se conectó el callback `info.revert` tanto en `eventDrop` como en `eventResize`, de modo que si ocurre cualquier error de red o de permisos, el evento regresa de forma fluida a su posición original sin inconsistencias visuales.
  4. En el modal de edición al hacer clic en "Editar Evento" (`#btn-det-edit`), se eliminó el uso de `.toISOString()`, usando `formatLocalDateTime` para preservar la hora sin alteraciones al cargar el formulario.
  5. En [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php), se reemplazó el uso de `.toISOString().slice(0, 16)` por `formatLocalIso(d)` en la creación de tareas docentes con fecha límite, protegiendo también el frontend de desfases horarios.




---

### 30. Eliminación de la Fila "Todo el Día" y Rediseño de Pantalla Completa Móvil estilo Google Calendar

#### A. Eliminación Total de la Fila "Todo el Día" (Backend y Frontend)
- **Motivación y Requisito:** En interfaces como Google Calendar, las cuadrículas horarias de eventos regulares no intercalan filas de "Todo el día" si el flujo principal está enfocado en horas de clases, talleres y evaluaciones específicas. Se solicitó su remoción integral para optimizar el espacio vertical.
- **Implementación:**
  1. **Configuración FullCalendar:** Se fijó `allDaySlot: false` en las tres instancias del sistema:
     - Calendario del Administrador en [`assets/js/calendar-admin.js`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js).
     - Portal de Profesores (`window.teacherCalendarInstance`) en [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php).
     - Portal de Estudiantes (`window.studentCalendarInstance`) en [`modules/calendar/class-calendar-frontend.php`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php).
  2. **Reglas CSS Definitivas:** En [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css) y [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css), se forzó `.fc-timegrid-all-day, .fc-scrollgrid-section-all-day { display: none !important; height: 0 !important; border: none !important; }` garantizando que no se reserve ni 1px de espacio superior.

---

#### B. Rediseño de Pantalla Completa estilo Google Calendar (0 Márgenes / 0 Padding en Móviles)
- **Motivación y Requisito:** En dispositivos móviles, los márgenes, paddings de tarjetas y títulos de encabezado consumían hasta el 40% del área visible. Se requería que al activar pantalla completa, el calendario ocupe el 100% de la pantalla (`100vw` × `100dvh`) con cero espacios muertos idéntico a la app de Google Calendar.
- **Implementación:**
  1. **Contenedor Fullscreen `100vw × 100dvh` Sin Márgenes ni Relleno:**
     - En `.aura-calendar-is-fullscreen`, se eliminó todo padding (`padding: 0 !important`), bordes (`border: none !important`) y esquinas redondeadas (`border-radius: 0 !important`), ocupando de borde a borde toda la pantalla.
  2. **TopBar Compacta estilo Google Calendar (48px de alto):**
     - La cabecera `.aura-calendar-top-header` pasa a un formato de barra de aplicación de 48px de altura con fondo continuo, título conciso, botón de alternancia de pantalla completa y acceso directo a Google Calendar.
     - Los textos descriptivos largos de ayuda (`.aura-calendar-hint`) se ocultan automáticamente en pantalla completa para no robar espacio vertical.
  3. **Optimización Móvil Específica (`@media (max-width: 768px)`):**
     - El eje horario `.fc-timegrid-axis` se reduce de 82px a 44px, otorgando más del 85% del ancho de la pantalla a las columnas de los días.
     - Botones y cabecera de FullCalendar compactos con padding de 4px y tipografía nítida para máxima comodidad táctil sin desbordamientos.

---

#### C. Renderizado de Tarjetas de Eventos idéntico a Google Calendar
- **Implementación:**
  1. Se implementó la función unificada `renderGoogleStyleEvent(arg, currentUserId)` tanto en el panel administrativo como en los portales de Profesor y Estudiante.
  2. **Eventos en Vista Semanal/Diaria (`timeGrid`):**
     - Si duran menos de 40 min: formato compacto horizontal en 1 línea (`Hora` • `Título`).
     - Si duran 40 min o más: bloque vertical espacioso con **Título en negrita destacada**, horario claro (`10:00 – 13:30`), aula / profesor (`📍 Chimenea` / `👨‍🏫 Tomas Vidal`) y chip sutil de líder estudiantil (`⭐ Líder`).
  3. **Eventos en Vista Mensual (`dayGridMonth`):**
     - Formato de píldora redondeada limpia estilo Google Calendar con hora destacada y título sin cortes abruptos.

### 31. Optimización Integral de Pantalla Completa, Diseño Compacto Google Calendar y Experiencia Móvil

- **Problemas Detectados:**
  1. En modo Pantalla Completa, el calendario no cubría el 100% vertical de la pantalla en la vista de mes (`dayGridMonth`), y no permitía hacer scroll fluido en las vistas de semana (`timeGridWeek`), día (`timeGridDay`) ni agenda (`listWeek`).
  2. En el Backend, los encabezados y tarjetas de filtros superiores consumían espacio vertical en pantalla completa en lugar de dejar el lienzo despejado para el calendario.
  3. En el Frontend, la cabecera superior y notas ocupaban espacio en pantalla completa.
  4. La separación vertical entre eventos y entre el evento y el número del día generaba espacios vacíos excesivos que diferían de la densidad compacta de Google Calendar.
  5. En dispositivos móviles con pantalla normal, los encabezados, textos explicativos largos y barras de filtros restaban espacio vital al calendario.
  6. Los botones de salir de pantalla completa requerían unificarse en formato de solo icono universal (cuatro flechas desde las esquinas apuntando hacia el centro) flotante superior central.

- **Solución e Implementación:**
  1. **Ajuste de Pantalla Completa Líquida (100dvh) y Scroll Fluido:**
     - En `FullCalendar`, se configuraron dinámicamente las propiedades `height: '100%'` y `expandRows: true` al activar la pantalla completa (restaurando `height: 'auto'` y `expandRows: false` al salir).
     - Se añadió `updateSize()` en hooks de redimensionamiento y evento `datesSet` para sincronización inmediata tras cualquier cambio de mes/semana/día.
     - Se aplicó `-webkit-overflow-scrolling: touch !important; touch-action: pan-y !important;` en `.fc-scroller` garantizando un scroll vertical suave y sin trabas tanto en escritorio como en dispositivos móviles en vistas semanal, diaria y de agenda.
  2. **Controles Flotantes Superiores Centrales (Solo Icono):**
     - **Backend:** Se integró una barra flotante (`.aura-calendar-floating-fs-bar`) con efecto glassmorphism oscuro centrada en la parte superior que solo aparece en pantalla completa, conteniendo:
       - Botón circular de Crear Evento (icono plus SVG de 24px trazo 2.6).
       - Botón circular de Salir de Pantalla Completa (icono universal SVG con 4 flechas desde las esquinas apuntando hacia el centro).
     - **Frontend (Profesor y Estudiante):** Se implementó la misma barra flotante superior central mostrando únicamente el botón circular de Salir de Pantalla Completa con el icono universal de 4 flechas hacia el centro.
     - En ambos entornos se ocultaron por completo los filtros y cabeceras estándar durante la pantalla completa.
  3. **Densidad y Cero Espaciados estilo Google Calendar:**
     - Se eliminaron los márgenes y rellenos innecesarios en `.fc-daygrid-day-frame`, `.fc-daygrid-day-top`, `.fc-daygrid-day-events`, `.fc-daygrid-event-harness`, `.fc-daygrid-event`, `.fc-timegrid-col-events` y `.fc-timegrid-event`.
     - En pantallas grandes, las píldoras de eventos inician con el avatar del usuario y el indicador de líder estudiantil si corresponde, manteniendo un diseño limpio y profesional.
  4. **Tooltips Enriquecidos Delimitados y Capa Superior:**
     - El tooltip `#aura-cal-event-tooltip` se elevó a `z-index: 10000020 !important;` asegurando que siempre quede por encima de modales, barras fijas o canvas en pantalla completa.
     - Se implementó fijación matemática y CSS con `max-width: calc(100vw - 20px) !important; max-height: calc(100vh - 20px) !important; overflow-y: auto !important; -webkit-overflow-scrolling: touch !important;`, previniendo cualquier desbordamiento o corte en pantallas móviles y bordes laterales de la pantalla.
  5. **Optimización Móvil en Pantalla Normal:**
     - Se implementaron reglas `@media (max-width: 768px)` que ocultan textos largos de ayuda (`.aura-calendar-hint`), reducen los paddings de tarjetas a 8px, compactan la cabecera del calendario y estructuran los filtros de forma ergonómica sin robar espacio vertical.


---

## Sección 32 — Header/Filtros Ultra-Compacto Móvil + Botón FS Semi-Transparente con Icono Universal

### Archivos modificados

- `assets/css/calendar-admin.css`
- `assets/css/aura-frontend-dark-mode.css`
- `templates/calendar/tab-calendar.php`
- `modules/calendar/class-calendar-frontend.php`

### Cambios implementados

1. **Icono Universal del Botón "Salir de Pantalla Completa" (Backend + Frontend)**
   - Reemplazado el SVG anterior (mezcla de polylines cruzadas) por el icono universal estándar de **4 flechas desde las esquinas apuntando hacia el centro** (patrón Feather Icons `compress`).
   - El nuevo SVG usa `polyline` + `line` en las 4 esquinas: superior-izquierda, superior-derecha, inferior-izquierda e inferior-derecha, cada una con una flecha diagonal hacia el centro.
   - Aplicado en los 3 puntos: `tab-calendar.php` (admin backend), `class-calendar-frontend.php` (teacher), `class-calendar-frontend.php` (student).
   - `aria-hidden="true"` añadido para accesibilidad (el `aria-label` del botón lo describe).

2. **Semitransparencia de la Barra Flotante de Pantalla Completa**
   - `background` reducido de `rgba(15, 23, 42, 0.88)` a `rgba(15, 23, 42, 0.55)` — notablemente más translúcido.
   - `backdrop-filter: blur(14px) saturate(1.6)` para mejorar legibilidad sin tapar el contenido detrás.
   - `box-shadow` simplificado a una sombra suave y difusa en lugar de una sombra oscura pesada.
   - `transition` cambiado a `opacity + background` para animaciones más naturales.
   - Aplicado tanto en `calendar-admin.css` como en `aura-frontend-dark-mode.css`.

3. **Header y Filtros Ultra-Compactos en Móvil — Backend (`@media max-width: 768px`)**
   - Ocultar subtítulos/descripciones del encabezado de página (`.adp-page-desc`, `.adp-page-subtitle`, `p`).
   - Tabs de navegación (`adp-nav-tabs`) con gap y padding reducidos.
   - **Labels de los 3 filtros ocultados** — los selects ocupan toda la altura disponible directamente.
   - Filtros reorganizados en **fila horizontal con scroll táctil** en lugar de columna vertical, ahorrando espacio vertical crítico.
   - Cada select con `height: 30px`, `padding: 4px 6px`, `font-size: 11.5px`.
   - Botones de acciones (`Crear Evento`, `Limpiar`, `Pantalla Completa`) más pequeños.
   - Toolbar de FullCalendar en fila flexible (`flex-direction: row; flex-wrap: wrap`) en lugar de columna.
   - Títulos del toolbar reducidos a `13.5px` y botones a `10.5px`.

4. **Header y Filtros Ultra-Compactos en Móvil — Frontend (`@media max-width: 768px`)**
   - **Avatar/foto de perfil ocultado** en móvil para liberar espacio horizontal.
   - Header del portal (`aura-calendar-top-header`) con padding 0 y gaps mínimos de 4px.
   - Ocultar descripción/subtexto del header del portal.
   - Texto de botones de pantalla completa (`#btn-toggle-teacher-fullscreen .fs-text`, `#btn-toggle-student-fullscreen .fs-text`) ocultado en móvil — solo queda el icono.
   - Tabs del profesor con scroll horizontal táctil (`-webkit-overflow-scrolling: touch`).
   - Barra de filtros del frontend (`.aura-calendar-filters-card`): labels ocultos, selects en fila horizontal con scroll, `height: 28px`.
   - Toolbar FullCalendar en fila compacta, `font-size: 13px` para título, `10.5px` para botones.

5. **Build generado**: `aura-business-suite.zip` — 21.23 MB — 3304 archivos empaquetados.







---

## Sección 33 — Corrección de Posicionamiento Horario de Eventos en Vistas Semana y Día (Backend + Frontend)

### Causa Raíz Detectada
En [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css) y [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css), existía la regla:
```css
.fc .fc-timegrid-event-harness {
    inset: 0 !important;
}
```
FullCalendar v6 posiciona cada evento en la rejilla de horas mediante estilos inline `top: ...px; bottom: ...px; left: ...; right: ...;` aplicados sobre `.fc-timegrid-event-harness` en base a la hora de inicio y término del evento (`start_datetime` y `end_datetime`).
Al forzar `inset: 0 !important;`, la regla CSS sobreescribía el cálculo inline de FullCalendar, fijando `top: 0 !important;`, lo que hacía que todos los eventos se pegaran a la posición 0 de la columna (arriba de las 12:00 a. m.) como una barra plana colapsada sin mostrar el bloque correspondiente en su franja horaria.

### Cambios Implementados
1. **Eliminación de `inset: 0 !important;` en `.fc-timegrid-event-harness`:**
   - En [`assets/css/calendar-admin.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css) y [`assets/css/aura-frontend-dark-mode.css`](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css), se retiró la regla forzada en `.fc-timegrid-event-harness`, permitiendo que FullCalendar respete las coordenadas horarias reales del evento.
2. **Estilizado de Bloque Google Calendar para `.fc-timegrid-event`:**
   - Se garantizó que los eventos en timegrid (`.fc .fc-timegrid-event`) tengan bordes redondeados (`border-radius: 4px`), sombra sutil (`box-shadow`), borde refinado (`border: 1px solid rgba(255,255,255,0.2)`), y que `.fc-event-main` ocupe el 100% del alto y ancho con relleno limpio.
   - En hover, los eventos elevan su `z-index` y sombra para interactividad óptima.
3. **Verificación Automatizada en Navegador:**
   - Confirmado en el Portal del Instructor y Backend: el evento del miércoles 16 de septiembre se sitúa de forma exacta entre las 2:00 p. m. y 4:00 p. m. en la vista semanal (`timeGridWeek`) y en la vista diaria (`timeGridDay`), mostrando avatar, título, hora e instructor con total legibilidad.
4. **Build del Plugin:**
   - Ejecutado `php build-zip.php`, empaquetando 3304 archivos en `aura-business-suite.zip` (21.23 MB).


---

## Sección 34 — Adaptación Dinámica de Color de Texto en Eventos del Backend (Blanco o Negro según Luminancia)

### Causa Raíz Detectada
En el panel de administración (`wp-admin`), los eventos del calendario (`timeGridWeek`, `timeGridDay`, `dayGridMonth`) se encapsulan dentro de una etiqueta `<a>` generada por FullCalendar. WordPress y los estilos administrativos imponen sobre los enlaces reglas de color (`#2271b1` azul estándar de enlace de WordPress), además de que los elementos hijos heredaban colores oscuros/atenuados del tema. Al tener el evento un fondo oscuro (por ejemplo, verde esmeralda `#059669` o azul), el texto se visualizaba con muy bajo contraste e ilegible.

### Cambios Implementados
1. **Algoritmo de Luminancia y Contraste YIQ:**
   - **En PHP (`modules/calendar/class-calendar-events.php`):** Se creó el método `get_contrast_color($hex_color)` que calcula la luminancia YIQ `((R*299) + (G*587) + (B*114)) / 1000`. Si es `>= 145`, el fondo es claro y asigna `#0f172a` (negro oscuro); de lo contrario, asigna `#ffffff` (blanco puro).
   - Se añadió tanto `'textColor'` como `'extendedProps.text_color'` en los datos JSON enviados a FullCalendar.
   - **En JavaScript (`assets/js/calendar-admin.js`):** Se implementó la función homóloga `getEventContrastColor(colorStr)` compatible con formatos HEX y RGB, exponiéndola en `window.getEventContrastColor`.

2. **Renderizado de la Tarjeta del Evento (`renderGoogleStyleEvent`):**
   - Se inyecta explícitamente `color: ${textColor} !important;` en el contenedor `.aura-gcal-event-card`, en el título (`.aura-gcal-title`), en la hora (`.aura-gcal-time`), en los metadatos de aula/docente (`.aura-gcal-meta`), en la materia (`.aura-gcal-subj`), en el diseño compacto (`.aura-gcal-event-compact`) y en la píldora del mes (`.aura-gcal-month-pill`).

3. **Hook de Montaje en FullCalendar (`eventDidMount`):**
   - Se configuró `eventDidMount` en la instancia del backend para fijar directamente sobre el elemento DOM `info.el.style.color`, `--fc-event-text-color` y `.fc-event-main.style.color`, blindando el evento frente a cualquier regla externa de WordPress.

4. **Reglas CSS en `assets/css/calendar-admin.css`:**
   - Se anularon los colores y decoraciones de enlace de WordPress en `.fc a.fc-timegrid-event`, `.fc a.fc-event` y `.fc .fc-timegrid-event .aura-gcal-*` con `color: inherit !important; text-decoration: none !important;`.

5. **Verificación y Build:**
   - Verificado con recarga en el navegador en la vista semanal del backend: los textos se muestran en blanco puro `#ffffff` nítido sobre fondos oscuros y con contraste impecable.
   - Compilación exitosa ejecutando `php build-zip.php` (`aura-business-suite.zip`, 21.23 MB, 3304 archivos).


---

## Sección 35 — Corrección de Validación Bloqueante de Fechas en el Editor de Eventos del Calendario

### Causa Raíz Detectada
Al abrir o editar un evento en el modal `#modal-event-editor` en el backend del calendario, el navegador impedía guardar y arrojaba un tooltip nativo de validación HTML5 en el campo de fecha de fin (`#evt-end-dt`):
`"El valor debe ser igual o posterior a [fecha/hora].."`

Esto se debía a una combinación de tres factores técnicos:
1. **Atributo `min` residual huérfano:** En `assets/js/calendar-admin.js`, al cambiar la fecha de inicio (`#evt-start-dt`), se asignaba el atributo HTML `min` en `#evt-end-dt`. Sin embargo, al abrir cualquier otro evento mediante `openEventEditor()`, se ejecutaba `form.reset()`, el cual **no remueve atributos HTML dinámicos** (`min`, `max`). Como `$('#evt-start-dt').val(startVal)` no dispara el evento `change`, `#evt-end-dt` conservaba indefinidamente el atributo `min` del evento o fecha previamente seleccionada (por ejemplo, una fecha en mayo de 2027). Al editar un evento con fecha fin anterior (como abril de 2027 o enero de 2027), el navegador consideraba inválido el campo.
2. **Ausencia de `novalidate` en los formularios modales:** Ni `<form id="form-event-editor">` ni `<form id="form-program-editor">` poseían el atributo `novalidate`, permitiendo que el motor de validación nativo del navegador interceptara el submit antes de que el script jQuery pudiera procesar y validar amigablemente el formulario.
3. **Escucha de eventos:** `#evt-start-dt` y `#rec-date-start` solo escuchaban el evento `change` (que se dispara al desenfocar o confirmar), y no `input`, impidiendo que los ajustes en tiempo real actualizaran las restricciones adecuadamente mientras el usuario digita o usa el selector.

### Cambios Implementados
1. **Reset y Sincronización Explícita de Restricciones en `openEventEditor()` (`assets/js/calendar-admin.js`):**
   - Se removió cualquier atributo `min` residual previo en `#evt-end-dt` y `#rec-date-end` al abrir el modal (`removeAttr('min')`).
   - Se sincronizó el atributo `min` exactamente con el `startVal` del evento cargado en ese instante.
2. **Escucha Bidireccional `change input` en Fechas:**
   - `#evt-start-dt`, `#rec-date-start`, `#prog-start-date` y `#prog-end-date` ahora escuchan `'change input'`, recalculando y sincronizando dinámicamente las restricciones mínimas y coherencia de fechas en tiempo real.
3. **Atributo `novalidate` en Formularios Modales:**
   - Se incorporó `novalidate` en `<form id="form-event-editor" class="aura-modal-form" novalidate>` (`templates/calendar/modal-partials.php`) y en `<form id="form-program-editor" class="aura-modal-form" novalidate>` (`templates/calendar/tab-programs.php`).
4. **Validaciones JS Exhaustivas con Feedback UI (`showToast`):**
   - En el submit de `#form-event-editor`, se validan en JavaScript la presencia del título, selección del programa, coherencia de fechas tanto para eventos simples (`dtEnd >= dtStart`) como para series recurrentes (`rEnd >= rStart`, selección de días de la semana), mostrando toasts amigables y enfocando el campo correspondiente sin popups intrusivos del navegador.




✅ En las Tarjetas de `Materias del Programa` que estan dentro de un `Programa Académico`, quiero que introduzca un icono cuando una materia esté asignada al calendario y caundo pase el puntero encima, me aparezca un tooltip enriquecido con html donde me muestre las fechas programadas de esa materia dentro del Programa
✅ Que dentro del `.aura-tip-session-card`, del tooltip enriquecido, tenga un boton que me abra ese dia en que esta la clase en el calendario, ejemplo: `https://diserwp.test/wp-admin/admin.php?page=aura-calendar#/u/0/r/day/2026/9/23`



✅ Analiza la pagina de `Nuevo Estudiante`, en la url `https://diserwp.test/wp-admin/admin.php?page=aura-students-new` y revisa si en la creacion de `Nuevo Formulario` en Tipo de formulario: Inscripción a Curso, al dar clic en:
    `+ Insertar campos predeterminados`
    Formulario de Inscripción
    Los campos mapeados se sincronizan automáticamente con el Módulo de Estudiantes al enviar.
Lo anterior, se sincroniza en el Módulo de Estudiantes, para administrar los estudiantes que se inscriben, cuales son aprobados, cuales se inscribieron dos veces para editar y dejar una sola inscripción, cuales renuncian o desisten despues de la inscripción, etc.?

Searched for "Insertar campos predeterminados"
Searched for "mapping_key"
Viewed class-forms-submissions.php:270-380
Searched for "aura_form_submission_saved"
Viewed class-forms-enrollment.php:1-250
Viewed class-forms-enrollment.php:251-450
Viewed class-forms-enrollment.php:451-650
Searched for "applicant"
Searched for "postulante"
Viewed enrollments.php:30-100

**Sí, se sincroniza de forma automática e integral con el Módulo de Estudiantes**, funcionando como un embudo completo de admisiones y gestión académica.

A continuación te detallo cómo opera el flujo internamente y cómo resuelve cada uno de los escenarios que mencionas:

---

### 1. ¿Cómo funciona la sincronización automática?

Cuando en el constructor de formularios seleccionas **Tipo: Inscripción a Curso** y pulsas `+ Insertar campos predeterminados`:

1. **Mapeo de datos:** En [builder.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/forms/builder.php) y [class-forms-builder.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/forms/class-forms-builder.php), se insertan los campos con sus respectivas claves de mapeo (`first_name`, `last_name`, `email`, `phone`, `birthdate`, `gender`, `city`, `country`, `motivation`).
2. **Procesamiento del envío:** En [class-forms-submissions.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/forms/class-forms-submissions.php), al enviarse el formulario, se guarda la respuesta y se dispara el hook `aura_form_submission_saved`.
3. **Puente al módulo de estudiantes:** [class-forms-enrollment.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/forms/class-forms-enrollment.php) intercepta el evento:
   - **Crea el perfil del estudiante:** Lo registra en la tabla `wp_aura_students` con rol `student` y estado inicial **`applicant` (Postulante)**.
   - **Crea la inscripción:** La registra en `wp_aura_student_enrollments` con estado **`pending` (Pendiente de aprobación)** asociada al curso seleccionado.

---

### 2. Gestión de los casos planteados

#### A. Aprobación de postulantes
* **Dónde se gestiona:** Desde **Formularios > Postulantes** (`admin.php?page=aura-forms-enrollments`) o en **Estudiantes > Postulantes**.
* **Qué sucede al aprobar:**
  1. El estado del estudiante cambia a **`approved`** y la inscripción pasa a **`active`**.
  2. Se crea automáticamente el **usuario de WordPress** correspondiente (rol de alumno/suscriptor).
  3. Se genera su contraseña segura y se le envía un correo electrónico de bienvenida con sus credenciales para acceder al portal del estudiante.

#### B. Control de inscripciones duplicadas (se inscribió dos veces)
* **Detección automática por correo y curso:** En la función `create_pending_enrollment()` de [class-forms-enrollment.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/forms/class-forms-enrollment.php#L140-L175):
  - Si el estudiante vuelve a enviar el formulario con el mismo correo electrónico, el sistema **no duplica el perfil** del estudiante ni crea una segunda inscripción activa en el mismo curso: reutiliza la inscripción pendiente/activa existente y vincula el nuevo envío.
* **Consolidación manual:** Si el usuario envió el formulario con dos correos distintos y generó dos registros, el administrador puede ir a **Estudiantes > Lista de Estudiantes** (`admin.php?page=aura-students`), ingresar al perfil duplicado y editarlo o eliminarlo (`deleted_at`) para dejar únicamente el perfil oficial.

#### C. Renuncias o desistimientos posteriores
* **Estado "Retirado" (`withdrawn`):** 
  - Tanto desde la tabla de postulantes de formularios como desde el historial académico de la ficha del estudiante, existe la acción **"Marcar como retirado"**.
  - Al marcarlo como retirado (`withdrawn`), la inscripción se desactiva, **se libera el cupo del curso** para otros alumnos y se bloquea la generación de cargos contables o asistencias futuras.
* **Rechazo con motivo:** Si antes de ingresar desiste o no cumple con los requisitos, se puede **Rechazar** (`rejected`), permitiendo registrar el motivo del rechazo y notificando opcionalmente al postulante por correo.

---

### 3. Rutas de administración en WordPress

| Acción | Menú en WordPress | URL de acceso |
| :--- | :--- | :--- |
| **Bandeja de postulantes de formularios** | Formularios > Postulantes | `wp-admin/admin.php?page=aura-forms-enrollments` |
| **Lista general de estudiantes y postulantes** | Estudiantes > Todos los estudiantes | `wp-admin/admin.php?page=aura-students` |
| **Inscripciones y matrículas por curso** | Estudiantes > Inscripciones | `wp-admin/admin.php?page=aura-students-enrollments` |
| **Alta manual de estudiante** | Estudiantes > Nuevo Estudiante | `wp-admin/admin.php?page=aura-students-new` |




Actualmente **sí existe una sincronización parcial**, pero **debe existir y profundizarse hacia una sincronización automática bidireccional**. 

A continuación te detallo la situación actual y la arquitectura técnica correspondiente:

---

### 1. Lo que existe actualmente en el proyecto

1. **Dos tablas separadas pero convergentes:**
   - `wp_aura_student_courses` (Módulo de Estudiantes): Gestiona la parte administrativa, financiera y de admisiones (costo, moneda, cupos máximos, becas, matrículas).
   - `wp_aura_cal_programs` (Módulo de Calendario): Gestiona la parte pedagógica y de agenda (materias, eventos de clases, exámenes, tareas, asistencia y notas).

2. **Herramienta de sincronización existente:**
   - En **Calendario > Programas y Cursos de Capacitación** (`tab-programs.php`), existe el botón **`🔄 Sincronizar desde Estudiantes`**.
   - Al presionarlo, se ejecuta `Aura_Calendar_Programs::sync_from_student_courses()`, el cual lee todos los cursos activos de `wp_aura_student_courses` y crea de forma automática en `wp_aura_cal_programs` aquellos programas académicos que aún no existan en el calendario.

3. **Integración en Tareas y Asistencia:**
   - Al cargar los alumnos de un programa en el calendario (en `Aura_Calendar_Tasks::get_program_students`), el sistema busca a los estudiantes matriculados en `wp_aura_student_enrollments` cruzando tanto por ID como por coincidencia de nombre (`c.name = p.name`).

---

### 2. Por qué **DEBE existir** una sincronización automática y continua

La sincronización entre ambos módulos es fundamental por tres razones operativas:

1. **Experiencia del Estudiante en su Portal:**
   - Cuando un estudiante ingresa a su portal, el calendario filtra sus eventos comparando los cursos donde está matriculado activamente. Si un curso se crea en Estudiantes pero no existe como programa en el Calendario, el estudiante no verá sus clases ni horarios.
2. **Evitar duplicidad de trabajo para los administradores:**
   - Si se da de alta un nuevo curso o diplomado en Estudiantes, no debería ser necesario que el usuario tenga que ir manualmente al Calendario a presionar "Sincronizar" o a volver a crearlo desde cero.
3. **Coherencia de Docentes, Fechas y Áreas:**
   - El docente asignado al curso en Estudiantes debe coincidir con el instructor titular en el Calendario; igualmente, las fechas de inicio y fin del curso deben delimitar las clases programadas.

---

### 3. Conclusión y Recomendación Arquitectónica

- **Existe hoy:** Sincronización manual bajo demanda desde el Calendario (`sync_from_student_courses()`) y vinculación híbrida por nombre/ID.
- **Debe existir:** Que al **crear o editar un Curso en Estudiantes**, se dispare automáticamente la creación/actualización en **Programas del Calendario** (y viceversa mediante hooks internos de WordPress), manteniendo ambos módulos siempre vinculados por un identificador común sin intervención manual.

---

## 36. Distintivo e Icono con Tooltip Enriquecido de Fechas en Tarjetas de Materia

### Solicitud:
- En las tarjetas de **Materias del Programa** dentro de un **Programa Académico** (`admin.php?page=aura-calendar&tab=programs`), introducir un icono cuando una materia esté asignada al calendario y, al pasar el puntero por encima (hover), desplegar un tooltip enriquecido con HTML donde se muestren las fechas programadas de esa materia dentro del programa.

### Implementación Realizada:
1. **Consulta Masiva y Eficiente en Backend (`modules/calendar/class-calendar-subjects.php`):**
   - Se implementó el método `populate_scheduled_events( array $subjects ): array`.
   - Consulta en una sola sentencia SQL optimizada todos los eventos activos (`deleted_at IS NULL`) de `wp_aura_cal_events` vinculados a las materias consultadas (`subject_id IN (...)`).
   - Formatea automáticamente las fechas en español (`date_i18n`), rangos de horario en formato local de 12/24h, tipo de evento con emoji y etiqueta (📖 Clase, 🔬 Taller, 📝 Examen, etc.), ubicación/enlace online y estado de la sesión.
   - Integra la información directamente en `Aura_Calendar_Subjects::get_all()` y `Aura_Calendar_Subjects::get()`.

2. **Renderizado de Badge y Estructura Canónica de Tooltip (`templates/calendar/tab-programs.php`):**
   - En la cabecera de `.aura-subject-card`, se dispuso una fila flex con el código de materia y el distintivo interactivo `.aura-subj-cal-badge`.
   - Si la materia cuenta con eventos agendados (`! empty( $s->scheduled_events )`), se renderiza el distintivo con el icono `📅` y el número de sesiones programadas, conteniendo los atributos `data-events`, `data-subj-name`, `data-prog-name`, `data-subj-code`, `data-subj-color` y `data-subj-hours`.
   - Se migró el contenedor flotante singleton `#aura-subj-cal-tooltip` a la arquitectura canónica de tarjetas emergentes del Design System (`.aura-tip-card`), dotándolo de los IDs correspondientes:
     - `.aura-tip-card-header`: Avatar temático de la materia (`#aura-subj-cal-tip-avatar`), títulos jerárquicos (`#aura-subj-cal-tip-name`, `#aura-subj-cal-tip-prog`) y grupo de pills/badges canónicos (`#aura-subj-cal-tip-meta-badges`).
     - `.aura-tip-card-body`: Contenedor desplazable con scrollbar estilizada (`#aura-subj-cal-tip-list`) para las sesiones agendadas y contador (`#aura-subj-cal-tip-count`).
     - `.aura-tip-card-footer`: Pie con botón canónico hacia el Calendario Principal (`.btn.btn-sm.btn-indigo.btn-shimmer.btn-lift`).

3. **Homologación con Tooltips de Avatar y Estilos Opacos Adaptativos (`tab-programs.php` y `calendar-admin.css`):**
   - Se diagnosticó que el tooltip se mostraba transparente y desalineado debido a la falta de reglas CSS acopladas en la vista y a una discrepancia de selectores (`.aura-tip-card--subject` vs `#aura-subj-cal-tooltip`).
   - Se incorporaron las reglas de estilo de `#aura-subj-cal-tooltip` directamente en `<style>` de `tab-programs.php` y en `calendar-admin.css`, idéntico a `#aura-av-tooltip`:
     - Posicionamiento `position: fixed !important; z-index: 999999 !important;` y ancho de 360px.
     - Fondo sólido institucional 100% no transparente: `#ffffff` en Modo Claro y `#181b21` en Modo Oscuro (`body.aura-dark-mode`, `[data-theme="dark"]`, `.dark`, `html.wp-dark-mode-active`).
     - Sombra multicapa de alta profundidad (`box-shadow: 0 24px 50px rgba(0, 0, 0, 0.75)` en oscuro).
   - Componente **Date Calendar Tile** (`.aura-tip-date-tile`):
     - Un bloque de calendario tipográfico estilizado que desglosa el mes en mayúsculas (`.aura-tip-date-month`), el número del día en tipografía destacada (`.aura-tip-date-day`) y el día abreviado (`.aura-tip-date-wday`), erradicando emojis repetidos (`📅`, `⏰`, `📍`).
   - Tarjetas de sesión limpias (`.aura-tip-session-card`) con franja de color dinámico según la materia/evento, insignia semántica de estado (`status-scheduled`, `status-completed`, `status-cancelled`) y metadatos estructurados en fila para horario y aula/enlace virtual.

4. **Interactividad Dinámica y Posicionamiento Inteligente (`assets/js/calendar-admin.js`):**
   - Controladores para eventos `mouseenter`, `mouseleave`, `focus` y `blur` sobre `.aura-subj-cal-badge`.
   - Homologación con la lógica posicional de `#aura-av-tooltip`: cálculo dinámico con `getBoundingClientRect()`, posicionando arriba mediante `transform: translateY(-100%)` cuando hay espacio suficiente en el viewport, o invirtiéndose automáticamente hacia abajo (`transform: translateY(0)`) si la tarjeta está en el borde superior de la pantalla.
   - Parseo seguro de `data-events` tanto si jQuery lo entrega deserializado como objeto/array o como cadena JSON.
   - Generación de Date Tiles tipográficos para cada fecha agendada, orden cronológico y enlace directo al calendario en el footer.
   - Lógica de persistencia en hover que permite interactuar con el interior del tooltip (scroll y enlaces) sin cierres intempestivos.

5. **Botón de Apertura Directa de Día en el Calendario (`.aura-tip-goto-day-btn`):**
   - Cada tarjeta de sesión (`.aura-tip-session-card`) cuenta ahora con un botón de acción rápida que enlaza al día exacto en que se imparte la clase en el calendario.
   - La URL se genera de forma determinista con el hash `#/u/0/r/day/{año}/{mes}/{día}` (ejemplo: `https://diserwp.test/wp-admin/admin.php?page=aura-calendar#/u/0/r/day/2026/9/23`), cumpliendo con la arquitectura de rutas canónicas de Aura y Google Calendar.
   - Soporte dual interactivo:
     - Si el usuario se encuentra en otra vista o tab (ejemplo: `tab=programs`), el botón navega y abre la vista del día indicado.
     - Si el calendario ya está presente y visible en el DOM (`#aura-main-calendar`), el evento click intercepta la acción, transiciona de forma fluida a la vista diaria (`timeGridDay`) en esa fecha con `calendar.changeView()`, actualiza el hash en el historial (`pushState`) y oculta suavemente el tooltip.
   - Estilizado de diseño con micro-animación en hover (flecha `translateX`, sombra de elevación e inversión cromática tanto en Modo Claro como en Modo Oscuro).

6. **Empaquetado:**
   - Ejecutado `php build-zip.php` actualizando el archivo final `aura-business-suite.zip`.









- [x] Añade el modal Catálogo de Terceros a Programas y Materias.
- [x] Recuerda que Los Terceros pueden volversen en un futuro Usuarios del Sistema de Wordpress (Sincronización en tiempo real y detección en base de datos implementada)





### 30. Integración del Catálogo de Terceros y Entidades Comerciales en el Calendario Académico

Se integró completamente la base de datos centralizada de Terceros (`wp_aura_finance_third_parties`) y el modal reutilizable `AuraThirdPartySelector` (`Catálogo de Terceros y Entidades Comerciales`) con el modal de asignación de eventos del Calendario (`#modal-event-editor`), permitiendo vincular docentes externos, empresas, ponentes y fundaciones directamente desde el directorio institucional sin necesidad de registrarlos manualmente cada vez.

#### Componentes y Modificaciones Realizadas:
1. **Base de Datos y Persistencia Relacional:**
   - Se añadió la columna `third_party_id BIGINT UNSIGNED DEFAULT NULL` a la tabla `wp_aura_cal_event_instructors` mediante migración segura y declarativa en `Aura_Calendar_Setup::maybe_add_modules_and_externals_columns()`.
   - Se actualizó `Aura_Calendar_Events::save()` para capturar y persistir `third_party_id` en las asignaciones de instructores externos.
   - Se actualizó el mapeo en `Aura_Calendar_Events::get()` y `get_all()` para exponer `third_party_id` en los instructores externos cargados en el modal de eventos y en FullCalendar.

2. **Carga Modular de Recursos (Assets):**
   - En `Aura_Calendar_Admin::enqueue_assets()`, se encolaron los estilos y scripts del selector de terceros:
     - `jquery-ui-autocomplete` y estilos jQuery UI.
     - `aura-third-parties-directory-css` (`assets/css/third-parties-directory.css`).
     - `aura-third-party-selector` (`assets/js/third-party-selector.js`) con localización de `auraCounterpartiesData` (nonce `aura_search_counterparties_nonce` y roles contables).
     - Se añadió `aura-third-party-selector` como dependencia de `aura-calendar-admin`.

3. **Interfaz de Usuario en el Modal del Calendario (`templates/calendar/modal-partials.php`):**
   - Botón destacado **🏛️ Catálogo de Terceros** (`#btn-open-tp-catalog-explorer`) con diseño azul corporativo, hover y sombra de elevación.
   - Enlace directo **↗ Directorio** que abre `admin.php?page=aura-third-parties` en nueva pestaña para ver o dar de alta nuevas entidades.
   - Botón **➕ Agregar Manual** para el desplegable tradicional de creación rápida.
   - Input oculto `#ext-inst-third-party-id` e indicador visual `#ext-inst-linked-badge` (**🔗 Vinculado a Entidad del Catálogo**).
   - Placeholder inteligente en `#ext-inst-name` sugerente de búsqueda o registro directo.

4. **Interactividad Dinámica (`assets/js/calendar-admin.js`):**
   - Conexión del botón `#btn-open-tp-catalog-explorer` a `window.AuraThirdPartySelector.openExplorer()`.
   - Al seleccionar un tercero en el catálogo:
     - Prellenado automático de nombre/razón social, correo, teléfono y organización/tipo de entidad.
     - Sincronización del `third_party_id`.
     - Despliegue del formulario con enfoque en el selector de rol (`ext-inst-role`: Titular Externo, Invitado / Ponente, Co-instructor).
   - Autocompletado inteligente con jQuery UI en el campo de texto `#ext-inst-name` conectado a `action: 'aura_search_counterparties'`.
   - Distintivo visual `🏛️ Catálogo` en los chips de la lista de instructores asignados (`renderExternalInstructorsList()`).

5. **Mejora del Selector Central (`assets/js/third-party-selector.js`):**
   - Incorporación de `data-phone` en el botón de selección de la tarjeta de catálogo y propagación limpia en el objeto `item.phone` para callbacks de selección externos.

6. **Corrección de Jerarquía de Capas (Stacking Context y z-index):**
   - **Causa raíz:** `.aura-modal-overlay` del editor de eventos (`#modal-event-editor`) tiene asignado `z-index: 1000500 !important`. El modal del catálogo (`#aura-tp-explorer-modal`) se encontraba con `z-index: 999999` y añadido al `body`, lo que provocaba que se renderizara por detrás del modal de evento.
   - **Solución implementada:**
     - Se actualizó el `z-index` de `#aura-tp-explorer-modal` y su diálogo a `1002000 !important` en [assets/js/third-party-selector.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/third-party-selector.js), [assets/css/third-parties-directory.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/third-parties-directory.css) y [assets/css/calendar-admin.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css).
     - Se ajustó dinámicamente el montaje DOM en `openExplorer()`: si hay pantalla completa o un modal de eventos activo, el modal del catálogo se inserta inmediatamente después (`insertAfter`) o dentro del contenedor activo, compartiendo idéntico stacking context.
     - Se agregó aislamiento de eventos (`stopPropagation` y control de tecla Escape en [assets/js/calendar-admin.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js)) para evitar que cerrar el catálogo cierre inadvertidamente el modal de Crear/Editar Evento.

### 31. Catálogo de Terceros y Entidades Comerciales en Programas y Materias (con Detección y Sincronización Futura con Usuarios WordPress)

Se extendió el modal "Catálogo de Terceros y Entidades Comerciales" (`window.AuraThirdPartySelector`) a los módulos de **Programas Académicos** y **Materias**, permitiendo vincular entidades externas (empresas patrocinadoras, coordinadores aliados, directores de convenios, docentes externos y ponentes especializados) de forma persistente, con compatibilidad bidireccional y reactiva para terceros que puedan convertirse en usuarios de WordPress a futuro.

#### Componentes y Modificaciones Realizadas:

1. **Base de Datos y Migración Automática (`modules/calendar/class-calendar-setup.php`):**
   - Se añadieron las columnas:
     - `external_coordinators LONGTEXT DEFAULT NULL` en la tabla `wp_aura_cal_programs`.
     - `external_teachers LONGTEXT DEFAULT NULL` en la tabla `wp_aura_cal_subjects`.
   - Se incorporó la migración automática y declarativa dentro de `maybe_add_modules_and_externals_columns()`.

2. **Detección Dinámica y Enriquecimiento de Terceros <-> Usuarios WordPress (`modules/calendar/class-calendar-programs.php` y `modules/calendar/class-calendar-subjects.php`):**
   - **Premisa clave:** Un Tercero (`wp_aura_finance_third_parties`) puede no tener usuario inicialmente y crearse posteriormente una cuenta de WordPress (`wp_user_id`), o viceversa.
   - En `Aura_Calendar_Programs::populate_coordinators()` y `Aura_Calendar_Subjects::populate_teachers()`:
     - Se decodifican los JSONs `external_coordinators` y `external_teachers`.
     - Se consulta en tiempo real la tabla `wp_aura_finance_third_parties` para obtener el `wp_user_id`, nombres actualizados y avatares/logos.
     - Si el tercero tiene o adquiere un `wp_user_id > 0`, se recupera automáticamente su avatar de WordPress (`get_avatar_url`) y datos de usuario, marcándolo con la bandera `is_wp_user: true`.
     - Si no tiene usuario WP, se utiliza el logo del catálogo o sus iniciales estilizadas con diseño institucional.
   - En `save()`: se valida, sanitiza y almacena de forma segura la estructura JSON con los identificadores `third_party_id` y `wp_user_id`.

3. **Interfaz de Usuario en Pestaña Programas y Materias (`templates/calendar/tab-programs.php`):**
   - Se añadió la función `aura_avatar_stack_item_external()` para mostrar avatares de terceros en los avatar stacks de los cards de programas y materias con bordes diferenciados (verde esmeralda para usuarios WP vinculados y azul cian para terceros del catálogo) y tooltips enriquecidos adaptativos.
   - **Modal de Programa (`#modal-program-editor`):**
     - Botón **🏛️ Catálogo de Terceros** (`#btn-open-tp-catalog-prog`).
     - Botón **➕ Agregar Manual** (`#btn-toggle-add-external-coord`).
     - Formulario desplegable `#box-add-external-coord` con autocompletado y selección de rol (Coordinador Externo, Director Académico, Asesor / Enlace).
     - Contenedor dinámico de chips con badges de rol y eliminación instantánea.
   - **Modal de Materia (`#modal-subject-editor`):**
     - Botón **🏛️ Catálogo de Terceros** (`#btn-open-tp-catalog-subj`).
     - Botón **➕ Agregar Manual** (`#btn-toggle-add-external-teacher`).
     - Formulario desplegable `#box-add-external-teacher` con autocompletado y selección de rol (Docente Titular Externo, Profesor Invitado / Especialista, Auxiliar / Adjunto Externo).
     - Contenedor dinámico de chips con badges de rol y eliminación instantánea.

4. **Lógica JavaScript e Interactividad (`assets/js/calendar-admin.js`):**
   - Bindings de `#btn-open-tp-catalog-prog` y `#btn-open-tp-catalog-subj` conectados a `window.AuraThirdPartySelector.openExplorer()`.
   - Autocompletado inteligente con jQuery UI (`initExternalCoordAutocomplete` e `initExternalTeacherAutocomplete`) consumiendo la acción AJAX `aura_search_counterparties`.
   - Carga y poblado automático del array de externos al abrir el modal de edición (`.btn-edit-program` y `.btn-edit-subject`).
   - Serialización y envío automático en el formulario mediante `external_coordinators` y `external_teachers`.



### 32. Corrección de Doble Pegado de Eventos, Tooltip Enriquecido de Materias y Avatares de Terceros / Usuarios del Sistema

Se resolvieron de forma exhaustiva las 3 incidencias reportadas en el módulo de Calendario y Gestión Académica:

#### 1. Doble Confirmación y Duplicación de Eventos al Copiar y Pegar:
- **Causa Raíz:** En FullCalendar con `selectable: true`, un clic sobre una celda disparaba secuencialmente `dateClick` y a los pocos milisegundos `select`. Ambos eventos llamaban a `handlePasteEventToDate()` sin debounce ni semáforo de bloqueo, abriendo dos diálogos `confirm()` y despachando dos peticiones AJAX en paralelo hacia `aura_cal_duplicate_event`.
- **Solución Implementada (`assets/js/calendar-admin.js`):**
  - Se introdujo un candado de concurrencia `isPastingEvent` y un guard de tiempo por timestamp `(now - lastPasteTimestamp < 1000)`.
  - Se añadió la deselección visual inmediata en el calendario (`calendar.unselect()`).
  - Se liberó el candado de forma segura tanto si el usuario cancela la confirmación como al completarse o fallar la petición AJAX, garantizando una sola ejecución atómica por pegado.

#### 2. Tooltip Enriquecido en el Nombre de la Materia (Pestaña Programas y Materias):
- **Diseño y Arquitectura (`templates/calendar/tab-programs.php` y `assets/js/calendar-admin.js`):**
  - Se creó el contenedor singleton `#aura-subj-desc-tooltip` con arquitectura idéntica al Design System institucional de Aura.
  - El card flotante cuenta con:
    - Cabecera con avatar estilizado con el color de la materia y sus iniciales/código.
    - Nombre completo de la materia y programa académico al que pertenece.
    - Badges semánticos para **Código**, **Total de Horas** y **Módulo**.
    - Sección de **Descripción y Objetivos Pedagógicos** con formato de texto enriquecido, scroll suave y mensaje informativo en caso de no contar aún con descripción.
  - Soporte completo para modo oscuro institucional (`body.aura-dark-mode`, `.dark`, etc.).
  - Posicionamiento inteligente con cálculo de colisiones contra los bordes superior, inferior y laterales de la pantalla (`getBoundingClientRect`).
  - El nombre de la materia en cada tarjeta (`.aura-subject-card-name.has-desc-tooltip`) cuenta con cursor interactivo y subrayado dotted al hacer hover.

#### 3. Soporte Completo de Fotos / Avatares para Terceros y Usuarios del Sistema en Calendario, Programas y Materias:
- **Causa Raíz:**
  1. En el frontend de Eventos (`calendar-admin.js`), `renderExternalInstructorsList()` tenía un `<div>🏢</div>` rígido que ignoraba la imagen de perfil.
  2. En `#btn-open-tp-catalog-explorer` y en los autocompletados, no se almacenaba el `wp_user_id` ni el `avatar_url` en los inputs del formulario de instructores externos.
  3. En `third-party-selector.js`, al seleccionar un tercero, el objeto exportado no propagaba `logo_url` simultáneamente con `avatar_url`.
  4. En el backend (`modules/calendar/class-calendar-events.php`), los métodos `get_all()`, `get()` y `$persist_instructors` forzaban `teacher_id = 0` para externos sin resolver el avatar de WordPress ni propagar el avatar dinámico de `wp_aura_finance_third_parties`.
- **Solución Implementada:**
  - **Backend (`class-calendar-events.php`):**
    - En `get_all()` y `get()`: si un instructor externo tiene `real_id > 0` o un `third_party_id` vinculado a un usuario de WordPress (`wp_user_id`), se consulta y asigna `$inst->avatar = get_avatar_url(...)`, y se marca `is_wp_user: true`. Si no es usuario WP pero tiene logo de empresa, se asigna dicho logo.
    - En el guardado (`$persist_instructors`): cuando el instructor es un usuario de WP, se persiste en base de datos su `teacher_id` e `instructor_id` con dicho ID en vez de `0`.
  - **Selector de Terceros (`third-party-selector.js`):**
    - Se garantizó la exportación uniforme de `avatar_url`, `logo_url`, `wp_user_id` y `is_wp_user`.
  - **Formularios e Interfaces (`modal-partials.php` y `tab-programs.php`):**
    - Se incorporaron los campos ocultos `#ext-inst-wp-user-id` y `#ext-inst-avatar-url`.
    - En `renderExternalInstructorsList()`, se reemplazó el icono estático por una etiqueta `<img src="..." onerror="...">` con fallback elegante a `👤` (esmeralda) si es usuario del sistema o `🏢` (azul) si es tercero comercial, junto con la insignia distintiva correspondiente (`👤 Usuario WP` o `🏛️ Tercero`).
    - Se unificó este comportamiento de avatares en **Calendario (Clases)**, **Programas (Coordinadores)** y **Materias (Docentes)**.





flowchart TD
    A[Fase 1: Base de Datos y Migraciones para Hostinger] --> B[Fase 2: Materias por Módulos y Descripciones]
    B --> C[Fase 3: Capability CBAC y Conmutador en Portal Docente]
    C --> D[Fase 4: Tooltip Enriquecido y Visualización Apilada]
    D --> E[Fase 5: Clonar, Copiar, Pegar y Repetir Eventos]
    E --> F[Fase 6: Instructores Terceros y Filtro de Clases Asignadas]
    F --> G[Fase 7: Invitaciones de Calendario .ics / Google Calendar]
    G --> H[Fase 8: Empaquetado y Verificación de Despliegue en Hostinger]



Continua de inmediato con la Fase 7 (generación y envío de invitaciones de calendario a profesores mediante correo con archivo .ics descargable y enlaces directos de "Agregar a Google Calendar / Outlook")


- Las tarjetas de las materias salenapiladas una encima de la otra y no estan como antes, en la descripcionque aparece en el tooltip, en `.aura-tip-avatar-large`, muestra el logo del `Área Institucional`, si ha sido seleccionado alguno, ya que las areas tiene opcion de logo o imagen, al igual que en la inforamcion del Programa que aparece in icono o emogi y no el logo del area, por ejemplo en el programa de `Hadime Raíces 2026`, aparece un icono o emoji y no el logo: 🏢 Hadime Raíces.
- Que los botones en las tarjetas de Editar y Eliminar, sean mas elegantes y redondos y un poco mas pequeños, usa Íconos WordPress (Dashicons)

---

### Solución Aplicada (Tarjetas de Materias, Logos de Área Institucional y Botones Redondos Dashicons)

1. **Corrección de Tarjetas Apiladas (Grid CSS Restaurado)**:
   - Se detectó que en el renderizado de la tarjeta de materia faltaba el cierre `</div><!-- /.aura-subject-card-top -->` tras el badge de fechas programadas, lo que provocaba que el nombre, avatares, footer y las siguientes materias del grid se anidaran indebidamente dentro del encabezado.
   - Se restauró el cierre del contenedor `aura-subject-card-top` en [templates/calendar/tab-programs.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/tab-programs.php), restituyendo el flujo del grid CSS `.aura-subjects-grid` con `repeat(auto-fill, minmax(220px, 1fr))`.

2. **Logo del Área Institucional en Badge del Programa y en Tooltip de Materia**:
   - **Backend ([modules/calendar/class-calendar-programs.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-programs.php))**:
     - Se incorporó la extracción de `a.logo_id AS area_logo_id` en las consultas de programas.
     - En `populate_coordinators()`, se resuelve automáticamente la URL del archivo multimedia del logo (`wp_get_attachment_image_url($p->area_logo_id, 'medium') ?: wp_get_attachment_url($p->area_logo_id)`), guardándola en `$p->area_logo_url`.
   - **Cabecera del Programa ([templates/calendar/tab-programs.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/tab-programs.php))**:
     - El badge del área ahora renderiza la imagen `<img>` del logo institucional del área cuando existe (con fallback al emoji `🏢` si el área no tiene logo asignado).
   - **Tooltip Enriquecido de Materia ([assets/js/calendar-admin.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js))**:
     - Se pasan los atributos `data-area-logo` y `data-area-name` al elemento `.has-desc-tooltip`.
     - En `showSubjDescTooltip()`, si el programa/área posee logo institucional, se inyecta en `#aura-subj-desc-tip-avatar` (`.aura-tip-avatar-large`) la imagen del logo con estilo cuadrado institucional (`object-fit: cover; border-radius: 10px; background: #ffffff;`). Si no dispone de logo, se mantiene el fallback dinámico con las iniciales de la materia y su color pedagógico.

3. **Botones de Editar y Eliminar Redondos y Elegantes con Dashicons**:
   - Se reemplazaron los antiguos botones con emojis por botones circulares compactos `.aura-subj-action-btn` en las tarjetas de materias:
     - **Editar**: `<button class="aura-subj-action-btn btn-edit-subject"><span class="dashicons dashicons-edit"></span></button>`.
     - **Eliminar**: `<button class="aura-subj-action-btn is-danger btn-delete-subject"><span class="dashicons dashicons-trash"></span></button>`.
   - Se crearon reglas de estilo CSS dedicadas:
     - Diámetro de 26px, borde sutil circular (`border-radius: 50%`), sombra de elevación mínima.
     - Íconos Dashicons centrados de 14px.
     - Efecto hover con micro-escalado (`transform: scale(1.08)`), color de acento primario (`#6366f1`) para edición y rojo de alerta (`#ef4444`) para eliminación.
     - Total compatibilidad con Aura Dark Mode y WP Dark Mode (`rgba(255,255,255,0.08)` con bordes translúcidos).




- Correccion de los terceros, cuando agrego un tercero como profesor, instructor o docente, desde Docentes Externos / Catálogo de Terceros, no aparecen en los `Stack + Ring Animado`de:
    - En Programas en <!-- Avatares de coordinadores (Internos + Terceros / Entidades) --> `.aura-avatar-group`.
    - En las tarjetas de Materias del Programa en <!-- Profesores en avatar stack (Internos + Terceros / Externos) --> `.aura-avatar-group`.
    - En <!-- Tarjeta Destacada de Profesores con Avatar Grande (44px) + Ring Animado y Stack --> `.aura-avatar-stack`.
- Solo aparecen los seleccionados de Profesor(es) Titular(es) o Profesor(es) o Instructor(es) a Cargo donde se selecciona el Titular o Principal.

---

### Solución Aplicada (Terceros y Docentes Externos en Avatar Stacks y Ring Animado)

1. **Causa Raíz Identificada y Corregida**:
   - **Operador Null Coalescing (`??`) sobre cadenas vacías**: En `aura_avatar_stack_item_external()` y en los módulos de programas y materias, se utilizaba `$name = $ext['commercial_name'] ?? $ext['name']`. Si un tercero (ej: Melody Vidal o Sandra Mosquera) tiene la clave `'commercial_name' => ''`, el operador `??` no pasaba al nombre personal porque la clave existe y no es `null`. Esto generaba cadenas vacías y omitía completamente a los terceros del avatar stack. Se implementó una resolución rigurosa con `!empty()`.
   - **Búsqueda exhaustiva de fotos de perfil**: Cuando un tercero externo no disponía de `avatar_url` explícita, el sistema no buscaba si su `email` coincidía con un usuario WP registrado, ni consultaba si `wp_user_id` o `third_party_id` tenían foto en `wp_aura_students` o `wp_aura_finance_third_parties`. Se implementó un algoritmo de resolución multinivel y normalización de URLs malformadas de WP.
   - **Falta de columna `avatar_url` en eventos**: La tabla `wp_aura_cal_event_instructors` no persistía la columna `avatar_url` para terceros. Se agregó la columna a la estructura de la base de datos MySQL mediante migración en runtime y se ejecutó en la base de datos de producción local.
   - **Fallback automático a la materia**: Si un evento agendado no tenía instructores asignados explícitamente en la tabla de cruce de eventos, `get_events()` y `get()` ahora consultan y heredan de inmediato los profesores y docentes externos asignados a la materia vinculada.
   - **Sincronización en el modal de agendar evento**: Se agregó un listener a `#evt-subject-id` para que al seleccionar una materia, se precarguen automáticamente los docentes externos del Catálogo de Terceros en `currentEventExternalInstructors`.

2. **Archivos Modificados**:
   - [templates/calendar/tab-programs.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/templates/calendar/tab-programs.php): Corrección completa de `aura_avatar_stack_item_external()`.
   - [modules/calendar/class-calendar-programs.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-programs.php): Enriquecimiento de `populate_coordinators()` y persistencia de `avatar_url` en `save()`.
   - [modules/calendar/class-calendar-subjects.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-subjects.php): Enriquecimiento de `populate_teachers()` y persistencia de `avatar_url` en `save()`.
   - [modules/calendar/class-calendar-setup.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-setup.php): Adición de columna `avatar_url` e índice `third_party_id` en `wp_aura_cal_event_instructors`.
   - [modules/calendar/class-calendar-events.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php): Persistencia en `save()`, resolución y fallback a materia en `get_events()` y `get()`.
   - [assets/js/calendar-admin.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js): Renderizado con Ring Animado e iniciales/fotos en `openEventDetail()`, y auto-precarga de terceros al cambiar materia `#evt-subject-id`.


### Corrección de Visualización del Calendario idéntico a Google Calendar (Backend, Portal Docente y Estudiante)

1. **Causa Raíz Diagnosticada**:
   - **Orden `-duration`**: FullCalendar tenía configurado `eventOrder: 'start,-duration,allDay,title'`. El parámetro `-duration` forzaba a ordenar de mayor a menor duración, empujando los eventos largos (de varios días o 24 horas continuas) a colocarse en la primera fila de cada celda diaria de la vista de mes (`dayGridMonth`), apilándose horizontalmente y sepultando los eventos cortos con horarios específicos.
   - **`allDaySlot: false` en Semana y Día**: Al estar desactivada la fila superior "Todo el día", los eventos multiodía y de 24 horas continuas se renderizaban como bloques masivos que cubrían toda la columna horaria (00:00 a 24:00) en las vistas de semana (`timeGridWeek`) y día (`timeGridDay`), tapando por completo los eventos y clases de 1 o 2 horas.
   - **Ausencia del atributo `allDay` en el backend**: `get_events()` en `class-calendar-events.php` no detectaba ni pasaba el flag `allDay` a FullCalendar, tratando eventos de 9 días o 24 horas como eventos de franja horaria normal.
   - **Avatar del titular ausente o desbordado**: No se garantizaba la renderización del avatar en píldoras horizontales ni existía fallback a inicial circular si el titular no tenía foto cargada o si el evento era institucional/sin profesor directo.

2. **Soluciones Implementadas**:
   - **Detección Automática de `allDay` y Multiodía en Backend ([modules/calendar/class-calendar-events.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-events.php))**:
     - Se calcula `$is_multi_day = ($start_date !== $end_date)` y `$is_all_day_hours = ($duration_secs >= 86400 || ($start_time === '00:00' && $end_time === '00:00'))`.
     - Se asigna `'allDay' => ($is_multi_day || $is_all_day_hours)` en el array de FullCalendar.
     - Fallback de titular: si no hay instructores ni líderes, se asume el creador del evento (`$row->created_by`) asegurando siempre nombre y foto/inicial.
   - **Configuración Google Calendar en los 3 Calendarios ([assets/js/calendar-admin.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js) y [modules/calendar/class-calendar-frontend.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php))**:
     - Se habilitó `allDaySlot: true`, `allDayText: 'Todo el día'` y `slotEventOverlap: true`.
     - Se configuró `dayMaxEvents: 3` y `moreLinkClick: 'popover'` para vista mensual limpia.
     - **Corrección crítica de `eventOrder`**: En FullCalendar v6, la función interna de ordenamiento pasa objetos normalizados donde `start` es un número timestamp primitivo (`start: r.start.valueOf()`). Llamar a `.getTime()` arrojaba `TypeError: a.start.getTime is not a function`, lo cual congelaba el hilo de ejecución e impedía renderizar eventos y navegar fechas. Se corrigió implementando la directiva nativa y segura de FullCalendar: `eventOrder: '-allDay,start,duration,title'`.
     - **Garantía de Carga de FullCalendar en Frontend**: Se desacopló la dependencia estricta de `aura-calendar-frontend` dejándola en `['jquery']` y asegurando la carga incondicional del bundle `fullcalendar-bundle` en todas las pantallas de portales para evitar scripts huérfanos.
     - Se añadió la vista de día (`timeGridDay`) y soporte completo al Portal de Estudiantes.
   - **Renderizado Visual Google Calendar (`renderGoogleStyleEvent`)**:
     - Vista mensual (`dayGridMonth`) y fila superior "Todo el día" (`allDaySlot` en semana y día): diseño de píldora horizontal de una línea con esquinas redondeadas (`4px`), fondo del color asignado al evento, avatar circular del titular de 16px con borde blanco sutil (con inicial en círculo translúcido si no tiene foto), badge de líder si aplica, hora si no es todo el día y título con elipsis.
     - Rejilla de horas (`timeGridWeek` / `timeGridDay`): tarjeta vertical compacta o detallada con avatar en cabecera, hora, profesor y materia, preservando el color de fondo asignado y calculando automáticamente el texto de alto contraste (`#ffffff` o `#0f172a`).
   - **Estilos CSS Dedicados ([assets/css/calendar-admin.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css) y [assets/css/aura-frontend-dark-mode.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css))**:
     - Estilos para la fila `.fc-timegrid-allday` con etiqueta "Todo el día" alineada.
     - Estilos Material 3 / Google Calendar para el popover `.fc-popover` al hacer clic en `+X más` (sombra elevada, bordes redondeados y soporte completo Dark Mode).

### Erradicación Definitiva del Solapamiento de Eventos y Conservación de Color en Backend y Frontend

1. **Causas Raíz Identificadas**:
   - **Anulación del `marginTop` dinámico en `.fc-daygrid-event-harness`**: FullCalendar v6 en la vista mensual posiciona los eventos multiodía de forma absoluta (`top: [coord]px`) y coloca los eventos diarios individuales en el flujo normal, calculando y aplicando inline un desplazamiento superior: `style="margin-top: [placement.marginTop]px;"` para empujarlos exactamente debajo de las barras multiodía. Las reglas `.fc .fc-daygrid-event-harness { margin: 0 0 2px 0 !important; }` en `calendar-admin.css` y `margin: 0 !important;` en `aura-frontend-dark-mode.css` forzaban `margin-top: 0 !important;`, lo cual anulaba el cálculo inline de FullCalendar. Como resultado, los eventos individuales saltaban inmediatamente a `top: 0`, dibujándose directamente encima de las barras horizontales de los eventos largos/multiodía.
   - **Forzado destructivo de color azul (#6366f1) en Frontend**: En `aura-frontend-dark-mode.css`, las reglas `.aura-teacher-portal .fc { --fc-event-bg-color: #6366f1; }` y `background-color: var(--fc-event-bg-color, #6366f1) !important;` forzaban el color índigo sobre todos los eventos del portal de profesores y estudiantes, suprimiendo los colores reales de la base de datos (naranja, magenta, verde, etc.).
   - **`visibility: visible !important` sobre segmentos ocultos**: Reglas CSS forzaban visibilidad sobre los eventos, impidiendo que FullCalendar pudiera ocultar segmentos de relleno o eventos que no cabían en el día.

2. **Soluciones Aplicadas**:
   - **Limpieza de Márgenes en Harness ([assets/css/calendar-admin.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/calendar-admin.css) y [assets/css/aura-frontend-dark-mode.css](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/css/aura-frontend-dark-mode.css))**:
     - Se eliminaron todas las declaraciones `margin: ... !important` de `.fc-daygrid-event-harness` tanto en backend como frontend y móviles. Ahora el arnés solo tiene `padding: 0 !important;`, permitiendo que el navegador respete íntegramente el `marginTop` inline que FullCalendar calcula para separar verticalmente los eventos y evitar colisiones.
     - La separación entre eventos se aplicó limpiamente sobre el nodo hijo `.fc-daygrid-event` con `margin: 1px 0 !important;`.
   - **Restauración y Blindaje del Color Real ([assets/js/calendar-admin.js](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/assets/js/calendar-admin.js) y [modules/calendar/class-calendar-frontend.php](file:///c:/laragon/www/diserwp/wp-content/plugins/aura-business-suite/modules/calendar/class-calendar-frontend.php))**:
     - Se eliminaron las variables `--fc-event-bg-color: #6366f1;` globales forzadas en `aura-frontend-dark-mode.css`.
     - En el hook `eventDidMount(info)` de los tres calendarios (admin, profesor y estudiante), se inyectan dinámicamente con `info.el.style.setProperty()` las propiedades `background-color`, `border-color`, `--fc-event-bg-color`, `--fc-event-border-color` y `--fc-event-text-color` con `!important` a partir del color original de la base de datos (`info.event.backgroundColor || p.color`).
     - Se eliminó la regla `visibility: visible !important;` que rompía el manejo de elementos ocultos de FullCalendar.

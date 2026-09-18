# Registro de Correcciones y Tareas

⏳ A veces al cargar un modal se demora en mostrarse, requiero modernizar esto mostrando Skeleton Loaders dentro del modal para esto y no tener la sensacion de que no carga nada, pon esto en el archivo `documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md`

X Cambia todas las versiones de este plugin de wordpress a la version 1.8.1 y que no aparezca nada de 1.7.9

⏳ El sistema de Aura Suite es muy grande ya que tiene varios modulos con diferentes funciones, hay alguna manera de implementar documentacion de uso que sirvan como tutoriales para que los que operen cualquier funcionallidad y tengan dudas sigan estos pasos?

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

⏳ Corrige el archivo `assets\css\aura-design-system.css`, con los que tenga de referencia para evitar esto y anotalo en `documentacion\TRACKER-MIGRACION-DESIGN-SYSTEM.md`, para que no vuelva a suceder.

⏳ AGREGAR MODULO DE CONTACTOS con CRUD, sincronizado con los contactos de una cuenta de Google, quiero porde tener todos los contactos importantes de la organización en un modulo nuevo y que se sincronice con los terceros, proveedores, Personas Naturales, etc, todo sincronizado al 100% en los contactos de la cuenta de google, quiero tener todos los contactos de wordpress y terceros sincronizados en contactos de google y que pueda administrarlos desde el modulo.

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
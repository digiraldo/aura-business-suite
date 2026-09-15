# ImplementacionExcel.md

## 1) Objetivo

Dejar el modulo de Finanzas operando al 100% para el flujo real del Excel operativo 2025-2026, con:

- Registro confiable de ingresos y egresos.
- Presupuesto por Area/Programa con control de ejecucion.
- Importacion masiva estable desde CSV/XLSX.
- Reportes exportables y consistentes con datos reales/presupuesto.
- Cierre mensual trazable, sin errores funcionales ni regressions.

Este documento es el plan tecnico de implementacion, hardening y validacion para pasar de estado actual a estado productivo estable.

---

## 2) Alcance funcional comparado con el Excel

### Cobertura requerida por el Excel

1. Cargar movimientos 2025 y 2026 por mes.
2. Separar escenarios:
   - Real (actual).
   - Presupuesto/proyeccion.
3. Analizar por:
   - Categoria financiera.
   - Area/Programa.
   - Tipo (income/expense).
4. Entregar reportes de:
   - P&L.
   - Flujo de efectivo.
   - Presupuesto vs ejecutado.
   - Detalle por Area.
5. Exportar resultados en CSV/XLSX sin fallos.

### Estado actual observado

El modulo tiene base robusta y amplia, pero con brechas que impiden afirmar 100% productivo para el caso real del Excel:

- Importador funcional, con rollback e historial.
- Reportes funcionales con export CSV/XLSX.
- Presupuestos por Area activos.
- Aprobaciones automaticas activas por umbral.

Pendientes criticos detectados (detallados abajo):

- Inconsistencias funcionales entre UI, reglas de negocio y datos del Excel.
- Varios puntos con riesgo de error silencioso en exportes y reportes.
- Falta de cierre formal de QA integral end-to-end por escenario real/presupuesto.

---

## 3) Diagnostico profundo de brechas

## P0 (bloqueantes para "100% sin errores")

1. Regla de guardado exige `expense_category_id` para todo tipo de transaccion.
   - Riesgo: bloqueo de ingresos validos o inconsistencia semantica.
   - Impacto: errores en captura manual, integraciones y datos importados.
   - Accion: hacer la regla condicional por tipo o unificar modelo de categoria.

2. Accion masiva de exportacion sin implementacion completa en listado.
   - El frontend contempla `bulk_export_csv` y `bulk_export_pdf` pero no completa descarga.
   - El backend de accion masiva no tiene casos para exportar.
   - Impacto: funcionalidad visible no operativa.

3. Reporte de presupuesto con consulta potencialmente defectuosa.
   - Consulta en `get_budget_data()` construida con `prepare` y argumentos no utilizados.
   - Impacto: warnings SQL, comportamiento no determinista en algunos entornos.

4. Programacion de reportes por correo con adjuntos de alto riesgo de incompatibilidad.
   - Se debe verificar envio real con adjunto en `wp_mail` segun forma soportada.
   - Impacto: reportes automaticos no llegan o llegan incompletos.

5. Dependencia critica de `PhpSpreadsheet` no blindada a nivel de despliegue.
   - Si falta `vendor/autoload.php`, export/import Excel falla.
   - Impacto: quiebre de funcionalidades clave.

## P1 (altas, afectan confiabilidad de operacion)

1. Inconsistencia de limites de importacion.
   - UI comunica hasta 1000 filas, backend permite hasta 5000.
   - Impacto: confusion operativa y soporte.

2. Estandarizacion incompleta de `payment_method`.
   - Conviven terminos en ingles y espanol.
   - Impacto: reportes fragmentados por metodo de pago.

3. Importador no mapea explicitamente campos del modelo extendido (Area/escenario/etiquetas de plan).
   - Impacto: perdida de granularidad al comparar Excel vs sistema.

4. Falta de validacion cruzada estricta entre presupuesto por area y categoria presupuestaria.
   - Impacto: asientos validos tecnicamente pero pobres analiticamente.

5. Riesgo de ordenamiento no whitelist en listado de transacciones.
   - Sanitizar texto no equivale a whitelist SQL.
   - Impacto: seguridad y estabilidad SQL.

## P2 (mejoras de calidad y mantenimiento)

1. `admin-scripts.js` conserva handlers legacy de export con alertas "en desarrollo".
2. Falta bateria de pruebas automatas para import/export/reportes.
3. Faltan indicadores de calidad de datos (campos vacios, categorias huerfanas, area nula).

---

## 4) Plan de implementacion por fases

## Fase A - Hardening de modelo y reglas (P0)

Objetivo: asegurar que toda transaccion valida del Excel tenga representacion coherente en BD y UI.

Tareas:

1. Redefinir contrato de categorias:
   - Regla recomendada:
     - `category_id` = categoria presupuestaria/contable principal.
     - `expense_category_id` = detalle operativo opcional o condicional solo para egresos.
   - Ajustar validaciones en alta/edicion/importacion.

2. Corregir guardas de negocio para ingresos.

3. Migracion de datos existentes:
   - Completar `expense_category_id` cuando aplique.
   - Evitar filas semanticas invalidas.

4. Definir matriz de validacion obligatoria por tipo:
   - Income: fecha, tipo, categoria principal, monto, descripcion.
   - Expense: lo anterior + regla de detalle segun politica aprobada.

Entregable:

- Modelo consistente y documentado sin contradicciones funcionales.

---

## Fase B - Exportaciones y reportes confiables (P0/P1)

Objetivo: que todo reporte y export funcione en UI y jobs programados.

Tareas:

1. Implementar `bulk_export_csv` y `bulk_export_pdf` de forma completa.
2. Corregir consulta de presupuesto en reportes para eliminar errores de `prepare`.
3. Verificar y corregir adjuntos de reportes programados con archivos temporales reales.
4. Unificar delimitadores y formatos regionales en CSV/XLSX.
5. Estandarizar traducciones de metodo de pago en toda salida.

Entregable:

- Exportaciones masivas y de reportes 100% operativas, sin placeholders ni stubs.

---

## Fase C - Importador alineado al Excel real 2025-2026 (P1)

Objetivo: importar los dos escenarios (actual/presupuesto) sin perder trazabilidad.

Tareas:

1. Extender mapeo de importacion para columnas adicionales del Excel:
   - `area_id` o `area_name`.
   - `scenario` (actual/presupuesto).
   - `period_label` (ENE26, FEB26, etc).
   - `source_file`.

2. Reglas de normalizacion:
   - Fechas unificadas a `Y-m-d`.
   - Montos positivos, tipo determina signo logico.
   - Metodo de pago normalizado.

3. Deteccion de duplicados mejorada:
   - Hash por fecha+tipo+categoria+monto+descripcion.
   - Modo estricto y modo tolerante configurable.

4. Coherencia UX:
   - Igualar limite real de filas entre UI y backend.

5. Validacion de categorias:
   - Modo "solo catalogo existente".
   - Modo "crear categoria" con prefijo controlado y auditoria.

Entregable:

- Pipeline de importacion reproducible para 2025 y 2026 sin errores manuales.

---

## Fase D - Calidad de datos y cierre contable mensual (P1/P2)

Objetivo: garantizar que reportes y dashboard representen fielmente lo que el Excel espera.

Tareas:

1. Comando/accion de auditoria de calidad de datos:
   - Transacciones sin area.
   - Transacciones con categoria inexistente/inactiva.
   - Duplicados potenciales.
   - Montos atipicos por categoria.

2. Cierre mensual automatizable:
   - Corte por mes.
   - Snapshot de KPIs.
   - Export paquete mensual (CSV+XLSX).

3. Matriz de conciliacion Excel vs Aura:
   - Total ingresos mes.
   - Total egresos mes.
   - Balance.
   - Ejecucion presupuestaria.

Entregable:

- Proceso de cierre mensual con evidencia y trazabilidad.

---

## Fase E - Seguridad, permisos y performance (P1/P2)

Objetivo: robustecer estabilidad operativa en entorno real.

Tareas:

1. Whitelist estricta de `orderby` y `order` en listados.
2. Revisar todos los endpoints AJAX de finanzas con matriz de capabilities.
3. Revisar indices SQL para consultas frecuentes de reportes.
4. Control de tamano de exportaciones y limpieza de temporales.

Entregable:

- Endpoints y consultas endurecidas para uso productivo.

---

## 5) Archivos y componentes a intervenir

## Nucleo transaccional

- `modules/financial/class-financial-transactions.php`
- `modules/financial/class-financial-transactions-update.php`
- `modules/financial/class-financial-transactions-list.php`

## Importacion

- `modules/financial/class-financial-import.php`
- `assets/js/import-wizard.js`
- `templates/financial/import-page.php`

## Reportes y exportes

- `modules/financial/class-financial-reports.php`
- `modules/financial/class-financial-export.php`
- `assets/js/financial-reports.js`
- `assets/js/transactions-list.js`

## Presupuestos y configuracion

- `modules/financial/class-financial-budgets.php`
- `modules/financial/class-financial-settings.php`

## Limpieza legacy

- `assets/js/admin-scripts.js` (eliminar stubs legacy de export)

---

## 6) Matriz de pruebas obligatorias

## Pruebas funcionales

1. Crear ingreso manual con y sin area.
2. Crear egreso manual con control de presupuesto.
3. Flujo de aprobacion: pending -> approved/rejected.
4. Edicion y eliminacion (own/all) por rol.
5. Export individual y masivo (CSV/XLSX/PDF segun alcance).

## Pruebas de importacion

1. CSV real ene-abr actual.
2. CSV real may-dic presupuesto.
3. XLSX mixto con formatos de fecha distintos.
4. Rollback <24h y bloqueo >24h.
5. Duplicados en modo ignore/import.

## Pruebas de reportes

1. P&L por mes y anual.
2. Flujo de efectivo por metodo.
3. Presupuesto vs ejecutado por area.
4. Detalle por area con transacciones.
5. Export reporte CSV/XLSX y envio programado por correo.

## Pruebas de seguridad

1. Nonces invalidos en endpoints.
2. Usuario sin capability en acciones protegidas.
3. Intentos de ordenar por campos no permitidos.

## Pruebas de performance

1. Listado con filtros + paginacion.
2. Export con alto volumen (limite configurado).
3. Reportes con periodos amplios.

---

## 7) Criterios de aceptacion para "100%"

Se considera completado solo si se cumplen todos:

1. Cero errores PHP/JS en flujo completo de Finanzas.
2. Importacion de los dos CSV 2026 ejecuta y concilia con expected totals.
3. Reportes principales coinciden con control del Excel (tolerancia 0.01).
4. Exportaciones masivas funcionan sin stubs ni alertas de placeholder.
5. Programacion de reportes envia adjuntos validos.
6. Roles y permisos aplican correctamente en cada endpoint.
7. No existen warnings SQL por `prepare` ni consultas mal formadas.
8. QA documentado con evidencia de prueba por caso.

---

## 8) Plan de despliegue seguro

1. Respaldar BD y uploads antes de migraciones.
2. Ejecutar migraciones en staging con copia real.
3. Correr suite de pruebas funcionales y reconciliacion con Excel.
4. Publicar en ventana controlada.
5. Monitorear 72 horas:
   - errores PHP,
   - fallos AJAX,
   - reportes programados,
   - exportaciones.

Rollback:

- Revertir plugin a version estable previa.
- Restaurar BD si se detecta corrupcion de datos.
- Mantener log de lotes de import para deshacer movimientos recientes.

---

## 9) Orden recomendado de ejecucion

1. Fase A (modelo y validaciones).
2. Fase B (reportes/exportes).
3. Fase C (importador alineado al Excel).
4. Fase D (cierre mensual + calidad de datos).
5. Fase E (seguridad/performance).
6. QA integral y despliegue.

---

## 10) Resultado esperado al finalizar

El modulo de Finanzas quedara:

- Coherente con el Excel operativo real.
- Robusto para captura manual e importaciones masivas.
- Con reporteria y exportacion confiables.
- Con control presupuestario por area util en gestion.
- Con trazabilidad y seguridad adecuadas para uso productivo.

---

## 11) Sistema de ayuda contextual con icono ? (click para explicar)

Objetivo de esta seccion:

- Que cualquier usuario pueda entender que hace cada bloque de Finanzas sin capacitacion externa.
- Reducir errores de operacion (captura, aprobacion, importacion, reportes).
- Estandarizar una experiencia de ayuda consistente en todas las pantallas.

### 11.1 Reglas UX del icono ?

1. El icono ? debe aparecer junto a:
   - Titulos de pagina.
   - Filtros complejos.
   - Botones de accion critica.
   - Campos con impacto contable.
2. Al dar click, abrir:
   - Opcion A: Popover corto (1-3 parrafos).
   - Opcion B: Modal lateral con detalle y ejemplos.
3. Incluir siempre 4 bloques en la ayuda:
   - Que es.
   - Para que sirve.
   - Cuando usarlo.
   - Error comun a evitar.
4. Todo texto en espanol claro, orientado a operacion real (no tecnico).
5. Soporte teclado y accesibilidad:
   - Enter y Space sobre el icono.
   - Escape para cerrar.
   - Atributos aria-label y aria-expanded.

### 11.2 Tipos de ayuda a implementar

1. Micro-ayuda:
   - Tooltip muy corto para campos simples.
2. Ayuda contextual:
   - Popover con ejemplo practico.
3. Ayuda completa de modulo:
   - Modal con secciones y enlaces a sub-ayudas.

---

## 12) Mapa completo de ayudas por pantalla de Finanzas

Nota: cada item define el texto minimo recomendado para el icono ?.

### 12.1 Dashboard financiero

1. Ayuda en KPI Ingresos:
   - Explica que suma solo transacciones aprobadas del periodo.
2. Ayuda en KPI Egresos:
   - Explica relacion con presupuesto y alertas.
3. Ayuda en Balance:
   - Formula: ingresos - egresos.
4. Ayuda en graficas:
   - Como leer tendencias y comparativos.

### 12.2 Nueva transaccion

1. Tipo de transaccion:
   - Diferencia ingreso vs egreso.
2. Categoria principal:
   - Uso contable/presupuestario.
3. Categoria de gasto (si aplica):
   - Uso operativo detallado.
4. Area/Programa:
   - Impacto en control presupuestario por area.
5. Estado inicial:
   - Explicar pendiente vs aprobado automatico.
6. Comprobante:
   - Formatos permitidos y tamano maximo.

### 12.3 Listado de transacciones

1. Filtros avanzados:
   - Tipo, estado, fecha, categoria, usuario, area.
2. Acciones rapidas:
   - Aprobar, rechazar, ver, editar, eliminar.
3. Acciones masivas:
   - Riesgo de impacto en lote y recomendaciones.
4. Exportar:
   - Diferencia entre exportar seleccion vs filtros actuales.

### 12.4 Editar transaccion

1. Historial de cambios:
   - Para auditoria y trazabilidad.
2. Motivo de edicion:
   - Obligatorio en cambios sensibles.
3. Reglas de permiso:
   - Diferencia entre editar propias y todas.

### 12.5 Pendientes de aprobacion

1. Reglas de aprobacion:
   - Umbral y excepciones.
2. Rechazo:
   - Importancia del motivo.
3. Prioridad:
   - Transacciones antiguas y de mayor monto.

### 12.6 Papelera financiera

1. Soft delete:
   - Que significa enviar a papelera.
2. Restaurar vs eliminar definitivo:
   - Consecuencias operativas.

### 12.7 Categorias

1. Tipo de categoria:
   - Income, expense o ambos.
2. Jerarquia:
   - Categoria padre e impacto en orden.
3. Categoria inactiva:
   - Que sucede en formularios y reportes.

### 12.8 Presupuestos por area

1. Presupuesto total:
   - Monto asignado por periodo.
2. Ejecutado:
   - Suma de egresos aprobados.
3. Proyectado:
   - Estimacion lineal al cierre de periodo.
4. Alerta:
   - Umbrales y estados (ok, warning, critical, overrun).

### 12.9 Reportes

1. P&L:
   - Interpretacion y uso gerencial.
2. Flujo de efectivo:
   - Entradas/salidas por metodo de pago.
3. Presupuesto vs ejecutado:
   - Como interpretar sobregiro y disponible.
4. Auditoria:
   - Alcance, limites y filtros.
5. Exportar CSV/XLSX:
   - Que contiene cada formato.

### 12.10 Analitica visual

1. Tendencias y outliers:
   - Como detectar movimientos atipicos.
2. Proyecciones:
   - Supuestos y limitaciones.

### 12.11 Importacion CSV/XLSX

1. Paso 1 Subida:
   - Formatos soportados y limite de filas.
2. Paso 2 Mapeo:
   - Campos obligatorios y opcionales.
3. Paso 3 Validacion:
   - Error vs advertencia.
4. Paso 4 Importar:
   - Estado por defecto, duplicados, auto-categoria.
5. Historial y rollback:
   - Ventana de 24h y que revierte.

### 12.12 Etiquetas y busqueda

1. Etiquetas:
   - Estandar de nomenclatura.
2. Busqueda avanzada:
   - Combinacion de filtros y guardado de presets.

### 12.13 Auditoria y notificaciones

1. Auditoria:
   - Quien hizo que y cuando.
2. Notificaciones:
   - Eventos que disparan alertas.

### 12.14 Integraciones

1. Export contable:
   - Uso para sistemas externos.
2. Integraciones cross-modulo:
   - Inventario, estudiantes, biblioteca, vehiculos.

### 12.15 Mi Finanzas / Libro mayor / Caja USD

1. Mi dashboard personal:
   - Movimientos vinculados al usuario.
2. Libro mayor por usuario:
   - Saldos y detalle historico.
3. Caja chica USD:
   - Registro en USD y conversion a MXN.

---

## 13) Especificacion tecnica sugerida para implementar las ayudas

### 13.1 Estructura de datos de ayudas

Guardar catalogo de ayudas por clave unica:

- help_key: identificador estable.
- title: titulo corto.
- short_text: texto tooltip.
- long_text: texto popover/modal.
- screen: pantalla donde aparece.
- roles: que roles la ven.

Opciones de almacenamiento:

1. Archivo PHP central de configuracion de ayudas.
2. Option de WordPress con fallback a archivo.
3. Filtro para extender desde otros modulos.

### 13.2 Componente UI recomendado

1. Clase CSS unica:
   - aura-help-icon.
2. Atributos data:
   - data-help-key.
3. Handler JS global:
   - Click en icono abre panel con contenido.
4. Reutilizable en todas las pantallas.

### 13.3 Comportamiento de carga

1. Cargar solo ayudas de la pantalla actual.
2. Cache local por sesion para evitar requests repetidos.
3. Fallback si falta contenido:
   - Mostrar mensaje generico de ayuda no disponible.

### 13.4 Seguridad y permisos

1. Sanitizar contenido de ayuda al render.
2. Si ayuda incluye datos dinamicos, validar capability.
3. Evitar exponer claves internas o SQL.

---

## 14) Criterios de aceptacion del sistema de ayuda

1. 100% de pantallas principales de Finanzas tienen al menos 1 icono ?.
2. Cada accion critica tiene ayuda contextual.
3. Textos en lenguaje operativo comprensible para tesoreria y administracion.
4. Accesibilidad por teclado y lectores de pantalla validada.
5. Cero errores JS/PHP al abrir/cerrar ayudas.
6. Cobertura minima de QA:
   - Desktop y movil.
   - Admin y roles de finanzas.

---

## 15) Plan de ejecucion rapido para esta funcionalidad

1. Semana 1:
   - Definir catalogo de ayudas.
   - Implementar componente icono ? + popover.
2. Semana 2:
   - Integrar en Dashboard, Transacciones, Importacion y Reportes.
3. Semana 3:
   - Integrar resto de pantallas.
   - QA funcional + accesibilidad.
4. Semana 4:
   - Ajustes de texto con usuarios reales.
   - Liberacion a produccion.

Resultado esperado:

- El usuario entiende cada parte de Finanzas al momento de usarla, con ayuda contextual inmediata, consistente y mantenible.

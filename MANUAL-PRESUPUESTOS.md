# Manual de Usuario — Presupuestos por Categoría
**Aura Business Suite**

---

## ¿Para qué sirve este módulo?

El módulo de **Presupuestos** te permite establecer un límite de gasto para cada categoría financiera (por ejemplo: Nómina, Publicidad, Servicios Públicos). El sistema lleva el control automático de cuánto ya se ha gastado y te avisa por correo electrónico cuando se acerca o supera ese límite.

En pocas palabras:
> "Defino cuánto puedo gastar en una categoría durante un período → el sistema me dice en tiempo real cómo voy."

---

## ¿Cómo acceder?

1. Ingresa al panel de administración de WordPress.
2. En el menú lateral busca **Aura Suite**.
3. Haz clic en **📊 Presupuestos**.

---

## Pantalla principal

Al ingresar verás:

### Barra de resumen (parte superior)
Seis tarjetas con un vistazo general:

| Tarjeta | Qué muestra |
|---|---|
| Total presupuestado | Suma de todos los presupuestos activos |
| Total ejecutado | Cuánto se ha gastado en total |
| Presupuestos | Cuántos presupuestos tienes activos |
| Sobrepasados | Cuántos ya superaron el 100% |
| En alerta | Cuántos están entre 70% y 99% |
| En buen estado | Cuántos están por debajo del 70% |

### Filtros
Puedes filtrar la tabla por:
- **Tipo de período:** Mensual, Trimestral, Semestral o Anual.
- **Estado:** En buen estado, Advertencia, Crítico o Sobrepasado.

Haz clic en **Limpiar** para quitar los filtros.

### Tabla de presupuestos
Cada fila muestra:

| Columna | Descripción |
|---|---|
| **Categoría** | Ícono y nombre de la categoría |
| **Período** | Tipo de período (Mensual, Trimestral, etc.) |
| **Presupuesto** | Monto total definido |
| **Ejecutado** | Cuánto se ha gastado hasta hoy |
| **Disponible** | Lo que queda (o el exceso si está en rojo) |
| **%** | Porcentaje consumido del presupuesto |
| **Progreso** | Barra visual de color según el estado |
| **Acciones** | Botones: Ver detalle, Editar, Eliminar |

#### Colores de la barra de progreso

| Color | Qué significa |
|---|---|
| 🟢 Verde | Menos del 70% consumido — todo bien |
| 🟡 Amarillo | Entre 70% y 89% — empezar a controlar |
| 🟠 Naranja | Entre 90% y 99% — casi agotado |
| 🔴 Rojo | 100% o más — presupuesto sobrepasado |

---

## Crear un presupuesto nuevo

1. Haz clic en el botón **+ Nuevo Presupuesto** (parte superior derecha).
2. Se abre un formulario. Completa los campos:

### Campos del formulario

**Categoría**
Selecciona la categoría financiera a la que quieres asignar el presupuesto. Las categorías están agrupadas en *Ingresos* y *Egresos*. Los presupuestos normalmente se aplican a categorías de **Egresos**.

**Monto del presupuesto**
Escribe el monto máximo que puedes gastar en esta categoría durante el período. Ejemplo: `500000`

**Tipo de período**
Elige cada cuánto se renueva el presupuesto:

| Opción | Duración |
|---|---|
| **Mensual** | 1 mes |
| **Trimestral** | 3 meses |
| **Semestral** | 6 meses |
| **Anual** | 12 meses |

> **Consejo:** Al elegir el tipo de período, la **Fecha fin se calcula automáticamente** a partir de la Fecha inicio. Solo debes ajustarla si quieres un rango personalizado.

**Fecha inicio / Fecha fin**
Define el rango de vigencia del presupuesto. Pueden ser fechas futuras, presentes o pasadas.

Ejemplo para un presupuesto mensual de febrero:
- Fecha inicio: `2026-02-01`
- Fecha fin: `2026-02-28` *(calculada automáticamente)*

**Alertas**

| Opción | Qué hace |
|---|---|
| Alerta al llegar a __% | Envía un correo cuando el gasto llegue a ese porcentaje. Por defecto: 80% |
| Alerta al sobrepasar el 100% | Envía un correo si el presupuesto se supera |

**Notificar a**

| Opción | Quién recibe el correo |
|---|---|
| Creador del presupuesto | El usuario que creó este presupuesto |
| Administradores | Todos los administradores del sistema |
| Emails adicionales | Otras personas (escribe sus correos separados por coma) |

3. Haz clic en **Guardar Presupuesto**.

---

## Editar un presupuesto

1. En la tabla, haz clic en el botón ✏️ (lápiz) de la fila que deseas modificar.
2. El formulario se abre con los datos actuales.
3. Realiza los cambios y haz clic en **Guardar Presupuesto**.

> **Nota:** Al editar un presupuesto, las alertas se "resetean" para que puedan volver a dispararse si el gasto sigue subiendo.

---

## Ver el detalle de un presupuesto

1. Haz clic en el botón 👁️ (ojo) de cualquier fila de la tabla.
2. Se abre el panel de detalle con tres secciones:

### Sección izquierda

**Gráfico de dona**
Muestra visualmente cuánto del presupuesto está consumido (porción coloreada) y cuánto queda disponible (porción gris). Si está sobrepasado, aparece una porción roja indicando el exceso.

**Estadísticas del período**

| Dato | Descripción |
|---|---|
| Presupuesto | Monto total definido |
| Ejecutado | Lo que se ha gastado (y el % correspondiente) |
| Disponible | Lo que queda por gastar |
| Proyección al fin del período | Estimado de cuánto se gastará al terminar el período, basado en el ritmo de gasto actual |

> **Ejemplo:** Si a mitad del mes de 30 días ya gastaste $400,000 y el presupuesto es $500,000, la proyección indica $800,000 al finalizar el mes — es decir, lo superará.

**Ajuste rápido**
Puedes aumentar o reducir el monto del presupuesto sin abrir el formulario de edición:
- Selecciona si quieres ajustar por **%** o por **Monto fijo**.
- Escribe el valor (positivo para aumentar, negativo para reducir).
- Haz clic en **Aplicar**.

Ejemplos:
- `+10` en modo `%` → aumenta el presupuesto un 10%
- `-200000` en modo `Monto fijo` → reduce el presupuesto en $200,000

### Sección derecha

**Gráfico histórico**
Compara el presupuesto vs. el gasto real en los **últimos 6 períodos** del mismo tipo. Útil para ver tendencias.

**Transacciones del período**
Lista las últimas 50 transacciones registradas en esa categoría durante el período vigente, con fecha, descripción, monto y estado de aprobación.

---

## Eliminar un presupuesto

1. Haz clic en el botón 🗑️ (papelera) de la fila que deseas eliminar.
2. Confirma la acción en el mensaje de alerta.

> El presupuesto no se borra definitivamente de la base de datos, solo se desactiva. Las transacciones históricas no se ven afectadas.

---

## Alertas por correo electrónico

El sistema revisa automáticamente **una vez al día** todos los presupuestos activos y envía correos de alerta en dos situaciones:

### Alerta de umbral
Se envía cuando el gasto supera el porcentaje configurado (por defecto 80%).

**Asunto del correo:** Alerta de presupuesto — [Nombre de categoría]

**Contenido:** Indica el porcentaje consumido, cuánto queda disponible y el período vigente.

### Alerta de exceso
Se envía cuando el gasto supera el 100% del presupuesto (si tienes activada esa opción).

**Asunto del correo:** Presupuesto sobrepasado — [Nombre de categoría]

> **Importante:** Cada alerta se envía **una sola vez por período**. Si editas o ajustas el presupuesto, el sistema puede volver a enviarla si el gasto sigue siendo alto.

---

## Widget en el Dashboard Financiero

En la página del **Dashboard Financiero** encontrarás un bloque llamado **Estado de Presupuestos** que muestra los 5 presupuestos más consumidos del período actual. Es un resumen rápido para ver de un vistazo si algo requiere atención.

Haz clic en **Ver todos →** para ir directamente a la página de Presupuestos.

---

## Preguntas frecuentes

**¿Puedo tener más de un presupuesto para la misma categoría?**
Sí, siempre que los períodos no se superpongan. Por ejemplo, puedes tener un presupuesto mensual de enero y otro de febrero para la misma categoría.

**¿Qué pasa si registro gastos de meses anteriores?**
El sistema calcula el monto ejecutado basándose en la fecha de la transacción, no en cuándo se registró. Si ingresas una transacción con fecha dentro del período vigente de un presupuesto, ese gasto se contabiliza automáticamente.

**¿Las transacciones rechazadas cuentan en el presupuesto?**
No. Solo se contabilizan transacciones en estado **aprobado** o **pendiente**. Las rechazadas y las eliminadas no afectan el presupuesto.

**¿Qué ocurre al terminar el período?**
Nada automáticamente. El presupuesto queda con la información del período transcurrido para consulta histórica. Debes crear un nuevo presupuesto para el siguiente período.

**No recibí el correo de alerta, ¿qué hago?**
Verifica que:
1. El correo del creador o administrador esté configurado correctamente en WordPress.
2. Las opciones de notificación estén activadas en el presupuesto (✓ Notificar creador / ✓ Administradores).
3. El correo no haya caído en la carpeta de spam.

**¿Puedo cambiar el presupuesto a mitad del período?**
Sí, usa el botón ✏️ para editar el monto, o el **Ajuste rápido** dentro del panel de detalle sin necesidad de abrir el formulario completo.

---

## Consejos de uso

- **Crea los presupuestos antes de que empiece el período** para que el seguimiento sea desde el primer día.
- **Revisa el gráfico histórico** periódicamente para identificar categorías donde consistentemente se supera el presupuesto y ajustarlo de forma realista.
- **Usa la proyección** como señal temprana: si a mitad del mes la proyección ya supera el presupuesto, actúa antes de que ocurra.
- **Configura emails adicionales** para incluir al responsable directo de cada área de gasto, no solo a los administradores.

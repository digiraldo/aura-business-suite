# Guía Simple: Cómo funcionan los Presupuestos por Programa

> Para personas que no saben de programación

---

## ¿De qué trata esto?

El sistema permite que cada **programa del instituto** (Hadime Junior, Hadime Raíces, etc.) tenga su propio **presupuesto** y que los gastos de cada programa se registren y controlen de forma separada.

---

## Los tres pilares del sistema

### 1. Los Programas (Áreas)
Cada programa tiene un nombre, un color y un **responsable** — es decir, un usuario del sistema que está a cargo de ese programa.

Los programas que vienen cargados por defecto son:

| Programa | Color |
|---|---|
| Hadime Junior | Amarillo |
| Hadime Más | Verde |
| Hadime Raíces | Borgoña |
| Hadime Líderes | Morado |
| Hadime Misioneros | Azul |
| Hadime Voluntarios | Naranja |
| Hadime Rentas | Gris |
| Hadime Nuevo Programa | Turquesa |

---

### 2. Los Presupuestos
Cada programa puede tener un **presupuesto asignado** para un período de tiempo (mensual, trimestral, semestral o anual).

El presupuesto dice: *"Este programa puede gastar hasta $X en este período de tiempo."*

Cuando se crea un presupuesto, también se puede configurar:
- **Umbral de alerta:** si el gasto llega al 80% del límite, avisar por correo.
- **Alerta al sobrepasar:** si el gasto supera el 100%, avisar por correo.
- **A quiénes avisar:** al creador del presupuesto, a los administradores, y/o a otros correos específicos.

---

### 3. Las Transacciones (Gastos e Ingresos)
Cada gasto o ingreso que se registra queda vinculado a un **programa específico**. Así el sistema sabe cuánto ha gastado cada programa.

---

## ¿Qué ve cada persona?

### El responsable de un programa
- Solo ve los gastos y presupuestos de **su programa**.
- Cuando registra un gasto, el sistema automáticamente lo asigna a su programa — **no puede cambiarlo**.

### El administrador
- Ve los gastos y presupuestos de **todos los programas**.
- Puede crear, editar y eliminar presupuestos.
- Puede ajustar el monto de un presupuesto sin borrarlo.

---

## ¿Qué pasa cuando se registra un gasto?

```
El usuario llena el formulario de gasto
        │
        ▼
El sistema revisa si ese programa tiene presupuesto activo
        │
        ├── SÍ tiene presupuesto → muestra cuánto queda disponible
        │
        └── NO tiene presupuesto → avisa que no hay presupuesto configurado
        │
        ▼
El usuario ingresa el monto
        │
        ├── Si el monto cabe dentro del presupuesto → se registra normalmente
        │
        └── Si el monto supera el presupuesto disponible
              → Se avisa con una advertencia en rojo
              → El gasto queda en estado "pendiente de aprobación"
              → Un administrador debe aprobarlo manualmente
```

---

## ¿Cómo se controla que no se gaste de más?

Cada día, el sistema revisa automáticamente todos los presupuestos activos:

- Si el gasto acumulado llegó al **80%** (o el porcentaje configurado) → envía un correo de advertencia.
- Si el gasto acumulado **superó el 100%** → envía otro correo de alerta.
- Estos correos solo se envían **una vez** por presupuesto (no spamea todos los días).

Además, si alguien intenta registrar un gasto que sobrepasaría el límite, el sistema **no lo aprueba automáticamente** — queda esperando la revisión de un administrador.

---

## ¿Qué información se puede ver en la pantalla de Presupuestos?

En la sección de presupuestos del menú, se puede ver:

- Una **lista de todos los presupuestos** con una barra de progreso que muestra cuánto se ha gastado.
- Al hacer clic en un presupuesto:
  - **Estadísticas rápidas:** monto asignado, ejecutado, disponible, y una proyección de si el programa llegará al límite antes de que termine el período.
  - **Historial gráfico:** cómo ha evolucionado el gasto en los últimos períodos.
  - **Lista de transacciones** del período actual.
  - **Análisis por categoría:** qué tipo de gastos (impresos, transporte, etc.) representan la mayor parte del presupuesto.

---

## ¿Qué información se ve al registrar un gasto?

Cuando alguien llena el formulario de un nuevo gasto, aparece automáticamente un **recuadro informativo** que muestra:

```
💰  Hadime Raíces → Papelería
    Asignado: $5,000   |   Gastado: $1,200 (24%)   |   Disponible: $3,800
```

Si el monto que ingresa supera el disponible, aparece una advertencia en rojo antes de guardar.

---

## Resumen en una sola frase

> Cada programa tiene su presupuesto, cada gasto se le asigna a un programa, y el sistema avisa automáticamente cuando el dinero se está acabando o se sobrepasó el límite.

---

*Guía actualizada: 21 de febrero de 2026*

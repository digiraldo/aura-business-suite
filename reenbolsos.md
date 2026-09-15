Listed directory financial
Viewed class-financial-user-ledger.php:1-60
Viewed class-financial-accounts.php:1-80
Searched for "reimbursements"
Searched for "ajax_create_reimbursement"
Searched for "function ajax_create_reimbursement"
Searched for "reimbursement"

En **Aura Business Suite**, el flujo para cuando **tú pagas compras de tu bolsillo personal y luego la organización te reembolsa el dinero** está diseñado mediante el módulo de **Reembolsos de Gastos y Libro Mayor por Usuario**.

A continuación tienes el paso a paso exacto de cómo registrarlo y cómo auditar lo que te deben vs. lo que ya te pagaron.

---

### 📋 Flujo de Trabajo en Aura Suite

```
  [1. Compras con tu dinero] 
             ↓
  [2. Registras el Reembolso en Aura Suite]  ───→  Estado: "Pendiente" (La org te debe)
             ↓
  [3. La org te consigna a tu cuenta bancaria]
             ↓
  [4. Registras el Pago de Reembolso]        ───→  Estado: "Pagado" (Deuda saldada)
             ↓
  [5. Análisis, Métricas y Libro Mayor]      ───→  Historial, saldos y reportes
```

---

### 🔹 Paso 1: Registrar el Gasto Realizado con tu Dinero

Hay **dos formas** de ingresarlo en el sistema:

#### **Opción A (Recomendada y directa): Desde la sección de Reembolsos**
1. Ve a **Finanzas** ➔ **Bancos y Cuentas** (URL: `admin.php?page=aura-financial-accounts`).
2. Haz clic en la pestaña **Reembolsos**.
3. Pulsa el botón **+ Nuevo Reembolso**:
   * **Beneficiario / Usuario:** Selecciona tu usuario del sistema.
   * **Monto Adeudado:** El total del ticket o factura que pagaste de tu bolsillo.
   * **Área / Categoría:** Asigna el Área (ej. *Hadime Más*, *Operaciones*, etc.) y la Categoría del gasto (ej. *Papelería*, *Transporte*, *Materiales*).
   * **Notas / Concepto:** Describe qué compraste y para qué.
   * **Adjuntar comprobante:** Sube la foto del ticket o factura.
4. Al guardar, quedará registrado automáticamente con estado **`Pendiente`** (la organización tiene una cuenta por pagar contigo).

#### **Opción B: Desde el Registro de Transacciones**
1. Ve a **Finanzas** ➔ **Transacciones** ➔ **+ Nueva Transacción**.
2. En el tipo de transacción, selecciona **`Reembolso de gastos`**.
3. En el campo **Usuario / Responsable**, indícate a ti mismo y especifica el área a la que pertenece el gasto.
4. El sistema creará el pasivo pendiente por pagar a tu favor.

---

### 🔹 Paso 2: Cuando la Organización te Consigna el Dinero de Vuelta

Cuando la organización tenga fondos y te transfiera el dinero a tu cuenta personal:

1. Ve a **Finanzas** ➔ **Bancos y Cuentas** ➔ Pestaña **Reembolsos**.
2. Ubica el reembolso (o reembolsos) que te están pagando y haz clic en el botón de acción **Pagar Reembolso** (icono de dinero/tarjeta).
3. En el modal emergente:
   * **Cuenta de Origen:** Selecciona la cuenta bancaria de la organización desde donde salió el dinero (ej. *Banco Principal*, *Caja General*).
   * **Monto Pagado:** Puedes pagar el total o un monto parcial si te abonaron una parte.
   * **Referencia / Comprobante:** Número de transferencia o foto del comprobante de consignación.
4. Al confirmar:
   * El estado del reembolso pasa a **`Pagado`** (o **`Parcial`** si fue un abono).
   * Se descuenta automáticamente el dinero de la cuenta bancaria de la organización.
   * La deuda queda saldada en el sistema.

---

### 🔹 Paso 3: ¿Cómo ver Estadísticas y saber qué te deben o qué te pagaron?

Para auditar en cualquier momento cuánto dinero has puesto de tu bolsillo y el estado de tus cuentas:

1. **Libro Mayor por Usuario (`admin.php?page=aura-user-ledger`):**
   * Selecciona tu usuario.
   * Verás tu **Balance Acumulado Corriente**, mostrando el total exacto que la organización te debe en tiempo real.
   * Puedes filtrar por rangos de fecha y conceptos.
2. **Filtros en la Tabla de Reembolsos:**
   * En la pestaña **Reembolsos**, puedes filtrar por estado:
     * **Pendientes:** Lista todo lo que compraste y que la organización aún tiene pendiente de pagarte.
     * **Pagados:** Historial de todas las compras que ya te fueron devueltas/reembolsadas.
3. **Exportación a Excel / CSV:**
   * Puedes pulsar el botón **Exportar CSV** para tener una planilla detallada con fechas, montos, comprobantes y números de transferencia para tu control personal.

Viewed prompt-maestro.md:29-53

























Searched for "ajax_pay_reimbursement"
Viewed class-financial-accounts.php:1520-1610

**Exactamente, tienes dos maneras de hacerlo según el nivel de detalle que necesites:**

---

### 1️⃣ Si lo registras directamente desde este Modal de Reembolsos:
* **¿Qué hace?**: Registra la **deuda por pagar** directamente a tu nombre en el módulo de Tesorería/Bancos (`wp_aura_finance_reimbursements`).
* **¿Qué pasa al pagar?**: Descuenta automáticamente el dinero de la cuenta bancaria de la organización y genera un **Movimiento de Cuenta** (*Débito bancario por Reembolso*).
* **Sobre el Área**: Este modal es para control rápido de tesorería y préstamos/deudas, por lo que no pide área ni categoría contable en este formulario simplificado.

---

### 2️⃣ Si quieres que SÍ afecte el Área, el Presupuesto y aparezca en Transacciones:
Si compraste algo específico para un Área (ejemplo: *Hadime Más*) y quieres que:
1. **Aparezca en la tabla general de Transacciones**.
2. **Se descuente del Presupuesto del Área** (y se vea en el Dashboard del Área).
3. **Se cree automáticamente la deuda de Reembolso** para que la organización te pague.

👉 **El método ideal es registrarlo desde Transacciones:**
1. Ve a **Finanzas ➔ Transacciones ➔ + Nueva Transacción**.
2. En **Tipo de Transacción**, elige: **`Reembolso de gastos`**.
3. Selecciona el **Área** (ej. *Hadime Más*) y la **Categoría**.
4. En **Monto**, pon lo que pagaste.
5. En **Usuario / Responsable**, selecciónate a ti.
6. Al guardar:
   * Queda registrada la **Transacción** con su Área y Presupuesto.
   * El sistema genera **automáticamente** la deuda en la pestaña **Reembolsos** con el ID de la transacción ya vinculado.
   * Cuando la organización te devuelva el dinero, solo vas a **Reembolsos ➔ Pagar Reembolso**, y todo el ciclo contable queda cerrado y conciliado.
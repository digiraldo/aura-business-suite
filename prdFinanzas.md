<div align="center">

![Logo AURA](image/logo-aura.png)

# PRD - Módulo de Finanzas
## AURA Business Suite - Sistema de Gestión Financiera
### Prompts de Desarrollo por Fases e Ítems

> **Gestión Financiera Completa para Pequeñas Empresas y Fundaciones**

</div>

---

## 📋 Índice de Contenidos

1. [Introducción](#1-introducción)
   - 1.1 Visión del Módulo
   - 1.2 Usuarios Objetivo
   - 1.3 Funcionalidades Principales
   - 1.4 Casos de Uso Específicos del Instituto
2. [Arquitectura del Módulo](#2-arquitectura-del-módulo)
   - 2.1 Estructura de Base de Datos
   - 2.2 Capabilities del Módulo
   - 2.3 Integración con Otros Módulos (Inventario, Biblioteca, Vehículos)
3. [FASE 1: Configuración y Categorías](#fase-1-configuración-y-categorías)
4. [FASE 2: Gestión de Transacciones](#fase-2-gestión-de-transacciones)
5. [FASE 3: Dashboard y Visualizaciones](#fase-3-dashboard-y-visualizaciones)
6. [FASE 4: Reportes y Exportación](#fase-4-reportes-y-exportación)
7. [FASE 5: Funciones Avanzadas](#fase-5-funciones-avanzadas)
8. [Mejores Prácticas](#mejores-prácticas)

---

## 1. Introducción

### 1.1 Visión del Módulo
El módulo de Finanzas de AURA está diseñado específicamente para pequeñas empresas y fundaciones que necesitan:
- Control preciso de ingresos y egresos
- Gestión de categorías personalizables
- Flujo de aprobación de transacciones
- Reportes financieros claros y visuales
- Trazabilidad completa de operaciones

### 1.2 Usuarios Objetivo
- **Fundaciones**: Control de donaciones, becas y asignaciones
- **Pequeñas Empresas**: Gestión de gastos operativos y ventas
- **ONGs**: Transparencia en uso de recursos
- **Cooperativas**: Administración de fondos comunes

### 1.3 Funcionalidades Principales

#### Gestión de Categorías
- ✅ Crear categorías de ingresos y egresos
- ✅ Editar categorías existentes
- ✅ Eliminar categorías (con validación de uso)
- ✅ Categorías activas/inactivas (soft delete)
- ✅ Subcategorías jerárquicas (opcional)
- ✅ Asignación de colores para visualización

#### Gestión de Transacciones
- ✅ Registro de ingresos y egresos
- ✅ Edición de transacciones propias o todas (según permisos)
- ✅ Eliminación con confirmación (soft o hard delete)
- ✅ Adjuntar comprobantes (imágenes/PDFs)
- ✅ Sistema de estados (pendiente, aprobado, rechazado)
- ✅ Notas y observaciones
- ✅ Etiquetas personalizadas

### 1.4 Casos de Uso Específicos del Instituto

El módulo de Finanzas está optimizado para las necesidades reales de un **instituto tipo rancho/finca con actividades múltiples**:

#### 🏛️ Contexto del Instituto

**Ubicación**: Terreno tipo rancho/finca  
**Actividades principales**:
- Voluntarios para limpieza y mantenimiento
- Recepción de misioneros
- Clases a estudiantes (inscripciones vía formularios)
- Alquiler de instalaciones a iglesias cristianas

**Recursos del instituto**:
- ⚡ Herramientas eléctricas y de batería
- 🚜 Herramientas de motor para jardinería
- 💧 Sistema automatizado de riego
- 🎤 Kiosco/terraza con equipo de sonido (mixer, cabinas, micrófonos)
- 📚 Biblioteca con sistema de préstamos
- 🚗 Vehículos para transporte
- 🏠 Habitaciones con camas, baños, ventiladores
- 🚻 Baños públicos

---

#### 💰 Casos de Uso Financieros

**1. Ingreso por Alquiler de Instalaciones**
```
Escenario: Iglesia alquila el kiosco para evento
→ Registro de ingreso: Categoría "Alquileres y Rentas → Alquiler a Iglesias"
→ Monto: $XXX
→ Comprobante: Foto del recibo firmado
→ Integración: Formulario de reserva (módulo Formularios)
```

**2. Egreso por Mantenimiento de Herramienta**
```
Escenario: Cortadora de césped requiere reparación
→ Registro de egreso: Categoría "Mantenimiento → Herramientas de Motor"
→ Integración: Item #45 del módulo Inventario (Cortadora Husqvarna)
→ Adjunto: Factura del taller
→ Estado: Pendiente aprobación del supervisor
```

**3. Ingreso por Inscripción de Estudiante**
```
Escenario: Estudiante se inscribe a curso bíblico
→ Registro de ingreso: Categoría "Inscripciones y Matrículas → Cursos"
→ Integración: Formulario de inscripción (módulo Formularios)
→ Validación: Pago verificado antes de generar ingreso
```

**4. Egreso por Compra de Libros para Biblioteca**
```
Escenario: Adquisición de 10 libros nuevos
→ Registro de egreso: Categoría "Biblioteca → Adquisición de Libros"
→ Monto: $XXX (costo total)
→ Integración: Crear 10 items en módulo Biblioteca automáticamente
→ Aprobado por: Director del instituto
```

**5. Egreso por Consumo Eléctrico del Sistema de Riego**
```
Escenario: Pago mensual de electricidad
→ Registro de egreso: Categoría "Servicios Públicos → Electricidad"
→ Integración: Lectura del módulo Electricidad
→ Comparación: Mes actual vs mes anterior (alerta si incrementa >20%)
```

**6. Donación Específica para Misiones**
```
Escenario: Misionero recibe donación etiquetada
→ Registro de ingreso: Categoría "Donaciones → Donación de Misiones"
→ Etiquetas: #misiones #uganda #abril2026
→ Reporte: Transparencia de uso de donaciones por proyecto
```

**7. Egreso por Suministros de Limpieza para Voluntarios**
```
Escenario: Compra de productos de limpieza
→ Registro de egreso: Categoría "Suministros de Limpieza"
→ Inventario: Actualizar stock de productos
→ Control: Presupuesto mensual de limpieza ($XXX)
```

---

#### 📊 Reportes Clave para el Instituto

1. **Reporte de Ingresos por Alquileres**: Cuánto genera cada espacio
2. **Costo de Mantenimiento de Instalaciones**: Desglose por categoría
3. **Balance de Inscripciones**: Ingresos por estudiantes vs costos de cursos
4. **Eficiencia de Recursos**: Inversión en herramientas vs uso
5. **Transparencia de Donaciones**: Uso de fondos etiquetados
6. **Consumo Energético**: Tendencia de gastos eléctricos
7. **Presupuesto Anual**: Comparativo real vs proyectado

---

## 2. Arquitectura del Módulo

### 2.1 Estructura de Base de Datos

```sql
-- Tabla de categorías financieras
CREATE TABLE wp_aura_finance_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    type ENUM('income', 'expense', 'both') DEFAULT 'both',
    parent_id BIGINT UNSIGNED NULL,
    color VARCHAR(7) DEFAULT '#3498db',
    icon VARCHAR(50) DEFAULT 'dashicons-category',
    description TEXT,
    is_active BOOLEAN DEFAULT 1,
    display_order INT DEFAULT 0,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parent_id) REFERENCES wp_aura_finance_categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_type (type),
    INDEX idx_active (is_active),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de transacciones financieras
CREATE TABLE wp_aura_finance_transactions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM('income', 'expense') NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT NOT NULL,
    notes TEXT,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    payment_method VARCHAR(50),
    reference_number VARCHAR(100),
    recipient_payer VARCHAR(255),
    receipt_file VARCHAR(255),
    tags VARCHAR(500),
    -- Campos de integración con otros módulos
    related_module ENUM('inventory', 'library', 'vehicles', 'forms') NULL COMMENT 'Módulo relacionado',
    related_item_id BIGINT UNSIGNED NULL COMMENT 'ID del item en el módulo relacionado',
    related_action VARCHAR(50) NULL COMMENT 'Acción: purchase, maintenance, rental, loan, etc.',
    -- Campos de auditoría
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    approved_at DATETIME NULL,
    rejection_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_type (transaction_type),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_date (transaction_date),
    INDEX idx_deleted (deleted_at),
    INDEX idx_related (related_module, related_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de presupuestos por categoría (opcional)
CREATE TABLE wp_aura_finance_budgets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    budget_amount DECIMAL(15, 2) NOT NULL,
    period_type ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    alert_threshold INT DEFAULT 80,
    is_active BOOLEAN DEFAULT 1,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLAS DEL MÓDULO DE INVENTARIO Y MANTENIMIENTOS
-- ============================================================================
-- Estas tablas se integran con el módulo de Finanzas para rastrear costos
-- de mantenimientos y compras de equipos

-- Tabla de equipos y herramientas del inventario
CREATE TABLE wp_aura_inventory_equipment (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del equipo o herramienta',
    brand VARCHAR(100) COMMENT 'Marca',
    model VARCHAR(100) COMMENT 'Modelo',
    serial_number VARCHAR(100) UNIQUE COMMENT 'Número de serie',
    category VARCHAR(100) NOT NULL COMMENT 'Categoría: Hidráulico, Motor4T, Motor2T, Eléctrico, Sonido, etc.',
    photo_url VARCHAR(255) COMMENT 'URL de foto del equipo',
    acquisition_date DATE COMMENT 'Fecha de compra',
    acquisition_cost DECIMAL(10, 2) DEFAULT 0 COMMENT 'Costo de compra',
    current_value DECIMAL(10, 2) DEFAULT 0 COMMENT 'Valor actual estimado',
    physical_location VARCHAR(255) COMMENT 'Ubicación física (almacén, taller, campo)',
    status ENUM('available', 'in_use', 'maintenance', 'repair', 'retired') DEFAULT 'available',
    
    -- CONFIGURACIÓN DE MANTENIMIENTOS PERIÓDICOS
    requires_maintenance BOOLEAN DEFAULT 0 COMMENT 'Activar sección de mantenimientos periódicos',
    interval_type ENUM('time', 'hours', 'both') COMMENT 'Por tiempo, por horas de uso, o ambos',
    interval_months INT COMMENT 'Intervalo en meses (3, 6, 12...)',
    interval_hours INT COMMENT 'Intervalo en horas de uso (50, 100, 150...)',
    last_maintenance_date DATE COMMENT 'Fecha del último mantenimiento',
    last_maintenance_hours INT COMMENT 'Horas del equipo al último mantenimiento',
    next_maintenance_date DATE COMMENT 'Calculado automáticamente',
    next_maintenance_hours INT COMMENT 'Calculado automáticamente',
    alert_days_before INT DEFAULT 7 COMMENT 'Días de anticipación para alertas',
    alert_users TEXT COMMENT 'JSON array de IDs de usuarios a notificar',
    
    -- ESPECIFICACIONES TÉCNICAS (según tipo de equipo)
    oil_type VARCHAR(100) COMMENT 'Tipo de aceite requerido',
    oil_capacity DECIMAL(5, 2) COMMENT 'Capacidad de aceite en litros',
    fuel_type VARCHAR(50) COMMENT 'Gasolina, diésel, mezcla 2T',
    rated_pressure INT COMMENT 'Presión nominal (equipos hidráulicos) en PSI',
    voltage INT COMMENT 'Voltaje (equipos eléctricos)',
    current_hours INT DEFAULT 0 COMMENT 'Contador de horas de uso actual',
    
    -- DATOS ADMINISTRATIVOS
    responsible_user_id BIGINT UNSIGNED COMMENT 'Usuario responsable',
    supplier VARCHAR(255) COMMENT 'Proveedor',
    warranty_expiration DATE COMMENT 'Vencimiento de garantía',
    manual_file VARCHAR(255) COMMENT 'URL del manual de usuario (PDF)',
    notes TEXT COMMENT 'Notas administrativas',
    
    -- AUDITORÍA
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL COMMENT 'Soft delete',
    
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (responsible_user_id) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_category (category),
    INDEX idx_status (status),
    INDEX idx_requires_maintenance (requires_maintenance),
    INDEX idx_next_maintenance_date (next_maintenance_date),
    INDEX idx_deleted (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de historial de mantenimientos
CREATE TABLE wp_aura_inventory_maintenance (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL COMMENT 'Equipo al que se le hizo mantenimiento',
    maintenance_type ENUM('preventive', 'corrective', 'oil_change', 'cleaning', 'inspection', 'major_repair') NOT NULL,
    maintenance_date DATE NOT NULL COMMENT 'Fecha en que se realizó',
    equipment_hours INT COMMENT 'Lectura del horímetro al momento del mantenimiento',
    
    -- DESCRIPCIÓN DEL TRABAJO
    work_description TEXT NOT NULL COMMENT 'Descripción detallada del trabajo realizado',
    parts_replaced TEXT COMMENT 'Lista de partes reemplazadas (aceite, filtros, bujías, válvulas)',
    parts_cost DECIMAL(10, 2) DEFAULT 0 COMMENT 'Costo de repuestos/insumos',
    labor_cost DECIMAL(10, 2) DEFAULT 0 COMMENT 'Costo de mano de obra (si externo)',
    total_cost DECIMAL(10, 2) GENERATED ALWAYS AS (parts_cost + labor_cost) STORED COMMENT 'Costo total calculado',
    
    -- INTERNO VS EXTERNO
    performed_by ENUM('internal', 'external') NOT NULL,
    workshop_name VARCHAR(255) COMMENT 'Nombre del taller (si externo)',
    internal_technician_id BIGINT UNSIGNED COMMENT 'Usuario técnico interno',
    invoice_file VARCHAR(255) COMMENT 'URL de factura/comprobante',
    invoice_number VARCHAR(100) COMMENT 'Número de factura',
    
    -- ESTADO POST-MANTENIMIENTO
    post_status ENUM('operational', 'needs_followup', 'out_of_service') DEFAULT 'operational',
    followup_date DATE COMMENT 'Fecha de próximo seguimiento si aplica',
    observations TEXT COMMENT 'Observaciones adicionales',
    
    -- INTEGRACIÓN CON FINANZAS
    create_finance_transaction BOOLEAN DEFAULT 0 COMMENT 'Si se creó transacción en Finanzas',
    finance_transaction_id BIGINT UNSIGNED COMMENT 'ID de transacción en wp_aura_finance_transactions',
    
    -- AUDITORÍA
    registered_by BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que registró el mantenimiento',
    approved_by BIGINT UNSIGNED COMMENT 'Usuario que aprobó (si requiere aprobación)',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    
    FOREIGN KEY (equipment_id) REFERENCES wp_aura_inventory_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (internal_technician_id) REFERENCES wp_users(ID) ON DELETE SET NULL,
    FOREIGN KEY (registered_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    FOREIGN KEY (finance_transaction_id) REFERENCES wp_aura_finance_transactions(id) ON DELETE SET NULL,
    INDEX idx_equipment (equipment_id),
    INDEX idx_maintenance_type (maintenance_type),
    INDEX idx_maintenance_date (maintenance_date),
    INDEX idx_performed_by (performed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de préstamos de equipos
CREATE TABLE wp_aura_inventory_loans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    equipment_id BIGINT UNSIGNED NOT NULL,
    loaned_to_user_id BIGINT UNSIGNED COMMENT 'Usuario de WordPress que retira',
    loaned_to_name VARCHAR(255) COMMENT 'Nombre de persona externa (si no es usuario)',
    loan_date DATETIME NOT NULL,
    expected_return_date DATE NOT NULL,
    actual_return_date DATETIME NULL,
    loan_reason VARCHAR(255) COMMENT 'Motivo o proyecto',
    condition_on_loan TEXT COMMENT 'Estado del equipo al prestar',
    condition_on_return TEXT COMMENT 'Estado del equipo al devolver',
    requires_maintenance_after BOOLEAN DEFAULT 0 COMMENT 'Requiere mantenimiento después de devolución',
    hours_used INT COMMENT 'Horas de uso durante el préstamo',
    observations TEXT,
    status ENUM('active', 'returned', 'overdue') DEFAULT 'active',
    
    -- AUDITORÍA
    created_by BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que registró el préstamo',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    returned_by BIGINT UNSIGNED COMMENT 'Usuario que registró la devolución',
    returned_at DATETIME NULL,
    
    FOREIGN KEY (equipment_id) REFERENCES wp_aura_inventory_equipment(id) ON DELETE CASCADE,
    FOREIGN KEY (loaned_to_user_id) REFERENCES wp_users(ID) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (returned_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_equipment (equipment_id),
    INDEX idx_status (status),
    INDEX idx_expected_return (expected_return_date),
    INDEX idx_loan_date (loan_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLAS DEL MÓDULO DE ESTUDIANTES E INSCRIPCIONES
-- ============================================================================
-- Estas tablas se integran con el módulo de Finanzas para registrar automáticamente
-- los pagos de estudiantes como ingresos

-- Tabla de cursos y capacitaciones
CREATE TABLE wp_aura_courses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL COMMENT 'Nombre del curso/capacitación',
    slug VARCHAR(255) UNIQUE NOT NULL,
    description TEXT COMMENT 'Descripción y requisitos',
    category VARCHAR(100) COMMENT 'Bíblicos, Técnicos, Liderazgo, etc.',
    duration_weeks INT COMMENT 'Duración en semanas',
    duration_months INT COMMENT 'Duración en meses',
    base_cost DECIMAL(10, 2) NOT NULL DEFAULT 0 COMMENT 'Costo total del curso',
    start_date DATE COMMENT 'Fecha de inicio',
    end_date DATE COMMENT 'Fecha de finalización',
    available_slots INT DEFAULT 0 COMMENT 'Cupos disponibles',
    enrolled_count INT DEFAULT 0 COMMENT 'Cantidad de inscritos',
    status ENUM('open', 'in_progress', 'completed', 'cancelled') DEFAULT 'open',
    
    -- AUDITORÍA
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_category (category),
    INDEX idx_start_date (start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de estudiantes
CREATE TABLE wp_aura_students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    identification VARCHAR(50) UNIQUE COMMENT 'Cédula, DNI, pasaporte',
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(50),
    address TEXT,
    birth_date DATE,
    country VARCHAR(100),
    photo_url VARCHAR(255) COMMENT 'URL de foto del estudiante',
    
    -- RELACIÓN CON WORDPRESS
    user_id BIGINT UNSIGNED UNIQUE COMMENT 'Usuario WordPress asociado para login',
    
    -- ESTADO
    status ENUM('active', 'inactive', 'graduated', 'suspended') DEFAULT 'active',
    graduation_date DATE COMMENT 'Fecha de graduación',
    notes TEXT COMMENT 'Notas administrativas',
    
    -- AUDITORÍA
    created_by BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que registró al estudiante',
    registration_method ENUM('web_form', 'manual') DEFAULT 'web_form',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_identification (identification)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de inscripciones (relación estudiante-curso)
CREATE TABLE wp_aura_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    course_id BIGINT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    
    -- COSTOS Y BECAS
    course_cost DECIMAL(10, 2) NOT NULL COMMENT 'Costo del curso para este estudiante',
    scholarship_type ENUM('none', 'internal', 'external') DEFAULT 'none',
    scholarship_percentage DECIMAL(5, 2) DEFAULT 0 COMMENT '0-100%',
    scholarship_entity VARCHAR(255) COMMENT 'Organización que otorga beca externa',
    scholarship_amount DECIMAL(10, 2) GENERATED ALWAYS AS (course_cost * scholarship_percentage / 100) STORED,
    amount_to_pay DECIMAL(10, 2) GENERATED ALWAYS AS (course_cost - (course_cost * scholarship_percentage / 100)) STORED,
    
    -- ESQUEMA DE PAGO
    payment_scheme ENUM('full', 'installments', 'initial_plus_installments') NOT NULL DEFAULT 'full',
    number_of_installments INT DEFAULT 1 COMMENT 'Número de cuotas',
    installment_amount DECIMAL(10, 2) COMMENT 'Monto por cuota',
    initial_payment DECIMAL(10, 2) COMMENT 'Pago inicial si es esquema mixto',
    first_installment_date DATE COMMENT 'Fecha de primera cuota',
    installment_frequency ENUM('monthly', 'biweekly', 'weekly') DEFAULT 'monthly',
    
    -- ESTADO DE LA INSCRIPCIÓN
    enrollment_status ENUM('pending_approval', 'confirmed', 'cancelled') DEFAULT 'pending_approval',
    payment_status ENUM('pending_first', 'current', 'overdue', 'completed', 'scholarshiped_100') DEFAULT 'pending_first',
    total_paid DECIMAL(10, 2) DEFAULT 0 COMMENT 'Total pagado hasta el momento',
    balance DECIMAL(10, 2) GENERATED ALWAYS AS (amount_to_pay - total_paid) STORED COMMENT 'Saldo pendiente',
    
    -- APROBACIÓN DE BECA INTERNA
    scholarship_approved_by BIGINT UNSIGNED COMMENT 'Usuario que aprobó la beca interna',
    scholarship_approved_at DATETIME COMMENT 'Fecha de aprobación de beca',
    scholarship_justification TEXT COMMENT 'Justificación de beca interna',
    scholarship_documentation VARCHAR(255) COMMENT 'Documentación de beca externa',
    
    -- NOTAS
    notes TEXT COMMENT 'Observaciones administrativas',
    
    -- AUDITORÍA
    created_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED COMMENT 'Usuario que aprobó la inscripción',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    approved_at DATETIME NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (student_id) REFERENCES wp_aura_students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES wp_aura_courses(id) ON DELETE CASCADE,
    FOREIGN KEY (scholarship_approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL,
    INDEX idx_student (student_id),
    INDEX idx_course (course_id),
    INDEX idx_enrollment_status (enrollment_status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_scholarship_type (scholarship_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de cuotas programadas (generadas automáticamente)
CREATE TABLE wp_aura_payment_quotas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL COMMENT 'Para queries rápidas',
    quota_number INT NOT NULL COMMENT 'Número de cuota (1, 2, 3...)',
    due_date DATE NOT NULL COMMENT 'Fecha de vencimiento',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'Monto de la cuota',
    status ENUM('pending', 'paid', 'overdue', 'cancelled') DEFAULT 'pending',
    paid_date DATE COMMENT 'Fecha en que se pagó',
    days_overdue INT DEFAULT 0 COMMENT 'Días de atraso',
    
    -- AUDITORÍA
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (enrollment_id) REFERENCES wp_aura_enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES wp_aura_students(id) ON DELETE CASCADE,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_student (student_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de pagos de estudiantes
CREATE TABLE wp_aura_student_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    enrollment_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL COMMENT 'Para queries rápidas',
    quota_id BIGINT UNSIGNED COMMENT 'Cuota que se está pagando (si aplica)',
    
    -- DATOS DEL PAGO
    amount_paid DECIMAL(10, 2) NOT NULL COMMENT 'Monto recibido',
    payment_date DATE NOT NULL,
    payment_concept VARCHAR(255) COMMENT 'Cuota 1/3, Pago inicial, Pago completo',
    payment_method VARCHAR(50) COMMENT 'Efectivo, Transferencia, Tarjeta',
    reference_number VARCHAR(100) COMMENT 'Número de comprobante/factura',
    receipt_file VARCHAR(255) COMMENT 'Comprobante escaneado',
    
    -- INTEGRACIÓN CON FINANZAS
    finance_transaction_id BIGINT UNSIGNED COMMENT 'ID en wp_aura_finance_transactions',
    create_finance_transaction BOOLEAN DEFAULT 1 COMMENT 'Si se creó transacción automática',
    
    -- AUDITORÍA
    registered_by BIGINT UNSIGNED NOT NULL COMMENT 'Usuario que registró el pago',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT COMMENT 'Observaciones del pago',
    
    FOREIGN KEY (enrollment_id) REFERENCES wp_aura_enrollments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES wp_aura_students(id) ON DELETE CASCADE,
    FOREIGN KEY (quota_id) REFERENCES wp_aura_payment_quotas(id) ON DELETE SET NULL,
    FOREIGN KEY (finance_transaction_id) REFERENCES wp_aura_finance_transactions(id) ON DELETE SET NULL,
    FOREIGN KEY (registered_by) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_enrollment (enrollment_id),
    INDEX idx_student (student_id),
    INDEX idx_payment_date (payment_date),
    INDEX idx_finance_transaction (finance_transaction_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Capabilities del Módulo

```php
// Array de capabilities del módulo de finanzas
$finance_capabilities = [
    'aura_finance_create',          // Crear transacciones
    'aura_finance_edit_own',        // Editar propias transacciones
    'aura_finance_edit_all',        // Editar cualquier transacción
    'aura_finance_delete_own',      // Eliminar propias transacciones
    'aura_finance_delete_all',      // Eliminar cualquier transacción
    'aura_finance_approve',         // Aprobar/rechazar transacciones
    'aura_finance_view_own',        // Ver solo propias transacciones
    'aura_finance_view_all',        // Ver todas las transacciones
    'aura_finance_charts',          // Ver gráficos financieros
    'aura_finance_export',          // Exportar reportes
    'aura_finance_category_manage', // Gestionar categorías
    'aura_finance_budget_manage',   // Gestionar presupuestos
];
```

### 2.3 Integración con Otros Módulos

El módulo de Finanzas actúa como núcleo del sistema, integrándose con los demás módulos para proporcionar trazabilidad financiera completa:

#### 🔗 Integración con Módulo de INVENTARIO Y MANTENIMIENTOS

**Flujo de integración**:
```
Compra de Herramienta → Registro en Inventario + Egreso en Finanzas
Mantenimiento de Equipo → Registro en Inventario + Egreso en Finanzas (automático)
Mantenimiento Externo → Registro con costo + Creación automática de egreso en Finanzas
Baja de Herramienta → Actualización en Inventario + Nota en Finanzas (opcional)
```

**Arquitectura de Mantenimientos Periódicos**:

El módulo de Inventario ahora incluye un sistema completo de mantenimientos periódicos que se integra profundamente con Finanzas:

**Equipos con Mantenimiento Periódico**:
- **Compresor de aire**: Cada 3 meses o 100 horas
- **Bomba de aire**: Cada 6 meses
- **Tanque precargado hidráulico**: Cada 6 meses
- **Motores 4 tiempos** (cortadoras, generadores): Cambio de aceite cada 50 horas o 3 meses
- **Motores 2 tiempos** (motosierras, desmalezadoras): Limpieza cada 25 horas o 2 meses

**Casos de uso integrados**:

**Caso 1: Compra de herramienta eléctrica**:
   - Usuario registra egreso en Finanzas (categoría: "Compra de Herramientas y Equipos → Herramientas Eléctricas")
   - Opción: "¿Agregar al inventario?" → Crea automáticamente item en módulo Inventario
   - Datos compartidos: Monto, fecha, descripción, comprobante
   - Si el equipo requiere mantenimiento: Activa sección de mantenimientos periódicos al crearlo

**Caso 2: Mantenimiento preventivo de compresor (interno)**:
   - Sistema alerta 7 días antes: "Mantenimiento de Compresor vence en X días"
   - Técnico interno realiza mantenimiento (cambio aceite, limpieza filtros)
   - Registra en módulo Inventario:
     * Tipo: Preventivo
     * Costo partes: $25 (aceite + filtros comprados)
     * Costo mano obra: $0 (interno)
     * Realizado por: Técnico Juan Pérez
   - **NO se crea transacción en Finanzas** (costo bajo, mantenimiento interno)
   - Sistema actualiza: `next_maintenance_date` = fecha actual + 3 meses
   - Historial del equipo muestra: Total invertido en mantenimientos acumulado

**Caso 3: Reparación externa de cortadora de césped**:
   - Cortadora con motor 4 tiempos requiere reparación mayor
   - Usuario registra mantenimiento en Inventario:
     * Tipo: Reparación mayor
     * Realizado por: Externo
     * Taller: "Maquinaria López"
     * Costo total: $120 (partes $80 + mano obra $40)
     * Adjunta: Factura PDF
     * Checkbox: ✅ "Crear transacción en Finanzas"
   - **Sistema automáticamente**:
     1. Crea registro en `wp_aura_finance_transactions`:
        * `transaction_type`: 'expense'
        * `category_id`: ID de "Mantenimiento → Herramientas de Motor"
        * `amount`: $120
        * `description`: "Reparación mayor - Cortadora Husqvarna - Taller Maquinaria López"
        * `receipt_file`: Copia de factura adjunta
        * `payment_method`: Efectivo (o el que configure)
        * `related_module`: 'inventory'
        * `related_item_id`: ID del equipo en inventario
        * `related_action`: 'maintenance'
        * `status`: 'approved' (o 'pending' según configuración)
     2. Guarda `finance_transaction_id` en tabla `wp_aura_inventory_maintenance`
     3. **Enlace bidireccional**:
        * Desde Finanzas: Ver detalles del equipo y mantenimiento
        * Desde Inventario: Ver transacción financiera asociada
     4. Actualiza contadores:
        * Total invertido en mantenimientos del equipo: $345 (acumulado)
        * Porcentaje del valor: 86% (si equipo costó $400)
     5. Alerta si costo de mantenimientos > 60% del valor de compra: 
        "💡 Considerar reemplazo de este equipo"

**Caso 4: Mantenimiento de generador por horas de uso**:
   - Generador configurado: Mantenimiento cada 100 horas
   - Contador actual: 395 horas
   - Último mantenimiento: 300 horas
   - Próximo mantenimiento: 400 horas
   - Sistema alerta: "⚠️ Generador próximo a mantenimiento (faltan 5 horas)"
   - Al registrar salida/uso del generador:
     * Usuario actualiza horas: 405 horas
     * Sistema verifica: 405 > 400 → ALERTA ROJA: "Mantenimiento vencido"
   - Técnico realiza mantenimiento (cambio aceite + revisión)
   - Registro en sistema actualiza:
     * `last_maintenance_hours`: 405
     * `next_maintenance_hours`: 505 (405 + 100)
     * Alerta desaparece del dashboard

**Caso 5: Compra de equipo desde transacción financiera**:
   - Usuario crea egreso en Finanzas: "Compra Bomba de agua Honda"
   - Categoría: "Compra de Herramientas y Equipos → Equipos Hidráulicos"
   - Monto: $850
   - Después de guardar, sistema pregunta:
     "¿Deseas agregar este item al Inventario?"
     [Sí, agregar] [No, solo registro financiero]
   - Si "Sí":
     * Abre formulario pre-llenado:
       - Nombre: "Bomba de agua Honda"
       - Costo de adquisición: $850
       - Fecha de compra: [fecha de la transacción]
       - Categoría: Hidráulico
     * Usuario completa:
       - Marca/Modelo/Serie
       - Activar mantenimientos periódicos: ✅
       - Intervalo: 6 meses
       - Usuarios a notificar: [Técnico Juan, Supervisor Pedro]
     * Al guardar:
       - Crea equipo en `wp_aura_inventory_equipment`
       - Enlaza con transacción: `related_item_id` = ID del equipo
       - Calcula `next_maintenance_date` = fecha actual + 6 meses
       - Programa alertas automáticas

**Campos adicionales en transacciones financieras** (YA EXISTENTES):
```php
// En tabla wp_aura_finance_transactions
'related_module' => 'inventory',        // Módulo relacionado
'related_item_id' => BIGINT,            // ID del equipo en wp_aura_inventory_equipment
'related_action' => VARCHAR(50),        // 'purchase', 'maintenance', 'rental', 'repair'
```

**Campos nuevos en equipos de inventario**:
```php
// En tabla wp_aura_inventory_equipment
'requires_maintenance' => BOOLEAN,      // Activar sección de mantenimientos
'interval_type' => 'time'|'hours'|'both',  // Por tiempo, horas, o ambos
'interval_months' => INT,               // Cada X meses
'interval_hours' => INT,                // Cada X horas
'next_maintenance_date' => DATE,        // Calculado automáticamente
'alert_days_before' => INT,             // Días de anticipación (default: 7)
'alert_users' => TEXT,                  // JSON de IDs de usuarios
```

**API REST - Endpoints de integración**:

```php
// 1. Crear transacción desde mantenimiento de inventario
POST /wp-json/aura/v1/finance/transactions/from-maintenance
Body: {
  "equipment_id": 45,
  "maintenance_id": 123,
  "action": "maintenance",
  "amount": 120.00,
  "workshop_name": "Taller López",
  "invoice_file": "url/to/invoice.pdf",
  "category_slug": "mantenimiento-herramientas-motor"
}
Response: {
  "transaction_id": 789,
  "message": "Egreso creado automáticamente desde mantenimiento"
}

// 2. Crear equipo en inventario desde transacción financiera
POST /wp-json/aura/v1/inventory/equipment/from-transaction
Body: {
  "transaction_id": 456,
  "name": "Compresor Ingersoll Rand",
  "category": "Hidráulico",
  "requires_maintenance": true,
  "interval_months": 3
}
Response: {
  "equipment_id": 78,
  "message": "Equipo agregado al inventario y enlazado con transacción"
}

// 3. Obtener resumen de costos de mantenimiento de un equipo
GET /wp-json/aura/v1/inventory/equipment/{id}/maintenance-costs
Response: {
  "equipment_id": 45,
  "equipment_name": "Cortadora Husqvarna",
  "acquisition_cost": 400.00,
  "total_maintenance_cost": 345.00,
  "maintenance_cost_percentage": 86.25,
  "maintenance_count": 5,
  "average_maintenance_cost": 69.00,
  "last_maintenance_date": "2025-12-15",
  "next_maintenance_date": "2026-03-15",
  "status": "needs_attention", // Si % > 60
  "recommendation": "Considerar reemplazo del equipo"
}
```

---

#### 🔗 Integración con Módulo de ESTUDIANTES E INSCRIPCIONES

**Flujo de integración**:
```
Inscripción de Estudiante → Registro en Estudiantes + Calendario de Pagos
Pago de Cuota/Matrícula → Registro en Estudiantes + Ingreso Automático en Finanzas
Beca 100% Externa → Registro sin generar ingresos en Finanzas
Beca Interna → Registro + Nota de egreso ficticio (opcional para tracking)
```

**Arquitectura de Pagos de Estudiantes**:

El módulo de Estudiantes gestiona inscripciones con diferentes esquemas de pago y becas, integrándose automáticamente con Finanzas para registrar cada pago como ingreso.

**Esquemas de Pago Soportados**:
1. **Pago Completo**: Un solo pago al inscribirse (descuento 5% configurable)
2. **Pago Inicial + Cuotas**: 20-50% inicial + saldo en cuotas mensuales
3. **Solo Cuotas**: Sin pago inicial, todo en cuotas (1-12 meses)
4. **Con Beca**: El sistema calcula el monto a pagar tras aplicar porcentaje de beca

**Tipos de Becas**:
- **Beca Interna** (25%, 50%, 75%, 100%): Otorgada por el instituto, requiere aprobación
- **Beca Externa** (cualquier %): Pagada por organización externa, requiere documentación
- **Beca Combinada**: Estudiante puede tener beca parcial + pagar en cuotas

**Casos de uso integrados**:

**Caso 1: Estudiante con pago completo (sin beca)**
```
Estudiante: Pedro García
Curso: Liderazgo Cristiano ($400)
Esquema: Pago completo

Flujo:
1. Estudiante se inscribe vía formulario web
2. Sistema crea registro en wp_aura_students
3. Sistema crea inscripción en wp_aura_enrollments:
   - course_cost: $400
   - scholarship_type: 'none'
   - amount_to_pay: $400 (con descuento 5%: $380)
   - payment_scheme: 'full'
4. Al registrar pago en módulo Estudiantes:
   - Tesorería registra pago de $380
   - Sistema crea registro en wp_aura_student_payments
   
   **INTEGRACIÓN AUTOMÁTICA CON FINANZAS:**
   5. Sistema crea transacción en wp_aura_finance_transactions:
      * transaction_type: 'income'
      * category_id: ID de "Inscripciones y Matrículas → Cursos"
      * amount: $380
      * description: "Pago completo - Pedro García - Curso Liderazgo Cristiano"
      * payment_method: Transferencia (copiado del registro de pago)
      * receipt_file: Comprobante adjunto
      * related_module: 'students'
      * related_item_id: ID del estudiante
      * related_action: 'enrollment_payment'
      * status: 'approved' (configurablemente automático)
   6. Guarda finance_transaction_id en wp_aura_student_payments
   7. Actualiza wp_aura_enrollments:
      * total_paid: $380
      * balance: $0
      * payment_status: 'completed'
   8. Email al estudiante: "Pago recibido, inscripción confirmada"
```

**Caso 2: Estudiante con beca interna 50% + pago en 3 cuotas**
```
Estudiante: María López
Curso: Curso Bíblico ($300)
Beca: Interna 50%
Esquema: Pago inicial $50 + 3 cuotas de $33.33

Flujo:
1. María solicita beca interna al inscribirse
2. Sistema crea inscripción con enrollment_status: 'pending_approval'
3. Director Académico aprueba beca:
   - scholarship_type: 'internal'
   - scholarship_percentage: 50
   - scholarship_amount: $150 (calculado automáticamente)
   - amount_to_pay: $150
   - scholarship_approved_by: ID del director
4. Sistema genera calendario de cuotas en wp_aura_payment_quotas:
   - Cuota 0 (pago inicial): $50 - Vencimiento: 15/Feb/2026
   - Cuota 1: $33.33 - Vencimiento: 15/Mar/2026
   - Cuota 2: $33.33 - Vencimiento: 15/Abr/2026
   - Cuota 3: $33.34 - Vencimiento: 15/May/2026
5. María paga cuota inicial ($50):
   - Tesorería registra pago
   
   **INTEGRACIÓN CON FINANZAS (primer pago):**
   6. Sistema crea ingreso en Finanzas:
      * transaction_type: 'income'
      * amount: $50
      * description: "Pago inicial (1/4) - María López - Curso Bíblico"
      * category_id: "Inscripciones y Matrículas"
      * related_module: 'students'
   7. Actualiza cuota 0: status = 'paid'
   8. Actualiza inscripción: total_paid = $50, balance = $100
   9. Email a María: "Pago recibido. Próxima cuota: $33.33 el 15/Mar"

10. Cada vez que María paga una cuota:
    → Sistema repite pasos 6-9 con el monto correspondiente
    → Al pagar la última cuota: payment_status = 'completed'
    → Email: "¡Felicitaciones! Pagos completados. Certificado de paz y salvo disponible"
```

**Caso 3: Estudiante con beca externa 100%**
```
Estudiante: Carlos Ruiz
Curso: Liderazgo Cristiano ($400)
Beca: Externa 100% - Iglesia "La Roca"

Flujo:
1. Carlos se inscribe indicando beca externa
2. Adjunta carta de la iglesia confirmando pago del 100%
3. Sistema crea inscripción:
   - scholarship_type: 'external'
   - scholarship_percentage: 100
   - scholarship_entity: 'Iglesia La Roca'
   - scholarship_amount: $400
   - amount_to_pay: $0
   - payment_status: 'scholarshiped_100'
4. **NO se generan cuotas** (amount_to_pay = 0)
5. **NO se crean transacciones en Finanzas** (Carlos no paga nada al instituto)
6. Estado de Carlos: "Becado 100% - No requiere pagos"
7. Dashboard de paz y salvo: ✅ "Al día (becado 100%)"

Nota: Si el instituto requiere trackear becas externas como "ingresos no monetarios",
se puede configurar para crear transacción con status especial 'scholarship_external'.
```

**Caso 4: Estudiante con beca parcial externa + pago en cuotas**
```
Estudiante: Ana Martínez
Curso: Curso Bíblico ($300)
Beca: Externa 40% - Fundación "Esperanza" ($120)
Esquema: Estudiante paga $180 en 6 cuotas de $30

Flujo:
1. Ana se inscribe con beca externa 40%
2. Adjunta documento de la fundación
3. Sistema calcula:
   - course_cost: $300
   - scholarship_amount: $120 (40%)
   - amount_to_pay: $180 (lo que paga Ana)
4. Genera 6 cuotas de $30 mensuales
5. Ana paga primera cuota ($30):
   
   **INTEGRACIÓN CON FINANZAS:**
   6. Sistema crea ingreso:
      * amount: $30
      * description: "Cuota 1/6 - Ana Martínez - Curso Bíblico (Beca externa 40%)"
   7. Actualiza: total_paid = $30, balance = $150
   8. Email: "Pago recibido. Próxima cuota: $30 el 15/Mar"

9. Cada mes Ana paga $30 → Se repite integración con Finanzas
10. Al completar 6 pagos:
    - total_paid = $180
    - balance = $0
    - payment_status = 'completed'
    - Certificado de paz y salvo disponible
```

**Caso 5: Inscripción manual desde administración**
```
Problema: Juan Pérez no tiene internet para inscribirse online

Flujo:
1. Secretaria con capability 'aura_students_create' accede a:
   "Estudiantes > Agregar Nuevo"
2. Llena formulario manual:
   - Datos personales de Juan
   - Checkbox: "Crear usuario WordPress" (genera credenciales)
3. Sistema crea estudiante en wp_aura_students:
   - registration_method: 'manual'
   - created_by: ID de la secretaria
4. Secretaria crea inscripción:
   - Selecciona curso
   - Configura beca (si aplica)
   - Define esquema de pago
5. Sistema genera calendario de cuotas
6. Secretaria imprime cronograma y entrega a Juan
7. Sistema envía email con credenciales de acceso al portal
8. Juan puede consultar su estado de cuenta desde el portal
```

**Caso 6: Alertas de morosidad y recordatorios**
```
Estudiante: Laura González
Cuota vencida: $50 el 15/Feb/2026
Hoy: 20/Feb/2026 (5 días de atraso)

Alertas automáticas:
1. 5 días antes (10/Feb): Email recordatorio
2. 1 día antes (14/Feb): Email recordatorio urgente
3. Día del vencimiento (15/Feb): Si no pagó, cuota pasa a 'overdue'
4. Al día siguiente (16/Feb): Email "Pago vencido"
5. Cada 3 días: Recordatorio hasta 30 días
6. A los 30 días: Alerta a administración
7. Dashboard muestra: 🔴 "Laura González - Mora 5 días - $50"

Cuando Laura paga:
1. Tesorería registra pago de $50
2. Sistema crea ingreso en Finanzas
3. Cuota cambia de 'overdue' a 'paid'
4. Si no hay más cuotas vencidas: payment_status = 'current'
5. Email: "Pago recibido. Gracias por ponerte al día"
```

**API REST - Endpoints de integración**:

```php
// 1. Crear transacción de ingreso desde pago de estudiante
POST /wp-json/aura/v1/finance/transactions/from-student-payment
Body: {
  "student_id": 45,
  "enrollment_id": 123,
  "payment_id": 789,
  "amount": 50.00,
  "payment_method": "Transferencia",
  "reference_number": "TRANS-12345",
  "receipt_file": "url/to/receipt.pdf",
  "payment_concept": "Cuota 2/6",
  "category_slug": "inscripciones-matriculas"
}
Response: {
  "transaction_id": 456,
  "message": "Ingreso creado automáticamente desde pago de estudiante",
  "student_name": "María López",
  "course_name": "Curso Bíblico",
  "balance": 100.00
}

// 2. Obtener estado de pagos de un estudiante
GET /wp-json/aura/v1/students/{id}/payment-status
Response: {
  "student_id": 45,
  "student_name": "María López",
  "enrollments": [
    {
      "enrollment_id": 123,
      "course_name": "Curso Bíblico",
      "course_cost": 300.00,
      "scholarship_type": "internal",
      "scholarship_percentage": 50,
      "amount_to_pay": 150.00,
      "total_paid": 50.00,
      "balance": 100.00,
      "payment_status": "current",
      "overdue_quotas": 0,
      "next_quota": {
        "quota_number": 2,
        "amount": 33.33,
        "due_date": "2026-03-15",
        "days_until_due": 23
      },
      "paz_y_salvo": false
    }
  ],
  "total_balance": 100.00,
  "overall_status": "current"
}

// 3. Obtener reporte de ingresos por inscripciones
GET /wp-json/aura/v1/finance/reports/enrollment-income
Query params: ?start_date=2026-01-01&end_date=2026-12-31&course_id=5
Response: {
  "period": "2026-01-01 to 2026-12-31",
  "course": "Curso Bíblico",
  "total_enrolled": 25,
  "total_projected_income": 7500.00,
  "total_scholarships": 2000.00,
  "net_projected_income": 5500.00,
  "total_collected": 3200.00,
  "collection_rate": 58.18,
  "pending_collection": 2300.00,
  "overdue_amount": 450.00,
  "students_overdue": 3,
  "by_payment_method": [
    {"method": "Transferencia", "amount": 2000.00},
    {"method": "Efectivo", "amount": 1000.00},
    {"method": "Tarjeta", "amount": 200.00}
  ],
  "monthly_breakdown": [
    {"month": "2026-01", "collected": 800.00, "projected": 1200.00},
    {"month": "2026-02", "collected": 950.00, "projected": 1200.00},
    // ...
  ]
}

// 4. Dashboard de paz y salvo
GET /wp-json/aura/v1/students/paz-y-salvo-dashboard
Query params: ?course_id=5&payment_status=overdue
Response: {
  "total_students": 25,
  "students_current": 18,
  "students_overdue": 5,
  "students_completed": 2,
  "total_pending": 3500.00,
  "total_overdue": 650.00,
  "students": [
    {
      "student_id": 12,
      "name": "Laura González",
      "course": "Curso Bíblico",
      "total_to_pay": 150.00,
      "total_paid": 100.00,
      "balance": 50.00,
      "payment_status": "overdue",
      "days_overdue": 5,
      "overdue_amount": 50.00,
      "next_quota_date": "2026-02-15",
      "status_badge": "🔴 Moroso"
    },
    // ...
  ]
}
```

**Triggers y Hooks de WordPress**:

```php
// Hook que se ejecuta al guardar un pago de estudiante
add_action('aura_student_payment_saved', 'aura_create_finance_transaction_from_payment', 10, 2);

function aura_create_finance_transaction_from_payment($payment_id, $payment_data) {
    global $wpdb;
    
    // Solo crear transacción si está configurado
    if (!$payment_data['create_finance_transaction']) {
        return;
    }
    
    // Obtener categoría de Inscripciones
    $category = $wpdb->get_row(
        "SELECT id FROM wp_aura_finance_categories 
         WHERE slug = 'inscripciones-matriculas' LIMIT 1"
    );
    
    // Crear transacción de ingreso
    $transaction_id = $wpdb->insert('wp_aura_finance_transactions', [
        'transaction_type' => 'income',
        'category_id' => $category->id,
        'amount' => $payment_data['amount_paid'],
        'transaction_date' => $payment_data['payment_date'],
        'description' => $payment_data['payment_concept'] . ' - ' . $payment_data['student_name'],
        'payment_method' => $payment_data['payment_method'],
        'reference_number' => $payment_data['reference_number'],
        'receipt_file' => $payment_data['receipt_file'],
        'related_module' => 'students',
        'related_item_id' => $payment_data['student_id'],
        'related_action' => 'enrollment_payment',
        'status' => 'approved',
        'created_by' => get_current_user_id()
    ]);
    
    // Guardar relación en pago
    $wpdb->update('wp_aura_student_payments',
        ['finance_transaction_id' => $transaction_id],
        ['id' => $payment_id]
    );
    
    // Enviar notificación
    do_action('aura_finance_transaction_created', $transaction_id, 'student_payment');
}
```

**Reportes Integrados en Dashboard de Finanzas**:

1. **Widget: "Ingresos por Inscripciones (Mes Actual)"**
   - Total recaudado del mes
   - Comparativa vs mes anterior
   - Gráfico de tendencia

2. **Reporte: "Estado de Cobros de Inscripciones"**
   - Total proyectado vs cobrado
   - Tasa de cobro (%)
   - Monto en mora
   - Estudiantes con pagos vencidos

3. **Reporte: "Impacto de Becas en Ingresos"**
   - Total becas otorgadas (monto)
   - Becas internas vs externas
   - Porcentaje de estudiantes becados
   - Ingresos reales vs ingresos potenciales sin becas

4. **Dashboard Ejecutivo: "Salud Financiera del Módulo de Estudiantes"**
   - Ingresos totales por inscripciones (histórico)
   - Proyección de ingresos (basado en cuotas pendientes)
   - Riesgo de incobrables (estudiantes >30 días mora)
   - ROI por curso (ingresos vs costos operativos)

---

#### 🔗 Integración con Módulo de BIBLIOTECA

**Flujo de integración**:
```
Compra de Libros → Egreso en Finanzas + Registro en Biblioteca
Multa por Retraso → Ingreso en Finanzas + Nota en préstamo
```

**Casos de uso**:
1. **Adquisición de libros**:
   - Usuario registra egreso en Finanzas (categoría: "Biblioteca → Adquisición de Libros")
   - Sistema puede crear lote de libros en módulo Biblioteca
   - Datos compartidos: Monto total, fecha de compra, proveedor

2. **Multa por retraso en devolución**:
   - Módulo Biblioteca calcula multa automáticamente
   - Crea ingreso en Finanzas (categoría: "Otros Ingresos → Multas de Biblioteca")
   - Enlace: Usuario puede ver detalle del préstamo desde la transacción

**Reporte integrado**: "Inversión en Biblioteca vs Ingresos por Multas"

---

#### 🔗 Integración con Módulo de VEHÍCULOS

**Flujo de integración**:
```
Mantenimiento de Vehículo → Registro en Vehículos + Egreso en Finanzas
Alquiler de Vehículo → Registro en Vehículos + Ingreso en Finanzas
Compra de Combustible → Registro en Vehículos + Egreso en Finanzas
```

**Casos de uso**:
1. **Mantenimiento vehicular**:
   - Usuario registra mantenimiento en módulo Vehículos
   - Sistema crea automáticamente egreso en Finanzas (categoría: "Mantenimiento → Vehículos")
   - Enlace bidireccional con datos de kilometraje y tipo de servicio

2. **Alquiler de vehículo a iglesias**:
   - Usuario registra salida de vehículo como "Alquiler" en módulo Vehículos
   - Puede generar automáticamente ingreso en Finanzas (categoría: "Alquileres y Rentas")
   - Tracking: Cuánto ha generado cada vehículo en alquileres

**Dashboard integrado**: 
- KPI: "Costo de mantenimiento por vehículo"
- KPI: "Ingresos por alquiler de vehículos"
- Gráfico: "Ratio costo/beneficio por vehículo"

---

#### 📊 Ventajas de la Integración

1. **Trazabilidad completa**: Cada gasto tiene contexto (qué herramienta, qué libro, qué vehículo)
2. **Automatización**: Reducción de entrada duplicada de datos
3. **Reportes poderosos**: Análisis financiero por tipo de recurso
4. **Control presupuestario**: Alerta si gastos en herramientas exceden presupuesto
5. **Auditoría mejorada**: Enlaces bidireccionales facilitan revisión

---

## FASE 1: Configuración y Categorías

### 📌 Objetivo de la Fase
Implementar el sistema completo de gestión de categorías financieras con interfaz CRUD, validaciones y jerarquías opcionales.

---

### Item 1.1: Crear Custom Post Type para Categorías

**Prompt de Desarrollo:**
```
Crea un Custom Post Type llamado 'aura_fin_category' para gestionar las categorías 
financieras en WordPress. Debe incluir:

1. Register el CPT con estos parámetros:
   - public: false
   - show_ui: true
   - show_in_menu: 'aura-business-suite'
   - supports: ['title', 'custom-fields']
   - capability_type: 'aura_finance_category'
   
2. Agregar meta boxes personalizados para:
   - Tipo de categoría (Ingreso/Egreso/Ambos)
   - Categoría padre (dropdown para jerarquía)
   - Color (color picker)
   - Icono (dashicons selector)
   - Estado activo/inactivo
   - Orden de visualización
   
3. Implementar validaciones:
   - Nombre único de categoría (slug único)
   - No permitir eliminar categorías con transacciones asociadas
   - No permitir ciclos en jerarquía padre-hijo
   
4. Archivo: modules/financial/class-financial-categories-cpt.php

5. Hook de activación: crear tabla wp_aura_finance_categories
```

**Checklist de Implementación:**
- [ ] CPT registrado correctamente
- [ ] Meta boxes funcionales
- [ ] Validaciones activas
- [ ] Tabla creada en activación
- [ ] Documentación en código

---

### Item 1.2: Interfaz de Gestión de Categorías

**Prompt de Desarrollo:**
```
Desarrolla una interfaz administrativa para gestionar categorías financieras con las 
siguientes características:

1. Tabla de categorías (WP_List_Table extendida):
   - Columnas: Nombre, Tipo, Categoría Padre, Color (badge), Estado, Acciones
   - Filtros: Por tipo (Ingreso/Egreso), por estado (Activo/Inactivo)
   - Búsqueda en tiempo real (AJAX)
   - Ordenamiento por columnas
   - Acciones en fila: Editar, Desactivar/Activar, Eliminar
   - Acción masiva: Desactivar seleccionadas
   
2. Modal para crear/editar categoría:
   - Campos:
     * Nombre de categoría (requerido)
     * Tipo: Radio buttons (Ingreso/Egreso/Ambos)
     * Categoría padre: Select dropdown (opcional)
     * Color: Color picker (#hex)
     * Icono: Selector de Dashicons
     * Descripción: Textarea
     * Estado: Checkbox (Activo)
   
   - Validaciones frontend (JavaScript):
     * Nombre no vacío
     * Color en formato hex válido
     
   - Validación backend (PHP):
     * Sanitize todos los campos
     * Verificar slug único
     * Verificar que categoría padre no cree ciclo
     
3. Mensajes de confirmación:
   - Antes de eliminar: "¿Eliminar categoría X? Esta acción no se puede deshacer."
   - Si tiene transacciones: "No se puede eliminar. Tiene N transacciones asociadas. 
     ¿Desactivarla en su lugar?"
   
4. AJAX handlers:
   - wp_ajax_aura_create_category
   - wp_ajax_aura_update_category
   - wp_ajax_aura_delete_category
   - wp_ajax_aura_toggle_category_status
   - wp_ajax_aura_get_categories
   
5. Archivos:
   - templates/financial/categories-page.php
   - assets/js/financial-categories.js
   - assets/css/financial-categories.css
   - modules/financial/class-financial-categories.php

6. Permisos requeridos: aura_finance_category_manage
```

**Checklist de Implementación:**
- [ ] Tabla WP_List_Table implementada
- [ ] Modal funcional con todos los campos
- [ ] Validaciones frontend y backend
- [ ] AJAX handlers funcionando
- [ ] Mensajes de confirmación
- [ ] Verificación de permisos
- [ ] Responsive design
- [ ] Testing con datos reales

---

### Item 1.3: API REST para Categorías

**Prompt de Desarrollo:**
```
Implementa endpoints REST API para consumir categorías desde frontend (VueJS/React) 
o aplicaciones externas:

1. Endpoints a crear:
   
   GET /wp-json/aura/v1/finance/categories
   - Parámetros: type (income/expense), status (active/inactive), parent_id
   - Respuesta: Lista de categorías con estructura jerárquica
   - Permisos: aura_finance_view_own o aura_finance_view_all
   
   POST /wp-json/aura/v1/finance/categories
   - Body: {name, type, parent_id, color, icon, description}
   - Respuesta: Categoría creada con ID
   - Permisos: aura_finance_category_manage
   
   PUT /wp-json/aura/v1/finance/categories/{id}
   - Body: Campos a actualizar
   - Respuesta: Categoría actualizada
   - Permisos: aura_finance_category_manage
   
   DELETE /wp-json/aura/v1/finance/categories/{id}
   - Parámetros: force (true para hard delete)
   - Respuesta: Éxito o error con razón
   - Permisos: aura_finance_category_manage
   
   GET /wp-json/aura/v1/finance/categories/tree
   - Respuesta: Árbol jerárquico completo de categorías
   - Útil para dropdowns con subcategorías

2. Validaciones en cada endpoint:
   - Verificar permisos
   - Sanitizar entradas
   - Validar tipos de datos
   - Manejar errores con códigos HTTP apropiados
   
3. Documentación Swagger/OpenAPI (comentarios PHPDoc)

4. Archivo: modules/financial/class-financial-categories-api.php
```

**Checklist de Implementación:**
- [ ] 5 endpoints implementados
- [ ] Autenticación y permisos verificados
- [ ] Validaciones y sanitización
- [ ] Respuestas JSON consistentes
- [ ] Manejo de errores HTTP
- [ ] Documentación PHPDoc
- [ ] Testing con Postman/Insomnia

---

### Item 1.4: Categorías Predeterminadas

**Prompt de Desarrollo:**
```
Crea un sistema de categorías predeterminadas que se instalen automáticamente al 
activar el módulo, facilitando el inicio rápido:

1. Categorías de INGRESOS predeterminadas:
   - Donaciones (#27ae60, dashicons-heart)
     └─ Donación General (subcategoría)
     └─ Donación Especial (subcategoría)
     └─ Donación de Misiones (subcategoría)
     └─ Donación de Construcción (subcategoría)
     └─ Donación de Emergencia (subcategoría)
     └─ Donación de Alimentos (subcategoría)
     └─ Donación de Voluntarios (subcategoría)
   - Ofrendas (#2ecc71, dashicons-groups)
     └─ Ofrenda General (subcategoría)
     └─ Ofrenda Especial (subcategoría)
     └─ Ofrenda de Misiones (subcategoría)
     └─ Ofrenda de Construcción (subcategoría)
     └─ Ofrenda de Emergencia (subcategoría)
   - **Alquileres y Rentas** (#3498db, dashicons-admin-home)
     └─ Alquiler de Instalaciones (subcategoría)
     └─ Alquiler a Iglesias (subcategoría)
     └─ Alquiler de Equipo de Sonido (subcategoría)
     └─ Alquiler de Kiosco/Terraza (subcategoría)
   - **Inscripciones y Matrículas** (#2980b9, dashicons-welcome-learn-more)
     └─ Inscripción de Estudiantes (subcategoría)
     └─ Cursos y Talleres (subcategoría)
   - Ventas de Productos (#1e8a98, dashicons-cart)
   - Ventas de Servicios (#16a085, dashicons-admin-tools)
   - Subvenciones (#8e44ad, dashicons-money-alt)
   - Intereses Bancarios (#9b59b6, dashicons-chart-line)
   - Otros Ingresos (#95a5a6, dashicons-plus-alt)

2. Categorías de EGRESOS predeterminadas:
   - Salarios y Sueldos (#e74c3c, dashicons-groups)
     └─ Salario (subcategoría)
     └─ Honorarios (subcategoría)
     └─ Voluntarios (subcategoría)
   - Servicios Públicos (#e67e22, dashicons-lightbulb)
     └─ Electricidad (subcategoría)
     └─ Internet (subcategoría)
     └─ Gas (subcategoría)
     └─ Agua (subcategoría)
     └─ Teléfono (subcategoría)
   - **Mantenimiento** (#d35400, dashicons-admin-tools)
     └─ Vehículos (subcategoría) *[Integra con módulo Vehículos]*
     └─ Instalaciones (subcategoría)
     └─ Herramientas Eléctricas (subcategoría) *[Integra con módulo Inventario]*
     └─ Herramientas de Motor (subcategoría) *[Integra con módulo Inventario]*
     └─ Equipo de Sonido (subcategoría) *[Integra con módulo Inventario]*
     └─ Sistema de Riego (subcategoría) *[Integra con módulo Inventario]*
     └─ Jardinería (subcategoría)
   - **Compra de Herramientas y Equipos** (#f39c12, dashicons-admin-tools)
     └─ Herramientas Eléctricas (subcategoría) *[Integra con módulo Inventario]*
     └─ Herramientas de Batería (subcategoría) *[Integra con módulo Inventario]*
     └─ Herramientas de Motor (subcategoría) *[Integra con módulo Inventario]*
     └─ Equipo de Sonido (subcategoría) *[Integra con módulo Inventario]*
     └─ Mobiliario (subcategoría) *[Integra con módulo Inventario]*
   - **Biblioteca** (#9b59b6, dashicons-book)
     └─ Adquisición de Libros (subcategoría) *[Integra con módulo Biblioteca]*
     └─ Materiales Bibliográficos (subcategoría) *[Integra con módulo Biblioteca]*
   - Suministros de Oficina (#16a085, dashicons-portfolio)
   - Suministros de Limpieza (#1abc9c, dashicons-admin-home)
   - Programas y Proyectos (#8e44ad, dashicons-welcome-learn-more)
   - Becas y Ayudas (#27ae60, dashicons-heart)
   - Marketing (#34495e, dashicons-megaphone)
   - Tecnología (#2c3e50, dashicons-desktop)
   - Otros Gastos (#7f8c8d, dashicons-minus)

3. Implementación:
   - Función: aura_finance_install_default_categories()
   - Hook: register_activation_hook
   - Verificar que no existan antes de crear
   - Opción para reinstalar categorías
   - Exportar/importar categorías personalizadas (JSON)
   
4. Archivo: modules/financial/class-financial-setup.php
```

**Checklist de Implementación:**
- [ ] 15+ categorías predeterminadas creadas
- [ ] Jerarquía de subcategorías funcional
- [ ] Colores e iconos asignados
- [ ] Verificación de duplicados
- [ ] Opción de reinstalación
- [ ] Sistema de importar/exportar categorías

---

### Item 1.5: Crear Tablas de Transacciones y Complementarias

**Prompt de Desarrollo:**
```
Crea las tablas de base de datos necesarias para el sistema de transacciones 
financieras con campos de integración para otros módulos:

1. Tabla principal: wp_aura_finance_transactions
   
   Campos principales:
   - id: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
   - transaction_type: ENUM('income', 'expense') NOT NULL
   - category_id: BIGINT UNSIGNED NOT NULL (FK a wp_aura_finance_categories)
   - amount: DECIMAL(15, 2) NOT NULL
   - transaction_date: DATE NOT NULL
   - description: TEXT NOT NULL
   - notes: TEXT
   - status: ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
   - payment_method: VARCHAR(50)
   - reference_number: VARCHAR(100)
   - recipient_payer: VARCHAR(255)
   - receipt_file: VARCHAR(255)
   - tags: VARCHAR(500)
   
   Campos de INTEGRACIÓN con otros módulos (CRÍTICOS):
   - related_module: ENUM('inventory', 'library', 'vehicles', 'forms', 'students') NULL
     * Indica qué módulo generó esta transacción automáticamente
   - related_item_id: BIGINT UNSIGNED NULL
     * ID del registro en el módulo relacionado
   - related_action: VARCHAR(50) NULL
     * Tipo de acción: 'purchase', 'maintenance', 'rental', 'loan', 'payment', 'enrollment'
   
   Campos de auditoría:
   - created_by: BIGINT UNSIGNED NOT NULL (FK a wp_users)
   - approved_by: BIGINT UNSIGNED NULL (FK a wp_users)
   - approved_at: DATETIME NULL
   - rejection_reason: TEXT
   - created_at: DATETIME DEFAULT CURRENT_TIMESTAMP
   - updated_at: DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   - deleted_at: DATETIME NULL (soft delete)
   
   Índices:
   - INDEX idx_type (transaction_type)
   - INDEX idx_category (category_id)
   - INDEX idx_status (status)
   - INDEX idx_date (transaction_date)
   - INDEX idx_deleted (deleted_at)
   - INDEX idx_related (related_module, related_item_id) ← CLAVE PARA INTEGRACIONES
   
   Foreign Keys:
   - FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE RESTRICT
   - FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE
   - FOREIGN KEY (approved_by) REFERENCES wp_users(ID) ON DELETE SET NULL

2. Tabla de presupuestos: wp_aura_finance_budgets
   
   - id: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
   - category_id: BIGINT UNSIGNED NOT NULL (FK)
   - budget_amount: DECIMAL(15, 2) NOT NULL
   - period_type: ENUM('monthly', 'quarterly', 'yearly') DEFAULT 'monthly'
   - start_date: DATE NOT NULL
   - end_date: DATE NOT NULL
   - alert_threshold: INT DEFAULT 80 (porcentaje de alerta)
   - is_active: BOOLEAN DEFAULT 1
   - created_by: BIGINT UNSIGNED NOT NULL
   - created_at: DATETIME DEFAULT CURRENT_TIMESTAMP
   
   Foreign Keys:
   - FOREIGN KEY (category_id) REFERENCES wp_aura_finance_categories(id) ON DELETE CASCADE
   - FOREIGN KEY (created_by) REFERENCES wp_users(ID) ON DELETE CASCADE

3. Tabla de historial de cambios: wp_aura_finance_transaction_history
   
   Para auditoría de ediciones (requerido para Item 2.4):
   - id: BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
   - transaction_id: BIGINT UNSIGNED NOT NULL (FK)
   - field_changed: VARCHAR(100) NOT NULL
   - old_value: TEXT
   - new_value: TEXT
   - changed_by: BIGINT UNSIGNED NOT NULL (FK a wp_users)
   - change_reason: TEXT
   - changed_at: DATETIME DEFAULT CURRENT_TIMESTAMP
   
   Índices:
   - INDEX idx_transaction (transaction_id)
   - INDEX idx_changed_at (changed_at)
   
   Foreign Keys:
   - FOREIGN KEY (transaction_id) REFERENCES wp_aura_finance_transactions(id) ON DELETE CASCADE
   - FOREIGN KEY (changed_by) REFERENCES wp_users(ID) ON DELETE CASCADE

4. Implementación del método estático:
   
   Archivo: modules/financial/class-financial-transactions.php
   
   Agregar método:
   public static function create_transactions_table() {
       global $wpdb;
       $charset_collate = $wpdb->get_charset_collate();
       
       // SQL para las 3 tablas
       // Usar dbDelta() para creación segura
       // Registrar versión de BD
   }

5. Actualizar hook de activación:
   
   Archivo: aura-business-suite.php
   
   En método activate():
   - Llamar a Aura_Financial_Transactions::create_transactions_table()
   - Ubicar después de create_categories_table()

6. Casos de uso de campos de integración:
   
   Ejemplo 1 - Inventario → Finanzas:
   - Usuario registra mantenimiento externo de motoguadaña (costo $150)
   - Sistema crea transacción automáticamente:
     * related_module = 'inventory'
     * related_item_id = 45 (ID de la motoguadaña)
     * related_action = 'maintenance'
     * category_id = ID de "Mantenimiento → Herramientas de Motor"
   
   Ejemplo 2 - Estudiantes → Finanzas:
   - Estudiante paga cuota de $100
   - Sistema crea transacción automáticamente:
     * related_module = 'students'
     * related_item_id = 23 (ID del estudiante)
     * related_action = 'payment'
     * category_id = ID de "Inscripciones → Inscripción de Estudiantes"
   
   Consulta bidireccional:
   SELECT * FROM wp_aura_finance_transactions 
   WHERE related_module = 'inventory' AND related_item_id = 45
   → Devuelve todos los gastos de mantenimiento de ese equipo
```

**Checklist de Implementación:**
- [ ] Tabla wp_aura_finance_transactions creada
- [ ] Campos de integración implementados (related_module, related_item_id, related_action)
- [ ] Tabla wp_aura_finance_budgets creada
- [ ] Tabla wp_aura_finance_transaction_history creada
- [ ] Índices y foreign keys configurados
- [ ] Método create_transactions_table() implementado
- [ ] Hook de activación actualizado
- [ ] Verificación con script SQL
- [ ] Documentación de campos de integración
- [ ] Testing de creación de tablas

**⚠️ IMPORTANCIA CRÍTICA:**
Sin estos campos de integración (`related_module`, `related_item_id`, `related_action`), 
las integraciones documentadas en las líneas 420-1100 del prdFinanzas.md NO FUNCIONARÁN.
Estos campos permiten rastrear qué módulo generó cada transacción y vincular 
bidireccionalmente Finanzas ↔ Inventario, Finanzas ↔ Estudiantes, etc.

---

## FASE 2: Gestión de Transacciones

### 📌 Objetivo de la Fase
Implementar el sistema completo de registro, edición y eliminación de transacciones financieras con flujo de aprobación y adjuntos.

---

### Item 2.1: Formulario de Registro de Transacciones

**Prompt de Desarrollo:**
```
Desarrolla un formulario completo para registrar ingresos y egresos con interfaz 
intuitiva y validaciones robustas:

1. Diseño de formulario (ubicación: página "Nueva Transacción"):
   
   A. Selector de tipo (Toggle switch destacado):
      [💰 INGRESO] ←→ [💸 EGRESO]
      - Verde para ingresos, rojo para egresos
      - Cambia dinámicamente labels y opciones
   
   B. Campos principales:
      * Fecha de transacción: Datepicker (por defecto hoy)
      * Categoría: Select filtrado por tipo seleccionado
      * Monto: Input numérico con formato de moneda ($)
      * Descripción: Textarea (requerido, min 10 caracteres)
      * Método de pago: Select (Efectivo, Transferencia, Cheque, Tarjeta)
      * Número de referencia: Input texto (factura, cheque, etc.)
      * Beneficiario/Pagador: Input texto
      
   C. Campos opcionales (colapsables):
      * Notas adicionales: Textarea
      * Etiquetas: Input con autocompletado
      * Comprobante: Upload de archivo (JPG, PNG, PDF, max 5MB)
      * Proyecto relacionado: Select (si aplica)
      
   D. Previsualización:
      - Card con resumen antes de guardar
      - Muestra: Tipo, Monto, Categoría, Descripción
      
2. Validaciones frontend (JavaScript):
   - Monto mayor a 0
   - Fecha no futura (opcional, según configuración)
   - Categoría seleccionada
   - Descripción mínimo 10 caracteres
   - Formato de archivo válido
   - Tamaño de archivo no excede límite
   
3. Envío AJAX:
   - Action: wp_ajax_aura_save_transaction
   - Mostrar loading spinner
   - Mensaje de éxito con opción "Ver transacción" o "Crear otra"
   - Manejo de errores con mensajes claros
   
4. Funcionalidad de autoguardado (draft):
   - Guardar en localStorage cada 30 segundos
   - Recuperar draft al reabrir formulario
   - Botón "Limpiar draft"
   
5. Archivos:
   - templates/financial/transaction-form.php
   - assets/js/transaction-form.js
   - assets/css/transaction-form.css
   - modules/financial/class-financial-transactions.php (lógica backend)

6. Permisos: aura_finance_create
```

**Checklist de Implementación:**
- [ ] Formulario con todos los campos
- [ ] Toggle ingreso/egreso funcional
- [ ] Datepicker y selects funcionando
- [ ] Upload de archivos implementado
- [ ] Validaciones frontend completas
- [ ] AJAX save funcional
- [ ] Autoguardado en localStorage
- [ ] Previsualización de transacción
- [ ] Responsive design
- [ ] Testing exhaustivo

---

### Item 2.2: Listado de Transacciones con Filtros Avanzados

**Prompt de Desarrollo:**
```
Crea un listado completo de transacciones con filtros potentes y acciones 
contextuales según permisos:

1. Tabla de transacciones (mejorando WP_List_Table):
   
   Columnas:
   - Estado: Badge (Pendiente/Aprobado/Rechazado)
   - Fecha: Formato dd/mm/yyyy
   - Tipo: Icono + color (↑ verde ingreso, ↓ rojo egreso)
   - Categoría: Badge con color de categoría
   - Descripción: Truncada a 50 chars con tooltip
   - Monto: Formato moneda con signo
   - Método de pago: Icono + texto
   - Creado por: Avatar + nombre
   - Acciones: Dropdown contextual
   
2. Filtros avanzados (sidebar colapsable):
   
   A. Filtros básicos:
      - Rango de fechas: Date range picker
      - Tipo: Checkbox (Ingresos, Egresos)
      - Estado: Checkbox (Pendiente, Aprobado, Rechazado)
      - Categoría: Select múltiple jerárquico
      
   B. Filtros avanzados:
      - Rango de monto: Min - Max
      - Método de pago: Select múltiple
      - Creado por: Select de usuarios (solo si tiene aura_finance_view_all)
      - Etiquetas: Input con autocompletado
      - Con/sin comprobante: Radio buttons
      
   C. Filtros guardados:
      - Guardar configuración actual como "Filtro favorito"
      - Aplicar filtros guardados con un click
      - Ejemplos predefinidos: "Este mes", "Pendientes de aprobación", 
        "Mis transacciones", "Gastos mayores a $1000"
   
3. Búsqueda global:
   - Buscar en: Descripción, Notas, Referencia, Beneficiario
   - Resultados en tiempo real (AJAX)
   - Resaltar texto encontrado
   
4. Acciones por transacción (según permisos):
   
   Si tiene aura_finance_view_all o es creador:
   - Ver detalle (modal)
   
   Si tiene aura_finance_edit_own/all:
   - Editar (modal o página)
   
   Si tiene aura_finance_approve y estado=pendiente:
   - Aprobar
   - Rechazar (con motivo)
   
   Si tiene aura_finance_delete_own/all:
   - Eliminar (con confirmación)
   
5. Acciones masivas:
   - Seleccionar múltiples transacciones (checkbox)
   - Acciones disponibles:
     * Exportar seleccionadas (CSV/PDF)
     * Cambiar categoría (si son editables)
     * Aprobar seleccionadas (si aplica)
     * Eliminar seleccionadas (con confirmación)
   
6. Estadísticas en cabecera:
   - Total ingresos (filtrados): $X,XXX.XX
   - Total egresos (filtrados): $X,XXX.XX
   - Balance: $X,XXX.XX (verde/rojo)
   - Cantidad de transacciones: N
   
7. Paginación:
   - 20, 50, 100 por página
   - AJAX load more o scroll infinito (opcional)
   
8. Archivos:
   - templates/financial/transactions-list.php
   - assets/js/transactions-list.js
   - modules/financial/class-financial-transactions-list.php
```

**Checklist de Implementación:**
- [ ] Tabla con todas las columnas
- [ ] Filtros básicos funcionales
- [ ] Filtros avanzados funcionales
- [ ] Búsqueda en tiempo real
- [ ] Acciones contextuales por permisos
- [ ] Acciones masivas
- [ ] Estadísticas en cabecera
- [ ] Paginación funcional
- [ ] Performance optimizado (índices en BD)
- [ ] Responsive con scroll horizontal en móvil

---

### Item 2.3: Modal de Detalle de Transacción

**Prompt de Desarrollo:**
```
Implementa un modal detallado para visualizar toda la información de una transacción 
con opciones de acción rápida:

1. Diseño del modal (fullscreen en móvil, 800px en desktop):
   
   A. Cabecera:
      - Badge de estado (grande, destacado)
      - Monto (grande, color según tipo)
      - Tipo de transacción (icono)
      - Fecha
      - Botón cerrar (X)
      
   B. Sección principal (tabs):
      
      Tab 1: Información General
      - Categoría (badge con color)
      - Descripción completa
      - Método de pago
      - Número de referencia
      - Beneficiario/Pagador
      - Etiquetas (badges)
      - Creado por (avatar + nombre + fecha)
      
      Tab 2: Notas y Observaciones
      - Notas del creador
      - Historial de cambios (si fue editada)
      - Motivo de rechazo (si aplica)
      
      Tab 3: Comprobante
      - Visualizador de imagen/PDF
      - Botones: Descargar, Ampliar, Eliminar
      - Opción de subir si no hay
      
      Tab 4: Auditoría (solo admin/auditor)
      - Creado el: fecha + hora + usuario
      - Última edición: fecha + hora + usuario
      - Aprobado por: usuario + fecha
      - IP de origen
      - Cambios realizados (log)
   
   C. Pie del modal (acciones rápidas):
      
      Botones dinámicos según permisos:
      - [Editar] → Abrir formulario de edición
      - [Aprobar] → Confirmar aprobación
      - [Rechazar] → Modal para ingresar motivo
      - [Eliminar] → Confirmación de eliminación
      - [Duplicar] → Crear nueva basada en esta
      - [Exportar PDF] → Generar comprobante PDF
      
2. Interacciones:
   - Abrir modal con AJAX desde listado
   - Navegación entre tabs sin recargar
   - Actualización en tiempo real si otros usuarios modifican
   - Cerrar con ESC o click fuera
   
3. AJAX handlers:
   - wp_ajax_aura_get_transaction_details
   - wp_ajax_aura_approve_transaction
   - wp_ajax_aura_reject_transaction
   
4. Archivos:
   - templates/financial/transaction-modal.php
   - assets/js/transaction-modal.js
```

**Checklist de Implementación:**
- [ ] Modal con diseño completo
- [ ] 4 tabs implementados
- [ ] Acciones rápidas funcionales
- [ ] Visualizador de comprobantes
- [ ] Log de auditoría
- [ ] AJAX load de datos
- [ ] Actualización en tiempo real
- [ ] Responsive design

---

### Item 2.4: Edición de Transacciones

**Prompt de Desarrollo:**
```
Implementa la funcionalidad de editar transacciones existentes con control de 
permisos y registro de cambios:

1. Lógica de permisos:
   
   A. Puede editar si:
      - Tiene aura_finance_edit_all, O
      - Tiene aura_finance_edit_own Y es el creador Y estado=pendiente
      
   B. Restricciones:
      - No editar transacciones aprobadas (solo admin puede)
      - No editar transacciones de hace más de 30 días (configurable)
      - No cambiar el tipo (ingreso/egreso) una vez creada
      
2. Formulario de edición:
   - Reutilizar formulario de creación
   - Pre-llenar todos los campos con datos actuales
   - Marcar campos modificados con indicador visual
   - Botón "Restaurar valor original" por campo
   - Comparación lado a lado: "Valor actual" vs "Nuevo valor"
   
3. Registro de cambios:
   - Guardar en tabla wp_aura_finance_transaction_history:
     * transaction_id
     * field_changed (columna modificada)
     * old_value
     * new_value
     * changed_by (usuario)
     * changed_at (timestamp)
     * change_reason (opcional)
   
   - Tabla de historial visible en modal de detalle (tab Auditoría)
   
4. Validaciones específicas:
   - No permitir cambio de categoría si afecta presupuestos
   - Verificar que nueva categoría coincida con tipo de transacción
   - Si cambia monto significativamente (>20%), requerir motivo
   
5. Notificaciones:
   - Si transacción estaba aprobada y se edita, volver a "pendiente"
   - Notificar al aprobador que debe revisar nuevamente
   - Email opcional al creador si alguien más edita su transacción
   
6. Implementación:
   - Action: wp_ajax_aura_update_transaction
   - Hook: do_action('aura_finance_transaction_updated', $old, $new, $user_id)
   - Archivo: modules/financial/class-financial-transactions-update.php
```

**Checklist de Implementación:**
- [ ] Control de permisos implementado
- [ ] Formulario de edición funcional
- [ ] Registro de cambios en BD
- [ ] Validaciones específicas
- [ ] Notificaciones configuradas
- [ ] Tab de auditoría mostrando historial
- [ ] Testing de casos edge

---

### Item 2.5: Eliminación de Transacciones (Soft Delete)

**Prompt de Desarrollo:**
```
Implementa un sistema de eliminación con soft delete, recuperación y eliminación 
permanente:

1. Tipos de eliminación:
   
   A. Soft Delete (por defecto):
      - Marcar campo deleted_at con timestamp
      - Transacción no aparece en listados normales
      - Recuperable desde "Papelera"
      - Permisos: aura_finance_delete_own o aura_finance_delete_all
      
   B. Hard Delete (permanente):
      - Eliminar completamente de BD
      - Solo administradores
      - Requiere doble confirmación
      - No recuperable
   
2. Flujo de eliminación:
   
   Paso 1: Usuario hace click en "Eliminar"
   - Modal de confirmación:
     "¿Eliminar transacción de $X,XXX por [Descripción]?
      Esta acción enviará la transacción a la papelera.
      [Cancelar] [Enviar a Papelera]"
   
   Paso 2: Transacción marcada como eliminada
   - Actualizar deleted_at = NOW()
   - Mensaje: "Transacción enviada a papelera. [Deshacer]"
   - Botón deshacer disponible 10 segundos
   
3. Página de Papelera:
   - Similar a listado de transacciones
   - Filtros activos
   - Columna adicional: "Eliminado el"
   - Acciones: [Restaurar] [Eliminar permanentemente]
   - Acción masiva: Vaciar papelera (después de N días)
   
4. Restauración:
   - Limpiar campo deleted_at
   - Transacción vuelve a listado principal
   - Mensaje: "Transacción restaurada exitosamente"
   
5. Auto-limpieza:
   - Cron job diario: aura_finance_empty_trash
   - Eliminar permanentemente transacciones en papelera > 30 días
   - Configurable en ajustes
   
6. Validaciones:
   - No permitir eliminar transacciones referenciadas (ej. en reportes fiscales)
   - Advertir si es una transacción de alto monto (configurable)
   - Log de eliminaciones en tabla de auditoría
   
7. Implementación:
   - wp_ajax_aura_delete_transaction
   - wp_ajax_aura_restore_transaction
   - wp_ajax_aura_permanent_delete_transaction
   - Función: aura_finance_empty_trash_scheduled()
   - Archivo: modules/financial/class-financial-transactions-delete.php
```

**Checklist de Implementación:**
- [ ] Soft delete implementado
- [ ] Modal de confirmación
- [ ] Botón "Deshacer" funcional
- [ ] Página de papelera completa
- [ ] Restauración funcionando
- [ ] Hard delete con doble confirmación
- [ ] Cron job de auto-limpieza
- [ ] Validaciones de seguridad
- [ ] Log de auditoría

---

### Item 2.6: Sistema de Aprobación y Rechazo

**Prompt de Desarrollo:**
```
Desarrolla un flujo completo de aprobación de transacciones con notificaciones y 
seguimiento:

1. Estados de transacción:
   - pending: Recién creada, esperando aprobación
   - approved: Aprobada por usuario con permisos
   - rejected: Rechazada con motivo especificado
   
2. Flujo de aprobación:
   
   A. Usuario con aura_finance_approve:
      - Ve badge "Pendientes: N" en menú
      - Acceso a página "Aprobaciones Pendientes"
      - Listado filtrado solo con status=pending
      
   B. Aprobar transacción:
      - Botón "Aprobar" en listado o modal
      - Opcional: Agregar nota de aprobación
      - Actualizar:
        * status = 'approved'
        * approved_by = current_user_id
        * approved_at = NOW()
      - Notificación al creador: "Tu transacción fue aprobada"
      
   C. Rechazar transacción:
      - Botón "Rechazar" en listado o modal
      - Modal obligatorio: "Motivo de rechazo" (textarea, min 20 chars)
      - Actualizar:
        * status = 'rejected'
        * approved_by = current_user_id (quien rechaza)
        * rejection_reason = motivo
        * approved_at = NOW()
      - Notificación al creador: "Tu transacción fue rechazada. Motivo: [...]"
      - Opción: Permitir editar y reenviar (vuelve a pending)
   
3. Página "Aprobaciones Pendientes":
   - Contador en tiempo real
   - Filtros: Por monto, por categoría, por usuario
   - Ordenar: Por fecha, por monto, por prioridad
   - Vista rápida con datos clave
   - Aprobar/Rechazar en masa (checkbox)
   
4. Notificaciones:
   
   A. Email (opcional, configurable):
      - Al creador cuando su transacción es aprobada/rechazada
      - Al aprobador cuando hay nuevas pendientes (diario)
      
   B. Notificación in-app:
      - Badge con contador en menú
      - Dropdown con últimas 5 pendientes
      - Link a la transacción específica
      
5. Dashboard widget:
   - "Transacciones Pendientes"
   - Muestra últimas 5
   - Link "Ver todas"
   
6. Permisos y restricciones:
   - Usuario no puede aprobar sus propias transacciones
   - Admin puede configurar aprobación automática bajo cierto monto
   - Multi-nivel: Aprobador 1 (hasta $X) → Aprobador 2 (hasta $Y) → Admin (sin límite)
   
7. Implementación:
   - wp_ajax_aura_approve_transaction
   - wp_ajax_aura_reject_transaction
   - wp_ajax_aura_get_pending_count
   - Hook: do_action('aura_finance_transaction_approved', $transaction_id, $approver_id)
   - Hook: do_action('aura_finance_transaction_rejected', $transaction_id, $approver_id, $reason)
   - Archivo: modules/financial/class-financial-approval.php
```

**Checklist de Implementación:**
- [ ] Estados de transacción funcionando
- [ ] Flujo de aprobación completo
- [ ] Página de pendientes
- [ ] Modal de rechazo con validación
- [ ] Sistema de notificaciones in-app
- [ ] Emails opcionales
- [ ] Dashboard widget
- [ ] Restricción de auto-aprobación
- [ ] Niveles de aprobación (opcional)
- [ ] Testing de workflow completo

---

## FASE 3: Dashboard y Visualizaciones

### 📌 Objetivo de la Fase
Crear un dashboard financiero interactivo con gráficos, KPIs y reportes visuales para análisis rápido.

---

### Item 3.1: Dashboard Principal del Módulo

**Prompt de Desarrollo:**
```
Desarrolla un dashboard financiero completo con widgets interactivos y gráficos en 
tiempo real:

1. Estructura del dashboard (layout en grid responsivo):
   
   Fila 1: KPIs principales (4 cards)
   ┌─────────────┬─────────────┬─────────────┬─────────────┐
   │ Total       │ Total       │ Balance     │ Pendientes  │
   │ Ingresos    │ Egresos     │ Neto        │ Aprobación  │
   │ $XX,XXX     │ $XX,XXX     │ $XX,XXX     │ N trans.    │
   │ ↑ +15%      │ ↓ -8%       │ ● Verde/Rojo│ [Ver]       │
   └─────────────┴─────────────┴─────────────┴─────────────┘
   
   Fila 2: Gráficos principales
   ┌──────────────────────────────┬─────────────────────────┐
   │ Ingresos vs Egresos          │ Gastos por Categoría    │
   │ (Gráfico de líneas mensual)  │ (Gráfico de dona)       │
   │                              │                         │
   │ Chart.js/ApexCharts          │ Top 5 categorías        │
   └──────────────────────────────┴─────────────────────────┘
   
   Fila 3: Información adicional
   ┌──────────────────────────────┬─────────────────────────┐
   │ Últimas Transacciones        │ Alertas y Notificaciones│
   │ (Tabla compacta, 10 items)   │ • Presupuesto excedido  │
   │ [Ver todas →]                │ • N pendientes de aprobar│
   │                              │ • Comprobantes faltantes│
   └──────────────────────────────┴─────────────────────────┘

2. Filtros globales del dashboard:
   - Selector de período:
     * Hoy
     * Esta semana
     * Este mes (default)
     * Este trimestre
     * Este año
     * Personalizado (date range picker)
   
   - Comparar con período anterior (checkbox)
   
   - Filtrar por categorías (multiselect)
   
3. KPIs detallados:
   
   A. Total Ingresos:
      - Monto del período
      - Porcentaje de cambio vs período anterior
      - Icono tendencia (↑↓)
      - Sparkline (mini gráfico)
      - Click para drill-down
      
   B. Total Egresos:
      - Igual que ingresos
      - Desglose: Fijos vs Variables (tooltip)
      
   C. Balance Neto:
      - Ingresos - Egresos
      - Color dinámico (verde si positivo, rojo si negativo)
      - Barra de progreso hacia objetivo (si hay presupuesto)
      
   D. Pendientes de Aprobación:
      - Contador live
      - Monto total pendiente
      - Link directo a página de aprobaciones
   
4. Gráfico "Ingresos vs Egresos":
   - Tipo: Líneas (dual)
   - Eje X: Meses/semanas/días (según período)
   - Eje Y: Monto ($)
   - Línea verde: Ingresos
   - Línea roja: Egresos
   - Área sombreada: Balance
   - Interactivo: Hover muestra tooltip con valores
   - Exportar: PNG, SVG, CSV
   
5. Gráfico "Gastos por Categoría":
   - Tipo: Dona (doughnut)
   - Colores: De cada categoría
   - Porcentajes mostrados
   - Leyenda: Nombre + monto + %
   - Click en segmento: Filtra transacciones de esa categoría
   - Centro: Total de egresos
   
6. Widget "Últimas Transacciones":
   - Tabla compacta: Fecha, Tipo, Categoría, Monto, Estado
   - Solo 10 más recientes
   - Estados con badges
   - Link "Ver todas" a página de listado
   
7. Widget "Alertas":
   - Alertas codificadas por color:
     * Rojo: Crítico (presupuesto excedido >100%)
     * Amarillo: Advertencia (presupuesto >80%)
     * Azul: Informativo
   - Ejemplos:
     * "Presupuesto de 'Suministros' al 95%"
     * "5 transacciones pendientes hace más de 7 días"
     * "10 transacciones sin comprobante"
   
8. Tecnologías:
   - Chart.js o ApexCharts para gráficos
   - AJAX para actualización de datos
   - LocalStorage para guardar preferencias de filtros
   
9. Archivos:
   - templates/financial/dashboard.php
   - assets/js/financial-dashboard.js
   - modules/financial/class-financial-dashboard.php
   - assets/css/financial-dashboard.css
```

**Checklist de Implementación:**
- [ ] Layout en grid responsivo
- [ ] 4 KPIs funcionales con datos reales
- [ ] Gráfico de líneas ingresos vs egresos
- [ ] Gráfico de dona por categorías
- [ ] Widget de últimas transacciones
- [ ] Widget de alertas
- [ ] Filtros globales funcionando
- [ ] Comparación con período anterior
- [ ] AJAX refresh sin recargar página
- [ ] Exportación de gráficos
- [ ] Responsive en móvil/tablet
- [ ] Performance optimizado

---

### Item 3.2: Reportes Financieros Predefinidos

**Prompt de Desarrollo:**
```
Implementa una librería de reportes financieros listos para usar, exportables y 
personalizables:

1. Tipos de reportes predefinidos:
   
   A. Estado de Resultados (P&L):
      - Ingresos por categoría
      - Egresos por categoría
      - Balance neto
      - Período: Seleccionable
      - Comparación año anterior
      
   B. Flujo de Efectivo:
      - Efectivo inicial
      - Entradas de efectivo
      - Salidas de efectivo
      - Efectivo final
      - Desglose por método de pago
      
   C. Análisis por Categoría:
      - Top 10 categorías con más movimiento
      - Distribución porcentual
      - Tendencia mensual por categoría
      - Gráfico de barras comparativo
      
   D. Reporte de Transacciones Pendientes:
      - Listado completo de pendientes
      - Agrupado por usuario creador
      - Antigüedad de cada transacción
      - Monto total pendiente
      
   E. Reporte de Presupuesto:
      - Presupuesto asignado vs ejecutado
      - Porcentaje de ejecución
      - Proyección al final del período
      - Alertas de sobregiro
      
   F. Reporte de Auditoría:
      - Todas las transacciones del período
      - Con detalles de auditoría
      - Creador, aprobador, fechas
      - Cambios realizados
      
2. Interfaz de generación de reportes:
   
   Página: "Reportes Financieros"
   
   ┌─────────────────────────────────────────────┐
   │ Seleccionar tipo de reporte:               │
   │ [Dropdown con los 6 tipos]                  │
   │                                             │
   │ Configurar parámetros:                      │
   │ • Período: [Date range picker]             │
   │ • Categorías: [Multiselect]                │
   │ • Estados: [Pendiente, Aprobado, Rechazado]│
   │ • Creado por: [Select usuarios]            │
   │                                             │
   │ Formato de salida:                          │
   │ ○ Ver en pantalla                          │
   │ ○ Exportar PDF                             │
   │ ○ Exportar Excel                           │
   │ ○ Exportar CSV                             │
   │                                             │
   │ [Generar Reporte]                           │
   └─────────────────────────────────────────────┘
   
3. Vista previa en pantalla:
   - Tabla responsive con datos
   - Gráficos relevantes según tipo
   - Totales y subtotales destacados
   - Paginación si es extenso
   - Botones: [Imprimir] [Exportar] [Guardar configuración]
   
4. Exportación PDF:
   - Librería: TCPDF o DomPDF
   - Header: Logo, nombre empresa, fecha
   - Contenido: Tabla formateada + gráficos
   - Footer: Página X de Y, usuario generador
   - Marca de agua: "Generado el [fecha]"
   
5. Exportación Excel:
   - Librería: PhpSpreadsheet
   - Hoja 1: Datos tabulados
   - Hoja 2: Gráficos (si aplica)
   - Formato: Números con formato moneda, fechas dd/mm/yyyy
   - Totales en negrita
   
6. Reportes programados (opcional):
   - Configurar envío automático por email
   - Periodicidad: Semanal, quincenal, mensual
   - Destinatarios: Múltiples emails
   - Formato: PDF adjunto
   - Cron job: aura_finance_scheduled_reports
   
7. Guardar configuraciones:
   - Usuario puede guardar configuración de reporte favorito
   - Nombre descriptivo: "Reporte mensual de gastos operativos"
   - Aplicar con un click
   - Compartir con otros usuarios (opcional)
   
8. Implementación:
   - modules/financial/class-financial-reports.php
   - templates/financial/reports-page.php
   - assets/js/financial-reports.js
   - wp_ajax_aura_generate_report
   - wp_ajax_aura_export_report_pdf
   - wp_ajax_aura_export_report_excel
```

**Checklist de Implementación:**
- [ ] 6 tipos de reportes implementados
- [ ] Interfaz de selección y configuración
- [ ] Vista previa en pantalla
- [ ] Exportación PDF funcional
- [ ] Exportación Excel funcional
- [ ] Exportación CSV funcional
- [ ] Reportes programados (cron)
- [ ] Guardar configuraciones
- [ ] Permisos por tipo de reporte
- [ ] Testing de cada tipo
- [ ] Documentación de uso

---

### Item 3.3: Gráficos Interactivos Avanzados

**Prompt de Desarrollo:**
```
Crea una página de análisis visual con gráficos interactivos y comparaciones 
avanzadas:

1. Página "Análisis Visual" con tabs:
   
   Tab 1: Tendencias Temporales
   - Gráfico de líneas: Ingresos, Egresos, Balance (3 líneas)
   - Selector de granularidad: Día/Semana/Mes/Trimestre/Año
   - Zoom interactivo (arrastrar para ampliar)
   - Marcadores de eventos (ej. "Mayor ingreso del año")
   - Proyección futura (línea punteada basada en tendencia)
   
   Tab 2: Distribución por Categorías
   - Gráfico de barras horizontales: Top 10 categorías
   - Toggle: Ingresos / Egresos / Ambos
   - Ordenar por: Monto / Frecuencia / Alfabético
   - Subcategorías expandibles
   - Drill-down: Click abre listado de transacciones
   
   Tab 3: Comparaciones
   - Selector de períodos: Período A vs Período B
   - Gráfico de barras comparativas lado a lado
   - Tabla de diferencias absolutas y porcentuales
   - Destacar categorías con mayor variación
   
   Tab 4: Análisis de Patrones
   - Heatmap: Día de la semana vs Hora (cuándo se registran más)
   - Gráfico de dispersión: Correlación monto vs frecuencia por categoría
   - Identificación de outliers (transacciones atípicas)
   
   Tab 5: Presupuesto vs Realidad
   - Gráfico de barras con dos colores:
     * Verde: Dentro de presupuesto
     * Rojo: Sobre presupuesto
   - Porcentaje de ejecución por categoría
   - Proyección: "A este ritmo, terminará el mes en X%"
   
2. Controles interactivos:
   - Filtros aplicados a todos los tabs
   - Sincronización de períodos entre tabs
   - Botón "Resetear vista"
   - Modo pantalla completa por gráfico
   - Comparar hasta 3 categorías simultáneamente
   
3. Tooltips enriquecidos:
   - Al hacer hover en punto/barra:
     * Fecha exacta
     * Monto
     * Cantidad de transacciones
     * Categoría principal
     * Link "Ver detalles"
   
4. Anotaciones manuales:
   - Usuario puede agregar anotaciones en gráficos
   - Ej. "Aumento por campaña de donaciones"
   - Guardar en BD, mostrar como marcadores persistentes
   
5. Librería de gráficos:
   - ApexCharts (recomendado por interactividad)
   - Fallback: Chart.js
   - Consistencia visual con paleta de colores del proyecto
   
6. Archivos:
   - templates/financial/visual-analytics.php
   - assets/js/financial-charts-advanced.js
   - modules/financial/class-financial-analytics.php
```

**Checklist de Implementación:**
- [ ] 5 tabs de análisis implementados
- [ ] Gráficos interactivos funcionando
- [ ] Filtros sincronizados entre tabs
- [ ] Tooltips enriquecidos
- [ ] Sistema de anotaciones
- [ ] Zoom y drill-down
- [ ] Proyecciones futuras
- [ ] Performance con grandes volúmenes de datos
- [ ] Responsive design
- [ ] Testing de interactividad

---

## FASE 4: Reportes y Exportación

### 📌 Objetivo de la Fase
Implementar capacidades avanzadas de exportación, integración con otras herramientas y reportes personalizables.

---

### Item 4.1: Sistema de Exportación Multi-formato

**Prompt de Desarrollo:**
```
Desarrolla un sistema robusto de exportación que soporte múltiples formatos y use 
cases:

1. Formatos soportados:
   
   A. CSV (valores separados por coma):
      - Columnas: ID, Fecha, Tipo, Categoría, Monto, Descripción, Estado, Creador
      - Encoding: UTF-8 con BOM (compatibilidad Excel)
      - Delimitador configurable: coma, punto y coma, tab
      - Uso: Importar a Excel, Google Sheets, sistemas contables
      
   B. Excel (.xlsx):
      - Librería: PhpSpreadsheet
      - Múltiples hojas: Transacciones, Resumen, Gráficos
      - Formato: Números con formato moneda, fechas formateadas
      - Estilos: Encabezados en negrita, filas alternadas
      - Filtros automáticos en encabezados
      - Totales al final de columna "Monto"
      - Gráfico embebido (opcional)
      
   C. PDF:
      - Librería: TCPDF
      - Orientación: Horizontal (landscape) para tablas anchas
      - Header personalizado: Logo, título, período
      - Footer: Número de página, fecha generación, usuario
      - Tabla responsive: Ajuste automático de columnas
      - Saltos de página inteligentes
      - Marca de agua: "Confidencial" (opcional)
      
   D. JSON:
      - Estructura completa de transacciones
      - Uso: APIs, integraciones, backups
      - Incluir metadata: Total de registros, fecha de exportación
      
   E. XML:
      - Formato: Estándar contable (opcional)
      - Uso: Importar a software contable especializado
   
2. Interfaz de exportación:
   
   Botón "Exportar" en listado de transacciones
   → Abrir modal:
   
   ┌───────────────────────────────────────────┐
   │ Exportar Transacciones                    │
   │                                           │
   │ Formato:                                  │
   │ ○ CSV    ○ Excel    ○ PDF    ○ JSON      │
   │                                           │
   │ Datos a exportar:                         │
   │ ☑ Usar filtros actuales (N transacciones)│
   │ ☐ Todas las transacciones                │
   │ ☐ Selección actual (M seleccionadas)     │
   │                                           │
   │ Columnas a incluir:                       │
   │ ☑ ID ☑ Fecha ☑ Tipo ☑ Categoría         │
   │ ☑ Monto ☑ Descripción ☑ Estado          │
   │ ☐ Notas ☐ Método de pago ☐ Referencia   │
   │ ☐ Creado por ☐ Aprobado por             │
   │                                           │
   │ Opciones adicionales (según formato):     │
   │ • Incluir logo empresa [CSV/Excel/PDF]   │
   │ • Incluir totales [Excel/PDF]            │
   │ • Incluir gráfico resumen [Excel/PDF]    │
   │                                           │
   │ [Cancelar]  [Exportar]                   │
   └───────────────────────────────────────────┘
   
3. Generación de archivo:
   - Mostrar loading con progreso (si >1000 registros)
   - Generar en background si >5000 registros
   - Descargar automáticamente o link de descarga
   - Guardar en /wp-content/uploads/aura-exports/ (temporal)
   - Auto-eliminar archivos después de 24 horas (cron job)
   
4. Exportación programada:
   - Configurar exportación automática periódica
   - Enviar por email
   - Subir a servidor FTP/SFTP (opcional)
   - Sincronizar con Google Drive/Dropbox (integración futura)
   
5. Límites y permisos:
   - Solo exportar transacciones que el usuario tiene permiso de ver
   - Límite de registros por exportación: Configurable (default 10,000)
   - Log de exportaciones: Quién, cuándo, cuántos registros
   
6. Implementación:
   - wp_ajax_aura_export_transactions
   - modules/financial/class-financial-export.php
   - Función: aura_finance_generate_csv(), generate_excel(), generate_pdf()
```

**Checklist de Implementación:**
- [ ] 5 formatos de exportación funcionando
- [ ] Modal de opciones de exportación
- [ ] Exportación de datos filtrados
- [ ] Selección de columnas
- [ ] Opciones específicas por formato
- [ ] Generación en background (grandes volúmenes)
- [ ] Auto-limpieza de archivos temporales
- [ ] Verificación de permisos
- [ ] Log de exportaciones
- [ ] Testing con diferentes volúmenes de datos

---

### Item 4.2: Importación de Transacciones (CSV/Excel)

**Prompt de Desarrollo:**
```
Implementa un sistema de importación masiva de transacciones desde archivos 
externos con validación y previsualización:

1. Página " Importar Transacciones":
   
   Paso 1: Subir archivo
   ┌────────────────────────────────────────┐
   │ Seleccionar archivo:                   │
   │ [Seleccionar archivo .csv o .xlsx]    │
   │                                        │
   │ Formatos aceptados: CSV, Excel (.xlsx)│
   │ Tamaño máximo: 5 MB                   │
   │ Máximo registros: 1,000                │
   │                                        │
   │ [Subir y Validar]                      │
   └────────────────────────────────────────┘
   
   Paso 2: Mapear columnas
   - Mostrar vista previa (primeras 5 filas)
   - Mapear columnas del archivo a campos del sistema:
   
   | Columna Archivo | ← | Campo Sistema     |
   |-----------------|---|-------------------|
   | fecha           | → | transaction_date  |
   | tipo            | → | transaction_type  |
   | categoria       | → | category_id       |
   | monto           | → | amount            |
   | descripcion     | → | description       |
   | [Ignorar]       |   | notes             |
   
   - Auto-detección inteligente de columnas
   - Opción de guardar mapeo como plantilla
   
   Paso 3: Validar datos
   - Ejecutar validaciones:
     * Fechas en formato válido
     * Montos numéricos
     * Categorías existen en sistema (o crear nuevas)
     * Tipos válidos (income/expense)
   
   - Mostrar errores por fila:
     Fila 3: ❌ Categoría "Ventas" no existe. [Crear automáticamente] [Ignorar fila]
     Fila 7: ❌ Monto inválido: "1.500,00" (formato incorrecto)
     Fila 12: ⚠️ Descripción vacía (se llenará con "Importado")
   
   - Estadísticas:
     * Total filas: 50
     * Válidas: 47
     * Con errores: 3
     * Con advertencias: 5
   
   Paso 4: Confirmar importación
   ┌────────────────────────────────────────┐
   │ Opciones de importación:               │
   │                                        │
   │ Estado de transacciones importadas:    │
   │ ○ Pendientes (requieren aprobación)   │
   │ ● Aprobadas automáticamente           │
   │                                        │
   │ Si categoría no existe:                │
   │ ● Crear automáticamente               │
   │ ○ Marcar fila como error              │
   │                                        │
   │ Duplicados (misma fecha + monto):      │
   │ ○ Ignorar fila                        │
   │ ○ Importar como nueva                 │
   │ ● Solicitar confirmación               │
   │                                        │
   │ [Cancelar] [Importar N transacciones] │
   └────────────────────────────────────────┘
   
   Paso 5: Resultado
   - Barra de progreso durante importación
   - Resumen final:
     ✅ 47 transacciones importadas exitosamente
     ❌ 3 filas ignoradas (ver log de errores)
     
   - Botones:
     [Ver transacciones importadas]
     [Descargar log de errores]
     [Importar otro archivo]

2. Plantilla de ejemplo:
   - Botón "Descargar plantilla CSV" pre-llenada con columnas correctas
   - 2-3 filas de ejemplo
   
3. Validaciones específicas:
   - Formato de fecha: dd/mm/yyyy, yyyy-mm-dd, etc. (auto-detectar)
   - Formato de monto: Detectar separador decimal (. o ,)
   - Tipo: Aceptar "ingreso/egreso" o "income/expense" o "I/E"
   - Categoría: Buscar por nombre o ID
   
4. Detección de duplicados:
   - Comparar: fecha + monto + descripción (similarity 80%)
   - Mostrar modal: "Posible duplicado encontrado. ¿Importar de todas formas?"
   
5. Rollback:
   - Guardar ID de importación
   - Opción "Deshacer importación" (enviar a papelera todas)
   - Disponible 24 horas después de importar
   
6. Implementación:
   - wp_ajax_aura_upload_import_file
   - wp_ajax_aura_validate_import
   - wp_ajax_aura_execute_import
   - modules/financial/class-financial-import.php
```

**Checklist de Implementación:**
- [ ] Upload de archivo CSV/Excel
- [ ] Vista previa de datos
- [ ] Mapeo de columnas
- [ ] Validaciones robustas
- [ ] Creación automática de categorías
- [ ] Detección de duplicados
- [ ] Importación con progreso
- [ ] Log de errores descargable
- [ ] Plantilla de ejemplo
- [ ] Opción de rollback
- [ ] Testing con archivos reales

---

## FASE 5: Funciones Avanzadas

### 📌 Objetivo de la Fase
Implementar características avanzadas que elevan el módulo a nivel profesional.

---

### Item 5.1: Sistema de Presupuestos por Categoría

**Prompt de Desarrollo:**
```
Desarrolla un sistema completo de gestión de presupuestos con alertas y seguimiento:

1. Página "Presupuestos":
   
   Vista principal: Tabla de presupuestos
   ┌────────────────────────────────────────────────────────────┐
   │ Categoría      │ Período  │ Presupuesto │ Ejecutado │ %  │
   │────────────────│──────────│─────────────│───────────│────│
   │ Suministros    │ Mensual  │ $5,000      │ $4,750    │ 95%│
   │ Mantenimiento  │ Mensual  │ $3,000      │ $1,200    │ 40%│
   │ Salarios       │ Mensual  │ $50,000     │ $50,000   │100%│
   └────────────────────────────────────────────────────────────┘
   
   - Barra de progreso visual por presupuesto:
     * Verde: 0-70%
     * Amarillo: 71-90%
     * Naranja: 91-100%
     * Rojo: >100% (sobrepasado)
   
   - Acciones: [Editar] [Ver detalle] [Eliminar]
   - Botón: [+ Nuevo Presupuesto]

2. Formulario "Crear/Editar Presupuesto":
   
   ┌────────────────────────────────────────┐
   │ Categoría: [Select]                    │
   │                                        │
   │ Monto del presupuesto: [$_____]        │
   │                                        │
   │ Período:                               │
   │ ● Mensual  ○ Trimestral  ○ Anual     │
   │                                        │
   │ Vigencia:                              │
   │ Desde: [01/01/2026]                   │
   │ Hasta: [31/12/2026]                   │
   │                                        │
   │ Alertas:                               │
   │ ☑ Enviar alerta al llegar a [80]%    │
   │ ☑ Enviar alerta al sobrepasar         │
   │                                        │
   │ Notificar a:                           │
   │ ☑ Creador del presupuesto             │
   │ ☑ Usuarios con rol: [Administrador]   │
   │ ☑ Email adicional: [email]            │
   │                                        │
   │ [Cancelar] [Guardar Presupuesto]       │
   └────────────────────────────────────────┘

3. Detalle de presupuesto (modal o página):
   
   A. Gráfico circular:
      - Ejecutado (color de categoría)
      - Disponible (gris claro)
      - Sobregiro (rojo, si aplica)
      
   B. Estadísticas:
      - Presupuesto total: $5,000
      - Ejecutado: $4,750 (95%)
      - Disponible: $250 (5%)
      - Proyección fin de período: $5,200 (104%)
      
   C. Transacciones del presupuesto:
      - Listado filtrado de transacciones de esa categoría en el período
      - Ordenadas por fecha descendente
      - Subtotal acumulado
      
   D. Historial:
      - Comparación con períodos anteriores
      - Gráfico de líneas: Presupuesto vs Ejecutado (últimos 6 períodos)

4. Widget de dashboard "Estado de Presupuestos":
   - Mostrar 5 presupuestos más críticos (mayor %)
   - Alertas en rojo si están sobrepasados
   - Link a página completa

5. Sistema de alertas:
   
   A. Alerta al 80%:
      - Notificación in-app
      - Email (opcional):
        Asunto: "⚠️ Presupuesto de [Categoría] al 80%"
        Cuerpo: Detalles del presupuesto, link al detalle
        
   B. Alerta al 100%:
      - Notificación destacada (roja)
      - Email obligatorio
        
   C. Alerta de sobregiro:
      - Notificación crítica
      - Email a administradores
      - Opcional: Bloquear nuevas transacciones de esa categoría
   
6. Reportes de presupuesto:
   - "Comparativo Presupuesto vs Real" (PDF/Excel)
   - Análisis de variaciones
   - Categorías con mayor desviación
   - Recomendaciones (IA opcional)

7. Funciones avanzadas:
   - Ajuste de presupuesto (aumentar/disminuir en un %)
   - Transferir presupuesto entre categorías
   - Presupuestos flexibles (rango min-max)
   - Presupuesto por proyecto (si hay módulo de proyectos)

8. Implementación:
   - Tabla: wp_aura_finance_budgets
   - modules/financial/class-financial-budgets.php
   - templates/financial/budgets-page.php
   - wp_ajax_aura_create_budget
   - wp_ajax_aura_get_budget_progress
   - Cron: aura_finance_check_budgets_daily()
```

**Checklist de Implementación:**
- [ ] CRUD completo de presupuestos
- [ ] Cálculo de ejecución en tiempo real
- [ ] Alertas al 80% y 100%
- [ ] Widget en dashboard
- [ ] Gráficos de presupuesto
- [ ] Proyección de fin de período
- [ ] Comparación histórica
- [ ] Reportes de presupuesto
- [ ] Notificaciones por email
- [ ] Testing de alertas

---

### Item 5.2: Etiquetas (Tags) y Búsqueda Avanzada

**Prompt de Desarrollo:**
```
Implementa un sistema de etiquetado flexible y búsqueda full-text avanzada:

1. Sistema de etiquetas:
   
   A. Agregar etiquetas a transacciones:
      - Campo "Etiquetas" en formulario de transacción
      - Input con autocompletado (suggest etiquetas existentes)
      - Múltiples etiquetas separadas por coma
      - Máximo 10 etiquetas por transacción
      
   B. Gestión de etiquetas:
      - Página "Etiquetas" (similar a categorías)
      - Listar todas las etiquetas con contador de uso
      - Renombrar etiqueta (actualiza todas las transacciones)
      - Fusionar etiquetas duplicadas
      - Eliminar etiquetas no usadas
      
   C. Visualización:
      - Tags como badges con colores suaves
      - Click en tag filtra transacciones con ese tag
      - Nube de etiquetas (tag cloud) en dashboard

2. Búsqueda avanzada:
   
   Página "Búsqueda Avanzada" o expandir formulario en listado:
   
   ┌──────────────────────────────────────────────┐
   │ Búsqueda de Transacciones                    │
   │                                              │
   │ Texto libre: [____________________________] │
   │ Buscar en: ☑ Descripción ☑ Notas           │
   │            ☑ Referencia ☑ Beneficiario      │
   │                                              │
   │ Filtros:                                     │
   │ Fecha: [Desde] - [Hasta]                   │
   │ Tipo: ☐ Ingresos ☐ Egresos                 │
   │ Categorías: [Multi-select]                  │
   │ Estado: [Todos / Pendiente / Aprobado...]  │
   │ Monto: Min [___] - Max [___]               │
   │ Método de pago: [Multi-select]              │
   │ Etiquetas: [Multi-select con autocompletar] │
   │ Creado por: [Select usuarios]               │
   │ Con comprobante: ○ Sí ○ No ○ Ambos        │
   │                                              │
   │ [Limpiar filtros] [Buscar]                  │
   │ [Guardar búsqueda...]                       │
   └──────────────────────────────────────────────┘
   
3. Búsquedas guardadas:
   - Guardar configuración de filtros actual
   - Nombre descriptivo: "Gastos mayores a $5000 del último trimestre"
   - Acceso rápido desde sidebar
   - Compartir con otros usuarios (opcional)

4. Operadores avanzados en texto libre:
   - "frase exacta" → Buscar frase completa
   - palabra1 AND palabra2 → Ambas presentes
   - palabra1 OR palabra2 → Al menos una presente
   - -palabra → Excluir resultados con esa palabra
   - categoría:Suministros → Buscar en campo específico
   - monto:>1000 → Operadores numéricos

5. Resultados de búsqueda:
   - Tabla similar a listado principal
   - Resaltar términos encontrados (highlight)
   - Ordenar por relevancia o por fecha
   - Estadísticas: "X resultados encontrados, Total: $Y"
   - Exportar resultados

6. Búsqueda relacionada:
   - Sugerencias: "También buscar: [tag relacionado]"
   - Últimas búsquedas del usuario (localStorage)

7. Implementación:
   - Full-text search con MySQL MATCH AGAINST
   - Índices en columnas: description, notes, reference_number
   - wp_ajax_aura_advanced_search
   - modules/financial/class-financial-search.php
```

**Checklist de Implementación:**
- [ ] Sistema de etiquetas funcional
- [ ] Autocompletado de tags
- [ ] Gestión de etiquetas (CRUD)
- [ ] Nube de etiquetas
- [ ] Formulario de búsqueda avanzada
- [ ] Operadores de búsqueda
- [ ] Búsquedas guardadas
- [ ] Highlighting de resultados
- [ ] Full-text search optimizado
- [ ] Testing de performance

---

### Item 5.3: Auditoría y Trazabilidad Completa

**Prompt de Desarrollo:**
```
Implementa un sistema robusto de auditoría que registre todas las acciones del módulo:

1. Tabla de logs de auditoría:

```sql
CREATE TABLE wp_aura_finance_audit_log (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES wp_users(ID) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_date (created_at)
) ENGINE=InnoDB;
```

2. Acciones a registrar:
   
   Transacciones:
   - transaction_created
   - transaction_updated (con diff de campos)
   - transaction_deleted
   - transaction_restored
   - transaction_approved
   - transaction_rejected
   
   Categorías:
   - category_created
   - category_updated
   - category_deleted
   
   Presupuestos:
   - budget_created
   - budget_updated
   - budget_exceeded
   
   Exportaciones:
   - export_executed (formato, cantidad de registros)
   
   Importaciones:
   - import_executed (cantidad exitosas, cantidad errores)
   
   Configuraciones:
   - settings_updated

3. Función de logging:

```php
function aura_finance_log_action( $action, $entity_type, $entity_id = null, $old_value = null, $new_value = null ) {
    global $wpdb;
    
    $user_id = get_current_user_id();
    $ip_address = aura_get_user_ip();
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $wpdb->insert(
        $wpdb->prefix . 'aura_finance_audit_log',
        [
            'user_id' => $user_id,
            'action' => $action,
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'old_value' => json_encode($old_value),
            'new_value' => json_encode($new_value),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
        ]
    );
}
```

4. Página "Registro de Auditoría":
   
   Solo accesible para: administradores y auditores
   
   Tabla de logs:
   ┌───────────────────────────────────────────────────────────┐
   │ Fecha/Hora  │ Usuario  │ Acción           │ Entidad    │ IP      │
   │─────────────│──────────│──────────────────│────────────│─────────│
   │ 15/02 14:30 │ Juan P.  │ Transacción creada│ Trans #123│ 192...  │
   │ 15/02 14:32 │ María L. │ Transacción aprobada│ Trans #123│ 10...│
   │ 15/02 15:00 │ Carlos R.│ Categoría eliminada│ Cat #45  │ 172...  │
   └───────────────────────────────────────────────────────────┘
   
   Filtros:
   - Por usuario
   - Por acción
   - Por rango de fechas
   - Por tipo de entidad
   - Por IP (detectar accesos sospechosos)
   
   Detalle de log (expandible):
   - Diff visual de cambios (antes/después)
   - User agent completo
   - Link a la entidad (si aún existe)

5. Widget de dashboard "Actividad Reciente":
   - Últimas 10 acciones en el módulo
   - Tiempo relativo ("hace 5 minutos")
   - Filtro rápido: "Ver solo mis acciones"

6. Alertas de seguridad:
   - Detectar múltiples intentos de eliminación
   - Detectar cambios masivos en poco tiempo
   - Accesos desde IPs inusuales
   - Exportaciones de gran volumen

7. Retención de logs:
   - Configurar tiempo de retención (default: 1 año)
   - Archivar logs antiguos
   - Opción de exportar logs antes de eliminar

8. Implementación:
   - Hook: do_action('aura_finance_log', $action, $entity_type, ...)
   - modules/financial/class-financial-audit.php
   - templates/financial/audit-log-page.php
```

**Checklist de Implementación:**
- [ ] Tabla de audit log creada
- [ ] Función de logging implementada
- [ ] Todas las acciones críticas logueadas
- [ ] Página de visualización de logs
- [ ] Filtros de búsqueda en logs
- [ ] Diff visual de cambios
- [ ] Widget de actividad reciente
- [ ] Alertas de seguridad
- [ ] Auto-limpieza de logs antiguos
- [ ] Exportación de logs

---

### Item 5.4: Notificaciones y Recordatorios

**Prompt de Desarrollo:**
```
Desarrolla un sistema completo de notificaciones in-app y por email:

1. Tipos de notificaciones:
   
   A. Transacciones:
      - Nueva transacción pendiente tu aprobación
      - Tu transacción fue aprobada
      - Tu transacción fue rechazada (con motivo)
      - Transacción editada por otro usuario
      
   B. Presupuestos:
      - Presupuesto alcanzó 80%
      - Presupuesto sobrepasado
      - Nuevo presupuesto asignado a tu categoría
      
   C. Recordatorios:
      - Ingresar transacciones pendientes de registro
      - Revisar transacciones sin comprobante
      - Transacciones rechazadas sin acción (hace 7 días)
      
   D. Sistema:
      - Nuevo reporte programado disponible
      - Importación completada
      - Exportación lista para descargar

2. Centro de notificaciones (in-app):
   
   Icono de campana en top bar con contador de no leídas
   
   Dropdown al hacer click:
   ┌─────────────────────────────────────────┐
   │ Notificaciones (3 nuevas)         [⚙️]  │
   │─────────────────────────────────────────│
   │ 🔴 Presupuesto "Suministros" al 95%     │
   │    hace 5 minutos                       │
   │─────────────────────────────────────────│
   │ 🟢 Transacción #123 aprobada            │
   │    hace 1 hora                          │
   │─────────────────────────────────────────│
   │ 🟡 5 transacciones pendientes           │
   │    hace 2 horas                         │
   │─────────────────────────────────────────│
   │ [Ver todas las notificaciones →]        │
   └─────────────────────────────────────────┘
   
   Página completa de notificaciones:
   - Listado completo con paginación
   - Marcar como leída/no leída
   - Filtrar por tipo
   - Eliminar notificaciones antiguas
   - Configuración de preferencias

3. Configuración de notificaciones (por usuario):
   
   Página "Mis Notificaciones"
   
   ┌────────────────────────────────────────────┐
   │ Notificaciones In-App      │ Email         │
   │────────────────────────────│───────────────│
   │ Transacciones pendientes   │ ☑ Sí  ☐ No   │
   │ Aprobaciones/Rechazos      │ ☑ Sí  ☐ No   │
   │ Alertas de presupuesto     │ ☑ Sí  ☐ No   │
   │ Recordatorios              │ ☐ Sí  ☑ No   │
   │ Reportes programados       │ ☑ Sí  ☐ No   │
   │────────────────────────────│───────────────│
   │ Frecuencia de emails:                     │
   │ ○ Inmediato                               │
   │ ● Diario (resumen)                        │
   │ ○ Semanal                                 │
   │────────────────────────────│───────────────│
   │ No molestar:                              │
   │ ☑ Fines de semana                         │
   │ ☑ Fuera de horario (6pm - 8am)           │
   └────────────────────────────────────────────┘

4. Templates de email:
   
   Usar HTML templates profesionales:
   - Header con logo
   - Contenido de notificación
   - Botón de acción (ej. "Ver Transacción")
   - Footer con link de desuscripción
   
   Ejemplo:
   ```html
   <!DOCTYPE html>
   <html>
   <head>...</head>
   <body>
     <div style="max-width: 600px; margin: 0 auto;">
       <h2>Presupuesto Sobrepasado</h2>
       <p>El presupuesto de la categoría <strong>Suministros</strong> 
          ha sido sobrepasado.</p>
       <p><strong>Presupuesto:</strong> $5,000<br>
          <strong>Ejecutado:</strong> $5,250 (105%)</p>
       <a href="[link]" style="...">Ver Detalle</a>
     </div>
   </body>
   </html>
   ```

5. Recordatorios automáticos (Cron jobs):
   
   Diario a las 9:00 AM:
   - Verificar presupuestos cercanos a límite
   - Contar transacciones pendientes por > 7 días
   - Enviar resumen a usuarios con notificaciones activas
   
   Semanal (lunes 9:00 AM):
   - Resumen de la semana anterior
   - Transacciones más grandes
   - Estado de presupuestos

6. Notificaciones push (opcional, futuro):
   - Integración con navegadores (Web Push API)
   - OneSignal o similar

7. Implementación:
   - Tabla: wp_aura_notifications
   - modules/common/class-notifications.php (reutilizable para otros módulos)
   - wp_ajax_aura_mark_notification_read
   - wp_ajax_aura_get_notifications
   - Función: aura_send_notification($user_id, $type, $title, $message, $link)
   - Cron: aura_finance_daily_reminders()
```

**Checklist de Implementación:**
- [ ] Tabla de notificaciones creada
- [ ] Icono de campana con contador
- [ ] Dropdown de notificaciones
- [ ] Página completa de notificaciones
- [ ] Marcar como leída/no leída
- [ ] Configuración de preferencias
- [ ] Templates de email HTML
- [ ] Envío de emails funcionando
- [ ] Cron jobs de recordatorios
- [ ] Testing de todos los tipos de notificación

---

### Item 5.5: Integraciones con Software Contable

**Prompt de Desarrollo:**
```
Implementa conectores para exportar datos a software contable popular:

1. Formatos de exportación específicos:
   
   A. QuickBooks:
      - Formato IIF (Intuit Interchange Format)
      - Mapear: Transacciones → Invoices/Expenses
      - Categorías → Accounts
      
   B. SAP o Contabilidad Electrónica (México):
      - Formato XML estándar
      - Cumplir con requerimientos fiscales
      
   C. Excel personalizado:
      - Plantilla configurable
      - Columnas personalizables
      - Fórmulas predefinidas

2. Asistente de exportación:
   
   Página "Exportar a Software Contable"
   
   Paso 1: Seleccionar software
   ○ QuickBooks
   ○ SAP
   ○ Contabilidad Electrónica MX
   ○ Excel Personalizado
   
   Paso 2: Configurar período y filtros
   - Período fiscal: [Select]
   - Solo transacciones aprobadas: ☑
   - Excluir categorías internas: ☑
   
   Paso 3: Mapear cuentas contables
   | Categoría AURA      | Cuenta Contable |
   |---------------------|-----------------|
   | Ventas de Servicios | → [4010]        |
   | Suministros         | → [5010]        |
   | Salarios            | → [5020]        |
   
   - Guardar mapeo para próximas exportaciones
   
   Paso 4: Descargar archivo
   - Previsualización
   - [Descargar archivo .iif/.xml/.xlsx]

3. Sincronización bidireccional (opcional, futuro):
   - API de QuickBooks Online
   - Sincronizar transacciones automáticamente
   - Evitar duplicados (por fecha + monto + concepto)

4. Implementación:
   - modules/financial/class-financial-integrations.php
   - wp_ajax_aura_export_accounting_format
```

**Checklist de Implementación:**
- [ ] Exportación formato QuickBooks (IIF)
- [ ] Exportación formato XML contable
- [ ] Asistente de exportación
- [ ] Mapeo de cuentas contables
- [ ] Guardar configuración de mapeo
- [ ] Validación de formatos
- [ ] Documentación de uso

---

## Mejores Prácticas

### 🔒 Seguridad

1. **Validación y Sanitización**
   - Siempre usar `sanitize_text_field()`, `absint()`, `sanitize_email()` en inputs
   - Validar en frontend Y backend
   - Escapar salidas con `esc_html()`, `esc_url()`, `esc_attr()`

2. **Nonces y Verificaciones**
   - Todos los formularios deben incluir `wp_nonce_field()`
   - Verificar con `check_ajax_referer()` en AJAX handlers
   - Verificar permisos con `current_user_can()` antes de acciones

3. **Prevención de SQL Injection**
   - Usar `$wpdb->prepare()` para queries personalizadas
   - Nunca concatenar variables directamente en SQL

4. **Control de Acceso**
   - Verificar capabilities en cada endpoint
   - No confiar en verificaciones del frontend
   - Log de accesos sospechosos

### ⚡ Performance

1. **Optimización de Consultas**
   - Usar índices en columnas frecuentemente filtradas
   - Evitar `SELECT *`, especificar columnas necesarias
   - Paginar resultados (20-50 por página)
   - Caché de queries costosas con Transients API

2. **Assets**
   - Minificar CSS/JS en producción
   - Cargar scripts solo en páginas necesarias
   - Usar `wp_enqueue_script()` con dependencias correctas
   - Lazy loading de imágenes/comprobantes

3. **AJAX**
   - Evitar requests síncronos
   - Debounce en búsquedas en tiempo real (300ms)
   - Mostrar loading states
   - Manejo de errores con mensajes claros

### 🎨 UX/UI

1. **Consistencia Visual**
   - Seguir guidelines de WordPress Admin
   - Paleta de colores consistente
   - Iconografía clara y uniforme
   - Espaciado y tipografía coherentes

2. **Feedback al Usuario**
   - Mensajes de éxito/error claros
   - Loading spinners en operaciones largas
   - Confirmaciones antes de acciones destructivas
   - Progress bars en procesos de múltiples pasos

3. **Accesibilidad**
   - Labels claros en formularios
   - Contraste de colores suficiente (WCAG AA)
   - Navegación por teclado
   - ARIA labels donde sea necesario

### 📱 Responsive Design

1. **Mobile First**
   - Diseñar primero para móvil
   - Tablas con scroll horizontal en móvil
   - Botones grandes touch-friendly (min 44x44px)
   - Menús colapsables en pantallas pequeñas

2. **Breakpoints**
   - Mobile: < 768px
   - Tablet: 768px - 1024px
   - Desktop: > 1024px

### 🧪 Testing

1. **Casos de Prueba Esenciales**
   - Crear transacción con todos los campos
   - Aprobar/Rechazar transacción
   - Editar transacción con historial
   - Eliminar (soft delete) y restaurar
   - Filtros combinados en listado
   - Exportación de 1000+ registros
   - Importación con errores de formato
   - Presupuesto alcanzando límites
   - Búsqueda con operadores avanzados

2. **Testing de Permisos**
   - Usuario sin permisos no puede acceder
   - Usuario solo ve sus transacciones (aura_finance_view_own)
   - Usuario no puede aprobar sus propias transacciones
   - Verificar cada capability en diferentes escenarios

3. **Testing de Performance**
   - 10,000+ transacciones: Listado < 2s
   - Exportación 5,000 registros < 10s
   - Dashboard carga < 1s con cache
   - Búsqueda < 500ms

### 📚 Documentación

1. **Código**
   - PHPDoc para todas las funciones y clases
   - Comentarios en lógica compleja
   - README en cada módulo

2. **Usuario Final**
   - Guía de inicio rápido (PDF)
   - Tooltips en interfaz
   - Video tutoriales (opcional)
   - FAQ

3. **Desarrollador**
   - Arquitectura del módulo
   - Hooks y filtros disponibles
   - Ejemplos de extensiones
   - API documentation

---

## Roadmap Futuro (Post-MVP)

### Versión 2.0
- [ ] Dashboard personalizable (drag & drop widgets)
- [ ] Proyectos: Asociar transacciones a proyectos específicos
- [ ] Conciliación bancaria: Importar extractos y conciliar automáticamente
- [ ] Facturación: Generar facturas desde transacciones
- [ ] Multi-moneda: Soporte para múltiples divisas con conversión automática
- [ ] API REST pública: Documentada con Swagger
- [ ] App móvil: React Native o Flutter
- [ ] Inteligencia Artificial: Predicción de gastos, categorización automática
- [ ] Blockchain: Trazabilidad inmutable de transacciones críticas

### Integraciones Futuras
- [ ] Google Drive / Dropbox: Sync automático de comprobantes
- [ ] Stripe / PayPal: Importación automática de transacciones
- [ ] Zapier: Automatizaciones con 1000+ apps
- [ ] Slack / Microsoft Teams: Notificaciones de aprobaciones
- [ ] WhatsApp Business: Alertas y recordatorios

---

## 🗺️ Roadmap Completo del Sistema AURA (6 Módulos)

### Visión del Ecosistema Integrado

El módulo de Finanzas es el **núcleo central** del sistema AURA, pero forma parte de un ecosistema de 6 módulos interconectados diseñados específicamente para las necesidades del instituto:

```
                    ┌─────────────────────┐
                    │   FINANZAS (Core)   │
                    │  Ingresos/Egresos   │
                    │   Presupuestos      │
                    └──────────┬──────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
   ┌────▼────┐           ┌─────▼─────┐         ┌─────▼─────┐
   │INVENTARIO│           │ BIBLIOTECA │         │ VEHÍCULOS │
   │Herramientas│         │ Préstamos  │         │   Flota   │
   │  Equipos  │         │   Libros   │         │Mantenimiento│
   └─────┬─────┘         └─────┬──────┘         └─────┬─────┘
         │                     │                       │
         │         ┌───────────┼───────────┐          │
         │         │           │           │          │
    ┌────▼─────┐  │      ┌────▼─────┐     │    ┌─────▼───────┐
    │FORMULARIOS│  │      │ELECTRICIDAD│   │    │   USUARIOS  │
    │Inscripciónes│◄──────┤  Consumo    │──┘    │ Permisos CBAC│
    │  Encuestas  │       │   Alertas   │       │  Auditoría   │
    └─────────────┘       └──────────────┘       └──────────────┘
```

---

### Priorización de Desarrollo

#### ✅ **FASE ACTUAL: Módulo de Finanzas**
**Estado**: En desarrollo - Item 1.3 completado  
**Próximo**: Item 1.4 - Categorías Predeterminadas  
**Duración estimada**: 6-8 semanas  
**Funcionalidades clave**:
- ✅ Categorías de ingresos/egresos (Items 1.1-1.3 completados)
- 🔄 Categorías predeterminadas (Item 1.4 - siguiente tarea)
- 📋 Gestión de transacciones (Fase 2)
- 📊 Dashboard y reportes (Fase 3)
- 🔗 Integración con otros módulos (Fase 4-5)

---

#### 🔜 **PRÓXIMO: Módulo de Inventario**
**Inicio estimado**: Semana 7-8  
**Duración estimada**: 4 semanas  
**Prioridad**: ALTA - Integra con Finanzas en mantenimiento y compras

**Funcionalidades principales**:
1. **Gestión de Items**:
   - Registro de herramientas (eléctricas, batería, motor)
   - Equipos (sonido, riego, mobiliario)
   - Categorías: Alineadas con categorías de Finanzas
   - Códigos QR/Barras para identificación rápida

2. **Control de Préstamos/Salidas**:
   - Check-out: Quién tomó qué, cuándo
   - Check-in: Devolución con verificación de estado
   - Historial de uso por item
   - Alertas de devoluciones pendientes

3. **Mantenimiento**:
   - Registro de mantenimientos preventivos/correctivos
   - Calendario de próximos mantenimientos
   - **Integración con Finanzas**: Cada mantenimiento genera egreso automático

4. **Stock y Alertas**:
   - Control de cantidades (ej. productos de limpieza)
   - Alertas de stock mínimo
   - Reporte de items más/menos usados

**Integración con Finanzas**:
```php
// Ejemplo de flujo integrado
Usuario registra compra de herramienta en Finanzas ($250)
→ Sistema pregunta: "¿Agregar al inventario?"
→ Si "Sí": Crea item en Inventario automáticamente
→ Enlace bidireccional: transaction_id ↔ inventory_item_id
```

---

#### 📚 **MÓDULO 3: Biblioteca**
**Inicio estimado**: Semana 11-12  
**Duración estimada**: 3 semanas  
**Prioridad**: MEDIA

**Funcionalidades principales**:
1. **Catálogo de Libros**:
   - Registro: ISBN, autor, editorial, categoría
   - Ubicación física (estante, sección)
   - Estado: Disponible / Prestado / Mantenimiento
   - Búsqueda avanzada

2. **Sistema de Préstamos**:
   - Préstamo a usuarios registrados
   - Plazo configurable (default 15 días)
   - Renovaciones (máximo 2 veces)
   - Historial de préstamos por libro y usuario

3. **Alertas y Multas**:
   - Notificaciones de devolución próxima (3 días antes)
   - Alertas de libros vencidos
   - **Integración con Finanzas**: Multas por retraso generan ingreso

4. **Reportes**:
   - Libros más prestados
   - Usuarios más activos
   - Tasa de retorno

**Integración con Finanzas**:
- Compra de libros → Egreso en Finanzas → Alta en Biblioteca
- Multa por retraso → Ingreso automático en Finanzas

---

#### 🚗 **MÓDULO 4: Vehículos**
**Inicio estimado**: Semana 14-15  
**Duración estimada**: 4 semanas  
**Prioridad**: ALTA - Ya existe código base en proyecto

**Funcionalidades principales**:
1. **Gestión de Flota**:
   - Registro de vehículos (placa, modelo, año, VIN)
   - Documentos: SOAT, revisión técnica, seguro
   - Kilometraje actual
   - Estado: Disponible / En uso / Mantenimiento

2. **Control de Salidas**:
   - Tipos: Personal / Mantenimiento / Alquiler a iglesias
   - Registro de conductor y destino
   - Kilometraje de salida y retorno
   - Reporte de novedades

3. **Mantenimientos**:
   - Programados (cada X km o cada Y meses)
   - Correctivos (reparaciones)
   - **Integración con Finanzas**: Cada mantenimiento genera egreso

4. **Alertas**:
   - Documentos próximos a vencer
   - Mantenimiento preventivo pendiente
   - Vehículo no devuelto en tiempo estimado

**Integración con Finanzas**:
- Mantenimiento → Egreso automático (categoría: Mantenimiento → Vehículos)
- Alquiler → Ingreso automático (categoría: Alquileres y Rentas)
- Combustible → Egreso vinculado al vehículo

---

#### 📝 **MÓDULO 5: Formularios**
**Inicio estimado**: Semana 18-19  
**Duración estimada**: 3 semanas  
**Prioridad**: MEDIA

**Funcionalidades principales**:
1. **Constructor de Formularios**:
   - Drag & drop builder
   - Tipos de campo: Texto, email, select, checkbox, radio, file upload
   - Validaciones configurables
   - Lógica condicional (mostrar campo X si respuesta Y)

2. **Casos de Uso del Instituto**:
   - Inscripción de estudiantes
   - Solicitud de alquiler de instalaciones
   - Encuestas de satisfacción
   - Registro de voluntarios
   - Solicitud de préstamo de biblioteca

3. **Gestión de Respuestas**:
   - Visualización de respuestas
   - Exportación a Excel/CSV
   - Estadísticas y gráficos
   - Notificaciones al recibir nueva respuesta

4. **Workflows**:
   - **Integración con Finanzas**: Inscripción con pago → Verifica pago → Genera ingreso
   - **Integración con Inventario**: Solicitud de herramienta → Crea préstamo en Inventario
   - **Integración con Biblioteca**: Solicitud de libro → Registra préstamo

**Integraciones**:
- Formulario de inscripción pagada → Ingreso en Finanzas
- Formulario de alquiler → Ingreso en Finanzas
- Formulario de solicitud de herramienta → Préstamo en Inventario

---

#### ⚡ **MÓDULO 6: Electricidad**
**Inicio estimado**: Semana 21-22  
**Duración estimada**: 2 semanas  
**Prioridad**: BAJA - Ya existe código base básico

**Funcionalidades principales**:
1. **Registro de Lecturas**:
   - Lectura mensual del contador
   - Cálculo automático de consumo (kWh)
   - Foto del contador (opcional)

2. **Dashboard de Consumo**:
   - Gráfico de tendencia mensual
   - Comparación con mes anterior
   - Proyección de consumo fin de mes
   - Costo estimado (kWh × tarifa)

3. **Alertas**:
   - Consumo superior al promedio (>20%)
   - Proyección de exceder presupuesto
   - Recordatorio de lectura mensual

4. **Reportes**:
   - Consumo anual
   - Costos por período
   - Eficiencia energética

**Integración con Finanzas**:
- Pago de recibo de luz → Egreso automático (categoría: Servicios Públicos → Electricidad)
- Dashboard de Finanzas muestra KPI de consumo eléctrico

---

### Matriz de Dependencias entre Módulos

| Módulo       | Depende de    | Lo usan      | Prioridad |
|--------------|---------------|--------------|-----------|
| Finanzas     | -             | Todos        | 1 (Core)  |
| Inventario   | Finanzas      | Formularios  | 2         |
| Biblioteca   | Finanzas      | Formularios  | 4         |
| Vehículos    | Finanzas      | Formularios  | 3         |
| Formularios  | -             | Todos        | 5         |
| Electricidad | Finanzas      | -            | 6         |

---

### Timeline Completo (Estimación)

```
Semana 1-8:  ████████████████ Finanzas (Fases 1-5)
Semana 7-11: ░░░░░░████████    Inventario
Semana 11-14:      ░░░░░       Biblioteca
Semana 14-18:         ████████ Vehículos
Semana 18-21:            ░░░░  Formularios
Semana 21-23:               ░░ Electricidad
Semana 23-26:                  Integración final y testing
────────────────────────────────────────────────────────
             26 semanas (6 meses aprox.)
```

---

### Beneficios de la Integración Completa

1. **Trazabilidad Total**:
   - Cada gasto tiene contexto: ¿Qué herramienta? ¿Qué vehículo? ¿Qué libro?
   - Auditoría facilitada: Seguir el dinero desde la fuente hasta el uso

2. **Automatización**:
   - Reducción de entrada duplicada de datos: Un registro actualiza múltiples módulos
   - Workflows inteligentes: Inscripción pagada → Ingreso + Usuario registrado

3. **Reportes Poderosos**:
   - Cross-module analytics: "Costo de mantenimiento de herramientas por categoría"
   - ROI de inversiones: "Ingresos por alquiler de vehículos vs costos de mantenimiento"

4. **Control Presupuestario**:
   - Alertas inteligentes: "Gastos en biblioteca exceden presupuesto del trimestre"
   - Proyecciones: "A este ritmo, terminará el año con saldo positivo de $X"

5. **User Experience**:
   - Una sola aplicación para todo
   - Un solo login, un solo sistema de permisos
   - Datos compartidos sin duplicación

---

### Próximos Hitos del Proyecto

#### ✅ Hito 1: MVP Finanzas (Semana 8)
- Categorías funcionales
- CRUD de transacciones
- Aprobaciones básicas
- Dashboard con gráficos
- **Entregable**: Sistema funcional para registrar ingresos/egresos

#### 🎯 Hito 2: Inventario + Finanzas Integrados (Semana 11)
- Gestión de herramientas
- Préstamos de equipos
- Mantenimientos vinculados a Finanzas
- **Entregable**: Control completo de herramientas con impacto financiero

#### 🎯 Hito 3: Ecosistema Base (Semana 18)
- Biblioteca operativa
- Vehículos con tracking
- Formularios para inscripciones
- **Entregable**: 5 de 6 módulos funcionando

#### 🎯 Hito 4: Sistema Completo (Semana 23)
- Electricidad integrado
- Todos los módulos comunicándose
- Dashboard unificado
- **Entregable**: Sistema completo listo para producción

#### 🎯 Hito 5: Optimización y Documentación (Semana 26)
- Testing exhaustivo
- Documentación de usuario
- Guías de administrador
- Videos tutoriales
- **Entregable**: Sistema en producción con soporte completo

---

## Conclusión

Este PRD proporciona una hoja de ruta completa para desarrollar un módulo de finanzas profesional y robusto para pequeñas empresas y fundaciones. Cada fase está diseñada para ser implementada de manera incremental, con ítems claramente definidos y checklists de verificación.

### Próximos Pasos

1. **Revisar y aprobar este PRD** con stakeholders
2. **Estimar tiempos de desarrollo** para cada fase/ítem
3. **Priorizar funcionalidades** según necesidad de negocio
4. **Asignar recursos** (desarrolladores, diseñadores, QA)
5. **Comenzar Fase 1** con configuración de categorías
6. **Iterar y mejorar** basándose en feedback de usuarios

### Criterios de Éxito

✅ **MVP funcional en 4-6 semanas**  
✅ **Sistema de permisos granulares operativo**  
✅ **Categorías y transacciones CRUD completo**  
✅ **Dashboard con gráficos en tiempo real**  
✅ **Exportación multi-formato**  
✅ **100% de cobertura de permisos verificados**  
✅ **Documentación completa de usuario y desarrollador**  
✅ **Performance: Listado de 10K transacciones < 2s**

---

<div align="center">

**Desarrollado con ❤️ para democratizar la gestión financiera**

</div>

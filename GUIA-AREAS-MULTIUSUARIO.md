# 🏢 Guía de Áreas y Programas Multi-Usuario

## 📋 Índice

1. [Resumen de Cambios](#resumen-de-cambios)
2. [Nueva Estructura de Base de Datos](#nueva-estructura-de-base-de-datos)
3. [Cómo Asignar Múltiples Usuarios](#cómo-asignar-múltiples-usuarios)
4. [Diferencia entre Categorías y Áreas](#diferencia-entre-categorías-y-áreas)
5. [Roles en Áreas](#roles-en-áreas)
6. [Mejoras Prácticas Empresariales](#mejoras-prácticas-empresariales)
7. [Instalación y Migración](#instalación-y-migración)

---

## 🆕 Resumen de Cambios

### Antes
- ❌ Solo se podía asignar **1 usuario responsable** por área
- ❌ No había integración con la página de permisos
- ❌ No se mostraban avatares en la tabla de áreas
- ❌ Confusión entre "Gestionar categorías" y "Gestionar áreas"

### Ahora
- ✅ Se pueden asignar **múltiples usuarios** a una misma área
- ✅ Integración completa con **Gestión de Permisos Granulares (CBAC)**
- ✅ Avatares visibles en toda la interfaz
- ✅ Claridad entre categorías financieras y áreas organizacionales
- ✅ Sistema de roles: `responsible`, `coordinator`, `viewer`

---

## 🗄️ Nueva Estructura de Base de Datos

### Tabla: `wp_aura_area_users`

```sql
CREATE TABLE IF NOT EXISTS `wp_aura_area_users` (
    id              BIGINT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    area_id         BIGINT UNSIGNED  NOT NULL,          -- ID del área
    user_id         BIGINT UNSIGNED  NOT NULL,          -- ID del usuario
    role            VARCHAR(50)      NOT NULL DEFAULT 'responsible',
    assigned_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    assigned_by     BIGINT UNSIGNED  NOT NULL DEFAULT 0, -- Quién lo asignó
    
    UNIQUE KEY uq_area_user (area_id, user_id),        -- Un usuario solo puede tener un rol por área
    INDEX idx_area (area_id),
    INDEX idx_user (user_id),
    INDEX idx_role (role)
);
```

### Relación Many-to-Many

```
┌─────────────────┐         ┌──────────────────────┐         ┌─────────────┐
│  wp_aura_areas  │────────>│ wp_aura_area_users   │<────────│  wp_users   │
│                 │         │                      │         │             │
│  - id           │         │  - area_id (FK)      │         │  - ID       │
│  - name         │         │  - user_id (FK)      │         │  - ...      │
│  - ...          │         │  - role              │         │             │
└─────────────────┘         │  - assigned_at       │         └─────────────┘
                            └──────────────────────┘
```

### Compatibilidad con Legacy

La tabla `wp_aura_areas` **mantiene** el campo `responsible_user_id` por compatibilidad, pero ahora es **sincronizado automáticamente** con la tabla `wp_aura_area_users`.

---

## 👥 Cómo Asignar Múltiples Usuarios

### Opción 1: Desde "Gestión de Permisos"

1. Ve a: **Aura Suite → Gestión de Permisos**
2. Selecciona un usuario de la lista
3. Desplázate hasta la sección **"🏢 Asignar Áreas/Programas"**
4. Marca las áreas a las que quieres asignar al usuario
5. Haz clic en **"Guardar Permisos"**

```
┌────────────────────────────────────────────┐
│  🏢 Asignar Áreas/Programas                │
├────────────────────────────────────────────┤
│  ☑️ 💰 Finanzas Generales (Departamento)   │
│  ☐ 🎓 Educación Infantil (Programa)        │
│  ☑️ 🏥 Salud Comunitaria (Programa)        │
│  ☐ 🌱 Medio Ambiente (Proyecto)            │
└────────────────────────────────────────────┘
```

### Opción 2: Desde "Áreas y Programas" (Frontend)

> **Nota:** Esta funcionalidad está disponible a través de AJAX con el endpoint `aura_areas_assign_users`

```javascript
// Ejemplo de uso desde el frontend
jQuery.ajax({
    url: ajaxurl,
    method: 'POST',
    data: {
        action: 'aura_areas_assign_users',
        nonce: aura_areas_nonce,
        area_id: 5,
        user_ids: [3, 7, 12], // IDs de usuarios
        role: 'responsible'
    },
    success: function(response) {
        console.log('Usuarios asignados:', response.data.assigned_users);
    }
});
```

---

## 🔍 Diferencia entre Categorías y Áreas

### ❌ ANTES: Confusión

- **"Gestionar categorías financieras"** era ambiguo
- No quedaba claro si se refería a áreas organizacionales o categorías de gastos

### ✅ AHORA: Claridad Total

| Concepto | Descripción | Ejemplos | Capacidad |
|----------|-------------|----------|-----------|
| **Categorías de Gastos/Ingresos** | Tipos de transacciones contables | Suministros, Salarios, Mantenimiento, Donaciones | `aura_finance_category_manage` |
| **Áreas/Programas** | Unidades organizacionales | Finanzas Generales, Programa Educación, Proyecto X | `aura_areas_manage` |

### Ejemplo Práctico

```
📊 TRANSACCIÓN:
├── Categoría: "Suministros de Oficina" (tipo de gasto)
├── Área: "Programa Educación" (unidad organizacional)
├── Monto: $150
└── Responsable: Usuario asignado al área
```

---

## 👔 Roles en Áreas

El sistema soporta diferentes roles para usuarios asignados a un área:

### `responsible` (Responsable)
- **Permisos:** Control total sobre el área
- **Uso:** Director de programa, gerente de departamento
- **Aparece como:** Usuario principal del área

### `coordinator` (Coordinador)
- **Permisos:** Apoya la gestión del área
- **Uso:** Subdirector, coordinador de proyecto
- **Aparece como:** Usuario secundario

### `viewer` (Observador)
- **Permisos:** Solo lectura
- **Uso:** Auditores, supervisores, stakeholders
- **Aparece como:** Usuario con acceso limitado

> **Nota Actual:** Por defecto todos se asignan como `responsible`. En futuras versiones se podrán diferenciar permisos por rol.

---

## 💼 Mejores Prácticas Empresariales

### 1. Segregación de Funciones

✅ **Correcto:**
```
Área: "Finanzas Generales"
└── Usuario A: Gerente Financiero (responsible)
└── Usuario B: Contador (coordinator)
└── Usuario C: Auditor Interno (viewer)
```

❌ **Evitar:**
```
Área: "Finanzas Generales"
└── Solo 1 usuario con control total
```

### 2. Matriz de Responsabilidades

| Área/Programa | Responsable Principal | Coordinadores | Observadores |
|---------------|----------------------|---------------|--------------|
| Finanzas Generales | Juan Pérez | María López | Auditoría |
| Programa Educación | Ana García | Pedro Ruiz, Lucía Fernández | Dirección |
| Proyecto Medio Ambiente | Carlos Díaz | - | ONG Socios |

### 3. Rotación de Personal

Cuando un responsable se va:

1. **Opción A - Reasignar desde Permisos:**
   - Ve a Gestión de Permisos
   - Selecciona al usuario saliente
   - Desmarca las áreas
   - Selecciona al nuevo usuario
   - Marca las mismas áreas

2. **Opción B - Mantener Continuidad:**
   - Asigna al nuevo responsable **antes** de remover al anterior
   - Verifica que el nuevo usuario tenga las capacidades necesarias
   - Luego remueve al usuario saliente

### 4. Auditoría

La tabla `wp_aura_area_users` registra:
- ✅ `assigned_at`: Cuándo fue asignado
- ✅ `assigned_by`: Quién lo asignó

Para ver el historial:
```sql
SELECT 
    a.name AS area,
    u.display_name AS usuario,
    au.role AS rol,
    au.assigned_at AS fecha_asignacion,
    assign.display_name AS asignado_por
FROM wp_aura_area_users au
JOIN wp_aura_areas a ON au.area_id = a.id
JOIN wp_users u ON au.user_id = u.ID
LEFT JOIN wp_users assign ON au.assigned_by = assign.ID
ORDER BY au.assigned_at DESC;
```

---

## 🚀 Instalación y Migración

### Paso 1: Ejecutar Script de Migración

1. Accede a: `http://diserwp.test/wp-content/plugins/aura-business-suite/migrate-area-users-table.php`
2. El script creará automáticamente la tabla `wp_aura_area_users`
3. Migrará los responsables existentes desde `responsible_user_id`
4. Mostrará un reporte de migración

### Paso 2: Activar Plugin de Avatares (Recomendado)

El sistema utiliza `get_avatar_url()` y es compatible con:

#### Opción 1: Simple Local Avatars (Recomendado)
- ✅ Permite al administrador y usuarios cargar imágenes locales
- ✅ No depende de Gravatar
- ✅ Descargado en: `simple-local-avatars.2.7.11.zip`
- 📥 Instalación: Plugins → Añadir nuevo → Subir plugin

#### Opción 2: WP User Avatar
- Similar a Simple Local Avatars
- Más opciones de personalización

#### Opción 3: Gravatar (predeterminado)
- Ya viene con WordPress
- Requiere cuenta en Gravatar.com

### Paso 3: Verificar Permisos

Asegúrate de que los usuarios tengan las capacidades correctas:

```php
// Ver en: Aura Suite → Gestión de Permisos
Capacidades recomendadas por rol:

ADMINISTRADOR:
✅ aura_areas_manage (Gestionar áreas/programas)
✅ aura_finance_category_manage (Gestionar categorías de gastos)
✅ aura_finance_transaction_manage (Gestionar transacciones)
✅ aura_finance_budget_manage (Gestionar presupuestos)

COORDINADOR FINANCIERO:
✅ aura_finance_transaction_manage
✅ aura_finance_budget_view
❌ aura_areas_manage (solo admin)
❌ aura_finance_category_manage (solo admin)

RESPONSABLE DE ÁREA:
✅ aura_finance_transaction_view (solo de su área)
✅ aura_finance_budget_view (solo de su área)
❌ aura_areas_manage
```

### Paso 4: Eliminar Archivos Temporales

Después de verificar que todo funciona:

```bash
# Eliminar script de migración
rm migrate-area-users-table.php
```

---

## 🧪 Testing y Validación

### Checklist de Pruebas

- [ ] Crear un área nueva
- [ ] Asignar 3 usuarios diferentes al área desde "Gestión de Permisos"
- [ ] Verificar que los avatares se muestran en la tabla de Áreas
- [ ] Crear una transacción asignada a esa área
- [ ] Verificar que los 3 usuarios pueden ver la transacción
- [ ] Desasignar un usuario y verificar que ya no ve la transacción
- [ ] Verificar resumen de áreas en página de permisos

### Queries de Diagnóstico

```sql
-- Ver todas las relaciones área-usuario
SELECT 
    a.name AS area,
    a.type AS tipo,
    u.display_name AS usuario,
    au.role AS rol,
    au.assigned_at
FROM wp_aura_area_users au
JOIN wp_aura_areas a ON au.area_id = a.id
JOIN wp_users u ON au.user_id = u.ID
ORDER BY a.name, u.display_name;

-- Ver usuarios sin áreas asignadas
SELECT ID, display_name, user_email
FROM wp_users
WHERE ID NOT IN (SELECT DISTINCT user_id FROM wp_aura_area_users);

-- Ver áreas sin usuarios asignados
SELECT id, name, type
FROM wp_aura_areas
WHERE id NOT IN (SELECT DISTINCT area_id FROM wp_aura_area_users);
```

---

## 🐛 Troubleshooting

### Problema: "No se muestran los avatares"

**Solución:**
1. Verifica que `get_avatar_url()` funciona:
```php
$avatar = get_avatar_url(1); // Usuario con ID 1
echo $avatar; // Debe mostrar una URL
```

2. Instala y activa "Simple Local Avatars"
3. Ve a Usuarios → Editar → Subir avatar local

### Problema: "No aparece la sección de áreas en permisos"

**Solución:**
1. Limpia caché del navegador (Ctrl+F5)
2. Verifica que estás en: `admin.php?page=aura-permissions`
3. Revisa que existen áreas creadas en `wp_aura_areas`

### Problema: "Error al guardar permisos"

**Solución:**
1. Revisa error de PHP en `wp-content/debug.log`
2. Verifica que la tabla `wp_aura_area_users` existe:
```sql
SHOW TABLES LIKE 'wp_aura_area_users';
```
3. Ejecuta nuevamente el script de migración

---

## 📚 Recursos Adicionales

- [PRD Finanzas](./prdFinanzas.md) - Especificación completa del módulo financiero
- [MANUAL PRESUPUESTOS](./MANUAL-PRESUPUESTOS.md) - Guía de uso de presupuestos
- [CONTRIBUTING](./CONTRIBUTING.md) - Guía para contribuidores

---

## 📞 Soporte

Para dudas o problemas:

1. Revisa esta guía completa
2. Ejecuta las queries de diagnóstico
3. Revisa `wp-content/debug.log`
4. Documenta el error con screenshots

---

**Última actualización:** 2024-02-24  
**Versión del plugin:** 1.1.0+  
**Autor:** Aura Business Suite Team

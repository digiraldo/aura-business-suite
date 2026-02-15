# Aura Business Suite

<div align="center">

![Logo AURA](assets/images/logo-aura.png)

**Aplicaciones Unificadas para Recursos Administrativos**

[![Version](https://img.shields.io/badge/version-1.0.0-blue.svg)](https://github.com/yourusername/aura-business-suite)
[![WordPress](https://img.shields.io/badge/WordPress-6.4%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/license-GPL--2.0-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

</div>

## 📖 Descripción

**Aura Business Suite** es una plataforma modular de gestión empresarial construida sobre WordPress que integra cuatro módulos críticos de negocio con un sistema unificado de permisos granulares (CBAC). Diseñada para pequeñas y medianas empresas que necesitan gestionar sus operaciones sin complejidad técnica.

### Características Principales

- ✅ **Sistema de Permisos Granulares (CBAC)**: Asignación individual de capabilities por usuario
- 💰 **Módulo de Finanzas**: Gestión de ingresos/egresos con flujo de aprobación
- 🚗 **Módulo de Vehículos**: Control de flota con alertas de mantenimiento
- 📝 **Módulo de Formularios**: Integración con Formidable Forms y Quiz and Survey Master
- ⚡ **Módulo de Electricidad**: Monitoreo de consumo con API REST para IoT
- 📊 **Dashboards Interactivos**: Visualización con gráficos Chart.js
- 📧 **Sistema de Notificaciones**: Emails automáticos personalizados
- 🔐 **Seguridad Robusta**: Basado en sistema de capabilities de WordPress

## 🚀 Instalación

### Requisitos Previos

- WordPress 6.4 o superior
- PHP 8.0 o superior
- MySQL 5.7+ o MariaDB 10.3+
- Servidor web (Apache/Nginx)

### Pasos de Instalación

1. **Clonar o descargar el repositorio**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/yourusername/aura-business-suite.git
   # O descomprimir el ZIP en esta carpeta
   ```

2. **Activar el plugin en WordPress**
   - Ve a `Plugins > Plugins Instalados`
   - Busca "Aura Business Suite"
   - Clic en "Activar"

3. **Instalación automática de capabilities**
   - El plugin registrará automáticamente todas las capabilities en el sistema
   - Los administradores tendrán acceso completo por defecto

4. **Configuración inicial**
   - Ve a `Aura Suite > Configuración`
   - Configura el email de notificaciones
   - Ajusta los umbrales de alerta para electricidad y vehículos

## 📚 Estructura del Plugin

```
aura-business-suite/
├── aura-business-suite.php      # Archivo principal del plugin
├── README.md                     # Este archivo
├── PRD.md                       # Documento de Requisitos del Producto
├── modules/                     # Módulos del sistema
│   ├── common/                  # Funcionalidades compartidas
│   │   ├── class-roles-manager.php
│   │   └── class-notifications.php
│   ├── financial/               # Módulo de Finanzas
│   │   ├── class-financial-cpt.php
│   │   ├── class-financial-dashboard.php
│   │   └── class-financial-charts.php
│   ├── vehicles/                # Módulo de Vehículos
│   │   ├── class-vehicle-cpt.php
│   │   ├── class-vehicle-alerts.php
│   │   └── class-vehicle-reports.php
│   └── electricity/             # Módulo de Electricidad
│       ├── class-electricity-cpt.php
│       ├── class-electricity-api.php
│       └── class-electricity-dashboard.php
├── assets/                      # Recursos estáticos
│   ├── css/
│   │   ├── admin-styles.css
│   │   └── frontend-styles.css
│   ├── js/
│   │   ├── admin-scripts.js
│   │   └── charts.js
│   └── images/
│       └── logo-aura.png
├── templates/                   # Plantillas HTML/PHP
│   ├── main-dashboard.php
│   ├── settings-page.php
│   └── permissions-page.php
└── languages/                   # Archivos de traducción
```

## 🔑 Sistema de Permisos (CBAC)

### Capabilities por Módulo

#### 📊 Finanzas
- `aura_finance_create` - Crear transacciones
- `aura_finance_edit_own` - Editar propias transacciones
- `aura_finance_edit_all` - Editar todas las transacciones
- `aura_finance_approve` - Aprobar/rechazar gastos ⭐
- `aura_finance_view_all` - Ver todas las transacciones
- `aura_finance_charts` - Ver gráficos
- `aura_finance_export` - Exportar reportes

#### 🚗 Vehículos
- `aura_vehicles_create` - Crear vehículos
- `aura_vehicles_exits_create` - Registrar salidas
- `aura_vehicles_km_update` - Actualizar kilometraje
- `aura_vehicles_view_all` - Ver todos los vehículos
- `aura_vehicles_reports` - Ver reportes
- `aura_vehicles_alerts` - Recibir alertas de mantenimiento

#### ⚡ Electricidad
- `aura_electric_reading_create` - Registrar lecturas
- `aura_electric_view_dashboard` - Ver dashboard
- `aura_electric_view_charts` - Ver gráficos
- `aura_electric_alerts_receive` - Recibir alertas
- `aura_electric_thresholds_config` - Configurar umbrales

#### ⚙️ Administración
- `aura_admin_users_manage` - Gestionar usuarios
- `aura_admin_permissions_assign` - Asignar permisos
- `aura_admin_settings` - Configurar sistema

### Asignar Permisos a Usuarios

1. Ve a `Aura Suite > Permisos`
2. Selecciona el usuario
3. Opcional: Aplica una plantilla predefinida (Tesorero, Auditor, etc.)
4. Marca las capabilities individuales necesarias
5. Guarda los cambios

## 💼 Uso de los Módulos

### Módulo de Finanzas

**Registrar una transacción:**
1. `Aura Suite > Nueva Transacción`
2. Completa: monto, fecha, descripción, comprobante
3. Selecciona tipo (Ingreso/Egreso) y categoría
4. Guarda como borrador o envía a aprobación

**Aprobar gastos:**
- Las transacciones en estado "Pendiente" aparecen en el dashboard
- Solo usuarios con `aura_finance_approve` pueden aprobar
- No puedes aprobar tus propias transacciones (control interno)

### Módulo de Vehículos

**Registrar un vehículo:**
1. `Aura Suite > Vehículos > Agregar Nuevo`
2. Ingresa: placa, marca, modelo, año, kilometraje
3. Define próximo mantenimiento en km

**Registrar salida:**
1. `Aura Suite > Salidas > Agregar Nueva`
2. Selecciona vehículo y tipo de salida
3. Registra km de salida y, al retornar, km de retorno
4. El sistema actualiza automáticamente el kilometraje del vehículo

### Módulo de Electricidad

**Registrar lectura manual:**
1. `Aura Suite > Lecturas > Agregar Nueva`
2. Ingresa fecha y lectura en kWh
3. El sistema calcula consumo vs lectura anterior

**Registrar via API (IoT):**
```bash
curl -X POST https://tusitio.com/wp-json/aura/v1/electricity/reading \
  -H "Content-Type: application/json" \
  -d '{
    "reading_kwh": 450.5,
    "cost_per_kwh": 0.12,
    "api_key": "TU_API_KEY_AQUI"
  }'
```

## 📊 Dashboards Disponibles

- **Dashboard Principal**: Resumen general y accesos rápidos
- **Dashboard Financiero**: Ingresos vs Egresos, distribución por categorías
- **Reportes de Vehículos**: Alertas de mantenimiento, kilometraje
- **Dashboard de Electricidad**: Consumo diario, comparativas mensuales

## 🔔 Sistema de Notificaciones

### Notificaciones Automáticas

- ✉️ **Transacción pendiente**: A usuarios con permiso de aprobar
- ✉️ **Transacción aprobada/rechazada**: Al creador
- ✉️ **Alerta de mantenimiento**: Cuando faltan < 500 km
- ✉️ **Consumo eléctrico alto**: Al superar umbral configurado

### Configurar emails
`Aura Suite > Configuración > Notificaciones`

## 🛠️ Desarrollo y Extensión

### Agregar una nueva capability

```php
// En modules/common/class-roles-manager.php
'nuevo_modulo' => array(
    'aura_nuevo_create' => __('Crear en nuevo módulo', 'aura-suite'),
    'aura_nuevo_edit'   => __('Editar en nuevo módulo', 'aura-suite'),
),
```

### Crear un nuevo módulo

1. Crea carpeta en `/modules/nombre_modulo/`
2. Implementa `class-nombre-cpt.php` con el CPT
3. Registra en `aura-business-suite.php`:
   ```php
   require_once AURA_PLUGIN_DIR . 'modules/nombre_modulo/class-nombre-cpt.php';
   Aura_Nombre_CPT::init();
   ```

### Hooks disponibles

```php
// Después de aprobar una transacción
do_action('aura_transaction_approved', $transaction_id, $approver_id);

// Después de registrar un vehículo
do_action('aura_vehicle_registered', $vehicle_id);

// Modificar datos del dashboard
apply_filters('aura_dashboard_data', $data, $module);
```

## 🐛 Solución de Problemas

### Los permisos no se aplican
- Verifica que el usuario no tenga el rol "Administrator" (tiene todos los permisos)
- Desactiva y reactiva el plugin para registrar capabilities

### No llegan las notificaciones
- Verifica la configuración SMTP de WordPress
- Instala un plugin SMTP (WP Mail SMTP)
- Revisa spam/correo no deseado

### Los gráficos no se muestran
- Verifica que Chart.js se carga correctamente (Consola del navegador)
- Limpia caché del navegador
- Desactiva plugins de cache/minificación temporalmente

### Error 403 al acceder a un módulo
- Verifica que el usuario tenga al menos una capability del módulo
- Revisa en `Aura Suite > Permisos` qué tiene asignado

## 🤝 Contribución

Las contribuciones son bienvenidas:

1. Fork el proyecto
2. Crea una rama para tu feature (`git checkout -b feature/AmazingFeature`)
3. Commit tus cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Abre un Pull Request

### Guías de Código

- Sigue [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
- Usa prefijo `aura_` para todas las funciones
- Documenta con PHPDoc
- Sanitiza inputs, escapa outputs

## 📝 Licencia

Este proyecto está bajo la Licencia GPL v2 o posterior - ver archivo [LICENSE](LICENSE) para más detalles.

## 👥 Autores

- **Aura Development Team** - [GitHub](https://github.com/yourusername)

## 🎯 Roadmap

### v1.1.0 (Próxima)
- [ ] Integración con facturación electrónica
- [ ] App móvil con REST API
- [ ] Exportación real a PDF/Excel
- [ ] Multi-idioma completo

### v1.2.0
- [ ] Módulo de nómina
- [ ] Dashboard con Power BI
- [ ] Integración con ERPs externos

## 📞 Soporte

- 📧 Email: soporte@aurabusiness.com
- 📖 Documentación: [docs.aurabusiness.com](https://docs.aurabusiness.com)
- 💬 Forum: [community.aurabusiness.com](https://community.aurabusiness.com)

---

<div align="center">
  
**Hecho con ❤️ para simplificar la gestión empresarial**

[⬆ Volver arriba](#aura-business-suite)

</div>

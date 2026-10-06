# Aura Business Suite

<p align="center">
  <img src="aura-icono.svg" alt="Aura Business Suite Logo" width="120" height="120">
</p>

<p align="center">
  <strong>Suite modular de gestión empresarial para WordPress con permisos granulares (CBAC)</strong><br>
  <em>Centraliza finanzas, inventario, flota vehicular, calendario académico, formularios, certificados y áreas multi-usuario en una sola plataforma.</em>
</p>

<p align="center">
  <a href="https://github.com/digiraldo/aura-business-suite/releases"><img src="https://img.shields.io/badge/version-1.8.3-blue.svg?style=flat-square" alt="Version"></a>
  <a href="https://wordpress.org"><img src="https://img.shields.io/badge/WordPress-%3E%3D%206.4-21759b.svg?style=flat-square&logo=wordpress" alt="WordPress"></a>
  <a href="https://php.net"><img src="https://img.shields.io/badge/PHP-%3E%3D%208.0-777bb4.svg?style=flat-square&logo=php" alt="PHP"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-GPL--2.0--or--later-green.svg?style=flat-square" alt="License"></a>
  <a href="https://github.com/digiraldo"><img src="https://img.shields.io/badge/autor-DiGiraldo-orange.svg?style=flat-square" alt="Author"></a>
</p>

---

## 📋 Descripción

**Aura Business Suite** es una solución enterprise para WordPress diseñada para optimizar los procesos operativos, administrativos y contables en organizaciones con múltiples departamentos, áreas y programas.

A diferencia de los plugins monolíticos convencionales, Aura Business Suite implementa una arquitectura modular con infraestructura compartida para control de acceso, auditoría, automatización y notificaciones.

---

## ✨ Módulos Incluidos

### 💰 1. Finanzas & Contabilidad
- Registro y control de transacciones en múltiples monedas (USD / MXN) con libro contable dedicado.
- Clasificación de gastos e ingresos con partidas de capital (**CapEx**) y gastos operacionales (**OpEx**).
- Catálogo jerárquico de categorías con colores, descripciones ricas y soporte completo para emojis.
- Presupuestos dinámicos, cuentas bancarias, tags, conciliación y reportes exportables en CSV, Excel y PDF.
- Importador y exportador masivo con sincronización inteligente tipo *Upsert*.

### 🚗 2. Vehículos & Gestión de Flotas
- Fichas completas de vehículos institucionales con historial de mantenimientos preventivos y correctivos.
- Bitácora de viajes, kilometraje inicial/final, consumo de combustible y asignación de conductores.
- Escaneo e inspección móvil mediante códigos QR generados dinámicamente (`qr.svg`).
- Pistas de auditoría y estadísticas de rendimiento por unidad.

### 📅 3. Calendario Académico & Asistencia
- Vista mensual interactiva y sincronización bidireccional (Google Calendar / RFC 5545).
- Compatibilidad estricta con eventos de día completo (*allDay*) y rangos multi-día (cálculo exclusivo de fecha fin).
- Gestión de programas educativos, materias, registro de tareas y calificaciones por periodo.

### 📦 4. Portabilidad & Migración Multimedia (ZIP Bundle)
- Exportación e importación integral de **Terceros**, **Áreas** y **Usuarios de WordPress**.
- Empaquetado en lote (*ZIP Bundle*) que preserva metadatos estructurados en JSON junto con las **imágenes físicas de perfil y avatares** en su directorio multimedia respectivo.

### 🏢 5. Áreas & Programas Multi-Usuario
- Relaciones muchos-a-muchos (*many-to-many*) entre áreas y usuarios del sistema.
- Roles funcionales dentro de cada área: `responsible` (responsable), `coordinator` (coordinador) y `viewer` (observador).
- Integración visual con avatares de usuario y badges cromáticos en las vistas administrativas.

### 📝 6. Formularios & Inscripciones
- Constructor visual (*Form Builder*) para formularios institucionales y encuestas.
- Pasarela de admisiones, inscripciones de alumnos y asignación automática a cursos.
- Renderizado frontend responsivo y panel de analítica de respuestas.

### 🎓 7. Certificados Digitales
- Diseñador de plantillas de certificados con variables dinámicas y firmas autorizadas.
- Emisión masiva de constancias y diplomas.
- Verificación pública externa mediante código único o folio de validación.

### 📦 8. Inventario & Activos
- Catálogo de equipos, herramientas y mobiliario con estado de conservación.
- Módulo de préstamos a personal o estudiantes con fechas de devolución y recordatorios.
- Registro de mantenimientos de equipamiento.

### 📚 9. Biblioteca & Préstamos
- Control de catálogo bibliográfico, inventario de ejemplares físicos y fichas técnicas.
- Circulación de libros: préstamos activos, historial, reservas y cálculo automático de multas por mora.

---

## 🔒 Seguridad y Control de Acceso (CBAC)

Aura Business Suite cuenta con un motor de **Capability-Based Access Control (CBAC)** que reemplaza la rigidez de los roles nativos de WordPress por permisos granulares por módulo y acción:

- **Finanzas:** `aura_finance_view`, `aura_finance_create`, `aura_finance_edit`, `aura_finance_delete`, `aura_finance_reports`.
- **Vehículos:** `aura_vehicles_manage`, `aura_vehicles_log_trip`, `aura_vehicles_view`.
- **Inventario:** `aura_inventory_view`, `aura_inventory_manage`, `aura_inventory_loans`.
- **Estudiantes & Formularios:** `aura_students_manage`, `aura_forms_build`, `aura_forms_submissions`.
- Protección completa en endpoints AJAX y REST API mediante verificación estricta de *nonces* y capacidades activas.

---

## 💻 Requisitos del Sistema

| Componente | Requisito Mínimo | Recomendado |
| :--- | :---: | :---: |
| **WordPress** | 6.4 | 6.7+ |
| **PHP** | 8.0 | 8.2+ |
| **Base de Datos** | MySQL 5.7+ / MariaDB 10.3+ | MySQL 8.0+ / MariaDB 10.6+ |
| **Extensiones PHP** | `curl`, `json`, `mbstring`, `zip`, `gd` | `curl`, `json`, `mbstring`, `zip`, `imagick` |

---

## 🚀 Instalación

### Método 1: Repositorio Git (Recomendado para desarrollo)
```bash
cd wp-content/plugins/
git clone https://github.com/digiraldo/aura-business-suite.git
```

### Método 2: Instalación Manual
1. Descarga el paquete comprimido `.zip` del repositorio o release oficial.
2. Extrae o sube la carpeta `aura-business-suite` en tu directorio `/wp-content/plugins/`.
3. Dirígete a **WordPress Admin > Plugins** y haz clic en **Activar**.
4. Accede al menú **Aura Suite** en la barra lateral para configurar tus módulos y permisos.

---

## 📁 Estructura del Repositorio

```text
aura-business-suite/
├── assets/                  # Hojas de estilo CSS, scripts JS, librerías y fuentes
│   ├── css/
│   └── js/
├── modules/                 # Lógica modular de negocio (Controladores y servicios)
│   ├── calendar/            # Calendario, asistencia, eventos RFC 5545
│   ├── certificates/        # Generador de certificados y verificación pública
│   ├── common/              # UI compartida, CBAC, listas y Portable Bundle
│   ├── financial/           # Libro diario, cuentas, CapEx, USD ledger
│   ├── forms/               # Builder, admisiones y reportes de formularios
│   ├── inventory/           # Activos, préstamos y equipos
│   ├── library/             # Libros, préstamos y reservas
│   ├── students/            # Expedientes, materias y pagos de alumnos
│   └── vehicles/            # Flotas, viajes, bitácoras y QR móvil
├── templates/               # Vistas y plantillas administrativas / frontend
├── languages/               # Archivos de traducción (.pot, .po, .mo)
├── aura-business-suite.php  # Punto de entrada y cargador principal del plugin
├── composer.json            # Metadatos del paquete y dependencias de autoload
├── README.md                # Documentación del repositorio
└── readme.txt               # Ficha técnica para el repositorio de WordPress
```

---

## 📝 Registro de Cambios (Changelog)

### [1.8.3] - Octubre 2026
- **Portabilidad & Migración Multimedia:** Incorporación del sistema `Aura_Portable_Bundle` con exportación e importación en lote de Terceros, Áreas y Usuarios de WordPress preservando sus imágenes de perfil y metadatos en un solo archivo ZIP.
- **Calendario Académico (RFC 5545):** Corrección y estandarización del cálculo de fechas fin exclusivas en eventos multidia y *allDay* para sincronización precisa con FullCalendar y Google Calendar.
- **Catálogo de Categorías Financieras:** Optimización del importador/exportador CSV con soporte completo para emojis, colores HSL y descripciones de producción tipo *Upsert*.
- **Control de Versiones & Seguridad:** Implementación de filtrado inverso estricto en `.gitignore` para repositorios públicos, asegurando la privacidad de credenciales, copias de seguridad y volcados locales.

### [1.8.2] - Octubre 2026
- Verificación y adaptación integral para despliegues en servidores Hostinger y entornos de producción con PHP 8.2+.
- Refuerzo en la persistencia de transacciones multi-moneda (USD/MXN).

### [1.8.0] - Septiembre 2026
- **Módulo de Vehículos:** Integración completa de bitácoras de viaje con cálculo de distancias, inspección por códigos QR y puente financiero con centros de costos automotrices.
- **Áreas Multi-Usuario:** Asignación granular de múltiples usuarios a una misma área institucional con visualización de avatares.

### [1.7.7] - Febrero 2026
- Consolidación de módulos principales y nuevas vistas administrativas responsive.
- Mejoras visuales en las tablas administrativas y estandarización de componentes UI.

---

## 👤 Autor & Soporte

Desarrollado y mantenido por **[DiGiraldo](https://github.com/digiraldo)**.  
Para reportar problemas o sugerir mejoras, puedes abrir un issue en el repositorio oficial.

Licenciado bajo la **[GNU General Public License v2.0 or later](LICENSE)**.
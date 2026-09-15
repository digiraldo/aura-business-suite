# AURA Business Suite

## Descripcion

AURA Business Suite es un plugin modular de gestion empresarial para WordPress.
Centraliza procesos operativos, permisos y reportes en una sola plataforma,
con una arquitectura pensada para organizaciones con multiples areas.

### Caracteristicas principales

- Estructura modular por area funcional.
- Modelo de permisos granulares (CBAC).
- Integracion entre modulos: Finanzas, Inventario, Estudiantes, Formularios,
  Certificados, Biblioteca, Vehiculos y Electricidad.
- Tablas administrativas responsive con estandar de UX unificado.
- Soporte de notificaciones (Email y WhatsApp, segun configuracion).
- Reportes y exportaciones para trazabilidad operativa.

### Modulos incluidos

- Finanzas
- Inventario
- Estudiantes
- Certificados
- Formularios y Encuestas
- Vehiculos
- Biblioteca
- Electricidad
- Areas y Programas

### Requisitos

- WordPress 6.4 o superior
- PHP 8.0 o superior
- MySQL o MariaDB compatible con WordPress

## Instalacion

1. Copia la carpeta del plugin en `wp-content/plugins/aura-business-suite`.
2. Activa el plugin desde WordPress Admin > Plugins.
3. Verifica que los menus y modulos de AURA aparezcan en el panel.
4. Revisa ajustes globales y permisos antes de usarlo en produccion.

## FAQ

### El plugin es monolitico o modular?

Es modular. Las funcionalidades estan organizadas por modulos con
infraestructura compartida para permisos, notificaciones e integraciones.

### Soporta permisos granulares por usuario?

Si. Usa un modelo CBAC para asignar capacidades por modulo y por accion.

### Incluye reportes y exportaciones?

Si. Varios modulos incluyen flujos de reportes y exportaciones segun permisos.

### Esta listo para produccion?

Se recomienda usarlo tras validar el entorno, revisar configuraciones y probar
flujos criticos (crear, editar, listar, reportar y exportar).

## Changelog

### 1.7.7

- Se consolidaron modulos y plantillas principales para operacion.
- Se amplio documentacion tecnica para hojas de ruta de implementacion.
- Se mejoraron assets UI para formularios, estudiantes, biblioteca,
  certificados y vistas financieras.
- Se actualizaron menus, ajustes e integracion de permisos en toda la suite.

### 1.7.6

- Correcciones en builder y flujo de inscripcion del modulo de formularios.
- Mejoras de estabilidad en render frontend e interacciones de admin.

### 1.7.5 and earlier

- Base de arquitectura, carga inicial de modulos y mejoras iterativas.

## Soporte

Para mantenimiento y mejoras, aplica cambios por fases y valida cada modulo
end-to-end antes de publicar.

Desarrollado con ❤️ por [DiGiraldo](https://github.com/digiraldo)  |  © 2026 AURA Business Suite
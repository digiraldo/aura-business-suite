# Guía de Despliegue - Aura Business Suite

## 📋 Pre-requisitos del Servidor

### Requisitos Mínimos
- **PHP**: 8.0 o superior
- **WordPress**: 6.4 o superior
- **MySQL**: 5.7+ o MariaDB 10.3+
- **Memoria PHP**: 128MB mínimo (256MB recomendado)
- **Post Max Size**: 32MB
- **Upload Max Filesize**: 32MB
- **Max Execution Time**: 60 segundos

### Extensiones PHP Requeridas
```bash
php -m | grep -E "(mysqli|json|mbstring|curl|zip|gd)"
```

Deben estar instaladas:
- `mysqli` - Conexión a base de datos
- `json` - Manipulación de datos JSON
- `mbstring` - Strings multibyte
- `curl` - Comunicación HTTP
- `zip` - Compresión de archivos
- `gd` o `imagick` - Procesamiento de imágenes

## 🚀 Instalación en Producción

### Opción 1: Instalación Manual

1. **Descargar el plugin**
   ```bash
   cd /var/www/html/wp-content/plugins/
   git clone https://github.com/yourusername/aura-business-suite.git
   # O subir ZIP via FTP/SFTP
   ```

2. **Configurar permisos**
   ```bash
   chown -R www-data:www-data aura-business-suite/
   find aura-business-suite/ -type d -exec chmod 755 {} \;
   find aura-business-suite/ -type f -exec chmod 644 {} \;
   ```

3. **Activar el plugin**
   - Acceder a `wp-admin/plugins.php`
   - Buscar "Aura Business Suite"
   - Clic en "Activar"

4. **Verificar activación**
   ```bash
   wp plugin list --allow-root
   # Debe aparecer: aura-business-suite | active
   ```

### Opción 2: Instalación via WP-CLI

```bash
# Descargar plugin
cd /var/www/html/wp-content/plugins/
git clone https://github.com/yourusername/aura-business-suite.git

# Activar con WP-CLI
wp plugin activate aura-business-suite --allow-root

# Verificar capabilities registradas
wp cap list administrator | grep aura_
```

### Opción 3: Subir via WordPress Admin

1. Comprimir la carpeta del plugin en `aura-business-suite.zip`
2. `Plugins > Añadir nuevo > Subir plugin`
3. Seleccionar el ZIP
4. Clic en "Instalar ahora"
5. Activar el plugin

## ⚙️ Configuración Post-Instalación

### 1. Configuración Inicial

```bash
# Acceder al dashboard de WordPress
https://tusitio.com/wp-admin/

# Navegar a: Aura Suite > Configuración
```

**Configurar:**
- ✅ Email de notificaciones
- ✅ Umbral de consumo eléctrico (kWh)
- ✅ Costo por kWh
- ✅ Intervalo de mantenimiento vehicular (km)
- ✅ Generar API key para IoT

### 2. Asignar Permisos a Usuarios

```bash
# Via WordPress Admin:
Aura Suite > Permisos > Seleccionar usuario

# O via WP-CLI:
wp user meta add 2 wp_capabilities 'a:1:{s:17:"aura_finance_view";b:1;}' --allow-root
```

**Plantillas predefinidas disponibles:**
- **Tesorero**: Finanzas completas + crear/aprobar
- **Auditor**: Solo lectura de finanzas + reportes
- **Operador de Campo**: Vehículos + electricidad
- **Director**: Acceso completo a todos los módulos

### 3. Configurar Cron Jobs

El plugin usa WP Cron por defecto. Para mayor fiabilidad, desactivar WP Cron y usar cron del sistema:

**a) Desactivar WP Cron en wp-config.php**
```php
define('DISABLE_WP_CRON', true);
```

**b) Agregar a crontab del servidor**
```bash
crontab -e

# Ejecutar WP Cron cada 5 minutos
*/5 * * * * cd /var/www/html && wp cron event run --due-now --allow-root > /dev/null 2>&1
```

**Verificar eventos programados:**
```bash
wp cron event list --allow-root | grep aura_
```

Deberían aparecer:
- `aura_daily_vehicle_alerts` - Diario a las 8:00 AM
- `aura_daily_electricity_alerts` - Diario a las 9:00 AM

### 4. Configurar SMTP para Emails

**Opción A: Plugin WP Mail SMTP**
```bash
wp plugin install wp-mail-smtp --activate --allow-root
```

Configurar en: `Ajustes > WP Mail SMTP`

**Opción B: Configuración manual en wp-config.php**
```php
define('SMTP_USER',   'noreply@tusitio.com');
define('SMTP_PASS',   'tu_contraseña_segura');
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_FROM',   'noreply@tusitio.com');
define('SMTP_NAME',   'Aura Business Suite');
define('SMTP_PORT',   '587');
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH',   true);
```

**Probar envío de emails:**
```bash
wp eval "wp_mail('test@example.com', 'Test AURA', 'Mensaje de prueba');" --allow-root
```

## 🔒 Seguridad Post-Despliegue

### 1. Proteger archivos sensibles

```apache
# En .htaccess del plugin
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>
```

### 2. Limitar acceso a REST API

```php
// En wp-config.php - Solo si NO usas IoT
define('AURA_DISABLE_REST_API', true);
```

### 3. Cambiar API Key inicial

```bash
# Generar nueva clave en:
Aura Suite > Configuración > API > Regenerar API Key
```

### 4. Activar logs de auditoría (opcional)

Instalar plugin recomendado:
```bash
wp plugin install simple-history --activate --allow-root
```

## 📊 Verificación de Instalación

### Checklist de Verificación

```bash
# 1. Plugin activo
wp plugin list | grep aura-business-suite
# ✅ Debe mostrar: active

# 2. CPTs registrados
wp post-type list | grep aura_
# ✅ Debe mostrar: aura_transaction, aura_vehicle, etc.

# 3. Taxonomías registradas
wp taxonomy list | grep aura_
# ✅ Debe mostrar: aura_transaction_type, aura_transaction_category, etc.

# 4. Capabilities registradas
wp cap list administrator | grep aura_ | wc -l
# ✅ Debe mostrar: 52

# 5. Cron events programados
wp cron event list | grep aura_ | wc -l
# ✅ Debe mostrar: 2 (vehicle_alerts y electricity_alerts)

# 6. REST API disponible
curl https://tusitio.com/wp-json/aura/v1/
# ✅ Debe retornar JSON con namespace info

# 7. Assets cargados
ls -lh wp-content/plugins/aura-business-suite/assets/
# ✅ Deben existir css/ y js/

# 8. Templates disponibles
ls -lh wp-content/plugins/aura-business-suite/templates/
# ✅ Deben existir 3 archivos .php
```

### Test Manual en el Admin

1. ✅ Acceder a `wp-admin/admin.php?page=aura-dashboard`
2. ✅ Debe cargar dashboard principal sin errores
3. ✅ Verificar que se carguen gráficos (inspeccionar consola JS)
4. ✅ Crear una transacción de prueba
5. ✅ Registrar un vehículo de prueba
6. ✅ Verificar que llegue email de notificación

### Test de la REST API

```bash
# Obtener API key de: Aura Suite > Configuración
API_KEY="tu_api_key_aqui"

# Enviar lectura de prueba
curl -X POST https://tusitio.com/wp-json/aura/v1/electricity/reading \
  -H "Content-Type: application/json" \
  -d '{
    "reading_kwh": 100.5,
    "cost_per_kwh": 0.12,
    "api_key": "'$API_KEY'"
  }'

# ✅ Debe retornar: {"success":true,"reading_id":123}
```

## 🔧 Troubleshooting

### Problema: Plugin no se activa

**Solución:**
```bash
# Verificar errores de PHP
tail -f /var/log/php-error.log

# Verificar versión de PHP
php -v
# Debe ser >= 8.0

# Activar modo debug en wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Problema: Los gráficos no cargan

**Solución:**
```bash
# Verificar que Chart.js se carga
curl -I https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js
# Debe retornar 200 OK

# Limpiar cache del navegador
# Desactivar plugins de cache temporalmente

# Verificar consola del navegador (F12)
# No deben haber errores de JavaScript
```

### Problema: No llegan emails

**Solución:**
```bash
# Test de envío
wp eval "var_dump(wp_mail('test@example.com', 'Test', 'Prueba'));" --allow-root
# Debe retornar: bool(true)

# Verificar logs de mail
tail -f /var/log/mail.log

# Instalar plugin SMTP
wp plugin install wp-mail-smtp --activate --allow-root
```

### Problema: Error 403 al acceder a módulos

**Solución:**
```bash
# Verificar capabilities del usuario
wp user meta get 2 wp_capabilities --allow-root

# Asignar capability manualmente
wp eval "get_user_by('id', 2)->add_cap('aura_finance_view');" --allow-root

# O via admin: Aura Suite > Permisos
```

## 📈 Optimización de Rendimiento

### 1. Object Cache (Redis/Memcached)

```bash
# Instalar Redis Object Cache
wp plugin install redis-cache --activate --allow-root
wp redis enable --allow-root
```

### 2. Minificar Assets

```bash
# Instalar Autoptimize
wp plugin install autoptimize --activate --allow-root

# Configurar en: Ajustes > Autoptimize
# ✅ Optimizar CSS
# ✅ Optimizar JavaScript
# ❌ NO agregar assets de Aura Suite (causaría conflictos)
```

### 3. CDN para Chart.js (ya configurado)

El plugin ya usa CDN de jsdelivr para Chart.js:
```php
wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js');
```

## 🔄 Actualización del Plugin

### Método Seguro de Actualización

```bash
# 1. Backup completo
wp db export backup-$(date +%Y%m%d).sql --allow-root
tar -czf aura-backup-$(date +%Y%m%d).tar.gz aura-business-suite/

# 2. Desactivar el plugin
wp plugin deactivate aura-business-suite --allow-root

# 3. Actualizar archivos
cd wp-content/plugins/aura-business-suite/
git pull origin main

# 4. Reactivar el plugin
wp plugin activate aura-business-suite --allow-root

# 5. Verificar que todo funcione
wp plugin list | grep aura
```

## 📞 Soporte

Si encuentras problemas durante el despliegue:

- 📧 Email: soporte@aurabusiness.com
- 🐛 Issues: https://github.com/yourusername/aura-business-suite/issues
- 📖 Docs: https://docs.aurabusiness.com

---

**¡Despliegue exitoso! 🎉**

Próximos pasos recomendados:
1. Configurar backups automáticos diarios
2. Instalar SSL/HTTPS si no lo tienes
3. Configurar firewall (Wordfence, Sucuri)
4. Capacitar a usuarios en el uso del sistema

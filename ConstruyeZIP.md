# Guía de Construcción del Paquete de Actualización (`aura-business-suite.zip`)

Esta guía describe cómo generar correctamente el archivo ZIP de actualización del plugin **Aura Business Suite** para su instalación o actualización en WordPress (Hostinger, cPanel, VPS o entorno local).

---

## 🚀 Métodos de Construcción

Dispones de dos métodos principales según el escenario de actualización:

### 1. Método Rápido / Liviano (Recomendado para Actualizaciones habituales)
> **Tamaño aproximado:** ~1.5 MB  
> **Uso:** Cuando el servidor de producción ya tiene instaladas las dependencias (`vendor/`) o se han actualizado módulos, CSS, JavaScript o plantillas PHP.

Ejecuta en la terminal dentro de la raíz del plugin:
```bash
php build-zip-sin-vendor.php
```
*O con PowerShell:*
```powershell
powershell -ExecutionPolicy Bypass -File .\build-zip.ps1
```

---

### 2. Método Completo (Con dependencias `vendor/`)
> **Uso:** Cuando se han instalado o actualizado librerías de Composer (por ejemplo: Google Client API, PhpSpreadsheet, etc.) y el servidor remoto necesita recibir la carpeta `vendor/`.

Ejecuta en la terminal:
```bash
php build-zip.php
```

---

## 🛠️ ¿Qué hacen automáticamente estos scripts?

| Característica | Beneficio |
| :--- | :--- |
| **Separadores de Ruta (`/`)** | Convierte las rutas a formato estándar POSIX/Linux (`/`), evitando el error clásico de WordPress en Linux *"El archivo del plugin no existe"*. |
| **Limpieza de BOM UTF-8** | Detecta y elimina el Byte Order Mark (`\xEF\xBB\xBF`) de todos los archivos `.php`, `.js` y `.css`, previniendo errores de `Headers already sent` o pantallas blancas. |
| **Estructura Raíz Correcta** | Empaqueta todo bajo la carpeta contenedora `aura-business-suite/`, lo que permite a WordPress detectar el slug del plugin y ofrecer el botón **"Reemplazar actual con el subido"**. |
| **Exclusión de Archivos Innecesarios** | Excluye `.git`, `.venv`, `.vscode`, tests, archivos temporales `.csv`, `.xlsx`, `.md` y backups previos `.zip`. |

---

## 📦 Proceso de Actualización en WordPress (Hostinger / cPanel)

1. **Generar el ZIP:**  
   Ejecuta `php build-zip-sin-vendor.php` en tu entorno de desarrollo.
2. **Acceder a WordPress:**  
   Ve a **Plugins** → **Añadir nuevo plugin** (o *Add New Plugin*).
3. **Subir el ZIP:**  
   Haz clic en el botón superior **Subir plugin** y selecciona el archivo generado:  
   `c:\laragon\www\diserwp\wp-content\plugins\aura-business-suite\aura-business-suite.zip`
4. **Instalar y Reemplazar:**  
   Haz clic en **Instalar ahora**. WordPress comparará la versión instalada con la nueva y mostrará una pantalla de confirmación.
5. **Confirmar:**  
   Haz clic en **Reemplazar la versión actual con la subida**.
6. **Verificación:**  
   Comprueba que los módulos y menús de Aura Business Suite carguen sin errores.

---

## 🔍 Solución de Problemas Comunes

- **Error: "El archivo del plugin no existe" o error de descompresión:**  
  Ocurre si se crea el ZIP con herramientas manuales de Windows que usan barras invertidas `\`. Usa siempre `php build-zip-sin-vendor.php` o `build-zip.ps1`.
- **Error: "El archivo subido excede upload_max_filesize":**  
  Si utilizas el ZIP completo con vendor y tu servidor tiene un límite bajo (ej. 2MB o 8MB), usa el **Método Liviano** (`build-zip-sin-vendor.php`, ~1.5 MB) o aumenta `upload_max_filesize` y `post_max_size` en el panel de PHP de tu hosting.
- **Error 500 tras actualizar:**  
  Revisa si tu cambio requería nuevas clases en `vendor/`. Si agregaste nuevas dependencias en `composer.json`, asegúrate de usar `php build-zip.php`.
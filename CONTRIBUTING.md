# Guía de Contribución - Aura Business Suite

¡Gracias por tu interés en contribuir a Aura Business Suite! 🎉

## 📋 Tabla de Contenidos

- [Código de Conducta](#código-de-conducta)
- [¿Cómo puedo contribuir?](#cómo-puedo-contribuir)
- [Configuración del Entorno de Desarrollo](#configuración-del-entorno-de-desarrollo)
- [Estándares de Código](#estándares-de-código)
- [Proceso de Pull Request](#proceso-de-pull-request)
- [Reportar Bugs](#reportar-bugs)
- [Sugerir Mejoras](#sugerir-mejoras)
- [Estructura del Proyecto](#estructura-del-proyecto)

## 📜 Código de Conducta

Este proyecto se adhiere a un código de conducta. Al participar, se espera que mantengas este código. Por favor reporta comportamientos inaceptables a conduct@aurabusiness.com.

### Nuestros Estándares

**Comportamientos positivos:**
- Usar lenguaje acogedor e inclusivo
- Respetar diferentes puntos de vista
- Aceptar críticas constructivas con gracia
- Enfocarse en lo mejor para la comunidad
- Mostrar empatía hacia otros miembros

**Comportamientos inaceptables:**
- Lenguaje o imágenes sexualizadas
- Comentarios insultantes o despectivos (trolling)
- Ataques personales o políticos
- Acoso público o privado
- Publicar información privada sin permiso

## 🤝 ¿Cómo puedo contribuir?

### Tipos de Contribuciones

1. **Reportar Bugs** - Encontraste un error, avísanos
2. **Sugerir Features** - Ideas para nuevas funcionalidades
3. **Escribir Código** - Implementar features o corregir bugs
4. **Mejorar Documentación** - README, comentarios, guías
5. **Traducir** - Ayudar con internacionalización
6. **Testing** - Probar nuevas versiones
7. **Diseño** - Mejorar UX/UI del plugin

## 💻 Configuración del Entorno de Desarrollo

### Requisitos

- **PHP** 8.0+
- **Composer** 2.x
- **Node.js** 16+ (opcional, para asset building)
- **WordPress** 6.4+ en ambiente local
- **Git** 2.x

### Instalación Local

1. **Clonar el repositorio**
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/yourusername/aura-business-suite.git
   cd aura-business-suite
   ```

2. **Instalar dependencias de desarrollo**
   ```bash
   composer install
   ```

3. **Activar el plugin en WordPress**
   ```bash
   wp plugin activate aura-business-suite
   ```

4. **Habilitar modo debug en wp-config.php**
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('WP_DEBUG_DISPLAY', false);
   define('SCRIPT_DEBUG', true);
   ```

5. **Crear rama para tu trabajo**
   ```bash
   git checkout -b feature/mi-nueva-funcionalidad
   ```

### Herramientas Recomendadas

- **IDE**: Visual Studio Code, PHPStorm
- **Extensions VSCode**:
  - PHP Intelephense
  - WordPress Snippets
  - phpcs (PHP CodeSniffer)
  - ESLint (para JS)
- **Local WordPress**: Local by Flywheel, Laravel Valet, XAMPP
- **Git Client**: GitKraken, Sourcetree, CLI

## 📝 Estándares de Código

### WordPress Coding Standards

Este proyecto sigue estrictamente [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/).

### PHP

**Instalar PHP CodeSniffer:**
```bash
composer require --dev squizlabs/php_codesniffer
composer require --dev wp-coding-standards/wpcs
```

**Configurar PHPCS:**
```bash
./vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
```

**Verificar código:**
```bash
composer phpcs
```

**Auto-corregir (cuando sea posible):**
```bash
composer phpcbf
```

### Reglas Importantes

#### Nomenclatura

```php
// ✅ Correcto
class Aura_Financial_CPT {}
function aura_get_transactions() {}
$aura_transaction_id = 123;

// ❌ Incorrecto
class AuraFinancialCPT {}
function getTransactions() {}
$transactionId = 123;
```

#### Documentación PHPDoc

```php
/**
 * Calcula el balance total de transacciones aprobadas.
 *
 * @since 1.0.0
 * 
 * @param int    $user_id ID del usuario (opcional).
 * @param string $period  Período: 'month', 'year', 'all'.
 * @return float Balance calculado.
 */
function aura_calculate_balance( $user_id = 0, $period = 'all' ) {
    // Implementación...
}
```

#### Sanitización y Escape

```php
// Input: Siempre sanitizar
$user_input = sanitize_text_field( $_POST['field_name'] );
$email = sanitize_email( $_POST['email'] );
$url = esc_url_raw( $_POST['website'] );

// Output: Siempre escapar
echo esc_html( $user_name );
echo esc_attr( $data_attribute );
echo esc_url( $link );
```

#### Nonces para Seguridad

```php
// Crear nonce en formulario
wp_nonce_field( 'aura_save_transaction', 'aura_transaction_nonce' );

// Verificar nonce al procesar
if ( ! isset( $_POST['aura_transaction_nonce'] ) || 
     ! wp_verify_nonce( $_POST['aura_transaction_nonce'], 'aura_save_transaction' ) ) {
    wp_die( 'Seguridad: Acceso denegado' );
}
```

#### Capabilities

```php
// ✅ Siempre verificar permisos
if ( ! current_user_can( 'aura_finance_create' ) ) {
    wp_die( 'No tienes permisos suficientes' );
}

// ✅ Usar funciones del Roles Manager
if ( Aura_Roles_Manager::user_can_view_module( get_current_user_id(), 'finance' ) ) {
    // Mostrar contenido
}
```

### JavaScript

```javascript
// ✅ Usar jQuery wrapper
jQuery(document).ready(function($) {
    $('.aura-button').on('click', function(e) {
        e.preventDefault();
        // Lógica...
    });
});

// ✅ Localizar strings
console.log(auraData.messages.success);  // No hardcodear textos

// ✅ Validar antes de enviar
if (!formData.amount || formData.amount <= 0) {
    alert(auraData.errors.invalidAmount);
    return false;
}
```

### CSS

```css
/* Prefijo aura- en todas las clases */
.aura-dashboard {}
.aura-kpi-card {}
.aura-transaction-table {}

/* BEM notation recomendada */
.aura-card {}
.aura-card__header {}
.aura-card__body {}
.aura-card--highlighted {}

/* Variables para consistencia */
:root {
    --aura-primary: #2271b1;
    --aura-success: #00a32a;
    --aura-danger: #d63638;
}
```

## 🔄 Proceso de Pull Request

### Checklist antes de enviar PR

- [ ] El código sigue WordPress Coding Standards
- [ ] PHPCS pasa sin errores: `composer phpcs`
- [ ] Has agregado PHPDoc a funciones nuevas
- [ ] Has probado el código en un ambiente WordPress limpio
- [ ] No introduces errores de consola JavaScript
- [ ] Has actualizado CHANGELOG.md
- [ ] Los commits tienen mensajes descriptivos
- [ ] Has resuelto conflictos con `main`
- [ ] Has agregado comentarios explicativos en código complejo

### Flujo de Trabajo

1. **Fork el repositorio**

2. **Crea una rama descriptiva**
   ```bash
   git checkout -b feature/add-payment-module
   git checkout -b fix/vehicle-km-validation
   git checkout -b docs/improve-readme
   ```

3. **Haz commits atómicos y descriptivos**
   ```bash
   git commit -m "feat: Agregar módulo de pagos con integración Stripe"
   git commit -m "fix: Corregir validación de kilometraje negativo"
   git commit -m "docs: Actualizar instrucciones de instalación"
   ```

   **Prefijos de commit:**
   - `feat:` - Nueva funcionalidad
   - `fix:` - Corrección de bug
   - `docs:` - Cambios en documentación
   - `style:` - Formato de código (no afecta lógica)
   - `refactor:` - Reestructuración de código
   - `test:` - Agregar o modificar tests
   - `chore:` - Tareas de mantenimiento

4. **Push a tu fork**
   ```bash
   git push origin feature/add-payment-module
   ```

5. **Abre un Pull Request**
   - Ve a GitHub y crea el PR
   - Usa la plantilla de PR (si existe)
   - Describe QUÉ cambia y POR QUÉ
   - Referencia issues relacionados: `Closes #42`
   - Agrega screenshots si hay cambios visuales

### Plantilla de PR

```markdown
## Descripción
Breve descripción de los cambios realizados.

## Tipo de cambio
- [ ] Bug fix (cambio que corrige un issue)
- [ ] Nueva funcionalidad (cambio que agrega funcionalidad)
- [ ] Breaking change (fix o feature que causaría que funcionalidad existente no funcione)
- [ ] Documentación

## ¿Cómo se ha probado?
Describe las pruebas que realizaste.

## Checklist
- [ ] Mi código sigue WordPress Coding Standards
- [ ] He realizado una auto-revisión de mi código
- [ ] He comentado código complejo
- [ ] He actualizado la documentación
- [ ] Mis cambios no generan warnings
- [ ] He actualizado CHANGELOG.md
```

## 🐛 Reportar Bugs

### Antes de reportar

1. **Busca si el bug ya fue reportado** en [Issues](https://github.com/yourusername/aura-business-suite/issues)
2. **Verifica que sea reproducible** en la última versión
3. **Determina si es un bug o una pregunta** de soporte

### Cómo reportar un bug

**Usa la plantilla de issue para bugs:**

```markdown
**Descripción del Bug**
Descripción clara del problema.

**Pasos para Reproducir**
1. Ve a '...'
2. Haz clic en '....'
3. Scroll hasta '....'
4. Ver error

**Comportamiento Esperado**
Qué debería suceder.

**Comportamiento Actual**
Qué está sucediendo.

**Screenshots**
Si aplica, agrega capturas de pantalla.

**Ambiente**
- WordPress version: [ej. 6.4.2]
- Plugin version: [ej. 1.0.0]
- PHP version: [ej. 8.1]
- Navegador: [ej. Chrome 120]

**Logs**
```
Pegar logs relevantes aquí
```

**¿Probaste desactivar otros plugins?**
- [ ] Sí, el problema persiste
- [ ] No

**Contexto Adicional**
Cualquier información adicional.
```

## 💡 Sugerir Mejoras

### Cómo sugerir una nueva funcionalidad

```markdown
**¿Es tu solicitud relacionada a un problema?**
Descripción clara: "Frustra cuando [...]"

**Describe la solución que te gustaría**
Descripción clara de lo que quieres que suceda.

**Describe alternativas que consideraste**
Otras soluciones o features que consideraste.

**¿Beneficia a otros usuarios?**
- [ ] Sí, es útil para muchos casos de uso
- [ ] Específico para mi caso de uso

**Contexto Adicional**
Mockups, diagramas, etc.
```

## 🏗️ Estructura del Proyecto

Comprender la arquitectura facilita las contribuciones:

```
aura-business-suite/
├── modules/                    # Código fuente organizado por módulos
│   ├── common/                 # Funcionalidades compartidas
│   │   ├── class-roles-manager.php      # CBAC system
│   │   └── class-notifications.php      # Email system
│   ├── financial/              # Módulo de finanzas
│   │   ├── class-financial-cpt.php
│   │   ├── class-financial-dashboard.php
│   │   └── class-financial-charts.php
│   ├── vehicles/               # Módulo de vehículos
│   └── electricity/            # Módulo de electricidad
├── assets/                     # Frontend assets
│   ├── css/
│   │   ├── admin-styles.css
│   │   └── frontend-styles.css
│   ├── js/
│   │   ├── admin-scripts.js
│   │   └── charts.js
│   └── images/
├── templates/                  # Plantillas HTML/PHP
│   ├── main-dashboard.php
│   ├── settings-page.php
│   └── permissions-page.php
├── languages/                  # Archivos de traducción (i18n)
├── tests/                      # Tests unitarios (por implementar)
├── aura-business-suite.php     # Plugin main file
├── composer.json               # Dependencias PHP
├── package.json                # Dependencias JS (si se agregan)
├── README.md                   # Documentación principal
├── CHANGELOG.md                # Historial de cambios
├── CONTRIBUTING.md             # Este archivo
└── LICENSE                     # Licencia GPL-2.0

```

### Agregar un nuevo módulo

1. Crea carpeta en `modules/nombre_modulo/`
2. Implementa clase principal: `class-nombre-cpt.php`
3. Registra capabilities en `class-roles-manager.php`
4. Agrega inicialización en `aura-business-suite.php`
5. Crea dashboard si necesario: `class-nombre-dashboard.php`
6. Agrega assets CSS/JS específicos si necesario
7. Documenta en README.md

## 🧪 Testing (En Desarrollo)

Estamos trabajando en implementar tests automatizados:

```bash
# Tests unitarios (próximamente)
composer test

# Tests de integración (próximamente)
composer test:integration
```

Por ahora, testing manual es requerido:

1. Activar plugin en WordPress limpio
2. Probar cada capability con diferentes usuarios
3. Verificar que no haya errores PHP en debug.log
4. Comprobar consola del navegador para errores JS
5. Probar en navegadores: Chrome, Firefox, Safari

## 📞 Obtener Ayuda

- 💬 **Discusiones**: Usa [GitHub Discussions](https://github.com/yourusername/aura-business-suite/discussions)
- 📧 **Email**: dev@aurabusiness.com
- 📖 **Docs**: https://docs.aurabusiness.com

## 🎖️ Reconocimientos

Todos los contribuidores serán reconocidos en:
- README.md - Sección "Autores"
- Releases notes correspondientes
- CONTRIBUTORS.md (por crear)

---

**¡Gracias por hacer Aura Business Suite mejor! 💙**

Tu tiempo y esfuerzo son muy apreciados por toda la comunidad.

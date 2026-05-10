# Flujo QR de Vehículos y Rol Base en CarTracker

## Objetivo de este documento
Este documento resume cómo funciona actualmente el registro de salida/entrada por QR desde la tabla de vehículos en `vehicles.php`, cómo se configura el usuario con `Rol Base`, qué problemas se encontraron y qué correcciones se aplicaron para que el QR sirva en escenario real (impreso en guantera).

## 1) Arquitectura del flujo QR

### 1.1 Generación y almacenamiento del QR por vehículo
- La tokenización del QR se gestiona en `VehicleManager::generateQRToken()`.
- Se genera un token aleatorio seguro de 32 chars hex (`random_bytes(16)` + `bin2hex`) y se guarda en cada vehículo:
  - `qr_token`
  - `qr_generated_at`
- URL de acceso QR:
  - `{BASE_URL}/driver-checkout.php?v={vehicle_id}&token={qr_token}`

Archivos clave:
- `includes/VehicleManager.php`
- `api/vehicles.php` (`generate_qr`, `get_qr_info`, `invalidate_qr`)
- `includes/QRGenerator.php`

### 1.2 Cómo se muestra en la tabla de vehículos
- En `vehicles.php`, el botón QR abre modal con `openQRModal(vehicleId, ...)`.
- El modal permite:
  - visualizar QR
  - descargar
  - imprimir
  - abrir URL
  - copiar URL

### 1.3 Corrección aplicada: estabilidad del QR impreso
Problema detectado:
- Cada vez que se abría el modal QR, se llamaba `generate_qr`, regenerando token y dejando inválidos QRs impresos anteriormente.

Corrección aplicada:
- `openQRModal()` ahora consulta primero `get_qr_info`.
- Solo genera (`generate_qr`) si el vehículo no tiene QR.
- Resultado: el QR impreso permanece estable hasta invalidación/re-generación explícita.

Archivo corregido:
- `vehicles.php`

---

## 2) Flujo de acceso móvil por QR (`driver-checkout.php`)

### 2.1 Validación inicial
Al entrar con URL QR:
- Lee `v` y `token`.
- Busca vehículo.
- Compara token contra `vehicle.qr_token`.
- Si no coincide: error “QR inválido o expirado”.

### 2.2 Modo de autenticación
Antes:
- Si no había sesión y no había conductor asignado, solo permitía login normal.
- Un conductor no registrado no podía operar.

Ahora (corregido):
- Se mantiene login normal para usuarios registrados.
- Se agregó modo **invitado QR** para no registrados:
  - formulario con nombre
  - crea sesión temporal restringida al vehículo del QR
  - permite checkout/checkin sin crear usuario permanente

Archivo corregido:
- `driver-checkout.php`

### 2.3 Auto-login de conductor asignado
Problema detectado:
- En auto-login se seteaba `$_SESSION['role']` pero no `$_SESSION['user_role']`.
- Eso rompía validaciones de `Middleware` que usan `user_role`.

Corrección aplicada:
- Ahora setea ambos (`user_role` y `role`) para compatibilidad.
- También fija `selected_area_id` según el vehículo.

Archivo corregido:
- `driver-checkout.php`

---

## 3) Permisos y seguridad en modo invitado QR

## 3.1 Restricción estricta por vehículo
Se añadió soporte en `Middleware::getUserVehicleIds()` para sesión invitada QR:
- Si `qr_guest = true`, solo agrega `qr_vehicle_id`.
- No abre acceso a otras áreas/vehículos.

Archivo corregido:
- `includes/Middleware.php`

## 3.2 Check-in con invitado QR
Regla existente:
- Driver/Editor solo podían cerrar salidas creadas por sí mismos.

Ajuste aplicado:
- Se permite check-in en sesión `qr_guest` cuando la renta corresponde al `qr_vehicle_id` de esa sesión.
- Mantiene control por token QR + vehículo.

Archivo corregido:
- `api/rentals.php`

## 3.3 Control de visibilidad de estado en pantalla móvil
En `driver-checkout.php`, cuando hay renta activa:
- ahora modo invitado QR puede ver `checkin` directamente para el vehículo escaneado.

---

## 4) Cómo funciona “Rol Base” al crear usuarios

### 4.1 Concepto
`Rol Base` es global y distinto de roles por área.

Roles base válidos:
- `none` (sin rol base, valor interno vacío)
- `driver`
- `owner` (solo owner puede asignarlo)

Roles contextuales por área:
- `primary_admin`
- `admin`
- `editor`

Archivos clave:
- `users.php` (UI de creación/edición)
- `api/users.php` (validación y persistencia)
- `includes/Middleware.php` (matriz de permisos)
- `includes/AuthManager.php` (create/update usuario)

### 4.2 Validación en API
`api/users.php`:
- Convierte `none` a `''`.
- Valida rol base contra `['', 'driver', 'owner']`.
- Restringe asignación de `owner` a usuarios owner.
- Asignaciones de roles por área se procesan aparte.

### 4.3 Recomendación práctica para operación QR
Para conductores recurrentes:
- crear usuario con `Rol Base = Conductor`.
- asignar vehículo cuando aplique.

Para conductores ocasionales/no registrados:
- usar `Continuar sin cuenta` desde QR (sesión temporal invitada).

---

## 5) Escenario operativo recomendado (guantera)

1. Admin/Owner genera QR una sola vez por vehículo.
2. Imprime y pega en guantera.
3. Conductor escanea:
   - si tiene cuenta: inicia sesión y opera.
   - si no tiene cuenta: usa “Continuar sin cuenta”.
4. En salida: registra checkout.
5. En retorno: escanea mismo QR y registra checkin.

Nota:
- El QR no cambia al abrir modal; solo cambia cuando se invalida/regenera explícitamente desde API/UI.

---

## 6) Lista de verificación funcional

- [ ] Abrir QR de un vehículo dos veces y confirmar que URL/token no cambian.
- [ ] Escanear QR sin sesión y usar “Continuar sin cuenta”.
- [ ] Registrar salida con invitado.
- [ ] Registrar retorno del mismo vehículo con invitado.
- [ ] Escanear QR con usuario registrado y registrar salida/entrada.
- [ ] Verificar que invitado QR no pueda operar otros vehículos.

---

## 7) Cambios aplicados en este ajuste

- `vehicles.php`
  - `openQRModal()` consulta `get_qr_info` y solo genera QR si no existe.

- `driver-checkout.php`
  - modo invitado QR (`Continuar sin cuenta`) con sesión temporal.
  - corrección de auto-login para setear `user_role` correctamente.
  - permisos de flujo QR ajustados para operación real.

- `includes/Middleware.php`
  - sesión invitada QR obtiene acceso restringido al `qr_vehicle_id`.

- `api/rentals.php`
  - excepción controlada para check-in en sesión invitada QR del mismo vehículo.

---

## 8) Observaciones de seguridad

- El acceso invitado no crea usuario persistente ni otorga permisos administrativos.
- El alcance queda limitado al vehículo del QR escaneado.
- Si se sospecha filtración, invalidar QR del vehículo y generar uno nuevo.
- Recomendado: revisar periódicamente rentas creadas por IDs `qr_guest_*` para auditoría operativa.

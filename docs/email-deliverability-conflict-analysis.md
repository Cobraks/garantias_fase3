# Análisis de riesgo de entregabilidad (Spamhaus/DBL) en el plugin

## Contexto
Este documento resume **posibles focos de conflicto** en la capa de formularios/correos del plugin que podrían contribuir a problemas de reputación/entregabilidad (p. ej. rechazos en Outlook/Hotmail), sin cambiar código de producción.

## Hallazgos técnicos en el plugin

### 1) Endpoints de registro públicos (potencial abuso para generación de correos)
- El registro REST está expuesto en namespace público y con `permission_callback => '__return_true'` para:
  - `/register/check-email`
  - `/register`
  - `/register/verify`
  - `/register/resend`
  - `/register/verification`
- Aunque el alta valida nonce (`go360_register_nonce`), no se observa captcha/hCaptcha/reCAPTCHA ni un rate-limit por IP en el endpoint de creación de cuenta.
- Riesgo: automatización de altas/verificaciones para disparar correo transaccional en volumen (a usuarios y/o admins), deteriorando reputación.

### 2) Envío con múltiples destinatarios ocultos (BCC)
- El sistema admite listas dinámicas de destinatarios y copia oculta (`bcc`) desde ajustes.
- Se usa `Bcc:` real en cabeceras de correo.
- Riesgo: si la lista es amplia o contiene direcciones inactivas/atrapa-spam, suben rebotes y señales negativas.

### 3) Resolución del remitente (`From`) dependiente de ajustes/filtros
- El `From` se construye con `EmailSettings::resolveSenderEmail(...)`, que parte de `admin_email` o fallback y permite override por filtro.
- En parte del flujo “professional” el fallback de remitente puede provenir del `reply_to`.
- Riesgo: si `From` queda en un dominio no alineado con SPF/DKIM/DMARC reales del servidor de salida, Outlook/Hotmail penaliza o bloquea.

### 4) Alto volumen potencial por eventos de negocio
- Hay varios disparadores de email (registro, verificación, bienvenida, cambios de estado de garantía, SEPA, reseteo de contraseña, etc.).
- Si hay procesos externos que actualizan estados en masa o reintentos, podría elevarse el throughput de correo transaccional.

### 5) Adjuntos en algunos envíos
- Se adjuntan documentos (p. ej. proforma/SEPA) en ciertos correos.
- Riesgo secundario: adjuntos + volumen elevan score antispam en algunos proveedores.

## Qué revisar primero (sin programar)

### A. Configuración y reputación del dominio de envío
1. Confirmar **SPF** incluye el MTA real que sale a Internet.
2. Confirmar **DKIM** firma activa para ese mismo dominio de `From`.
3. Confirmar **DMARC** (idealmente alineado con SPF/DKIM).
4. Comprobar **PTR/rDNS** de la IP saliente y HELO/EHLO coherentes.
5. Verificar si el dominio o subdominio de envío aparece en DBL/otras listas.

### B. Revisión de ajustes funcionales del plugin
1. Auditar campos de ACF/ajustes de notificaciones:
   - `direcciones_correo`
   - `direccion_respuesta`
   - `admin_email`
2. Reducir BCC a mínimos imprescindibles y depurar direcciones inválidas.
3. Validar que `From` final use un buzón del propio dominio autenticado (no externos).

### C. Señales operativas
1. Medir tasa de rebote y complaints por tipo de correo (registro, garantía, SEPA, etc.).
2. Revisar picos horarios de envío y correlación con endpoints públicos de registro.
3. Confirmar que los correos a Outlook/Hotmail no contienen patrones de “bulk” accidental.

## Hipótesis más probable de conflicto (prioridad)
1. **Exposición de flujos públicos de registro sin anti-bot fuerte** + envío automático de correos.
2. **Desalineación de remitente (From) con autenticación del dominio/IP**.
3. **Uso intensivo de BCC con listas no saneadas**.

## Próximo paso recomendado
Hacer una auditoría conjunta de:
- logs SMTP/MTA (rechazos 550/5.7.x de Microsoft),
- configuración DNS de autenticación,
- y volumen real por endpoint de registro.

Con eso se puede separar si el problema es principalmente de infraestructura de correo, de abuso de formularios o de ambas cosas.

# Auditoría técnica exhaustiva del sistema de envío automático de correos

## 1) Resumen ejecutivo

El repositorio **sí tiene un núcleo de envío relativamente centralizado** (`EmailMessage` + `Mailer` + `EmailNotificationService`) para la mayoría de notificaciones de garantías y parte del registro. Sin embargo, hay **riesgos relevantes de entregabilidad y abuso** que pueden contribuir a incidentes con Microsoft/Hotmail/M365:

- Existen **múltiples flujos de disparo** (autosave REST, cron diferido de contrato, trigger de pago, cancelación, registro público y reset de contraseña) con riesgo de patrones de volumen "irregulares" en picos operativos.
- La identidad de envío depende de `admin_email` y filtros (`go360/email/sender_email`) sin una política rígida de dominio alineado por entorno.
- Hay uso de **BCC configurable** para admins, útil operativamente pero sensible para reputación/volumen si la lista crece.
- El endpoint de autosave de garantías permite edición con `is_user_logged_in()` y el endpoint público de registro no aplica captcha/rate-limit por IP en backend (sí hay controles de reenvío por usuario/token), lo que deja una superficie de abuso funcional.
- La trazabilidad existe (logs `email_sent`, `email_failed`, `email_skipped`) pero **no captura** metadatos SMTP/Message-ID/transport ni códigos de rechazo reales del MTA.

## 2) Mapa de flujos de correo detectados

### 2.1 Capa técnica base

- `src/Notifications/Email/EmailMessage.php`
  - Objeto de mensaje con sanitización de destinatarios (`sanitize_email`), subject limpiado (`wp_strip_all_tags`), soporte de `cc`, `bcc`, `reply_to`, adjuntos.
- `src/Notifications/Email/Mailer.php`
  - Wrapper de envío único con `wp_mail()`, inyección de `Cc/Bcc/Reply-To`, content-type HTML por defecto y normalización de adjuntos.
- `src/Notifications/Email/TemplateRenderer.php`
  - Renderiza plantillas en `templates/email/*.php`.
- `src/Notifications/Email/EmailSettings.php`
  - Resuelve `reply_to`, sender email por contexto (`admin`/`professional`) y firma HTML desde settings ACF.

### 2.2 Flujos y triggers reales

#### Flujo A — Contratación/activación/cancelación de garantías

1. **Trigger de negocio**:
   - `GuaranteeRestController::autosave()` detecta transición de estado y encola notificación (`_go360_pending_contract_notice`) + cron `go360/guarantee/dispatch_contract_notice`.
   - Puede disparar también evento `go360/guarantee/payment_recorded`.
2. **Dispatcher**:
   - `dispatch_contract_notice_internal()` ejecuta `do_action('go360/guarantee/contracted', ...)`.
3. **Servicio de email**:
   - `EmailNotificationService` escucha:
     - `go360/guarantee/contracted`
     - `go360/guarantee/payment_recorded`
     - `go360/guarantee/cancelled`
4. **Composición**:
   - `GuaranteeEmailDataFactory` construye payload de garantía.
   - `GuaranteeEmailBuilder` define subjects y template por tipo.
5. **Envío**:
   - `Mailer::send()` → `wp_mail(...)`.
6. **Log**:
   - `GuaranteeLogger::log(... 'email_sent'|'email_failed'|'email_skipped' ...)`.

#### Flujo B — Transferencia reportada por profesional

- `GuaranteeRestController` crea `EmailMessage` directamente (sin pasar por `EmailNotificationService`) para `transfer-reported-admin` y lo envía con `Mailer`.
- Usa el mismo esquema de destinatarios admin/BCC resuelto desde settings.

#### Flujo C — Registro público

- `RegisterRestController` expone endpoints públicos (`permission_callback => __return_true`) para alta, verificación y reenvío.
- `RegistrationService::register()` dispara:
  - `notify_admin()` (registro nuevo)
  - `notify_user()` (código de verificación)
- `RegistrationService::verify()` puede disparar `notify_welcome()`.
- `RegistrationService::resend()` reenvía `notify_user()` con límite por token/usuario (cooldown + ventana).

#### Flujo D — Recuperación/cambio de contraseña

- `PasswordResetMailer` se integra en filtros de WP:
  - `retrieve_password_notification_email` (customiza email reset)
  - `password_change_admin_email` (customiza aviso admin)
  - `password_reset` (envía confirmación al usuario con `wp_mail` directo)

## 3) Hallazgos críticos

1. **Superficie de abuso en endpoint de autosave de garantías (`can_edit` demasiado laxo)**
   - `GuaranteeRestController::can_edit()` devuelve solo `is_user_logged_in()`.
   - Consecuencia: cualquier usuario autenticado podría potencialmente invocar rutas de edición/acciones sensibles (según validaciones internas de cada callback), incluyendo flujos que terminan en disparadores de correo.
   - Riesgo: generación de eventos anómalos y volumen no esperado (spam-like interno).

2. **Endpoints públicos de registro sin control anti-bot/rate-limit por IP en backend**
   - `RegisterRestController` usa `__return_true` en create/verify/resend/check-email.
   - Sí hay nonce en `RegistrationService::validate_registration`, pero no hay captcha server-side ni throttling por IP/UA.
   - Riesgo: abuso de creación de cuentas/correos de verificación, desgaste reputacional y posibles señales de envío sospechoso.

3. **Identidad de remitente no fuertemente forzada (dependencia de `admin_email`/filtros)**
   - `EmailSettings::resolveSenderEmail()` permite sobrescribir vía filtro y fallback `admin_email`.
   - `PasswordResetMailer` también recurre a `admin_email` o `no-reply@<home_url_domain>`.
   - Riesgo: desalineación de dominio From con SMTP envelope/HELO en infra multi-dominio (escenario sensible para Microsoft).

4. **Trazabilidad insuficiente para incidentes SMTP reales**
   - Se registra éxito/fallo lógico de `wp_mail`, pero no se persiste Message-ID, respuesta SMTP, código DSN o metadatos del transporte.
   - Riesgo: ante incidentes DBL/Microsoft no hay evidencia fina para reconstruir rechazo por receptor/causa MTA.

## 4) Hallazgos importantes

1. **Sistema parcialmente centralizado pero no único**
   - Mayoría en `EmailNotificationService`, pero `GuaranteeRestController` y `PasswordResetMailer` también construyen/envían por su cuenta.
   - Esto dificulta imponer políticas globales de seguridad, throttling y observabilidad.

2. **Uso de BCC para destinatarios internos configurable desde settings**
   - Útil, pero si se amplía puede elevar volumen por evento y crear patrón de broadcast interno.

3. **Riesgo de doble trigger en transiciones complejas**
   - Hay mecanismos anti-duplicado por meta (`_go360_email_notified_*`) y chequeos de cron programado.
   - Aun así, la coexistencia de `go360/guarantee/contracted` + `go360/guarantee/payment_recorded` + llamada manual `/notify` + cron diferido requiere control más explícito de idempotencia por “evento semántico”.

4. **`can_edit` y `can_list` basados en login para rutas amplias**
   - No aplica principio de mínimo privilegio para varias rutas REST.

## 5) Hallazgos menores

1. **No se define Return-Path explícito en aplicación**
   - Se delega al MTA/plugin SMTP; correcto en muchos casos, pero para incidentes de alineación conviene política explícita de envelope sender en la capa SMTP.

2. **Logging vía `error_log` disperso**
   - Hay mensajes útiles, pero no estructurados ni correlacionados por request-id.

3. **Cabeceras `From`/`Reply-To` se construyen en varios puntos**
   - Mejor centralizar para evitar divergencias futuras.

## 6) Riesgos concretos de entregabilidad

- From variable por contexto/filtro, sin enforcement de dominio único de envío.
- BCC internos potencialmente amplios por evento de garantía.
- Picos derivados de transiciones de estado + cron + reintentos operativos.
- Falta de telemetría SMTP para aislar rechazos de Microsoft (SCL, SNDS/JMRP, DSN, etc.).

## 7) Riesgos concretos de seguridad/abuso

- Registro público sin captcha/rate-limit por IP.
- Permisos REST de edición demasiado generales (`is_user_logged_in`).
- Endpoints públicos de comprobación de email y estado de verificación favorecen enumeración básica de cuentas.

## 8) Riesgos concretos de duplicidad/volumen

- Aunque existe anti-duplicado por meta de notificación, los flujos de contrato/pago/notify manual pueden encadenarse.
- Envío administrativo en paralelo a múltiples TO/BCC por evento.
- Reenvío de verificación limitado por token, pero no por IP/global actor.

## 9) Recomendaciones priorizadas

### Prioridad alta

1. **Blindar permisos REST de edición de garantías**
   - Reemplazar `can_edit()` por chequeo de capacidad/propiedad (similar a `can_view` + reglas de rol).
2. **Añadir rate limiting + captcha server-side en `go/public/v1/register*`**
   - Limitar por IP, token, user-agent, ventana temporal y acción.
3. **Forzar identidad única de remitente por entorno**
   - Unificar sender (From + envelope sender en SMTP plugin/MTA) con dominio transaccional único.
4. **Centralizar TODO envío en un único servicio**
   - Mover envío de `transfer-reported-admin` y `PasswordResetMailer` a una capa común con políticas uniformes.
5. **Registrar telemetría de transporte**
   - Hook de `wp_mail_failed` + captura de Message-ID + correlación por event-id.

### Prioridad media

1. **Idempotencia fuerte por clave de evento**
   - `event + guarantee_id + state_transition + time_bucket`.
2. **Control de volumen por tipo de evento**
   - Throttle por garantía, por vendedor y por acción administrativa.
3. **Revisión de BCC internos**
   - Consolidar en buzón único operativo cuando no sea estrictamente necesario el multi-destino.

### Prioridad baja

1. **Estandarizar cabeceras en un header builder único**.
2. **Añadir correlation-id en logs de correo**.
3. **Panel técnico de auditoría de email (últimos envíos/errores/reintentos)**.

## 10) Lista de archivos a corregir primero

1. `src/Rest/GuaranteeRestController.php`
2. `src/Rest/RegisterRestController.php`
3. `src/Notifications/Email/EmailSettings.php`
4. `src/Notifications/Email/EmailNotificationService.php`
5. `src/Auth/PasswordResetMailer.php`
6. `src/Register/RegistrationService.php`
7. `src/Notifications/Email/Mailer.php`
8. `src/GuaranteeLogger.php`

---

## Anexo A — Inventario solicitado (funciones, hooks, cron, endpoints)

### Funciones/clases que envían correo

- `src/Notifications/Email/Mailer.php`
  - `Mailer::send(EmailMessage $message): bool`
  - Usa `wp_mail`.
- `src/Auth/PasswordResetMailer.php`
  - `send_user_password_change_email(WP_User $user, string $new_password): void`
  - Usa `wp_mail` directo.
- `src/Notifications/Email/EmailNotificationService.php`
  - `handle_contracted`, `handle_payment_recorded`, `handle_cancelled`, `dispatch`.
- `src/Register/RegistrationService.php`
  - `notify_admin`, `notify_user`, `notify_welcome`.
- `src/Rest/GuaranteeRestController.php`
  - Bloque transferencia reportada (compone `EmailMessage` + `Mailer::send`).

### Hooks de WordPress implicados

- `go360/guarantee/contracted`
- `go360/guarantee/payment_recorded`
- `go360/guarantee/cancelled`
- `go360/email/dispatched`
- `retrieve_password_notification_email`
- `password_change_admin_email`
- `password_reset`
- Filtros de settings:
  - `go360/email/sender_email`
  - `go360/email/reply_to`
  - `go360/email/signature_html`
  - `go360/email/admin_recipients`
  - `go360/email/admin_bcc_recipients`
  - `go360/email/professional_recipients`

### Cron jobs / tareas programadas

- `go360/guarantee/dispatch_contract_notice` (single event, +5s)
- `go360/register/cleanup` (`twicedaily`)

### Endpoints REST que pueden disparar correo

- `go/public/v1/register` (alta + mails)
- `go/public/v1/register/resend` (reenvío código)
- `go/public/v1/register/verify` (welcome mail tras verificación)
- `go360/v1/guarantees/autosave` (encola notificaciones de contratación/pago)
- `go360/v1/guarantees/(?P<id>\d+)/notify` (dispatch manual)
- `go360/v1/guarantees/(?P<id>\d+)/report-transfer` (mail admin de transferencia reportada)
- Flujos de cancelación vía autosave con transición de estado.

### Integraciones SMTP/PHPMailer/API externa

- No se detecta uso directo de `PHPMailer` ni `mail()` nativo en el plugin.
- Se usa `wp_mail` (delegando a stack WP + plugin SMTP/MTA del entorno).

### Templates de email detectados

- `templates/email/*.php` (registro, reset, garantía, transferencia, sepa, parciales).

### Logging / trazabilidad

- `GuaranteeLogger` (mapea `email.sent`, `email.failed`, `email.skipped`).
- `ActivityLogger` en flujo de registro y verificación.
- `error_log` auxiliar en múltiples puntos de envío.


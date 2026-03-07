# Plan de auditoría de correo transaccional (producción)

> Objetivo: mantener la lógica funcional actual del plugin, reduciendo riesgos de picos de envío (bursts), duplicados y problemas de reputación del dominio.

## Contexto operativo
- Plugin: **Garantías Online - 360VO**.
- Entorno: WordPress + Postfix (Plesk).
- Incidencia observada: rechazos en Outlook/Hotmail por listado temporal en Spamhaus DBL (`550 5.7.1 ... S8001`).
- Hipótesis principal: burst de correos transaccionales en eventos encadenados (registro, contratación, documentación, avisos internos).

## Checklist de trabajo (seguimiento)

### Fase 1 — Inventario técnico de envíos (pendiente)
- [ ] Localizar todos los puntos de envío (`wp_mail()`, PHPMailer, wrappers propios, hooks relacionados).
- [ ] Mapear evento → plantilla → destinatarios (TO/CC/BCC) → remitente/cabeceras.
- [ ] Identificar si hay envíos síncronos dentro de peticiones HTTP críticas.

### Fase 2 — Detección de multiplicadores y bursts (pendiente)
- [ ] Revisar eventos que disparen múltiples correos por una única acción de usuario.
- [ ] Detectar duplicidades de destinatarios internos (cuentas repetidas o múltiples buzones similares).
- [ ] Identificar rutas de envío potencialmente concurrentes (REST/AJAX/callbacks).

### Fase 3 — Reintentos, duplicados e idempotencia (pendiente)
- [ ] Buscar loops o re-disparos por hooks encadenados.
- [ ] Verificar si existe protección de idempotencia por evento.
- [ ] Definir propuesta de clave idempotente (ej: `evento + entidad + hash_payload + time_bucket`).

### Fase 4 — SMTP, cabeceras y compatibilidad DKIM/SPF/DMARC (pendiente)
- [ ] Confirmar uso de `wp_mail()` y ausencia de `mail()` directo.
- [ ] Revisar manipulación de `From`, `Return-Path`, `Message-ID`, `Content-Type` y cabeceras personalizadas.
- [ ] Validar que los cambios propuestos no rompen firma DKIM ni política DMARC.

### Fase 5 — Seguridad de disparadores de email (pendiente)
- [ ] Auditar endpoints REST/AJAX que puedan iniciar envíos.
- [ ] Verificar nonces/capabilities/autorización por rol.
- [ ] Revisar sanitización/escape de datos inyectados en plantillas de correo.
- [ ] Revisar exposición de datos sensibles en logs o respuestas API.

### Fase 6 — Propuesta técnica de mitigación (pendiente)
- [ ] Diseñar rate limiting básico por acción + usuario/IP + ventana temporal.
- [ ] Diseñar control anti-duplicado con TTL corto.
- [ ] Evaluar cola asíncrona ligera (WP-Cron/Action Scheduler) para suavizar picos.
- [ ] Consolidar notificaciones internas a un buzón único cuando aplique.

### Fase 7 — Validación y despliegue seguro (pendiente)
- [ ] Plan de pruebas funcionales (sin pérdida de notificaciones críticas).
- [ ] Plan de observabilidad: métricas de envíos/minuto, errores SMTP, latencia.
- [ ] Plan de despliegue gradual y rollback.

## Checklist adicional — Hallazgos Wordfence / Plesk (pendiente)

### Núcleo WordPress (bajo riesgo reportado)
- [ ] **Cleartext `wp_signups.activation_key` (Low, Core)**: verificar si el sitio usa multisitio/alta en `wp_signups`; si no aplica, documentar excepción y mantener ignorado con justificación.
- [ ] **Blind SSRF no autenticado (Low, Core)**: aplicar mitigaciones de endurecimiento en superficie pública (XML-RPC/pingbacks y validación de callbacks externos) sin bloquear APIs REST necesarias del plugin.

### Endurecimiento Plesk (compatibilidad con el plugin)
- [ ] Bloquear acceso a archivos confidenciales (`.user.ini`, `.env`, backups, etc.) manteniendo funcionamiento de Wordfence WAF (`auto_prepend_file`).
- [ ] Revisar **Bloqueo XML-RPC** y **Desactivar pingbacks** (en principio compatibles con el plugin).
- [ ] Revisar **Prohibir ejecución PHP en `wp-content/uploads`** (en principio compatible con el plugin).
- [ ] Validar **Activar protección de bots** para no interferir con endpoints REST de registro y garantía.
- [ ] Revisar **Bloquear análisis author** para confirmar que no rompe navegación ni rutas personalizadas.

## Matriz rápida de compatibilidad (plugin 360VO)
- ✅ Seguro de activar normalmente:
  - Bloquear exploración de directorios.
  - Bloquear acceso a `wp-config.php`.
  - Bloquear acceso a archivos confidenciales (`.user.ini`, `.htaccess`, `.htpasswd`, etc.).
  - Prohibir ejecución PHP en `wp-includes`.
  - Prohibir ejecución PHP en `wp-content/uploads`.
  - Desactivar edición de archivos en WP-Admin.
  - Desactivar pingbacks.
- ⚠️ Activar con validación posterior (staging/ventana controlada):
  - Bloquear XML-RPC (normalmente no necesario para este plugin, pero validar si hay apps externas).
  - Protección de bots (puede afectar formularios/REST públicos del registro).
  - Bloquear análisis `author` (normalmente compatible, validar SEO/tema).

## Registro de avance
- **Estado actual:** planificación inicial creada y ampliada con checklist de hardening Wordfence/Plesk.
- **Próximo hito:** completar Fase 1 (inventario técnico completo de envíos) + validación de compatibilidad de medidas de hardening en staging.
- **Responsable sugerido:** equipo backend/plugin + sysadmin Plesk.
- **Última actualización:** 2026-03-05.

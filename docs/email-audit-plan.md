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

## Registro de avance
- **Estado actual:** planificación inicial creada.
- **Próximo hito:** completar Fase 1 (inventario técnico completo de envíos).
- **Responsable sugerido:** equipo backend/plugin.
- **Última actualización:** 2026-03-05.

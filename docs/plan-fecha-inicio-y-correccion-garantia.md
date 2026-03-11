# Plan técnico: fecha de inicio retroactiva (por rol) y flujo de “Corregir datos”

## 1) Diagnóstico actual

### Restricción de fecha (frontend)
- El campo `#fecha_inicio_garantia` se pinta como `type="date"` con `min="1980-01-01"` en plantilla, pero luego JS sobrescribe el mínimo al día actual para todos los usuarios.
- La lógica está en `setTodayForGarantia()` y ejecuta `fechaInput.min = today;` sin distinguir rol.

### Estado “Corregir datos”
- El botón existe en el panel de gestión de garantías, pero llega deshabilitado (`disabled` + clase visual de inactivo).
- En la gestión JS no hay manejo funcional para `data-management-action="certificate-error"`; hay acciones para factura, cancelación y edición en WordPress, pero no para corregir certificado.

### Flujo operativo actual en producción
- Para corregir una garantía activada/pendiente de pago, el equipo la pasa manualmente a `sin_finalizar` desde WordPress y luego se continúa el flujo desde “Continuar con la garantía”.
- Este flujo regenera PDF y dispara correo como si fuera nueva contratación (riesgo operativo si no se controla notificación/duplicados).

## 2) Riesgos y dependencias

1. **Legal / trazabilidad documental**
   - Si se regenera PDF, debe quedar histórico del documento anterior o de la acción de corrección (quién, cuándo, motivo).

2. **Integridad de estado**
   - Cambiar estados sin reglas claras puede abrir acciones no deseadas (factura/cancelación) o inconsistencias entre lista, detalle y backend.

3. **Notificaciones**
   - El flujo actual reenvía emails de contratación. Para correcciones internas puede no ser deseado.

4. **Control por rol**
   - La apertura de fechas retroactivas debe limitarse estrictamente a `admin`, `go_director_comercial`, `go_garantias`.

## 3) Propuesta de implementación por fases (segura)

## Fase A — Cambio mínimo y seguro (fecha retroactiva por rol)
Objetivo: habilitar solo la fecha de inicio anterior para roles internos autorizados, manteniendo bloqueo para particular/profesional/comercial.

### A1. Backend/plantilla: exponer permiso explícito al frontend
- En `templates/add-guarantee.php`, generar una bandera de capacidad (p. ej. `data-allow-retro-start="1|0"`) en el input `#fecha_inicio_garantia` o en un contenedor raíz.
- Regla inicial:
  - **Permitir retrofecha**: admin, director comercial, garantías.
  - **No permitir**: particular, profesional, comercial.

### A2. Frontend: respetar permiso al fijar `min`
- En `assets/js/modules/form-ui.js`, actualizar `setTodayForGarantia()`:
  - Si `allow-retro-start=1` → no fijar `min=today` (mantener mínimo técnico `1980-01-01` o configurable).
  - Si `allow-retro-start=0` → mantener comportamiento actual (`min=today`).

### A3. Defensa en backend (imprescindible)
- Añadir validación en endpoint de guardado/actualización de garantía (REST controller) para no depender solo de UI.
- Regla:
  - Si rol no autorizado y `fecha_inicio_garantia < hoy` → rechazo con error claro.
  - Si rol autorizado → permitir.

> Con A3 evitamos que un usuario fuerce peticiones HTTP y salte la restricción del navegador.

## Fase B — Activar “Corregir datos” sin romper producción
Objetivo: convertir el botón en flujo guiado, auditable y reversible.

### B1. UX: modal de confirmación tipo danger (como pediste)
- Reusar patrón `confirm-modal confirm-modal--danger is-open` ya existente.
- Contenido recomendado:
  - Aviso legal/operativo: “se va a regenerar certificado”.
  - Checkbox de confirmación obligatoria.
  - Campo “motivo de corrección” (select + texto libre “Otro”).
  - Opción (feature-flag inicial): “Notificar al cliente por email” (ON/OFF).

### B2. Acción backend de “iniciar corrección”
- Crear endpoint específico (ej. `POST /go/v1/guarantees/{id}/start-correction`) que:
  - valide permisos por rol,
  - registre auditoría (usuario, motivo, timestamp, estado previo),
  - pase el estado a `sin_finalizar` de forma controlada,
  - devuelva URL/UUID para continuar formulario.

### B3. Observabilidad y auditoría
- Guardar metadatos:
  - `correction_origin = management_hub`,
  - `correction_reason`,
  - `corrected_by`,
  - `corrected_at`,
  - `notify_client`.
- Añadir entrada en notas internas / log para trazabilidad.

## Fase C — Notificación opcional al finalizar
Objetivo: al completar la corrección, decidir si se envía email automáticamente.

- Si `notify_client = false`, suprimir envío automático en esta contratación derivada de corrección.
- Si `notify_client = true`, mantener email actual.
- Mantener por defecto la política actual hasta validar negocio (feature flag por entorno).

## 4) Plan de despliegue recomendado

1. **Release 1 (rápido y bajo riesgo):** Fase A completa.
2. **Release 2:** Fase B (botón Corregir datos + modal + endpoint + auditoría).
3. **Release 3:** Fase C (notificación opcional en cierre de corrección).

## 5) Plan de pruebas

### Matriz por rol (fecha inicio)
- Admin/director/garantías: permite seleccionar fecha anterior, guarda correctamente.
- Comercial/profesional/particular: no permite retrofecha (UI + backend).

### Regresión crítica
- Alta normal de garantía sin cambios de comportamiento.
- Continuar garantía en `sin_finalizar` funciona como hoy.
- Regeneración PDF tras corrección mantiene coherencia de datos y estados.

### Seguridad
- Intento por API de enviar retrofecha con rol no autorizado devuelve error 4xx.

## 6) Criterios de aceptación

1. Solo admin/director/garantías pueden guardar `fecha_inicio_garantia` anterior a hoy.
2. Usuarios no autorizados no pueden saltarse la restricción ni manipulando requests.
3. El botón “Corregir datos” inicia flujo guiado con confirmación y motivo.
4. Toda corrección queda auditada.
5. La política de notificación al cliente es explícita y controlable.

## 7) Estado de tareas (actualización 6.4.7)

- ✅ Popup de “Corregir datos” simplificado, sin enlace redundante y CTA actualizado a “Continuar formulario de garantía”.
- ✅ Opción operativa “No enviar correos” conectada al flujo de corrección.
- ✅ Si “No enviar correos” está activo, se omiten correos de cliente/profesional al finalizar la corrección.
- ✅ Se distingue en actividad y push cuándo es contratación nueva vs certificado regenerado.
- ⏳ Pendiente próximo sprint: sistema de favoritos en listado de garantías y filtro dedicado en la barra de búsqueda.

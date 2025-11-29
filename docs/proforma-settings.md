# Facturas proforma: control y orígenes de datos

## Gating actual (solo ajustes generales)
- **Activación global**: se lee `facturacion.factura_proforma.activar_proforma_general` vía REST (`/acf/v3/options/options`). Si es `false` o falta, no se genera ni se muestra la proforma.
- **Plantilla base obligatoria**: se exige que `factura_proforma.documentacion_base_proforma` exista en opciones para continuar.
- **Roles permitidos**: `factura_proforma.activar_para` define qué canales (profesional/particular/gestoría) pueden generar proforma. Si hay selección y el rol efectivo del vendedor no está incluido, se aborta.
- **Profesionales y método de pago**: para roles profesionales se cruza `factura_proforma.mostrar_a_profesionales` con el método de pago del usuario (ACF usuario → `gestion_pagos > gestion_sepa > estado_documentos.metodo_de_pago`). Solo continúa si transferencia/domiciliación coincide con los modos permitidos.
- **Presentación**: los checkboxes `factura_proforma.presentacion_notificaciones` gobiernan el subrayado del total, la visibilidad en documentos/pantalla de éxito y el futuro adjunto por correo. No hay overrides por usuario en esta versión.
- **IBAN**: se toma de `facturacion.datos_bancarios.iban_360vo` cuando es necesario renderizar el bloque de transferencia.

## Comportamiento en la UI
1. Autosave/finalización consultan las opciones ACF cacheadas; si el gating falla, no se lanza la generación ni se muestran enlaces de proforma.
2. Si el gating pasa, se generan firmas y PDFs con los flags de presentación activos; el subrayado del total se dibuja solo si `subrayar_total` está marcado.
3. La pantalla de éxito y la sección de documentos solo muestran el enlace de proforma cuando `mostrar_en_pantalla_exito`/`mostrar_en_documentos` están activos en las opciones globales.

## Notas
- Las respuestas ACF se cachean en memoria durante la sesión para evitar peticiones repetidas.
- Si un fetch de ACF falla se usan valores por defecto seguros (proforma permitida) para no bloquear la contratación, pero al recuperar las opciones se aplican todas las reglas anteriores.

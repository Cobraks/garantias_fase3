# Facturas proforma: control y orígenes de datos

## Qué se añadió
- **Gating centralizado**: antes de generar o mostrar la proforma se consulta ACF (opciones y usuario) por REST (`/acf/v3/options/options` y `/acf/v3/users/{id}`). Si el ajuste global `facturacion.factura_proforma.activar_proforma_general` es `false`, se aborta. El usuario debe tener `proforma.activar_proforma` activo (o sin desactivar) y su rol debe estar incluido en `activar_para`.
- **Profesionales y método de pago**: para roles profesionales se cruza `mostrar_a_profesionales` con el método de pago/estado SEPA del usuario (`gestion_pagos > gestion_sepa > estado_documentos.metodo_de_pago/estado_sepa`). Solo se genera cuando el modo permitido coincide (transferencia o domiciliación). Se normaliza el método para firmar la proforma.
- **Flags de presentación combinados**: los checkboxes de `presentacion_notificaciones` se fusionan (global + usuario) para decidir subrayado, presencia en pantalla de éxito/documentos y uso futuro en email. Los labels en pantalla respetan `mostrar_en_pantalla_exito` y el subrayado solo se dibuja cuando `subrayar_total` está activo.
- **IBAN desde ajustes**: el IBAN de transferencia se toma de `facturacion.datos_bancarios.iban_360vo` cuando está disponible, sin depender de binarios añadidos al repositorio.
- **Firmas deterministas**: la firma de la proforma ahora incluye los flags de presentación para evitar reutilizar PDFs con configuraciones distintas.

## Flujo de datos
1. Al disparar autosave/finalización se calcula el rol efectivo (canal de venta) y el ID del vendedor seleccionado.
2. Se resuelven ajustes globales y del usuario vía REST, se fusionan flags y se decide `shouldGenerateProforma`.
3. Si procede, se monta `proformaArgs` con método de pago normalizado, IBAN resultante y flags de presentación; en caso contrario no se lanza generación ni se muestran enlaces.
4. En la pantalla de éxito se oculta el link de proforma cuando `mostrar_en_pantalla_exito` es falso; el subrayado del total solo se dibuja si `subrayar_total` está activo.

## Notas
- Las peticiones ACF se cachean en memoria para evitar repeticiones durante la sesión.
- Si un fetch de ACF falla, el flujo no se rompe: se usan valores seguros por defecto para no bloquear la contratación.

# Historial de cambios

## 0.1.19 - 2025-08-16
- Panel de éxito renovado con cabecera degradada y aviso destacado.
- Documentación mostrada en tarjetas y detalles de transferencia en bloques copiables con aviso.

## 0.1.18 - 2025-08-16
- Rediseño del panel de éxito con enlaces de documentación sin encabezado.
- Bloque de transferencia modernizado con recordatorio integrado y tabla de datos copiables.

## 0.1.17 - 2025-08-16
- Mensaje de pago simplificado con aviso destacado.
- Documentación mostrada como botones alineados en una fila.
- Datos de transferencia con referencia, IBAN y cantidad copiables.

## 0.1.16 - 2025-08-16
- El formulario se expande a todo el ancho cuando desaparece el resumen.
- El botón "Contratar" muestra un spinner a la izquierda del texto.
- Pantalla de éxito con diseño mejorado e iconos PDF en la documentación.

## 0.1.15 - 2025-08-16
- Transición suave al finalizar la contratación: el resumen se desliza y el formulario se expande antes de mostrar la pantalla de éxito.
- Spinner de carga en el botón "Contratar".

## 0.1.14 - 2025-08-16
- Corrige la aparición prematura de la pantalla de éxito; ahora solo se muestra tras pulsar "Contratar".

## 0.1.13 - 2025-08-16
- La pantalla de éxito solo aparece tras pulsar “Contratar” y las pestañas se ocultan con una animación.
- Estilos del panel de éxito trasladados a `nueva_garantia.css`.

## 0.1.12 - 2025-08-16
- Pantalla de éxito tras contratar con animación de confeti y enlaces a la documentación.
- Mensajes y recordatorios de pago según el método seleccionado, con botones de contacto.

## 0.1.11 - 2025-08-16
- Muestra la etiqueta del canal de venta en el listado de garantías.
- Nuevos estilos para las insignias "Pendiente de pago", "Sin finalizar" y "Expira pronto".

## 0.1.10 - 2025-08-16
- El listado de garantías incluye todos los estados de publicación, mostrando borradores como "Sin finalizar".

## 0.1.9 - 2025-08-16
- Evita la creación de borradores duplicados al modificar la matrícula durante una nueva garantía.

## 0.1.8 - 2025-08-16
- Bloqueo inmediato del botón “Siguiente” al comprobar matrículas duplicadas y reactivación al corregirlas.

## 0.1.7 - 2025-08-16
- Validación en tiempo real de matrículas duplicadas en el formulario de nueva garantía.

## 0.1.6 - 2025-08-16
- Evita el aviso de matrícula duplicada al crear una garantía nueva.
- Nota: queda pendiente mejorar la eficiencia, paginación y seguridad del sistema de logs.

## 0.1.5 - 2025-08-16
- Formateo de fechas al español y precios con separador de miles y coma decimal.

## 0.1.4 - 2025-08-15
- Exposición de pares valor/etiqueta del estado en la API REST y renderizado de etiquetas en listados y detalle.

## 0.1.3 - 2025-08-15
- La insignia de estado muestra la etiqueta traducida manteniendo el valor para clases CSS.

## 0.1.2 - 2025-08-15
- Sistema de logs de garantías y API REST para el feed del panel.

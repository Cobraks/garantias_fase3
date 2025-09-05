# Historial de cambios

## 0.1.32 - 2025-09-05
- Permitida la letra R en validaciones de matrícula y datos de ejemplo.
- Inserción de firma y sello del vendedor en el certificado PDF.

## 0.1.31 - 2025-09-05
- Relleno de certificados trasladado al navegador usando **pdf-lib**.
- Añadido endpoint REST para subir el PDF generado desde el cliente.
- Eliminadas dependencias FPDF, FPDI, FPDM y pdftk del plugin.

## 0.1.30 - 2025-09-05
- Migrado el rellenado de formularios PDF a **pdftk** con wrapper PHP y binario embebido.
- Reintroducidas las librerías FPDF y FPDI para futuros fallbacks y ampliaciones.
- Eliminado SetaPDF del repositorio.

## 0.1.29 - 2025-09-05
- Evita cargas múltiples de SetaPDF ejecutando el plugin tras `plugins_loaded`.
- Preparado el entorno para integrar la librería completa de SetaPDF.

## 0.1.28 - 2025-09-05
- Migración a SetaPDF-FormFiller para completar campos de formularios PDF.
- Eliminadas las dependencias FPDF, FPDI y FPDM.

## 0.1.27 - 2025-09-04
- Eliminada la librería pdftk y su wrapper; FPDM se utiliza ahora para rellenar los campos del certificado.

## 0.1.26 - 2025-09-04
- Sustituido pdftk por la librería FPDM para rellenar formularios PDF sin dependencias externas.

## 0.1.25 - 2025-09-04
- Registra en el log la ruta de pdftk y avisa cuando el binario falta; usa FPDI solo si pdftk falla.

## 0.1.24 - 2025-08-16
- Rellena la referencia "Garantía [matrícula]" y la cantidad final en la tabla de transferencia.
- Confeti renovado con caída superior y piezas en primer y segundo plano.

## 0.1.23 - 2025-08-16
- Referencia y cantidad rellenadas correctamente desde el formulario.
- Cabecera y subtítulo estilizados con encabezado de documentación.
- Confeti explosivo con animación central y piezas en primer y segundo plano.

## 0.1.22 - 2025-08-16
- Cabecera del panel de éxito simplificada con título y plan.
- Tabla de transferencia muestra referencia "Garantía [matrícula]" y cantidad final.
- Contenedor de pago alineado con el resto y confeti con caída mejorada.

## 0.1.21 - 2025-08-16
- Panel de éxito con tarjetas de documentación y subtítulo del plan.
- Tabla de transferencia con iconos de copiado en línea y confeti explosivo.

## 0.1.20 - 2025-08-16
- Mezcla de estilos para el panel de éxito: cabecera original con enlaces de documentos en fila.
- Instrucciones de transferencia en tarjeta con tabla y botones de copiado.

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

# Guía para preparar la plantilla de factura proforma en InDesign

Esta guía indica qué marcar en el PDF base para que PDF-Lib pueda detectar la fila de referencia y dibujar la tabla dinámica.

## 1. Mantén sólo la fila de encabezado en la tabla
- Deja en el PDF únicamente la fila de títulos (Concepto, Importe). No incluyas filas de datos estáticas.
- Asegúrate de que la altura de la fila de encabezado sea la misma que la de las futuras filas de datos para poder reutilizarla como referencia visual.

## 2. Coloca campos de formulario como anclas de posición
- En el panel **Botones y formularios**, añade dos campos de texto en la primera fila de datos (aunque no exista visualmente), uno para cada columna:
  - Nombre exacto `concepto_1` en la primera columna.
  - Nombre exacto `importe_1` en la segunda columna.
- Ajusta el tamaño de cada campo para que coincida con el área de texto de la celda. Estos campos servirán para leer `x`/`y`, ancho y alto desde PDF-Lib.
- Alinea verticalmente ambos campos con el borde inferior de la fila para que el `y` sirva como punto de partida al iterar filas (ej. `y_start`).

## 3. Define altura de fila y márgenes
- Mide y deja fijo el **alto de fila** que usaremos para desplazar `y` en cada iteración (`rowHeight`). Puedes añadir guías o un rectángulo oculto para documentar esa altura.
- Mantén márgenes laterales constantes en las dos columnas; el ancho de las columnas se infiere del ancho de los campos `concepto_1` y `importe_1`.

## 4. Configura tipografía y estilo
- Aplica en los campos la misma fuente, tamaño y color que quieras en las filas dinámicas. PDF-Lib extraerá estos atributos o reutilizará la fuente para que el texto insertado encaje visualmente.
- Si usas alineación derecha en `importe_1`, se facilitará replicar la alineación al calcular offsets.

## 5. Exporta el PDF conservando los campos
- Exporta como **PDF interactivo** para conservar los campos de formulario.
- Desactiva la aplanación de transparencias y la conversión de campos a contornos; necesitamos que los campos `concepto_1`/`importe_1` lleguen intactos al PDF base.

## 6. Opcional: marcas de control de página
- Si prevés más filas de las que caben en la página, deja una guía o anotación que indique el espacio utilizable para la tabla. Esto ayudará a decidir cuándo crear una nueva página desde código.

Con estos pasos, PDF-Lib podrá leer las coordenadas de `concepto_1` y `importe_1` para replicar filas (`y_start` y `rowHeight`) y dibujar cada par concepto/importe de forma alineada.

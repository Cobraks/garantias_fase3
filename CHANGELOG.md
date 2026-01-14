# Historial de cambios

## 6.3.0 - 2026-09-26
- Añadido el estado de verificación en la ficha de clientes con opciones para reenviar el código o validar manualmente.

## 6.2.6 - 2026-09-26
- Para admin, selector de profesional ahora no limitado a 25

## 6.2.5 - 2026-09-26
- FIX Casilla aceptar condiciones quedaba por debajo de anterior / Registrarme en registro en pantallas de 1360px de anchura y poca altura
- FIX position relative para el summary container del register-page (ahora aunque la pantalla tenga poca altura, no quedará la casilla de aceptar términos por debajo de los datos anteriores. )

## 6.0.0 - 2025-11-25
- Añadida fecha de contratación
- Añadido detalle de facturación (desglose de recargos y descuentos en la propia garantía)
- Añadida antigüedad en los datos del vehículo.
- Añadido color rojo para datos del vehículo y detalles técnicos que tengan suplementos
- Pequeños cambios de estilos y estructura
- Añadido panel de gestión de garantía
    - Añadido sistema de notas
    - Añadido Cancelar garantía, y estado “cancelada”
    - Añadido al panel botón para eliminar la garantía
    - Añadido botón para editar en panel de administración WordPress.
- Validación más agresiva para correo y teléfono del cliente
- Ofertas de precio fijo desde panel administración clientes
- Cambios en el panel de clientes
    - Ahora aparece el nombre de la empresa primero
    - Cuando no han subido imagen de perfil, ahora tenemos Avatar de iniciales.
- Registros: Añadida pág de verificación independiente para los casos en que los clientes hayan cerrado la pestaña, o no lo hayan hecho a tiempo.  Ahora pueden volver a solicitar el código de validación (tengo que repasar los estilos de esto, es posible que haya alguna inconsistencia).
- Formulario: Eliminado el molesto mensaje de Google Chrome pidiendo que si quiere guardar los datos de contacto (dirección, correo etc).

## 5.9.1
- Corregido mensaje de error cuando antigüedad supera / está por debajo del límite establecido
- Arreglados mensajes de error cuando no hay garantías disponibles:
    - Antigüedad mínima, antigüedad máxima, excede km / no llega a km.
    - Mensaje genérico si, por el motivo que sea, no hay garantías disponibles.
- Añadido indicador en clientes cuando un cliente se encuentra Online. Si un cliente interactúa con la página, aparece el estado Online. Si en 2 minutos no ha hecho nada, se quita el estado online.
- Cuando un particular o profesional crea una nueva garantía, podía tardar hasta 5 minutos en mostrarse para los admin / comeciales / directores comerciales / gestores de garantías. Ahora, en el momento en el que se crea, ya aparece tras actualizar
- Además, ahora aprece en tiempo real, no hace falta actualizar la página.
- Notificación cuando una garantía ha sido inicalizada
- Arreglado toast de notificaciones cuando el header está escondido.
- Corregido No se guardaba tracción ni matrícula en el certificado de las Exclusive.
- Corregido No aparecía tracción en el panel de detalles de la garantía


## Referencia interna: patrones sospechosos para validaciones de contacto

### Correos (parte local antes de @)
- Genéricos de ausencia/negación: `notiene`, `nohay`, `no_tiene`, `no.tiene`, `no-tiene`, `ninguno`, `ninguna`, `nada`, `ningún`, `sincorreo`, `sinemail`, `sin_mail`, `sin.mail`, `sin-mail`, `nocorreo`, `nomail`, `noemail`, `nope`, `na`, `vacío`, `vacio`, `unknown`, `desconocido`, `anonimo`, `anonymous`, `placeholder`, `sinuser`.
- Palabras de prueba: `prueba`, `test`, `testing`, `demo`, `ejemplo`, `example`, `dummy`, `fake`, `falso`, `temporal`, `tmp`, `tempa`, `temporalmail`.
- Teclado y patrones rápidos: `asdf`, `asdfg`, `asdfgh`, `qwerty`, `qwer`, `qwert`, `zxcv`, `zxcvb`, `zxcvbn`, `poiuy`, `lkjh`, `mnbv`, `123`, `1234`, `12345`, `123456`, `000`, `0000`, `111`, `999`, `abc`, `abcd`, `abcde`, `xyz`, `hola`, `hello`.
- Repetición del mismo carácter (3+): `aaa`, `aaaa`, `aaaaa`, `bbb`, `bbbb`, `cc`, `ccc`, `cccc`, `dddd`, `eeeee`, etc. (cualquier letra o dígito repetido tres veces o más).
- Apodos desechables: `trash`, `spam`, `basura`, `correo`, `email`, `ninguno123`, `usuario`, `user`, `usuario1`, `fakeuser`, `fake123`, `guest`, `visitante`, `invited`, `cliente`, `cliente1`, `cliente2`.
- Negaciones y sinónimos extra: `sinregistro`, `sincuenta`, `sindato`, `nodisponible`, `nodato`, `no.dato`, `no-dato`, `sin.dato`, `sin-dato`, `nodato`, `nodatos`, `nodata`, `sindata`, `noaplica`, `naoaplica`, `naaplica`.
- Variantes con signos: cualquier término anterior separado por puntos, guiones o guiones bajos (`no.tiene`, `no_tiene`, `no-tiene`, `sin.correo`, `sin_correo`, `sin-correo`, etc.).

### Teléfonos (España, 9 dígitos) sospechosos
- Repetición completa del mismo dígito: `000000000`, `111111111`, `222222222`, `333333333`, `444444444`, `555555555`, `666666666`, `777777777`, `888888888`, `999999999`.
- Prefijo válido + todos ceros: `600000000`, `700000000`, `800000000`, `900000000`, `611111111`, `711111111`, `811111111`, `911111111`, `622222222`, `722222222`, `633333333`, `733333333`, `644444444`, `744444444`, `655555555`, `755555555`, `666000000`, `676000000`, `696000000`.
- Repetición parcial o bloques: `600600600`, `700700700`, `900900900`, `666000666`, `777000777`, `888000888`, `999000999`, `123123123`, `321321321`, `612612612`, `612312312`, `616161616`.
- Secuencias ascendentes o descendentes: `601234567`, `612345678`, `622345678`, `698765432`, `987654321`, `678901234`, `654321000`, `789012345`, `890123456`.
- Prefijo + ceros + contador mínimo: `600000001` a `600000019`, `700000001` a `700000019`, `900000001` a `900000019`, y variaciones próximas (`600000010`, `600000011`, `600000012`, `600000013`, etc.).
- Prefijo + número obvio repetido: `699999990`, `699999991`, `699999992`, `699999999`, `900000001`, `900000002`, `900000003`.
- Otros patrones genéricos detectables: `612312312`, `611223344`, `622233344`, `633344455`, `644455566`, `655566677`, `666112233`, `666123123`, `666999666`, `777123456`, `888123123`, `999123123`.


## 4.2.0 - 2025-11-15
- Panel de detalles de clientes renovado con hint guiado, acciones rápidas y filtros sincronizados con la API.
- Nueva sección "Resumen de Clientes" con métricas Global/Mensual, spotlight de clientes y microtendencias.
- Añadidos contadores y filtros rápidos en el listado de clientes, preservando el panel durante las recargas.
- Notificación de "Nueva garantía iniciada" sin plan predeterminado para evitar confusiones.
- El flujo de selección de planes solo guarda borradores tras una elección explícita y se respetan estados previos.

## 4.1.0 - 2025-11-14
- Arreglado el flujo completo de recuperación de contraseña, incluyendo redirecciones seguras y manejo de enlaces caducados.
- Añadido el correo de confirmación tras actualizar la contraseña y unificado el estilo de las notificaciones HTML.
- Mejorados los estilos responsive y la experiencia de usuario de los formularios de acceso, restablecimiento y registro con enfoque *mobile first*.
- Refinado el asistente de registro: selección de canal conmutables, validaciones progresivas y navegación accesible en todas las resoluciones.
- Rediseñadas las plantillas de correo (logos, insignias, pies y espaciados) y actualizados los enlaces legales a aviso legal y política de privacidad.


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

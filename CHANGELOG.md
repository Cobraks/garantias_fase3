# Historial de cambios

## 0.1.19 - 2025-08-16
- Corrige el identificador del formulario para que "Contratar" cambie la garantía a "Pendiente de pago".

## 0.1.18 - 2025-08-16
- El botón "Contratar" envía el formulario y cambia la garantía a "Pendiente de pago".

## 0.1.17 - 2025-08-16
- Los estados personalizados aparecen en la caja de publicación y se elimina "Pendiente de revisión" del desplegable.

## 0.1.16 - 2025-08-16
- Los estados de las garantías se manejan como estados de publicación estándar de WordPress.
- Eliminado el meta "estado contratación" en favor del `post_status` y sincronización en la API.

## 0.1.15 - 2025-08-16
- Añade la capacidad `create_garantias` para restaurar el acceso completo de administradores y profesionales.
- La contratación cambia el estado a "Pendiente de pago" y los listados de WP-Admin muestran etiquetas traducidas.

## 0.1.14 - 2025-08-16
- Asegura las capacidades del CPT para administradores en cada carga, recuperando el menú de Garantías.
- Los listados muestran "Borrador" en lugar de "Draft" y las garantías contratadas pasan a "Pendiente de pago".

## 0.1.13 - 2025-08-16
- Registro de estados personalizados de garantía (pendiente de pago, activada, expirada y expira pronto).
- Definición de capacidades propias del CPT y asignación automática al rol administrador.
- Listado de garantías incluye borradores y estados internos; al contratar se marca como pendiente de pago.

## 0.1.12 - 2025-08-16
- Restaura las capacidades por defecto del tipo de contenido para que los administradores vean todas las garantías.
- Corrige la comprobación de permisos al publicar una garantía.

## 0.1.11 - 2025-08-16
- Permite publicar garantías al contratar mapeando capacidades personalizadas del CPT y validando el resultado.

## 0.1.10 - 2025-08-16
- Al pulsar "Contratar", la garantía pasa de borrador a publicada.
- Nota: revisar el sistema en próximas iteraciones, aunque actualmente funciona correctamente.
- Tareas pendientes:
  - Mostrar popup con información.
  - Gestión de las notificaciones por correo.
  - Gestión de la documentación.

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

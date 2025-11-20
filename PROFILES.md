# Perfiles profesionales recomendados

Esta lista agrupa los perfiles clave que suelen intervenir en un plugin como **Garantías Online 360** dentro de un equipo pequeño. Cada rol combina áreas complementarias para evitar solapamientos excesivos.

| Perfil | Responsabilidades principales | Ámbitos del plugin en los que participa |
| --- | --- | --- |
| Liderazgo técnico / Arquitectura WordPress | Define el ciclo de vida del plugin, los hooks globales (`init`, `rest_api_init`, activación/desactivación), el enrutado de vistas y la estructura de CPT/taxonomías. Documenta convenciones y prioriza deuda técnica. | Bootstrap en `garantias-online-360vo.php`, router de vistas, reglas de reescritura, registro de CPT y roles. |
| Backend WordPress (REST + datos) | Mantiene los controladores WP‑JSON (garantías, usuarios, cuentas, ofertas, actividad), modela la meta y los campos ACF, y aplica capacidades/roles en los endpoints. | Controladores REST en `src/`, carga condicional de ACF y metacampos, lógica de estados de garantía. |
| Frontend / UX (plantillas + assets) | Diseña y maqueta las vistas públicas (alta/listado/login), ajusta la UX del panel de garantías y gestiona el sistema de iconos y fuentes. Coordina CSS/JS compartidos y su accesibilidad. | Plantillas en `templates/`, assets en `assets/css` y `assets/js`, componentes SVG. |
| Gestión de assets y rendimiento | Automatiza la minificación y sincronización de CSS/JS, controla el versionado/encolado de assets y vela por que existan variantes minificadas listas para producción. | `AssetLoader`/`AssetCompiler`, copias `.min.*`, integración con flujos de build/deploy. |
| Seguridad y autenticación | Implementa login/registro (formulario y REST), refuerza nonces/capacidades, y asegura el acceso a documentos privados. Supervisa GDPR y export/borrado de datos. | Módulos de auth y registro en `src/`, gestión de roles, protección de directorios privados, hooks GDPR. |
| Notificaciones y documentos | Configura servicios de email/push, define eventos disparadores y se encarga de la generación de PDFs interactivos (cliente y servidor) y del almacenamiento seguro de la documentación. | Servicios de notificación, integración pdf-lib/DomPDF/TCPDF, directorio de docs privados. |
| DevOps / Infraestructura | Prepara entornos, gestiona despliegues de minificados, crea tablas auxiliares (actividad/push), cuida la configuración de caché y monitoriza salud del plugin en producción. | Scripts de activación, seeders, tablas de actividad, pipelines de build. |
| QA / Automatización | Mantiene y ejecuta pruebas (PHPUnit/WP‑CLI) y checks de lint; valida los flujos críticos de altas, listados y gestión. | Suite de tests en `tests/`, validaciones de linting y comprobaciones de plantillas. |

> En equipos muy pequeños, algunos roles pueden solaparse (p. ej., Backend WP asumiendo Seguridad/REST o Frontend también gestionando assets). El objetivo es asegurar que cada área tenga responsables claros sin fragmentar en exceso el equipo.

Consulta `CHANGELOG.md` para la cronología de mejoras.

1.0.0
- Añadida imagen de perfil

0.1.15
- Restaurado el menú de garantías para administradores y profesionales añadiendo `create_garantias`.
- Al contratar, la garantía pasa automáticamente a "Pendiente de pago".

0.1.16
- Los estados de las garantías se gestionan mediante los estados estándar de WordPress.
- Eliminado el campo ACF "Estado contratación" y sincronización de estados en la API.

0.1.14
- Restaura el menú de garantías para administradores y muestra las etiquetas de estado en español.

0.1.13
- Estados personalizados de garantía y capacidades propias del CPT.
- Las garantías contratadas pasan a "Pendiente de pago" y el listado muestra todos los estados.

0.1.12
- Restaura capacidades por defecto para que los administradores vean todas las garantías y corrige la verificación de permisos al publicar.

0.1.11
- Permite publicar garantías desde el formulario al asignar capacidades específicas al tipo de contenido.

0.1.10
- Al pulsar "Contratar", la garantía pasa de borrador a publicada.

0.1.9
- Evita la creación de borradores duplicados al modificar la matrícula durante una nueva garantía.

0.1.8
- Bloqueo inmediato del botón “Siguiente” mientras se comprueban matrículas duplicadas.

0.1.7
- Validación en tiempo real de matrículas duplicadas en la creación de garantías.

0.1.6
- Evita el aviso de matrícula duplicada al crear una garantía nueva.

0.1.5
- Fechas en formato español y precios con separador de miles.

0.1.4
- Se muestran las etiquetas de estado correctas en listados y detalle, manteniendo el valor para clases CSS.


##Estructura de archivos
garantias-online-360vo/
├── assets/
│   ├── css/
│   │   ├── style-garantias.css
│   │   └── style-garantias.min.css      ← generado por AssetCompiler
│   ├── js/
│   │   ├── script-garantias.js
│   │   └── script-garantias.min.js      ← generado por AssetCompiler
│   └── images/                          ← iconos estáticos, logos…
│
├── endpoints/                           ← entry-points públicos
│   ├── garantias_online.php            ← carga WP y delega a Router
│   └── auth.php                         ← login / registro custom
│
├── includes/                            ← autoloader y bootstrap
│   └── class-autoloader.php            ← PSR-4 loader para `src/`
│
├── src/                                 ← código namespaced (PSR-4)
│   ├── Admin/
│   │   └── SettingsPage.php            ← página de opciones en WP-Admin
│   │
│   ├── REST/
│   │   └── GuaranteesController.php    ← rutas WP-JSON para CRUD
│   │
│   ├── AssetCompiler.php               ← minifica CSS/JS on-the-fly
│   ├── AssetLoader.php                 ← wp_enqueue y localize
│   ├── Router.php                      ← controla endpoints y rewrite rules
│   ├── Security.php                    ← nonces, capacidades y saneado masivo
│   ├── SvgIcons.php                    ← array de SVG + get_icon()
│   ├── TemplateLoader.php              ← carga template parts con override
│   ├── UserManager.php                 ← creación/búsqueda usuarios y roles
│   ├── GuaranteeCPT.php                ← registro del CPT “garantia”
│   ├── GuaranteeMeta.php               ← definición y guardado de custom fields
│   ├── Email.php                       ← envíos de mail y plantillas HTML en `/templates/emails/`
│   ├── PDFGenerator.php                ← HTML→PDF con DomPDF/TCPDF
│   ├── GDPR.php                        ← hooks para export/borrado de datos (GDPR)
│   └── Settings.php                    ← helpers de Settings API (si prefieres separar de Admin)
│
├── templates/                           ← vistas front-end y emails
│   ├── parts/
│   │   ├── header.php                   ← cabecera limpia (sin wp_head)
│   │   └── footer.php                   ← pie limpio (sin wp_footer)
│   │
│   ├── add-guarantee.php                ← formulario multistep
│   ├── my-guarantees.php                ← listado, filtros y acciones
│   │
│   ├── emails/
│   │   ├── new-guarantee-buyer.php      ← plantilla correo comprador
│   │   └── new-guarantee-seller.php     ← plantilla correo vendedor
│   │
│   └── auth/
│       ├── login.php                    ← formulario login custom
│       └── register.php                 ← formulario registro custom
│
├── languages/
│   └── garantias-online-360vo.pot       ← plantilla de traducciones
│
├── tests/                               ← PHPUnit + WP-CLI tests
│   ├── test-AssetLoader.php
│   ├── test-GuaranteeCPT.php
│   └── …
│
├── uninstall.php                        ← limpia CPT, meta y tablas al desinstalar
├── readme.txt                           ← cabecera para WordPress.org
└── garantias-online-360vo.php          ← bootstrap: carga Autoloader y hook de init

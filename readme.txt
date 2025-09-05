Consulta `CHANGELOG.md` para la cronología de mejoras.

1.0.0
- Añadida imagen de perfil

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


## Preparación del PDF de la modalidad
1. Diseña en InDesign el formulario con los campos de texto `pdf_combustible`, `pdf_cp` y `pdf_nombre_apellidos`.
2. Exporta mediante **Archivo → Exportar → Adobe PDF (Interactivo)** e incluye "Formularios y medios".
3. Tras exportar, deslinealiza el archivo con `qpdf plantilla.pdf --qdf salida.pdf` y usa `salida.pdf` como plantilla.

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

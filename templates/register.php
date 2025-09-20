<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

$is_register_page = true;
TemplateLoader::load_part('header', compact('is_register_page'));
?>

<main class="register-page" style="view-transition-name: register">
    <div class="register-page__layout">
        <aside class="register-page__aside register-page__aside--primary">
            <div class="register-page__badge"><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></div>
            <h1 class="register-page__title"><?php esc_html_e('Crea tu cuenta profesional en Garantías Online', 'garantias-online-360vo'); ?></h1>
            <p class="register-page__lead">
                <?php esc_html_e('Activa tu panel para contratar, seguir y gestionar todas tus garantías desde un único lugar, con soporte experto y trazabilidad completa.', 'garantias-online-360vo'); ?>
            </p>
            <ul class="register-page__highlights">
                <li>
                    <span class="register-page__highlight-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e('Gestiona a tus clientes y contratos en tiempo real, con avisos proactivos en cada fase.', 'garantias-online-360vo'); ?></span>
                </li>
                <li>
                    <span class="register-page__highlight-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e('Centraliza comunicaciones, documentos y pagos en una misma plataforma.', 'garantias-online-360vo'); ?></span>
                </li>
                <li>
                    <span class="register-page__highlight-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e('Automatiza certificados con firma y sello y mantén informados a tus clientes.', 'garantias-online-360vo'); ?></span>
                </li>
            </ul>
            <p class="register-page__switch">
                <?php esc_html_e('¿Ya tienes cuenta?', 'garantias-online-360vo'); ?>
                <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>">
                    <?php esc_html_e('Inicia sesión aquí', 'garantias-online-360vo'); ?>
                </a>
            </p>
        </aside>

        <div class="register-page__content">
            <div class="register-page__card">
                <div class="tabs register-tabs" role="tablist" aria-label="<?php esc_attr_e('Progreso de registro', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <div class="connector connector-1"></div>
                        <div class="connector connector-2"></div>
                    </div>
                    <button type="button" class="tabs__link is-active" data-step-trigger="0">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Datos de registro', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Perfil y opciones', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="2">
                        <div class="tabs__circle">3</div>
                        <div class="tabs__title"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="form register-form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <fieldset class="form__tab-content form__tab-content--active" data-step="0" aria-label="<?php esc_attr_e('Datos de registro', 'garantias-online-360vo'); ?>">
                        <legend class="form__legend"><?php esc_html_e('Datos de registro', 'garantias-online-360vo'); ?></legend>

                        <section class="register-block">
                            <header class="register-block__header">
                                <h2 class="register-block__title"><?php esc_html_e('Datos básicos', 'garantias-online-360vo'); ?></h2>
                                <p class="register-block__description"><?php esc_html_e('Canal de venta: ¿Eres un particular, un profesional o una gestoría?', 'garantias-online-360vo'); ?></p>
                            </header>
                            <div class="form__wrapper-inputs register-block__grid">
                                <div class="form__input-container">
                                    <select id="register_channel" class="form__select" aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>" required data-channel-select>
                                        <option value="" disabled selected><?php esc_html_e('Selecciona canal de venta', 'garantias-online-360vo'); ?></option>
                                        <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                        <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                        <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Usa este campo para definir cómo trabajas con Garantías Online.', 'garantias-online-360vo'); ?></p>
                                </div>
                                <div class="form__input-container" data-channel-visible="professional,agency">
                                    <input id="register_business_name" class="form__input" type="text" placeholder=" " required autocomplete="organization" />
                                    <label for="register_business_name" class="form__placeholder"><?php esc_html_e('Nombre de la empresa o concesionario', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Esta información aparece en tus certificados y comunicaciones.', 'garantias-online-360vo'); ?></p>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_first_name" class="form__input" type="text" placeholder=" " required autocomplete="given-name" />
                                    <label for="register_first_name" class="form__placeholder"><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_last_name" class="form__input" type="text" placeholder=" " required autocomplete="family-name" />
                                    <label for="register_last_name" class="form__placeholder"><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_phone" class="form__input" type="tel" placeholder=" " required autocomplete="tel" />
                                    <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>
                        </section>

                        <section class="register-block">
                            <header class="register-block__header">
                                <h2 class="register-block__title"><?php esc_html_e('Datos de inicio de sesión', 'garantias-online-360vo'); ?></h2>
                                <p class="register-block__description"><?php esc_html_e('Correo electrónico: lo usarás para iniciar sesión y será el canal principal de notificaciones.', 'garantias-online-360vo'); ?></p>
                            </header>
                            <div class="register-hint">
                                <p><?php esc_html_e('Puedes elegir una dirección distinta para recibir alertas y recordatorios operativos.', 'garantias-online-360vo'); ?></p>
                            </div>
                            <div class="form__wrapper-inputs register-block__grid">
                                <div class="form__input-container">
                                    <input id="register_email" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_email_confirm" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email_confirm" class="form__placeholder"><?php esc_html_e('Repite el correo electrónico', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_notification_email" class="form__input" type="email" placeholder=" " autocomplete="email" />
                                    <label for="register_notification_email" class="form__placeholder"><?php esc_html_e('Correo para notificaciones (opcional)', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container form__input-container--password">
                                    <input id="register_password" class="form__input" type="password" placeholder=" " required autocomplete="new-password" minlength="8" />
                                    <label for="register_password" class="form__placeholder"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                                    <button
                                        type="button"
                                        class="register-form__toggle-password"
                                        data-password-toggle="register_password"
                                        data-show-text="<?php esc_attr_e('Mostrar', 'garantias-online-360vo'); ?>"
                                        data-hide-text="<?php esc_attr_e('Ocultar', 'garantias-online-360vo'); ?>"
                                    >
                                        <span data-password-toggle-label><?php esc_html_e('Mostrar', 'garantias-online-360vo'); ?></span>
                                    </button>
                                    <p class="form__supporting-text"><?php esc_html_e('Usa al menos 8 caracteres combinando letras, números y símbolos.', 'garantias-online-360vo'); ?></p>
                                </div>
                                <div class="form__input-container form__input-container--password">
                                    <input id="register_password_confirm" class="form__input" type="password" placeholder=" " required autocomplete="new-password" minlength="8" />
                                    <label for="register_password_confirm" class="form__placeholder"><?php esc_html_e('Repite la contraseña', 'garantias-online-360vo'); ?></label>
                                    <button
                                        type="button"
                                        class="register-form__toggle-password"
                                        data-password-toggle="register_password_confirm"
                                        data-show-text="<?php esc_attr_e('Mostrar', 'garantias-online-360vo'); ?>"
                                        data-hide-text="<?php esc_attr_e('Ocultar', 'garantias-online-360vo'); ?>"
                                    >
                                        <span data-password-toggle-label><?php esc_html_e('Mostrar', 'garantias-online-360vo'); ?></span>
                                    </button>
                                </div>
                            </div>

                            <div class="form__input-container form__input-container--acceptance register-block__consent">
                                <input id="register_terms" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            </div>
                        </section>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="1" aria-label="<?php esc_attr_e('Completa tu perfil', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Completa tu perfil', 'garantias-online-360vo'); ?></legend>
                        <p class="register-block__intro"><?php esc_html_e('Todos estos campos son opcionales. Puedes completarlos ahora o más tarde desde tu panel.', 'garantias-online-360vo'); ?></p>

                        <section class="register-section">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Perfil', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Personaliza tu imagen', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Añade una imagen para identificarte en contratos y comunicaciones.', 'garantias-online-360vo'); ?></p>
                            </header>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="register_profile_image" class="form__input" type="file" accept="image/*" data-summary-field="register_profile_image" />
                                    <label for="register_profile_image" class="form__placeholder"><?php esc_html_e('Imagen de perfil', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>
                        </section>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Operativa del taller', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Haz que las incidencias sean más ágiles', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Si gestionas reparaciones, añade los datos de tu taller para coordinar las averías sin retrasos.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_has_workshop" class="form__checkbox" type="checkbox" data-toggle-control="workshop" data-channel-only="professional" data-summary-field="register_has_workshop" data-summary-on="<?php esc_attr_e('Sí', 'garantias-online-360vo'); ?>" />
                                <label for="register_has_workshop" class="form__checkbox-label"><?php esc_html_e('Dispongo de taller asociado a mis garantías', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="register-hint">
                                <p><?php esc_html_e('Si tienes un taller, añade los datos ahora para que en caso de avería el procedimiento sea más fluido.', 'garantias-online-360vo'); ?></p>
                            </div>
                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="workshop" hidden>
                                <div class="form__input-container">
                                    <input id="register_workshop_name" class="form__input" type="text" placeholder=" " autocomplete="organization" />
                                    <label for="register_workshop_name" class="form__placeholder"><?php esc_html_e('Nombre del taller', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_contact" class="form__input" type="text" placeholder=" " autocomplete="name" />
                                    <label for="register_workshop_contact" class="form__placeholder"><?php esc_html_e('Persona de contacto', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_phone" class="form__input" type="tel" placeholder=" " autocomplete="tel" />
                                    <label for="register_workshop_phone" class="form__placeholder"><?php esc_html_e('Teléfono del taller', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_email" class="form__input" type="email" placeholder=" " autocomplete="email" />
                                    <label for="register_workshop_email" class="form__placeholder"><?php esc_html_e('Correo del taller', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>
                        </section>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('360VO', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Conecta tu web 360VO', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Disfruta de integraciones automáticas con tu stock y tus comunicaciones.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_has_360vo" class="form__checkbox" type="checkbox" data-toggle-control="360vo" data-channel-only="professional" data-summary-field="register_has_360vo" data-summary-on="<?php esc_attr_e('Sí', 'garantias-online-360vo'); ?>" />
                                <label for="register_has_360vo" class="form__checkbox-label"><?php esc_html_e('Dispongo de una web 360VO activa', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="register-hint register-hint--list">
                                <p><?php esc_html_e('Puedes beneficiarte de varias ventajas si conectas tu web 360VO:', 'garantias-online-360vo'); ?></p>
                                <ul>
                                    <li><?php esc_html_e('Gestión de las garantías desde tu propia plataforma.', 'garantias-online-360vo'); ?></li>
                                    <li><?php esc_html_e('Añadir la información del vehículo automáticamente.', 'garantias-online-360vo'); ?></li>
                                    <li><?php esc_html_e('Logos e imágenes automáticas en contratos y documentos.', 'garantias-online-360vo'); ?></li>
                                    <li><?php esc_html_e('Integración con tu sistema de notificaciones.', 'garantias-online-360vo'); ?></li>
                                </ul>
                            </div>
                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="360vo" hidden>
                                <div class="form__input-container">
                                    <input id="register_360vo_url" class="form__input" type="url" placeholder="https://" />
                                    <label for="register_360vo_url" class="form__placeholder"><?php esc_html_e('URL de tu web 360VO', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>
                        </section>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Documentación y pagos', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Prepara tu documentación desde el primer día', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Activa la firma automática y deja lista la domiciliación SEPA cuando te venga mejor.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="register-hint">
                                <p><?php esc_html_e('Los certificados se generan automáticamente con tu firma y sello, listos para enviarse al cliente.', 'garantias-online-360vo'); ?></p>
                                <p><?php esc_html_e('Si no lo activas ahora tendrás que descargar, firmar, sellar y volver a subir cada documento.', 'garantias-online-360vo'); ?></p>
                            </div>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_auto_signature" class="form__checkbox" type="checkbox" data-toggle-control="signature" data-channel-only="professional" data-summary-field="register_auto_signature" data-summary-on="<?php esc_attr_e('Activada', 'garantias-online-360vo'); ?>" />
                                <label for="register_auto_signature" class="form__checkbox-label"><?php esc_html_e('Añadir firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="signature" hidden>
                                <div class="form__input-container">
                                    <input id="register_signature" class="form__input" type="file" accept="image/*" data-summary-field="register_signature" />
                                    <label for="register_signature" class="form__placeholder"><?php esc_html_e('Imagen de firma', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_stamp" class="form__input" type="file" accept="image/*" data-summary-field="register_stamp" />
                                    <label for="register_stamp" class="form__placeholder"><?php esc_html_e('Imagen de sello', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>

                            <div class="register-hint">
                                <p><?php esc_html_e('Seleccionando domiciliación bancaria como método de pago tus garantías quedan activadas automáticamente.', 'garantias-online-360vo'); ?></p>
                                <p><?php esc_html_e('No tendrás que justificar transferencias ni preocuparte por los plazos de pago.', 'garantias-online-360vo'); ?></p>
                                <p><?php esc_html_e('Solo tienes que rellenarlo una vez. Puedes hacerlo ahora o más tarde desde tus ajustes.', 'garantias-online-360vo'); ?></p>
                            </div>

                            <div class="form__sub-fieldset">
                                <legend class="form__sub-legend"><?php esc_html_e('Datos del deudor', 'garantias-online-360vo'); ?></legend>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_sepa_name" class="form__input" type="text" placeholder=" " autocomplete="name" />
                                        <label for="register_sepa_name" class="form__placeholder"><?php esc_html_e('Nombre completo', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_address" class="form__input" type="text" placeholder=" " autocomplete="street-address" />
                                        <label for="register_sepa_address" class="form__placeholder"><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container form__input-container--corto">
                                        <input id="register_sepa_zip" class="form__input" type="text" placeholder=" " autocomplete="postal-code" />
                                        <label for="register_sepa_zip" class="form__placeholder"><?php esc_html_e('Código postal', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_city" class="form__input" type="text" placeholder=" " autocomplete="address-level2" />
                                        <label for="register_sepa_city" class="form__placeholder"><?php esc_html_e('Población', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_state" class="form__input" type="text" placeholder=" " autocomplete="address-level1" />
                                        <label for="register_sepa_state" class="form__placeholder"><?php esc_html_e('Provincia', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_country" class="form__input" type="text" placeholder=" " autocomplete="country-name" />
                                        <label for="register_sepa_country" class="form__placeholder"><?php esc_html_e('País', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>

                            <div class="form__sub-fieldset">
                                <legend class="form__sub-legend"><?php esc_html_e('Cuenta bancaria', 'garantias-online-360vo'); ?></legend>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_sepa_swift" class="form__input" type="text" placeholder=" " autocomplete="off" />
                                        <label for="register_sepa_swift" class="form__placeholder"><?php esc_html_e('Swift / BIC', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_iban" class="form__input" type="text" placeholder=" " autocomplete="off" />
                                        <label for="register_sepa_iban" class="form__placeholder"><?php esc_html_e('Número de cuenta IBAN', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_city_date" class="form__input" type="text" placeholder="Madrid, <?php echo esc_attr(wp_date('j \d\e F \d\e Y')); ?>" data-preserve-disabled="true" disabled />
                                        <label for="register_sepa_city_date" class="form__placeholder"><?php esc_html_e('Lugar y fecha de firma', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_document" class="form__input" type="file" accept="application/pdf,image/*" data-summary-field="register_sepa_document" />
                                        <label for="register_sepa_document" class="form__placeholder"><?php esc_html_e('Documento SEPA firmado', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="register-section">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Comunicaciones', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Configura tus avisos', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Recibe recordatorios de vencimientos, contrataciones y documentación pendiente.', 'garantias-online-360vo'); ?></p>
                            </header>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_allow_notifications" class="form__checkbox" type="checkbox" data-summary-field="register_allow_notifications" data-summary-on="<?php esc_attr_e('Sí', 'garantias-online-360vo'); ?>" />
                                <label for="register_allow_notifications" class="form__checkbox-label"><?php esc_html_e('Permitir notificaciones por correo electrónico', 'garantias-online-360vo'); ?></label>
                            </div>
                        </section>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="2" aria-label="<?php esc_attr_e('Confirmación', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></legend>
                        <div class="register-summary">
                            <h2 class="register-summary__title"><?php esc_html_e('Revisa y confirma tus datos', 'garantias-online-360vo'); ?></h2>
                            <p class="register-summary__description"><?php esc_html_e('Comprueba que la información es correcta antes de solicitar el alta. Podrás completar los campos opcionales desde tu panel.', 'garantias-online-360vo'); ?></p>

                            <div class="register-summary__group">
                                <h3><?php esc_html_e('Datos de registro', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Canal de venta:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_channel"></span></li>
                                    <li data-summary-channel="professional"><strong><?php esc_html_e('Empresa o concesionario:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_business_name" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                    <li><strong><?php esc_html_e('Nombre y apellidos:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_first_name"></span> <span data-summary-field="register_last_name"></span></li>
                                    <li><strong><?php esc_html_e('Teléfono:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_phone"></span></li>
                                    <li><strong><?php esc_html_e('Correo de acceso:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_email"></span></li>
                                    <li><strong><?php esc_html_e('Correo para notificaciones:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_notification_email" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                </ul>
                            </div>

                            <div class="register-summary__group">
                                <h3><?php esc_html_e('Preferencias', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Imagen de perfil:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_profile_image" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                    <li data-summary-channel="professional"><strong><?php esc_html_e('Taller asociado:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_has_workshop" data-summary-empty-label="<?php esc_attr_e('No', 'garantias-online-360vo'); ?>"></span><span class="register-summary__detail" data-summary-field="register_workshop_name" data-summary-empty="true" data-summary-empty-label=""></span></li>
                                    <li data-summary-channel="professional"><strong><?php esc_html_e('Web 360VO:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_has_360vo" data-summary-empty-label="<?php esc_attr_e('No', 'garantias-online-360vo'); ?>"></span><span class="register-summary__detail" data-summary-field="register_360vo_url" data-summary-empty="true" data-summary-empty-label=""></span></li>
                                    <li><strong><?php esc_html_e('Notificaciones:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_allow_notifications" data-summary-empty-label="<?php esc_attr_e('No', 'garantias-online-360vo'); ?>"></span></li>
                                </ul>
                            </div>

                            <div class="register-summary__group" data-summary-channel="professional">
                                <h3><?php esc_html_e('Documentación y pagos', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Firma automática:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_auto_signature" data-summary-empty-label="<?php esc_attr_e('No', 'garantias-online-360vo'); ?>"></span></li>
                                    <li><strong><?php esc_html_e('Dirección:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_address" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                    <li><strong><?php esc_html_e('IBAN:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_iban" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                    <li><strong><?php esc_html_e('Documento SEPA:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_document" data-summary-empty="true" data-summary-empty-label="—"></span></li>
                                </ul>
                            </div>
                        </div>
                    </fieldset>
                </form>

                <div class="register-form__footer">
                    <p class="register-form__status" data-register-status></p>
                    <div class="nav-buttons">
                        <button type="button" class="btn btn-secondary" data-step-prev><?php esc_html_e('Anterior', 'garantias-online-360vo'); ?></button>
                        <button type="button" class="btn btn-primary" data-step-next>
                            <span data-step-next-label data-final-label="<?php esc_attr_e('Crear cuenta', 'garantias-online-360vo'); ?>"><?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <aside class="register-page__aside register-page__aside--secondary">
            <div class="register-side-card">
                <h2><?php esc_html_e('Tu registro en 3 pasos', 'garantias-online-360vo'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Completa tus datos de acceso y de empresa.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Configura tu perfil y deja lista la operativa del taller.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Revisa la información y solicita el alta.', 'garantias-online-360vo'); ?></li>
                </ol>
            </div>
            <div class="register-side-card">
                <h3><?php esc_html_e('¿Necesitas ayuda?', 'garantias-online-360vo'); ?></h3>
                <p><?php esc_html_e('Nuestro equipo te acompaña durante el proceso de alta y revisa tu documentación para que empieces a operar cuanto antes.', 'garantias-online-360vo'); ?></p>
                <p class="register-side-card__contact">
                    <strong><?php esc_html_e('Soporte 360VO', 'garantias-online-360vo'); ?></strong><br />
                    <a href="tel:+34910000000">+34 910 000 000</a><br />
                    <a href="mailto:soporte@360vo.es">soporte@360vo.es</a>
                </p>
            </div>
        </aside>
    </div>
</main>

<?php
TemplateLoader::load_part('footer', compact('is_register_page'));

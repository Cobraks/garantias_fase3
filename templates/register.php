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
        <aside class="register-page__aside">
            <div class="register-page__badge"><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></div>
            <h1 class="register-page__title"><?php esc_html_e('Crea tu cuenta profesional en Garantías Online', 'garantias-online-360vo'); ?></h1>
            <p class="register-page__lead">
                <?php esc_html_e('Activa tu panel para contratar, seguir y gestionar todas tus garantías desde un único lugar, con soporte experto y trazabilidad completa.', 'garantias-online-360vo'); ?>
            </p>
            <ul class="register-page__highlights">
                <li>
                    <span class="register-page__highlight-icon" aria-hidden="true"></span>
                    <span><?php esc_html_e('Lanza garantías en minutos y consulta su evolución en tiempo real.', 'garantias-online-360vo'); ?></span>
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

        <div class="register-page__content register-page__card">
            <div class="form-container register-form-container">
                <div class="tabs register-tabs" role="tablist" aria-label="<?php esc_attr_e('Progreso de registro', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <div class="connector connector-1"></div>
                        <div class="connector connector-2"></div>
                        <div class="connector connector-3"></div>
                    </div>
                    <button type="button" class="tabs__link active" data-step-trigger="0">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Acceso', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Actividad', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="2">
                        <div class="tabs__circle">3</div>
                        <div class="tabs__title"><?php esc_html_e('Opciones', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="3">
                        <div class="tabs__circle">4</div>
                        <div class="tabs__title"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="form register-form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <fieldset class="form__tab-content form__tab-content--active" data-step="0" aria-label="<?php esc_attr_e('Datos de acceso', 'garantias-online-360vo'); ?>">
                        <legend class="form__legend"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></legend>
                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Información básica', 'garantias-online-360vo'); ?></legend>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <select id="register_channel" class="form__select" aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>" required data-channel-select>
                                        <option value="" disabled selected><?php esc_html_e('Selecciona canal de venta', 'garantias-online-360vo'); ?></option>
                                        <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                        <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                        <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container" data-channel-visible="professional">
                                    <select id="register_company" class="form__select">
                                        <option value="" disabled selected><?php esc_html_e('Selecciona empresa o concesionario', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_company" class="form__placeholder form__placeholder--select"><?php esc_html_e('Empresa o concesionario', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Podrás completar esta información desde tu perfil más adelante.', 'garantias-online-360vo'); ?></p>
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
                                    <input id="register_email" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_phone" class="form__input" type="tel" placeholder=" " required autocomplete="tel" />
                                    <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container form__input-container--password">
                                    <input id="register_password" class="form__input" type="password" placeholder=" " required autocomplete="new-password" />
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
                            </div>
                        </fieldset>

                        <div class="form__input-container form__input-container--acceptance">
                            <input id="register_terms" class="form__checkbox" type="checkbox" required />
                            <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                        </div>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="1" aria-label="<?php esc_attr_e('Datos profesionales', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Datos profesionales', 'garantias-online-360vo'); ?></legend>
                        <div class="register-channel-intro" data-channel-empty>
                            <h3><?php esc_html_e('Selecciona un canal para continuar', 'garantias-online-360vo'); ?></h3>
                            <p><?php esc_html_e('Mostraremos los campos relevantes en función de tu actividad.', 'garantias-online-360vo'); ?></p>
                        </div>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Profesionales', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Personaliza la operativa de tu taller', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Activa la información clave para que cada garantía quede asociada a tu negocio.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="form__sub-fieldset">
                                <legend class="form__sub-legend"><?php esc_html_e('Taller', 'garantias-online-360vo'); ?></legend>
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_has_workshop" class="form__checkbox" type="checkbox" data-toggle-control="workshop" data-channel-only="professional" data-summary-field="register_has_workshop" data-summary-on="<?php esc_attr_e('Con taller asociado', 'garantias-online-360vo'); ?>" />
                                    <label for="register_has_workshop" class="form__checkbox-label"><?php esc_html_e('Gestiono un taller asociado a mis garantías', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__wrapper-inputs register-conditional" data-toggle-group="workshop">
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
                                        <label for="register_workshop_email" class="form__placeholder"><?php esc_html_e('Email del taller', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>

                            <div class="form__sub-fieldset">
                                <legend class="form__sub-legend"><?php esc_html_e('Presencia digital', 'garantias-online-360vo'); ?></legend>
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_has_360vo" class="form__checkbox" type="checkbox" data-toggle-control="360vo" data-channel-only="professional" data-summary-field="register_has_360vo" data-summary-on="<?php esc_attr_e('Cuenta con 360VO', 'garantias-online-360vo'); ?>" />
                                    <label for="register_has_360vo" class="form__checkbox-label"><?php esc_html_e('Dispongo de una web 360VO activa', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__wrapper-inputs register-conditional" data-toggle-group="360vo">
                                    <div class="form__input-container">
                                        <input id="register_360vo_url" class="form__input" type="url" placeholder="https://" />
                                        <label for="register_360vo_url" class="form__placeholder"><?php esc_html_e('URL de tu web 360VO', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="register-section" data-channel-section="individual,agency">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Particulares y gestorías', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Configura tu experiencia desde el panel', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Tras completar el registro podrás personalizar tus datos desde el área privada.', 'garantias-online-360vo'); ?></p>
                            </header>
                        </section>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="2" aria-label="<?php esc_attr_e('Opciones avanzadas', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Opciones avanzadas', 'garantias-online-360vo'); ?></legend>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Añade firma y sello a tus certificados', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Puedes activar esta opción ahora o completarla desde Ajustes cuando quieras.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="form__sub-fieldset">
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_auto_signature" class="form__checkbox" type="checkbox" data-toggle-control="signature" data-channel-only="professional" data-summary-field="register_auto_signature" data-summary-on="<?php esc_attr_e('Firma y sello automáticos', 'garantias-online-360vo'); ?>" />
                                    <label for="register_auto_signature" class="form__checkbox-label"><?php esc_html_e('Añadir firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                                </div>

                                <div class="form__wrapper-inputs register-conditional" data-toggle-group="signature">
                                    <div class="form__input-container">
                                        <input id="register_signature" class="form__input" type="file" accept="image/*" data-summary-field="register_signature" />
                                        <label for="register_signature" class="form__placeholder"><?php esc_html_e('Sube la imagen de tu firma', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_stamp" class="form__input" type="file" accept="image/*" data-summary-field="register_stamp" />
                                        <label for="register_stamp" class="form__placeholder"><?php esc_html_e('Sube la imagen de tu sello', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="register-section" data-channel-section="professional">
                            <header class="register-section__header">
                                <span class="register-section__badge"><?php esc_html_e('Pagos', 'garantias-online-360vo'); ?></span>
                                <h3 class="register-section__title"><?php esc_html_e('Prepara la domiciliación bancaria (SEPA)', 'garantias-online-360vo'); ?></h3>
                                <p class="register-section__description"><?php esc_html_e('Completar estos datos ahora facilitará la activación de pagos por transferencia.', 'garantias-online-360vo'); ?></p>
                            </header>

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
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="3" aria-label="<?php esc_attr_e('Confirmación', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></legend>
                        <div class="register-summary">
                            <h2 class="register-summary__title"><?php esc_html_e('Revisa y confirma tus datos', 'garantias-online-360vo'); ?></h2>
                            <p class="register-summary__description"><?php esc_html_e('Comprueba que la información es correcta antes de solicitar el alta. Podrás completar los campos opcionales desde tu panel.', 'garantias-online-360vo'); ?></p>

                            <div class="register-summary__group">
                                <h3><?php esc_html_e('Cuenta', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Canal de venta:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_channel"></span></li>
                                    <li><strong><?php esc_html_e('Nombre y apellidos:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_first_name"></span> <span data-summary-field="register_last_name"></span></li>
                                    <li><strong><?php esc_html_e('Correo electrónico:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_email"></span></li>
                                    <li><strong><?php esc_html_e('Teléfono:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_phone"></span></li>
                                </ul>
                            </div>

                            <div class="register-summary__group" data-summary-channel="professional">
                                <h3><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Concesionario:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_company" data-summary-empty="true"></span></li>
                                    <li><strong><?php esc_html_e('Taller:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_workshop_name" data-summary-empty="true"></span></li>
                                    <li><strong><?php esc_html_e('Web 360VO:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_360vo_url" data-summary-empty="true"></span></li>
                                    <li><strong><?php esc_html_e('Firma automática:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_auto_signature" data-summary-empty="true"></span></li>
                                </ul>
                            </div>

                            <div class="register-summary__group" data-summary-channel="professional">
                                <h3><?php esc_html_e('SEPA', 'garantias-online-360vo'); ?></h3>
                                <ul>
                                    <li><strong><?php esc_html_e('Dirección:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_address" data-summary-empty="true"></span></li>
                                    <li><strong><?php esc_html_e('IBAN:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_iban" data-summary-empty="true"></span></li>
                                    <li><strong><?php esc_html_e('Documento firmado:', 'garantias-online-360vo'); ?></strong> <span data-summary-field="register_sepa_document" data-summary-empty="true"></span></li>
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
    </div>
</main>

<?php
TemplateLoader::load_part('footer', compact('is_register_page'));

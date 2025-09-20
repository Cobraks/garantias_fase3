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
        <aside class="register-page__sidebar">
            <div class="register-card register-card--primary">
                <span class="register-card__badge"><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></span>
                <h1 class="register-card__title"><?php esc_html_e('Activa tu cuenta en Garantías Online', 'garantias-online-360vo'); ?></h1>
                <p class="register-card__description">
                    <?php esc_html_e('Gestiona garantías, comunicaciones y documentación desde un panel único, con soporte experto en cada paso.', 'garantias-online-360vo'); ?>
                </p>
                <ul class="register-card__list">
                    <li><?php esc_html_e('Centraliza contratos, pagos y avisos para tu equipo en tiempo real.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Ofrece a tus clientes certificados profesionales listos para firmar.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Controla cada garantía con trazabilidad completa y recordatorios automáticos.', 'garantias-online-360vo'); ?></li>
                </ul>
                <p class="register-card__switch">
                    <?php esc_html_e('¿Ya tienes cuenta?', 'garantias-online-360vo'); ?>
                    <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>">
                        <?php esc_html_e('Inicia sesión aquí', 'garantias-online-360vo'); ?>
                    </a>
                </p>
            </div>

            <div class="register-card register-card--info">
                <h2 class="register-card__subtitle"><?php esc_html_e('Documentación y pagos', 'garantias-online-360vo'); ?></h2>
                <div class="register-card__section">
                    <h3 class="register-card__section-title"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h3>
                    <p><?php esc_html_e('Añadir firma y sello automáticamente.', 'garantias-online-360vo'); ?></p>
                    <p><?php esc_html_e('Los certificados se generan automáticamente con tu firma y sello, por lo que están listos para enviarse al cliente y que proceda a firmarlos.', 'garantias-online-360vo'); ?></p>
                    <p><?php esc_html_e('Si no los añades ahora, tendrás que descargarlos, imprimirlos, firmarlos, sellarlos, escanearlos o fotografiarlos, mandarlos al cliente, que los firme y volver a subirlos a la plataforma.', 'garantias-online-360vo'); ?></p>
                </div>
                <div class="register-card__section">
                    <h3 class="register-card__section-title"><?php esc_html_e('Preparar domiciliación SEPA', 'garantias-online-360vo'); ?></h3>
                    <p><?php esc_html_e('Seleccionando la domiciliación bancaria como método de pago, tus garantías quedan activadas automáticamente.', 'garantias-online-360vo'); ?></p>
                </div>
                <div class="register-card__section">
                    <h3 class="register-card__section-title"><?php esc_html_e('Ventajas', 'garantias-online-360vo'); ?></h3>
                    <ul class="register-card__bullets">
                        <li><?php esc_html_e('No tienes que preocuparte de realizar la transferencia en menos de 48 horas ni de justificar el pago.', 'garantias-online-360vo'); ?></li>
                        <li><?php esc_html_e('Solo tienes que rellenarlo una vez. Puedes hacerlo ahora o más tarde desde los ajustes de tu perfil.', 'garantias-online-360vo'); ?></li>
                    </ul>
                </div>
            </div>

            <div class="register-card register-card--support">
                <h3 class="register-card__section-title"><?php esc_html_e('¿Necesitas ayuda?', 'garantias-online-360vo'); ?></h3>
                <p class="register-card__description"><?php esc_html_e('Nuestro equipo revisa tu documentación y te guía para que puedas empezar a operar cuanto antes.', 'garantias-online-360vo'); ?></p>
                <p class="register-card__contact">
                    <strong><?php esc_html_e('Soporte 360VO', 'garantias-online-360vo'); ?></strong><br />
                    <a href="tel:+34910000000">+34 910 000 000</a><br />
                    <a href="mailto:soporte@360vo.es">soporte@360vo.es</a>
                </p>
            </div>
        </aside>

        <div class="register-page__main">
            <div class="register-page__card form-container">
                <div class="tabs register-tabs" role="tablist" aria-label="<?php esc_attr_e('Progreso de registro', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <div class="connector connector-1"></div>
                    </div>
                    <button type="button" class="tabs__link is-active active" data-step-trigger="0">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="form register-form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <fieldset class="form__tab-content form__tab-content--active" data-step="0" aria-label="<?php esc_attr_e('Datos de acceso', 'garantias-online-360vo'); ?>">
                        <legend class="form__legend"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></legend>

                        <section class="register-section">
                            <header class="register-section__header">
                                <h2 class="register-section__title"><?php esc_html_e('Información de acceso', 'garantias-online-360vo'); ?></h2>
                                <p class="register-section__description"><?php esc_html_e('Configura el correo y la contraseña con los que entrarás en tu panel de Garantías Online.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="register-section__grid">
                                <div class="form__input-container">
                                    <input id="register_email" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_email_confirm" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email_confirm" class="form__placeholder"><?php esc_html_e('Confirmar correo electrónico', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>

                            <div class="register-section__grid">
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
                                    <p class="form__supporting-text"><?php esc_html_e('Utiliza al menos 8 caracteres combinando letras, números y símbolos.', 'garantias-online-360vo'); ?></p>
                                </div>
                                <div class="form__input-container form__input-container--password">
                                    <input id="register_password_confirm" class="form__input" type="password" placeholder=" " required autocomplete="new-password" minlength="8" />
                                    <label for="register_password_confirm" class="form__placeholder"><?php esc_html_e('Confirmar contraseña', 'garantias-online-360vo'); ?></label>
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
                        </section>

                        <div class="register-form__legal">
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_terms" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            </div>
                            <p class="register-form__hint"><?php esc_html_e('Podrás actualizar tus preferencias en cualquier momento desde tu perfil.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="1" aria-label="<?php esc_attr_e('Configuración de la cuenta', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></legend>

                        <section class="register-section">
                            <header class="register-section__header">
                                <h2 class="register-section__title"><?php esc_html_e('Tu perfil en Garantías Online', 'garantias-online-360vo'); ?></h2>
                                <p class="register-section__description"><?php esc_html_e('Completa la información principal de la cuenta. Podrás añadir más detalles una vez accedas a la plataforma.', 'garantias-online-360vo'); ?></p>
                            </header>

                            <div class="register-section__grid">
                                <div class="form__input-container">
                                    <select id="register_channel" class="form__select" aria-label="<?php esc_attr_e('Tipo de cuenta', 'garantias-online-360vo'); ?>" required data-channel-select>
                                        <option value="" disabled selected><?php esc_html_e('Selecciona el tipo de cuenta', 'garantias-online-360vo'); ?></option>
                                        <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                        <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                        <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Tipo de cuenta', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Indica si trabajas como profesional, particular o gestoría.', 'garantias-online-360vo'); ?></p>
                                </div>
                                <div class="form__input-container" data-channel-field="professional" data-channel-required="true" hidden>
                                    <input id="register_business_name" class="form__input" type="text" placeholder=" " required autocomplete="organization" />
                                    <label for="register_business_name" class="form__placeholder"><?php esc_html_e('Nombre de la empresa', 'garantias-online-360vo'); ?></label>
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
                                    <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_profile_image" class="form__input" type="file" accept="image/*" />
                                    <label for="register_profile_image" class="form__placeholder"><?php esc_html_e('Imagen de perfil (opcional)', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Se mostrará en certificados y comunicaciones con tus clientes.', 'garantias-online-360vo'); ?></p>
                                </div>
                            </div>
                        </section>
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

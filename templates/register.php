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
                <h1 class="register-card__title"><?php esc_html_e('Activa tu cuenta profesional en Garantías Online', 'garantias-online-360vo'); ?></h1>
                <p class="register-card__description">
                    <?php esc_html_e('Unifica la gestión de clientes, vehículos y certificados en un panel intuitivo diseñado para talleres, gestorías y profesionales del motor.', 'garantias-online-360vo'); ?>
                </p>
                <ul class="register-card__list">
                    <li><?php esc_html_e('Genera garantías listas para firmar con la imagen de tu negocio.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Controla vencimientos y avisos para tu equipo en tiempo real.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Ofrece a tus clientes una experiencia moderna, rápida y segura.', 'garantias-online-360vo'); ?></li>
                </ul>
                <p class="register-card__switch">
                    <?php esc_html_e('¿Ya tienes cuenta?', 'garantias-online-360vo'); ?>
                    <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>">
                        <?php esc_html_e('Inicia sesión', 'garantias-online-360vo'); ?>
                    </a>
                </p>
            </div>

            <div class="register-card register-card--support">
                <h2 class="register-card__subtitle"><?php esc_html_e('Estamos contigo en todo el proceso', 'garantias-online-360vo'); ?></h2>
                <p class="register-card__description">
                    <?php esc_html_e('Nuestro equipo revisará tu solicitud y te acompañará para que empieces a operar cuanto antes.', 'garantias-online-360vo'); ?>
                </p>
                <p class="register-card__contact">
                    <strong><?php esc_html_e('Soporte 360VO', 'garantias-online-360vo'); ?></strong><br />
                    <a href="tel:+34910000000">+34 910 000 000</a><br />
                    <a href="mailto:soporte@360vo.es">soporte@360vo.es</a>
                </p>
            </div>
        </aside>

        <div class="register-page__main">
            <div class="register-page__card form-container">
                <div class="tabs" role="tablist" aria-label="<?php esc_attr_e('Pasos del registro', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <span class="tabs__track"></span>
                        <span class="tabs__progress" data-tabs-progress style="--progress-ratio: 0"></span>
                    </div>
                    <button type="button" class="tabs__link active" data-step-trigger="0" aria-current="step">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="register-form form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <section class="register-form__step is-active" data-step="0" aria-label="<?php esc_attr_e('Datos de acceso', 'garantias-online-360vo'); ?>">
                        <header class="register-form__step-header">
                            <h2><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></h2>
                            <p><?php esc_html_e('Define cómo vas a entrar en la plataforma. Podrás actualizar esta información en cualquier momento.', 'garantias-online-360vo'); ?></p>
                        </header>

                        <div class="register-form__grid">
                            <div class="form__input-container">
                                <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                                <input id="register_email" class="form__input" type="email" required autocomplete="email" />
                            </div>
                            <div class="form__input-container">
                                <label for="register_email_confirm" class="form__placeholder"><?php esc_html_e('Confirmar correo electrónico', 'garantias-online-360vo'); ?></label>
                                <input id="register_email_confirm" class="form__input" type="email" required autocomplete="email" />
                            </div>
                            <div class="form__input-container form__input-container--password">
                                <label for="register_password" class="form__placeholder"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                                <input id="register_password" class="form__input" type="password" required autocomplete="new-password" minlength="8" />
                                <button
                                    type="button"
                                    class="register-form__toggle-password"
                                    data-password-toggle="register_password"
                                    data-show-text="<?php esc_attr_e('Mostrar', 'garantias-online-360vo'); ?>"
                                    data-hide-text="<?php esc_attr_e('Ocultar', 'garantias-online-360vo'); ?>"
                                >
                                    <span data-password-toggle-label><?php esc_html_e('Mostrar', 'garantias-online-360vo'); ?></span>
                                </button>
                            </div>
                            <div class="form__input-container form__input-container--password">
                                <label for="register_password_confirm" class="form__placeholder"><?php esc_html_e('Confirmar contraseña', 'garantias-online-360vo'); ?></label>
                                <input id="register_password_confirm" class="form__input" type="password" required autocomplete="new-password" minlength="8" />
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

                        <div class="register-form__legal">
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_terms" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            </div>
                            <p class="register-form__hint"><?php esc_html_e('Necesitamos tu consentimiento para crear tu cuenta y enviarte comunicaciones sobre el servicio.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </section>

                    <section class="register-form__step" data-step="1" aria-label="<?php esc_attr_e('Configuración de la cuenta', 'garantias-online-360vo'); ?>" hidden>
                        <header class="register-form__step-header">
                            <h2><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></h2>
                            <p><?php esc_html_e('Personaliza tu perfil para que podamos ofrecerte la mejor experiencia desde el primer inicio de sesión.', 'garantias-online-360vo'); ?></p>
                        </header>

                        <div class="register-form__grid">
                            <div class="form__input-container register-form__input--full">
                                <label for="register_channel" class="form__placeholder"><?php esc_html_e('Tipo de cuenta', 'garantias-online-360vo'); ?></label>
                                <select id="register_channel" class="form__select" required data-channel-select>
                                    <option value="" disabled selected><?php esc_html_e('Selecciona una opción', 'garantias-online-360vo'); ?></option>
                                    <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                    <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                    <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                </select>
                            </div>

                            <div class="form__input-container register-form__input--full" data-channel-field="professional" data-channel-required="true" hidden>
                                <label for="register_company" class="form__placeholder"><?php esc_html_e('Nombre de la empresa', 'garantias-online-360vo'); ?></label>
                                <input id="register_company" class="form__input" type="text" autocomplete="organization" />
                            </div>

                            <div class="form__input-container">
                                <label for="register_first_name" class="form__placeholder"><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></label>
                                <input id="register_first_name" class="form__input" type="text" required autocomplete="given-name" />
                            </div>
                            <div class="form__input-container">
                                <label for="register_last_name" class="form__placeholder"><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></label>
                                <input id="register_last_name" class="form__input" type="text" required autocomplete="family-name" />
                            </div>
                            <div class="form__input-container">
                                <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                                <input id="register_phone" class="form__input" type="tel" autocomplete="tel" />
                            </div>
                            <div class="form__input-container register-form__input--full register-form__input--file">
                                <label for="register_profile_image" class="form__placeholder"><?php esc_html_e('Imagen de perfil', 'garantias-online-360vo'); ?></label>
                                <input id="register_profile_image" class="form__input" type="file" accept="image/*" />
                                <p class="form__supporting-text"><?php esc_html_e('Se mostrará en tus certificados, comunicaciones y área de clientes.', 'garantias-online-360vo'); ?></p>
                            </div>
                        </div>

                        <p class="register-form__hint register-form__hint--muted"><?php esc_html_e('Podrás completar más información y añadir tu firma digital desde los ajustes de tu perfil cuando finalices el registro.', 'garantias-online-360vo'); ?></p>
                    </section>
                </form>

                <footer class="register-form__footer">
                    <p class="register-form__status" data-register-status></p>
                    <div class="register-form__actions">
                        <button type="button" class="register-form__action register-form__action--previous" data-step-prev><?php esc_html_e('Anterior', 'garantias-online-360vo'); ?></button>
                        <button type="button" class="register-form__action register-form__action--next" data-step-next>
                            <span data-step-next-label data-final-label="<?php esc_attr_e('Registrar cuenta', 'garantias-online-360vo'); ?>"><?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?></span>
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</main>

<?php
TemplateLoader::load_part('footer', compact('is_register_page'));

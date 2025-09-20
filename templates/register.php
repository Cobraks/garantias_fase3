<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

$is_register_page = true;
$is_auth_page = true;
TemplateLoader::load_part('header', compact('is_register_page', 'is_auth_page'));
?>

<main class="register-page" style="view-transition-name: register">
    <div class="register-page__shell">
        <aside class="register-page__intro">
            <div class="register-page__brand">
                <kbd><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></kbd>
                <h1 class="register-page__title">
                    <?php esc_html_e('Crea tu cuenta profesional en Garantías Online', 'garantias-online-360vo'); ?>
                </h1>
            </div>
            <p class="register-page__subtitle">
                <?php esc_html_e('Activa tu panel para contratar, seguir y gestionar todas tus garantías desde un único lugar, con soporte experto y trazabilidad completa.', 'garantias-online-360vo'); ?>
            </p>
            <ul class="register-page__highlights">
                <li class="register-page__highlight">
                    <i aria-hidden="true"></i>
                    <span><?php esc_html_e('Lanza garantías en minutos y consulta su evolución en tiempo real.', 'garantias-online-360vo'); ?></span>
                </li>
                <li class="register-page__highlight">
                    <i aria-hidden="true"></i>
                    <span><?php esc_html_e('Centraliza comunicaciones, documentos y pagos en una misma plataforma.', 'garantias-online-360vo'); ?></span>
                </li>
                <li class="register-page__highlight">
                    <i aria-hidden="true"></i>
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

        <div class="register-page__card">
            <div class="register-stepper" role="tablist" aria-label="<?php esc_attr_e('Progreso de registro', 'garantias-online-360vo'); ?>">
                <button type="button" class="register-stepper__item is-active" data-step-trigger="0" aria-current="step">
                    <span class="register-stepper__bullet">1</span>
                    <span class="register-stepper__label"><?php esc_html_e('Acceso', 'garantias-online-360vo'); ?></span>
                </button>
                <button type="button" class="register-stepper__item" data-step-trigger="1" aria-current="false">
                    <span class="register-stepper__bullet">2</span>
                    <span class="register-stepper__label"><?php esc_html_e('Configuración', 'garantias-online-360vo'); ?></span>
                </button>
                <button type="button" class="register-stepper__item" data-step-trigger="2" aria-current="false">
                    <span class="register-stepper__bullet">3</span>
                    <span class="register-stepper__label"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></span>
                </button>
            </div>

            <form id="register-form" class="register-form" novalidate>
                <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                <fieldset class="register-step" data-step="0">
                    <legend><?php esc_html_e('Paso 1 · Datos de acceso', 'garantias-online-360vo'); ?></legend>

                    <div class="register-grid">
                        <div class="register-field">
                            <label for="register_channel" class="register-field__label"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></label>
                            <select id="register_channel" class="register-field__select" required data-channel-select>
                                <option value="" disabled selected><?php esc_html_e('Selecciona una opción', 'garantias-online-360vo'); ?></option>
                                <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                            </select>
                        </div>
                        <div class="register-field">
                            <label for="register_first_name" class="register-field__label"><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></label>
                            <input id="register_first_name" class="register-field__input" type="text" required autocomplete="given-name">
                        </div>
                        <div class="register-field">
                            <label for="register_last_name" class="register-field__label"><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></label>
                            <input id="register_last_name" class="register-field__input" type="text" required autocomplete="family-name">
                        </div>
                        <div class="register-field">
                            <label for="register_email" class="register-field__label"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                            <input id="register_email" class="register-field__input" type="email" required autocomplete="email">
                        </div>
                        <div class="register-field">
                            <label for="register_phone" class="register-field__label"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                            <input id="register_phone" class="register-field__input" type="tel" required autocomplete="tel">
                        </div>
                        <div class="register-field">
                            <label for="register_password" class="register-field__label"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                            <div class="register-field__password">
                                <input id="register_password" class="register-field__input" type="password" required autocomplete="new-password">
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
                            <p class="register-field__description"><?php esc_html_e('Usa al menos 8 caracteres combinando letras, números y símbolos.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </div>

                    <div class="register-field register-field--checkbox">
                        <input id="register_terms" type="checkbox" required>
                        <div>
                            <label for="register_terms"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            <span><?php esc_html_e('Podrás gestionar tus consentimientos desde el panel en cualquier momento.', 'garantias-online-360vo'); ?></span>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="register-step" data-step="1" hidden>
                    <legend><?php esc_html_e('Paso 2 · Configura tu actividad', 'garantias-online-360vo'); ?></legend>

                    <div class="register-step-intro" data-channel-empty>
                        <h3><?php esc_html_e('Selecciona un canal de venta para continuar', 'garantias-online-360vo'); ?></h3>
                        <p><?php esc_html_e('Mostraremos los campos relevantes para tu negocio en función del canal elegido.', 'garantias-online-360vo'); ?></p>
                    </div>

                    <div class="register-section" data-channel-section="professional">
                        <div class="register-section__header">
                            <span class="register-section__badge"><?php esc_html_e('Profesionales', 'garantias-online-360vo'); ?></span>
                            <h3 class="register-section__title"><?php esc_html_e('Personaliza la operativa de tu taller', 'garantias-online-360vo'); ?></h3>
                            <p class="register-section__description">
                                <?php esc_html_e('Activa la información clave para que cada garantía quede asociada a tu negocio desde el primer día.', 'garantias-online-360vo'); ?>
                            </p>
                        </div>

                        <div class="register-grid">
                            <div class="register-field register-field--checkbox">
                                <input id="register_has_workshop" type="checkbox" data-toggle-control="workshop" data-channel-only="professional">
                                <div>
                                    <label for="register_has_workshop"><?php esc_html_e('Gestiono un taller asociado a mis garantías', 'garantias-online-360vo'); ?></label>
                                    <span><?php esc_html_e('Añade los datos de contacto para que tus clientes sepan dónde dirigirse.', 'garantias-online-360vo'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-grid register-conditional" data-toggle-group="workshop">
                            <div class="register-field">
                                <label for="register_workshop_name" class="register-field__label"><?php esc_html_e('Nombre del taller', 'garantias-online-360vo'); ?></label>
                                <input id="register_workshop_name" class="register-field__input" type="text" autocomplete="organization">
                            </div>
                            <div class="register-field">
                                <label for="register_workshop_contact" class="register-field__label"><?php esc_html_e('Persona de contacto', 'garantias-online-360vo'); ?></label>
                                <input id="register_workshop_contact" class="register-field__input" type="text" autocomplete="name">
                            </div>
                            <div class="register-field">
                                <label for="register_workshop_phone" class="register-field__label"><?php esc_html_e('Teléfono del taller', 'garantias-online-360vo'); ?></label>
                                <input id="register_workshop_phone" class="register-field__input" type="tel" autocomplete="tel">
                            </div>
                            <div class="register-field">
                                <label for="register_workshop_email" class="register-field__label"><?php esc_html_e('Email del taller', 'garantias-online-360vo'); ?></label>
                                <input id="register_workshop_email" class="register-field__input" type="email" autocomplete="email">
                            </div>
                        </div>

                        <div class="register-grid">
                            <div class="register-field register-field--checkbox">
                                <input id="register_has_360vo" type="checkbox" data-toggle-control="360vo" data-channel-only="professional">
                                <div>
                                    <label for="register_has_360vo"><?php esc_html_e('Dispongo de una web 360VO activa', 'garantias-online-360vo'); ?></label>
                                    <span><?php esc_html_e('Conecta tu escaparate digital para mostrar las garantías disponibles.', 'garantias-online-360vo'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-grid register-conditional" data-toggle-group="360vo">
                            <div class="register-field">
                                <label for="register_360vo_url" class="register-field__label"><?php esc_html_e('URL de tu web 360VO', 'garantias-online-360vo'); ?></label>
                                <input id="register_360vo_url" class="register-field__input" type="url" placeholder="https://">
                            </div>
                        </div>

                        <div class="register-grid">
                            <div class="register-field register-field--checkbox">
                                <input id="register_auto_signature" type="checkbox" data-toggle-control="signature" data-channel-only="professional">
                                <div>
                                    <label for="register_auto_signature"><?php esc_html_e('Añadir firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                                    <span><?php esc_html_e('Sube tus archivos para incorporarlos a los certificados sin pasos adicionales.', 'garantias-online-360vo'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-grid register-conditional" data-toggle-group="signature">
                            <div class="register-field">
                                <label for="register_signature_file" class="register-field__label"><?php esc_html_e('Firma (PNG o JPG)', 'garantias-online-360vo'); ?></label>
                                <input id="register_signature_file" class="register-field__file" type="file" accept="image/png,image/jpeg">
                            </div>
                            <div class="register-field">
                                <label for="register_stamp_file" class="register-field__label"><?php esc_html_e('Sello (PNG o JPG)', 'garantias-online-360vo'); ?></label>
                                <input id="register_stamp_file" class="register-field__file" type="file" accept="image/png,image/jpeg">
                            </div>
                        </div>

                        <div class="register-grid">
                            <div class="register-field register-field--checkbox">
                                <input id="register_enable_sepa" type="checkbox" data-toggle-control="sepa" data-toggle-required="true" data-channel-only="professional">
                                <div>
                                    <label for="register_enable_sepa"><?php esc_html_e('Preparar domiciliación SEPA', 'garantias-online-360vo'); ?></label>
                                    <span><?php esc_html_e('Completa tus datos bancarios para automatizar los cobros por domiciliación.', 'garantias-online-360vo'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-sepa-grid register-conditional" data-toggle-group="sepa">
                            <div class="register-field">
                                <label for="register_sepa_address" class="register-field__label"><?php esc_html_e('Dirección completa', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_address" class="register-field__input" type="text" autocomplete="street-address">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_postal" class="register-field__label"><?php esc_html_e('Código postal', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_postal" class="register-field__input" type="text" autocomplete="postal-code">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_city" class="register-field__label"><?php esc_html_e('Población', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_city" class="register-field__input" type="text" autocomplete="address-level2">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_province" class="register-field__label"><?php esc_html_e('Provincia', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_province" class="register-field__input" type="text" autocomplete="address-level1">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_country" class="register-field__label"><?php esc_html_e('País', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_country" class="register-field__input" type="text" autocomplete="country-name">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_bic" class="register-field__label"><?php esc_html_e('Swift / BIC', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_bic" class="register-field__input" type="text">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_iban" class="register-field__label"><?php esc_html_e('Número de cuenta IBAN', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_iban" class="register-field__input" type="text" autocomplete="off">
                            </div>
                            <div class="register-field">
                                <label for="register_sepa_city_date" class="register-field__label"><?php esc_html_e('Lugar y fecha de firma', 'garantias-online-360vo'); ?></label>
                                <input id="register_sepa_city_date" class="register-field__input" type="text" readonly value="<?php echo esc_attr(sprintf('%s, %s', get_option('blogname'), wp_date('d/m/Y'))); ?>">
                            </div>
                        </div>
                    </div>

                    <div class="register-section" data-channel-section="individual,agency">
                        <div class="register-section__header">
                            <span class="register-section__badge"><?php esc_html_e('Particulares y gestorías', 'garantias-online-360vo'); ?></span>
                            <h3 class="register-section__title"><?php esc_html_e('Personaliza tu perfil', 'garantias-online-360vo'); ?></h3>
                            <p class="register-section__description">
                                <?php esc_html_e('Con los datos básicos ya podrás contratar garantías. Añade tu imagen si quieres identificarte mejor en el panel.', 'garantias-online-360vo'); ?>
                            </p>
                        </div>
                        <div class="register-grid">
                            <div class="register-field">
                                <label for="register_avatar_file" class="register-field__label"><?php esc_html_e('Foto de perfil (opcional)', 'garantias-online-360vo'); ?></label>
                                <input id="register_avatar_file" class="register-field__file" type="file" accept="image/png,image/jpeg">
                                <p class="register-field__description"><?php esc_html_e('Aparecerá en certificados y comunicaciones internas.', 'garantias-online-360vo'); ?></p>
                            </div>
                        </div>
                        <div class="register-panel-note">
                            <?php esc_html_e('Tras crear tu cuenta podrás completar datos fiscales, añadir comerciales o configurar avisos desde el área de ajustes.', 'garantias-online-360vo'); ?>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="register-step" data-step="2" hidden>
                    <legend><?php esc_html_e('Paso 3 · Revisión final', 'garantias-online-360vo'); ?></legend>
                    <div class="register-summary" data-summary-container>
                        <h3 class="register-section__title"><?php esc_html_e('Confirma que los datos son correctos', 'garantias-online-360vo'); ?></h3>
                        <p class="register-section__description">
                            <?php esc_html_e('Si detectas algún error puedes volver atrás en cualquier momento. Siempre podrás completar información adicional desde tu perfil.', 'garantias-online-360vo'); ?>
                        </p>
                        <dl class="register-summary__list" data-summary-list>
                            <div>
                                <dt><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></dt>
                                <dd data-summary-field="register_first_name"></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></dt>
                                <dd data-summary-field="register_last_name"></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></dt>
                                <dd data-summary-field="register_email"></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></dt>
                                <dd data-summary-field="register_phone"></dd>
                            </div>
                            <div>
                                <dt><?php esc_html_e('Canal', 'garantias-online-360vo'); ?></dt>
                                <dd data-summary-field="register_channel"></dd>
                            </div>
                        </dl>
                        <div class="register-summary__cta">
                            <p><?php esc_html_e('Al hacer clic en “Crear cuenta” recibirás un email con los siguientes pasos para activar tus garantías.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </div>
                </fieldset>
            </form>

            <div class="register-form__actions">
                <button type="button" class="btn btn-secondary" data-step-prev disabled><?php esc_html_e('Anterior', 'garantias-online-360vo'); ?></button>
                <button type="button" class="btn btn-primary" data-step-next>
                    <span data-step-next-label data-final-label="<?php esc_attr_e('Crear cuenta', 'garantias-online-360vo'); ?>"><?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?></span>
                </button>
            </div>

            <div class="register-status" role="status" aria-live="polite" data-register-status></div>
        </div>
    </div>
</main>

<?php TemplateLoader::load_part('footer', compact('is_register_page', 'is_auth_page')); ?>

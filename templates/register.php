<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_register_page = true;
$is_auth_page = true;
TemplateLoader::load_part('header', compact('is_register_page', 'is_auth_page'));
?>

<section class="register-layout" style="view-transition-name: register">
    <aside class="register-layout__aside">
        <h1 class="register-layout__title"><?php esc_html_e('Crea tu cuenta en Garantías Online', 'garantias-online-360vo'); ?></h1>
        <p class="register-layout__subtitle">
            <?php esc_html_e('Activa tu espacio profesional y controla todo el ciclo de vida de tus garantías desde un único lugar.', 'garantias-online-360vo'); ?>
        </p>

        <ul class="register-layout__highlights">
            <li><?php esc_html_e('Lanza nuevas garantías en minutos y consulta su evolución en tiempo real.', 'garantias-online-360vo'); ?></li>
            <li><?php esc_html_e('Centraliza comunicaciones, documentos y pagos sin salir del panel.', 'garantias-online-360vo'); ?></li>
            <li><?php esc_html_e('Automatiza certificados con firma y sello y mantén la trazabilidad del cliente.', 'garantias-online-360vo'); ?></li>
        </ul>

        <p class="register-layout__switch">
            <?php esc_html_e('¿Ya tienes cuenta?', 'garantias-online-360vo'); ?>
            <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>">
                <?php esc_html_e('Inicia sesión aquí', 'garantias-online-360vo'); ?>
            </a>
        </p>
    </aside>

    <div class="register-layout__content">
        <div class="form-container register-form__container">
            <div class="tabs register-tabs" data-register-tabs>
                <div class="tabs__connector">
                    <div class="connector connector-1"></div>
                    <div class="connector connector-2"></div>
                </div>
                <div class="tabs__link active" data-step-trigger="0">
                    <div class="tabs__circle">1</div>
                    <div class="tabs__title"><?php esc_html_e('Acceso', 'garantias-online-360vo'); ?></div>
                </div>
                <div class="tabs__link" data-step-trigger="1">
                    <div class="tabs__circle">2</div>
                    <div class="tabs__title"><?php esc_html_e('Configuración', 'garantias-online-360vo'); ?></div>
                </div>
                <div class="tabs__link" data-step-trigger="2">
                    <div class="tabs__circle">3</div>
                    <div class="tabs__title"><?php esc_html_e('Confirmación', 'garantias-online-360vo'); ?></div>
                </div>
            </div>

            <form id="register-form" class="form register-form" novalidate>
                <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                <fieldset class="form__tab-content form__tab-content--active" data-step="0">
                    <legend class="form__legend"><?php esc_html_e('Paso 1 · Datos de acceso', 'garantias-online-360vo'); ?></legend>
                    <div class="form__wrapper-inputs">
                        <div class="form__input-container">
                            <input id="register_first_name" class="form__input" type="text" placeholder=" " required autocomplete="given-name">
                            <label for="register_first_name" class="form__placeholder"><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></label>
                            <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar nombre', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                        </div>
                        <div class="form__input-container">
                            <input id="register_last_name" class="form__input" type="text" placeholder=" " required autocomplete="family-name">
                            <label for="register_last_name" class="form__placeholder"><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></label>
                            <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar apellidos', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                        </div>
                        <div class="form__input-container">
                            <input id="register_email" class="form__input" type="email" placeholder=" " required autocomplete="email">
                            <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                            <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar correo electrónico', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                        </div>
                        <div class="form__input-container">
                            <input id="register_phone" class="form__input" type="tel" placeholder=" " required autocomplete="tel">
                            <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                            <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar teléfono', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                        </div>
                        <div class="form__input-container form__input-container--password">
                            <input id="register_password" class="form__input" type="password" placeholder=" " required autocomplete="new-password">
                            <label for="register_password" class="form__placeholder"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                            <button
                                type="button"
                                class="register-form__toggle-password"
                                data-password-toggle="register_password"
                                data-show-text="<?php esc_attr_e('Mostrar', 'garantias-online-360vo'); ?>"
                                data-hide-text="<?php esc_attr_e('Ocultar', 'garantias-online-360vo'); ?>"
                            >
                                <span class="register-form__toggle-password-label" data-password-toggle-label>
                                    <?php esc_html_e('Mostrar', 'garantias-online-360vo'); ?>
                                </span>
                            </button>
                        </div>
                        <div class="form__input-container">
                            <select id="register_channel" class="form__select" required data-channel-select>
                                <option value="" disabled selected><?php esc_html_e('Selecciona tu canal de venta', 'garantias-online-360vo'); ?></option>
                                <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                            </select>
                            <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></label>
                        </div>
                    </div>

                    <div class="form__input-container form__input-container--acceptance">
                        <input id="register_terms" class="form__checkbox" type="checkbox" required>
                        <label for="register_terms" class="form__checkbox-label">
                            <?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?>
                        </label>
                    </div>
                </fieldset>

                <fieldset class="form__tab-content" data-step="1">
                    <legend class="form__legend"><?php esc_html_e('Paso 2 · Configura tu actividad', 'garantias-online-360vo'); ?></legend>

                    <div class="register-step-intro" data-channel-empty>
                        <h3><?php esc_html_e('Selecciona un canal de venta para continuar', 'garantias-online-360vo'); ?></h3>
                        <p><?php esc_html_e('Al elegir el canal en el paso anterior mostraremos solo los campos relevantes para tu negocio.', 'garantias-online-360vo'); ?></p>
                    </div>

                    <div class="register-channel-section" data-channel-section="professional">
                        <div class="register-section-header">
                            <span class="register-section-badge"><?php esc_html_e('Profesionales', 'garantias-online-360vo'); ?></span>
                            <h3 class="register-section-title"><?php esc_html_e('Personaliza tu negocio', 'garantias-online-360vo'); ?></h3>
                            <p class="register-section-description">
                                <?php esc_html_e('Cuéntanos cómo trabajas para que podamos asociar cada garantía a tu taller y automatizar los procesos clave.', 'garantias-online-360vo'); ?>
                            </p>
                        </div>

                        <div class="register-section-block">
                            <h4 class="register-subtitle"><?php esc_html_e('Información del taller', 'garantias-online-360vo'); ?></h4>
                            <p class="register-section-helper"><?php esc_html_e('Indícanos si gestionas un taller físico para activar los datos de contacto asociados a tus garantías.', 'garantias-online-360vo'); ?></p>

                            <div class="form__wrapper-inputs">
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_has_workshop" class="form__checkbox" type="checkbox" data-toggle-control="workshop" data-channel-only="professional">
                                    <label for="register_has_workshop" class="form__checkbox-label"><?php esc_html_e('Gestiono un taller asociado a mis garantías', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>

                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="workshop">
                                <div class="form__input-container">
                                    <input id="register_workshop_name" class="form__input" type="text" placeholder=" ">
                                    <label for="register_workshop_name" class="form__placeholder"><?php esc_html_e('Nombre del taller', 'garantias-online-360vo'); ?></label>
                                    <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar nombre del taller', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_contact" class="form__input" type="text" placeholder=" ">
                                    <label for="register_workshop_contact" class="form__placeholder"><?php esc_html_e('Persona de contacto', 'garantias-online-360vo'); ?></label>
                                    <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar persona de contacto', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_phone" class="form__input" type="tel" placeholder=" ">
                                    <label for="register_workshop_phone" class="form__placeholder"><?php esc_html_e('Teléfono del taller', 'garantias-online-360vo'); ?></label>
                                    <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar teléfono del taller', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_workshop_email" class="form__input" type="email" placeholder=" ">
                                    <label for="register_workshop_email" class="form__placeholder"><?php esc_html_e('Email del taller', 'garantias-online-360vo'); ?></label>
                                    <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar email del taller', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-section-block">
                            <h4 class="register-subtitle"><?php esc_html_e('Presencia digital', 'garantias-online-360vo'); ?></h4>
                            <p class="register-section-helper"><?php esc_html_e('Conecta tu escaparate 360VO si quieres mostrar tus garantías en tu web comercial.', 'garantias-online-360vo'); ?></p>

                            <div class="form__wrapper-inputs">
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_has_360vo" class="form__checkbox" type="checkbox" data-toggle-control="360vo" data-channel-only="professional">
                                    <label for="register_has_360vo" class="form__checkbox-label"><?php esc_html_e('Dispongo de una web 360VO activa', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>

                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="360vo">
                                <div class="form__input-container">
                                    <input id="register_360vo_url" class="form__input" type="url" placeholder=" ">
                                    <label for="register_360vo_url" class="form__placeholder"><?php esc_html_e('URL de tu web 360VO', 'garantias-online-360vo'); ?></label>
                                    <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar URL de web 360VO', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="register-section-block">
                            <h4 class="register-subtitle"><?php esc_html_e('Documentación y cobros automatizados', 'garantias-online-360vo'); ?></h4>
                            <p class="register-section-helper"><?php esc_html_e('Puedes dejar preparados tus certificados y la domiciliación SEPA para ahorrar tiempo en cada contratación.', 'garantias-online-360vo'); ?></p>

                            <div class="form__wrapper-inputs">
                                <div class="form__input-container form__input-container--acceptance">
                                    <input id="register_auto_signature" class="form__checkbox" type="checkbox" data-toggle-control="signature" data-channel-only="professional">
                                    <label for="register_auto_signature" class="form__checkbox-label"><?php esc_html_e('Añadir firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>

                            <div class="form__wrapper-inputs register-conditional" data-toggle-group="signature">
                                <div class="form__input-container form__input-container--file">
                                    <label for="register_signature_file" class="form__file-label"><?php esc_html_e('Sube tu firma (PNG o JPG)', 'garantias-online-360vo'); ?></label>
                                    <input id="register_signature_file" class="form__file-input" type="file" accept="image/png,image/jpeg">
                                </div>
                                <div class="form__input-container form__input-container--file">
                                    <label for="register_stamp_file" class="form__file-label"><?php esc_html_e('Sube tu sello (PNG o JPG)', 'garantias-online-360vo'); ?></label>
                                    <input id="register_stamp_file" class="form__file-input" type="file" accept="image/png,image/jpeg">
                                </div>
                            </div>

                            <div class="register-sepa">
                                <h5 class="register-sepa__title"><?php esc_html_e('Datos SEPA (opcional)', 'garantias-online-360vo'); ?></h5>
                                <p class="register-section-helper"><?php esc_html_e('Si prefieres hacerlo más tarde podrás completarlo desde los ajustes de tu cuenta.', 'garantias-online-360vo'); ?></p>

                                <div class="form__wrapper-inputs register-sepa-grid">
                                    <div class="form__input-container">
                                        <input id="register_sepa_address" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_address" class="form__placeholder"><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar dirección', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_postal" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_postal" class="form__placeholder"><?php esc_html_e('Código postal', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar código postal', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_city" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_city" class="form__placeholder"><?php esc_html_e('Población', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar población', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_province" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_province" class="form__placeholder"><?php esc_html_e('Provincia', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar provincia', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_country" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_country" class="form__placeholder"><?php esc_html_e('País', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar país', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_bic" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_bic" class="form__placeholder"><?php esc_html_e('SWIFT / BIC', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar código SWIFT o BIC', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_iban" class="form__input" type="text" placeholder=" ">
                                        <label for="register_sepa_iban" class="form__placeholder"><?php esc_html_e('Número de cuenta IBAN', 'garantias-online-360vo'); ?></label>
                                        <span class="form__clear-btn" role="button" aria-label="<?php esc_attr_e('Borrar IBAN', 'garantias-online-360vo'); ?>"><?php echo Svg::icon('clear'); ?></span>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_city_date" class="form__input" type="text" placeholder=" " readonly value="<?php echo esc_attr(sprintf('%s, %s', get_option('blogname'), wp_date('d/m/Y'))); ?>">
                                        <label for="register_sepa_city_date" class="form__placeholder"><?php esc_html_e('Lugar y fecha de firma', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="register-channel-section" data-channel-section="individual,agency">
                        <div class="register-section-header">
                            <span class="register-section-badge"><?php esc_html_e('Particular o gestoría', 'garantias-online-360vo'); ?></span>
                            <h3 class="register-section-title"><?php esc_html_e('Empieza en pocos pasos', 'garantias-online-360vo'); ?></h3>
                            <p class="register-section-description">
                                <?php esc_html_e('Con los datos básicos ya podrás contratar garantías. Completa el resto más adelante si lo necesitas.', 'garantias-online-360vo'); ?>
                            </p>
                        </div>

                        <div class="register-section-block">
                            <h4 class="register-subtitle"><?php esc_html_e('Foto de perfil (opcional)', 'garantias-online-360vo'); ?></h4>
                            <p class="register-section-helper"><?php esc_html_e('Añade una imagen para identificarte en certificados y comunicaciones.', 'garantias-online-360vo'); ?></p>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container form__input-container--file">
                                    <label for="register_avatar_file" class="form__file-label"><?php esc_html_e('Sube una foto de perfil', 'garantias-online-360vo'); ?></label>
                                    <input id="register_avatar_file" class="form__file-input" type="file" accept="image/png,image/jpeg">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="register-channel-section register-channel-section--note" data-channel-section="professional,individual,agency">
                        <div class="register-section-block register-section-block--note">
                            <h4 class="register-subtitle"><?php esc_html_e('Recomendaciones generales', 'garantias-online-360vo'); ?></h4>
                            <p class="register-section-helper"><?php esc_html_e('Tras crear tu cuenta podrás completar datos fiscales, añadir comerciales y configurar avisos desde el área de ajustes.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="form__tab-content" data-step="2">
                    <legend class="form__legend"><?php esc_html_e('Paso 3 · Revisión final', 'garantias-online-360vo'); ?></legend>
                    <div class="register-summary" data-summary-container>
                        <h3 class="register-section-title"><?php esc_html_e('Revisa tus datos antes de crear la cuenta', 'garantias-online-360vo'); ?></h3>
                        <p class="register-section-description">
                            <?php esc_html_e('Verifica que la información principal es correcta. Siempre podrás completar detalles adicionales desde tu perfil.', 'garantias-online-360vo'); ?>
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
                    <span data-step-next-label data-final-label="<?php esc_attr_e('Crear cuenta', 'garantias-online-360vo'); ?>">
                        <?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?>
                    </span>
                </button>
            </div>

            <div class="register-status" role="status" aria-live="polite" data-register-status></div>
        </div>
    </div>
</section>

<?php TemplateLoader::load_part('footer', compact('is_register_page', 'is_auth_page')); ?>

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
                <div class="tabs" role="tablist" aria-label="<?php esc_attr_e('Progreso de registro', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <div class="connector connector-1"></div>
                        <div class="connector connector-2"></div>
                    </div>
                    <button type="button" class="tabs__link active" data-step-trigger="0">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Completa tu perfil', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="2">
                        <div class="tabs__circle">3</div>
                        <div class="tabs__title"><?php esc_html_e('Revisa y confirma', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="form register-form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <fieldset class="form__tab-content form__tab-content--active" data-step="0" aria-label="<?php esc_attr_e('Datos de acceso', 'garantias-online-360vo'); ?>">
                        <legend class="form__legend"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></legend>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Canal de venta y contacto principal', 'garantias-online-360vo'); ?></legend>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <select id="register_channel" class="form__select" aria-label="<?php esc_attr_e('Canal de venta', 'garantias-online-360vo'); ?>" required data-channel-select>
                                        <option value="" disabled selected><?php esc_html_e('Selecciona tu canal de venta', 'garantias-online-360vo'); ?></option>
                                        <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                        <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                        <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Canal de venta', 'garantias-online-360vo'); ?></label>
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
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Credenciales de acceso', 'garantias-online-360vo'); ?></legend>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="register_email" class="form__input" type="email" placeholder=" " required autocomplete="email" />
                                    <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
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
                        </fieldset>

                        <div class="form__sub-fieldset form__sub-fieldset--legal">
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_terms" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            </div>
                            <p class="register-form__hint"><?php esc_html_e('Este consentimiento es necesario para activar tu panel de Garantías Online.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="1" aria-label="<?php esc_attr_e('Completa tu perfil', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Completa tu perfil', 'garantias-online-360vo'); ?></legend>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Tu imagen profesional', 'garantias-online-360vo'); ?></legend>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="register_profile_image" class="form__input" type="file" accept="image/*" />
                                    <label for="register_profile_image" class="form__placeholder"><?php esc_html_e('Imagen de perfil', 'garantias-online-360vo'); ?></label>
                                    <p class="form__supporting-text"><?php esc_html_e('Se mostrará en comunicaciones, certificados y contratos que generes.', 'garantias-online-360vo'); ?></p>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('¿Dispones de taller propio?', 'garantias-online-360vo'); ?></legend>
                            <p class="register-form__hint"><?php esc_html_e('Si cuentas con taller interno podremos vincularlo desde el primer acceso.', 'garantias-online-360vo'); ?></p>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_has_workshop" class="form__checkbox" type="checkbox" data-conditional-toggle="workshop" />
                                <label for="register_has_workshop" class="form__checkbox-label"><?php esc_html_e('Quiero añadir la información de mi taller ahora', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__conditional-group" data-conditional-group="workshop" hidden>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_workshop_name" class="form__input" type="text" placeholder=" " autocomplete="organization" />
                                        <label for="register_workshop_name" class="form__placeholder"><?php esc_html_e('Nombre comercial del taller', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_workshop_phone" class="form__input" type="tel" placeholder=" " autocomplete="tel" />
                                        <label for="register_workshop_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_workshop_email" class="form__input" type="email" placeholder=" " autocomplete="email" />
                                        <label for="register_workshop_email" class="form__placeholder"><?php esc_html_e('Correo electrónico del taller', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_workshop_address" class="form__input" type="text" placeholder=" " autocomplete="street-address" />
                                        <label for="register_workshop_address" class="form__placeholder"><?php esc_html_e('Dirección completa', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></legend>
                            <p class="register-form__hint"><?php esc_html_e('Automatiza la emisión de certificados incluyendo tu firma y sello digital.', 'garantias-online-360vo'); ?></p>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_documents_auto" class="form__checkbox" type="checkbox" data-conditional-toggle="documents" />
                                <label for="register_documents_auto" class="form__checkbox-label"><?php esc_html_e('Agregar mi firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__conditional-group" data-conditional-group="documents" data-conditional-required="true" hidden>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_signature" class="form__input" type="file" accept="image/*" />
                                        <label for="register_signature" class="form__placeholder"><?php esc_html_e('Sube tu firma (PNG o JPG)', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_stamp" class="form__input" type="file" accept="image/*" />
                                        <label for="register_stamp" class="form__placeholder"><?php esc_html_e('Sube tu sello corporativo', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                            <div class="register-form__hint register-form__hint--muted">
                                <p><?php esc_html_e('Los certificados se generarán listos para enviar si añades estos elementos ahora.', 'garantias-online-360vo'); ?></p>
                                <p><?php esc_html_e('Si prefieres hacerlo más adelante, podrás gestionarlo desde los ajustes de tu perfil.', 'garantias-online-360vo'); ?></p>
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Pagos', 'garantias-online-360vo'); ?></legend>
                            <p class="register-form__hint"><?php esc_html_e('Activa la domiciliación SEPA para que tus garantías se validen automáticamente.', 'garantias-online-360vo'); ?></p>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_direct_debit" class="form__checkbox" type="checkbox" data-conditional-toggle="direct-debit" />
                                <label for="register_direct_debit" class="form__checkbox-label"><?php esc_html_e('Quiero preparar la domiciliación bancaria ahora', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__conditional-group" data-conditional-group="direct-debit" data-conditional-required="true" hidden>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_account_holder" class="form__input" type="text" placeholder=" " autocomplete="name" />
                                        <label for="register_account_holder" class="form__placeholder"><?php esc_html_e('Titular de la cuenta', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_iban" class="form__input" type="text" placeholder=" " autocomplete="iban" />
                                        <label for="register_iban" class="form__placeholder"><?php esc_html_e('IBAN', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_bank_name" class="form__input" type="text" placeholder=" " autocomplete="organization" />
                                        <label for="register_bank_name" class="form__placeholder"><?php esc_html_e('Entidad bancaria', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                                <p class="register-form__hint register-form__hint--muted"><?php esc_html_e('Solo tendrás que completar esta información una vez. Podrás revisarla antes de activar tus garantías.', 'garantias-online-360vo'); ?></p>
                            </div>
                        </fieldset>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="2" aria-label="<?php esc_attr_e('Revisa y confirma', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Revisa y confirma', 'garantias-online-360vo'); ?></legend>

                        <div class="register-review">
                            <h2 class="register-review__title"><?php esc_html_e('Comprueba tus datos antes de finalizar', 'garantias-online-360vo'); ?></h2>
                            <p class="register-review__description"><?php esc_html_e('Verifica que la información facilitada es correcta. Podrás editarla después desde tu panel de control.', 'garantias-online-360vo'); ?></p>
                            <ul class="register-review__list">
                                <li><?php esc_html_e('Datos de acceso y contacto configurados.', 'garantias-online-360vo'); ?></li>
                                <li><?php esc_html_e('Perfil profesional y opciones de documentación revisados.', 'garantias-online-360vo'); ?></li>
                                <li><?php esc_html_e('Método de pago seleccionado para activar las garantías automáticamente.', 'garantias-online-360vo'); ?></li>
                            </ul>
                        </div>

                        <div class="form__sub-fieldset form__sub-fieldset--legal">
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_terms_confirm" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms_confirm" class="form__checkbox-label"><?php esc_html_e('Confirmo que he revisado mis datos y acepto el alta de mi cuenta en Garantías Online.', 'garantias-online-360vo'); ?></label>
                            </div>
                        </div>
                    </fieldset>
                </form>

                <div class="register-form__footer">
                    <p class="register-form__status" data-register-status></p>
                    <div class="nav-buttons">
                        <button type="button" class="form__button--prev" data-step-prev><?php esc_html_e('Anterior', 'garantias-online-360vo'); ?></button>
                        <button type="button" class="form__button--next" data-step-next>
                            <span data-step-next-label data-final-label="<?php esc_attr_e('Registrar', 'garantias-online-360vo'); ?>"><?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
</main>

<?php
TemplateLoader::load_part('footer', compact('is_register_page'));

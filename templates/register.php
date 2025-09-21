<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

$is_register_page = true;
TemplateLoader::load_part('header', compact('is_register_page'));
?>

<main class="register-page" style="view-transition-name: register">
    <div class="register-layout">
        <aside class="register-info">
            <span class="register-info__badge"><?php esc_html_e('Registro de nuevo usuario', 'garantias-online-360vo'); ?></span>
            <h1 class="register-info__title"><?php esc_html_e('Activa tu cuenta en Garantías Online', 'garantias-online-360vo'); ?></h1>
            <p class="register-info__lead">
                <?php esc_html_e('Gestiona tus garantías con un panel profesional, seguimiento en tiempo real y soporte especializado en cada fase.', 'garantias-online-360vo'); ?>
            </p>

            <div class="register-info__section">
                <h2 class="register-info__heading"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h2>
                <p><?php esc_html_e('Añade tu firma y sello una única vez y automatiza los certificados listos para tus clientes.', 'garantias-online-360vo'); ?></p>
                <p><?php esc_html_e('Si prefieres no hacerlo ahora, podrás descargar, firmar y subir cada documento más adelante.', 'garantias-online-360vo'); ?></p>
            </div>

            <div class="register-info__section">
                <h2 class="register-info__heading"><?php esc_html_e('Pagos y SEPA', 'garantias-online-360vo'); ?></h2>
                <p><?php esc_html_e('Configura la domiciliación bancaria cuando te venga mejor y olvídate de justificar transferencias urgentes.', 'garantias-online-360vo'); ?></p>
                <ul>
                    <li><?php esc_html_e('Activación automática de tus garantías.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Sin gestiones adicionales ni llamadas para confirmar pagos.', 'garantias-online-360vo'); ?></li>
                    <li><?php esc_html_e('Solo lo completas una vez y queda guardado en tu perfil.', 'garantias-online-360vo'); ?></li>
                </ul>
            </div>

            <p class="register-info__login">
                <?php esc_html_e('¿Ya tienes cuenta?', 'garantias-online-360vo'); ?>
                <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>"><?php esc_html_e('Inicia sesión aquí', 'garantias-online-360vo'); ?></a>
            </p>
        </aside>

        <div class="register-content">
            <div class="form-container register-form-container">
                <div class="tabs register-tabs" role="tablist" aria-label="<?php esc_attr_e('Registro de cuenta', 'garantias-online-360vo'); ?>">
                    <div class="tabs__connector">
                        <div class="connector connector-1"></div>
                        <div class="connector connector-2"></div>
                    </div>
                    <button type="button" class="tabs__link active" data-step-trigger="0" aria-current="step">
                        <div class="tabs__circle">1</div>
                        <div class="tabs__title"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="1">
                        <div class="tabs__circle">2</div>
                        <div class="tabs__title"><?php esc_html_e('Configuración', 'garantias-online-360vo'); ?></div>
                    </button>
                    <button type="button" class="tabs__link" data-step-trigger="2">
                        <div class="tabs__circle">3</div>
                        <div class="tabs__title"><?php esc_html_e('Revisión', 'garantias-online-360vo'); ?></div>
                    </button>
                </div>

                <form id="register-form" class="form register-form" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <fieldset class="form__tab-content form__tab-content--active" data-step="0" aria-label="<?php esc_attr_e('Datos de acceso', 'garantias-online-360vo'); ?>">
                        <legend class="form__legend"><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></legend>
                        <div class="form__wrapper-inputs">
                            <div class="form__input-container">
                                <input id="register_email" class="form__input" type="email" placeholder=" " autocomplete="email" required data-summary-field="register_email" />
                                <label for="register_email" class="form__placeholder"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__input-container">
                                <input id="register_email_confirm" class="form__input" type="email" placeholder=" " autocomplete="email" required data-summary-field="register_email_confirm" />
                                <label for="register_email_confirm" class="form__placeholder"><?php esc_html_e('Confirmar correo electrónico', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="form__input-container">
                                <input id="register_password" class="form__input" type="password" placeholder=" " autocomplete="new-password" required minlength="8" data-summary-field="register_password" />
                                <label for="register_password" class="form__placeholder"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                                <p class="form__supporting-text"><?php esc_html_e('Mínimo 8 caracteres combinando letras, números y símbolos.', 'garantias-online-360vo'); ?></p>
                            </div>
                            <div class="form__input-container">
                                <input id="register_password_confirm" class="form__input" type="password" placeholder=" " autocomplete="new-password" required minlength="8" data-summary-field="register_password_confirm" />
                                <label for="register_password_confirm" class="form__placeholder"><?php esc_html_e('Confirmar contraseña', 'garantias-online-360vo'); ?></label>
                            </div>
                        </div>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="1" aria-label="<?php esc_attr_e('Configuración de la cuenta', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></legend>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Datos de la cuenta', 'garantias-online-360vo'); ?></legend>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <select id="register_channel" class="form__select" aria-label="<?php esc_attr_e('Tipo de cuenta', 'garantias-online-360vo'); ?>" required data-channel-select data-summary-field="register_channel">
                                        <option value="" disabled selected><?php esc_html_e('Selecciona tipo de cuenta', 'garantias-online-360vo'); ?></option>
                                        <option value="professional"><?php esc_html_e('Profesional', 'garantias-online-360vo'); ?></option>
                                        <option value="individual"><?php esc_html_e('Particular', 'garantias-online-360vo'); ?></option>
                                        <option value="agency"><?php esc_html_e('Gestoría', 'garantias-online-360vo'); ?></option>
                                    </select>
                                    <label for="register_channel" class="form__placeholder form__placeholder--select"><?php esc_html_e('Tipo de cuenta', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container" data-channel-only="professional">
                                    <input id="register_business_name" class="form__input" type="text" placeholder=" " autocomplete="organization" data-summary-field="register_business_name" />
                                    <label for="register_business_name" class="form__placeholder"><?php esc_html_e('Nombre de la empresa', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_first_name" class="form__input" type="text" placeholder=" " autocomplete="given-name" data-summary-field="register_first_name" />
                                    <label for="register_first_name" class="form__placeholder"><?php esc_html_e('Nombre', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_last_name" class="form__input" type="text" placeholder=" " autocomplete="family-name" data-summary-field="register_last_name" />
                                    <label for="register_last_name" class="form__placeholder"><?php esc_html_e('Apellidos', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_phone" class="form__input" type="tel" placeholder=" " autocomplete="tel" data-summary-field="register_phone" />
                                    <label for="register_phone" class="form__placeholder"><?php esc_html_e('Teléfono de contacto', 'garantias-online-360vo'); ?></label>
                                </div>
                                <div class="form__input-container">
                                    <input id="register_profile_image" class="form__input" type="file" accept="image/*" data-summary-field="register_profile_image" />
                                    <label for="register_profile_image" class="form__placeholder"><?php esc_html_e('Imagen de perfil', 'garantias-online-360vo'); ?></label>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset">
                            <legend class="form__sub-legend"><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></legend>
                            <div class="register-copy">
                                <p><?php esc_html_e('Los certificados se generan automáticamente con tu firma y sello, listos para que tu cliente los firme.', 'garantias-online-360vo'); ?></p>
                                <p><?php esc_html_e('Si no lo configuras ahora, tendrás que firmar y subir cada documento manualmente en cada alta.', 'garantias-online-360vo'); ?></p>
                            </div>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_auto_signature" class="form__checkbox" type="checkbox" data-toggle-control="signature" data-summary-field="register_auto_signature" data-summary-toggle="signature" data-summary-checked="<?php esc_attr_e('Activada', 'garantias-online-360vo'); ?>" data-summary-unchecked="<?php esc_attr_e('No añadida', 'garantias-online-360vo'); ?>" />
                                <label for="register_auto_signature" class="form__checkbox-label"><?php esc_html_e('Añadir firma y sello automáticamente', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="register-conditional" data-toggle-target="signature" hidden>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_signature" class="form__input" type="file" accept="image/*" data-summary-field="register_signature" />
                                        <label for="register_signature" class="form__placeholder"><?php esc_html_e('Imagen de firma', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_stamp" class="form__input" type="file" accept="image/*" data-summary-field="register_stamp" />
                                        <label for="register_stamp" class="form__placeholder"><?php esc_html_e('Imagen de sello', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="form__sub-fieldset form__sub-fieldset--last">
                            <legend class="form__sub-legend"><?php esc_html_e('Pagos', 'garantias-online-360vo'); ?></legend>
                            <div class="register-copy">
                                <p><?php esc_html_e('Seleccionando la domiciliación bancaria como método de pago, tus garantías se activan automáticamente.', 'garantias-online-360vo'); ?></p>
                                <ul>
                                    <li><?php esc_html_e('Sin preocuparte por transferencias urgentes de 48 horas.', 'garantias-online-360vo'); ?></li>
                                    <li><?php esc_html_e('Sin llamadas ni correos para justificar el pago.', 'garantias-online-360vo'); ?></li>
                                    <li><?php esc_html_e('Lo configuras una vez y queda disponible para futuras contrataciones.', 'garantias-online-360vo'); ?></li>
                                </ul>
                            </div>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="register_enable_sepa" class="form__checkbox" type="checkbox" data-toggle-control="sepa" data-summary-field="register_enable_sepa" data-summary-toggle="sepa" data-summary-checked="<?php esc_attr_e('Activada', 'garantias-online-360vo'); ?>" data-summary-unchecked="<?php esc_attr_e('Pendiente', 'garantias-online-360vo'); ?>" />
                                <label for="register_enable_sepa" class="form__checkbox-label"><?php esc_html_e('Preparar domiciliación SEPA', 'garantias-online-360vo'); ?></label>
                            </div>
                            <div class="register-conditional" data-toggle-target="sepa" hidden>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="register_sepa_holder" class="form__input" type="text" placeholder=" " autocomplete="name" data-summary-field="register_sepa_holder" />
                                        <label for="register_sepa_holder" class="form__placeholder"><?php esc_html_e('Titular de la cuenta', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_address" class="form__input" type="text" placeholder=" " autocomplete="street-address" data-summary-field="register_sepa_address" />
                                        <label for="register_sepa_address" class="form__placeholder"><?php esc_html_e('Dirección completa', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container form__input-container--corto">
                                        <input id="register_sepa_zip" class="form__input" type="text" placeholder=" " autocomplete="postal-code" data-summary-field="register_sepa_zip" />
                                        <label for="register_sepa_zip" class="form__placeholder"><?php esc_html_e('Código postal', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_city" class="form__input" type="text" placeholder=" " autocomplete="address-level2" data-summary-field="register_sepa_city" />
                                        <label for="register_sepa_city" class="form__placeholder"><?php esc_html_e('Ciudad', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_state" class="form__input" type="text" placeholder=" " autocomplete="address-level1" data-summary-field="register_sepa_state" />
                                        <label for="register_sepa_state" class="form__placeholder"><?php esc_html_e('Provincia', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_country" class="form__input" type="text" placeholder=" " autocomplete="country-name" data-summary-field="register_sepa_country" />
                                        <label for="register_sepa_country" class="form__placeholder"><?php esc_html_e('País', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_swift" class="form__input" type="text" placeholder=" " autocomplete="off" data-summary-field="register_sepa_swift" />
                                        <label for="register_sepa_swift" class="form__placeholder"><?php esc_html_e('Swift / BIC', 'garantias-online-360vo'); ?></label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="register_sepa_iban" class="form__input" type="text" placeholder=" " autocomplete="off" data-summary-field="register_sepa_iban" />
                                        <label for="register_sepa_iban" class="form__placeholder"><?php esc_html_e('Número de cuenta IBAN', 'garantias-online-360vo'); ?></label>
                                    </div>
                                </div>
                            </div>
                        </fieldset>
                    </fieldset>

                    <fieldset class="form__tab-content" data-step="2" aria-label="<?php esc_attr_e('Revisión final', 'garantias-online-360vo'); ?>" hidden>
                        <legend class="form__legend"><?php esc_html_e('Revisión final', 'garantias-online-360vo'); ?></legend>
                        <div class="register-summary">
                            <h2 class="register-summary__title"><?php esc_html_e('Revisa tus datos antes de enviar la solicitud', 'garantias-online-360vo'); ?></h2>
                            <div class="register-summary__grid">
                                <section class="register-summary__group">
                                    <h3><?php esc_html_e('Datos de acceso', 'garantias-online-360vo'); ?></h3>
                                    <dl>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_email" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Correo de confirmación', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_email_confirm" data-summary-empty="—"></dd>
                                        </div>
                                    </dl>
                                </section>
                                <section class="register-summary__group">
                                    <h3><?php esc_html_e('Configuración de la cuenta', 'garantias-online-360vo'); ?></h3>
                                    <dl>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Tipo de cuenta', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_channel" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item" data-summary-channel="professional">
                                            <dt><?php esc_html_e('Empresa', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_business_name" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Nombre completo', 'garantias-online-360vo'); ?></dt>
                                            <dd><span data-summary-value="register_first_name" data-summary-empty=""></span> <span data-summary-value="register_last_name" data-summary-empty=""></span></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Teléfono', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_phone" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Imagen de perfil', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_profile_image" data-summary-empty="—"></dd>
                                        </div>
                                    </dl>
                                </section>
                                <section class="register-summary__group" data-summary-section="signature">
                                    <h3><?php esc_html_e('Documentación', 'garantias-online-360vo'); ?></h3>
                                    <dl>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Firma y sello automáticos', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_auto_signature" data-summary-empty="<?php esc_attr_e('No añadida', 'garantias-online-360vo'); ?>"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Archivo de firma', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_signature" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Archivo de sello', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_stamp" data-summary-empty="—"></dd>
                                        </div>
                                    </dl>
                                </section>
                                <section class="register-summary__group" data-summary-section="sepa">
                                    <h3><?php esc_html_e('Domiciliación SEPA', 'garantias-online-360vo'); ?></h3>
                                    <dl>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Estado', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_enable_sepa" data-summary-empty="<?php esc_attr_e('Pendiente', 'garantias-online-360vo'); ?>"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Titular', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_sepa_holder" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Dirección', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_sepa_address" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Código postal y ciudad', 'garantias-online-360vo'); ?></dt>
                                            <dd>
                                                <span data-summary-value="register_sepa_zip" data-summary-empty=""></span>
                                                <span data-summary-value="register_sepa_city" data-summary-empty=""></span>
                                            </dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Provincia y país', 'garantias-online-360vo'); ?></dt>
                                            <dd>
                                                <span data-summary-value="register_sepa_state" data-summary-empty=""></span>
                                                <span data-summary-value="register_sepa_country" data-summary-empty=""></span>
                                            </dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('Swift / BIC', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_sepa_swift" data-summary-empty="—"></dd>
                                        </div>
                                        <div class="register-summary__item">
                                            <dt><?php esc_html_e('IBAN', 'garantias-online-360vo'); ?></dt>
                                            <dd data-summary-value="register_sepa_iban" data-summary-empty="—"></dd>
                                        </div>
                                    </dl>
                                </section>
                            </div>
                            <div class="register-summary__consent">
                                <input id="register_terms" class="form__checkbox" type="checkbox" required />
                                <label for="register_terms" class="form__checkbox-label"><?php esc_html_e('Acepto los términos y condiciones y la política de privacidad.', 'garantias-online-360vo'); ?></label>
                            </div>
                            <p class="register-summary__note"><?php esc_html_e('Podrás completar o modificar estos datos desde tu perfil una vez activada la cuenta.', 'garantias-online-360vo'); ?></p>
                        </div>
                    </fieldset>
                </form>

                <div class="register-actions">
                    <p class="register-form__status" data-register-status></p>
                    <div class="register-actions__buttons">
                        <button type="button" class="btn btn-secondary" data-step-prev disabled><?php esc_html_e('Anterior', 'garantias-online-360vo'); ?></button>
                        <button
                            type="button"
                            class="btn btn-primary"
                            data-step-next
                            data-step-default-label="<?php esc_attr_e('Siguiente', 'garantias-online-360vo'); ?>"
                            data-step-review-label="<?php esc_attr_e('Revisar datos', 'garantias-online-360vo'); ?>"
                            data-step-final-label="<?php esc_attr_e('Crear cuenta', 'garantias-online-360vo'); ?>"
                            data-step-terms-message="<?php esc_attr_e('Debes aceptar los términos y condiciones para finalizar el registro.', 'garantias-online-360vo'); ?>"
                            data-step-success-message="<?php esc_attr_e('Hemos recibido tu solicitud de alta. En breve nos pondremos en contacto contigo.', 'garantias-online-360vo'); ?>"
                        >
                            <span data-step-next-label><?php esc_html_e('Siguiente', 'garantias-online-360vo'); ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
TemplateLoader::load_part('footer', compact('is_register_page'));

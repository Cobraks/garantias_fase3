<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_register_page = true;
TemplateLoader::load_part('header', compact('is_register_page'));
?>

<main class="register-page" style="view-transition-name: register">
    <section class="register-page__layout">
        <aside class="register-page__sidebar">
            <span class="register-badge">Registro de nuevo usuario</span>
            <h1 class="register-title">Gestiona tus garantías con total control</h1>
            <p class="register-description">
                Activa tu cuenta profesional y sigue cada garantía desde un único panel con trazabilidad,
                documentación y soporte especializado en todo momento.
            </p>
            <ul class="register-benefits">
                <li>
                    <span class="register-benefits__icon">✓</span>
                    <span>Contrata, renueva y consulta el estado de tus garantías en tiempo real.</span>
                </li>
                <li>
                    <span class="register-benefits__icon">✓</span>
                    <span>Envía certificados listos para firmar y centraliza la documentación importante.</span>
                </li>
                <li>
                    <span class="register-benefits__icon">✓</span>
                    <span>Automatiza pagos con SEPA para activar garantías sin esperas ni gestiones manuales.</span>
                </li>
            </ul>
        </aside>

        <div class="register-page__card">
            <div class="tabs register-tabs">
                <div class="tabs__connector">
                    <div class="tabs__progress"></div>
                </div>
                <button type="button" class="tabs__link active" data-step="0">
                    <span class="tabs__circle">1</span>
                    <span class="tabs__title">Datos de acceso</span>
                </button>
                <button type="button" class="tabs__link" data-step="1">
                    <span class="tabs__circle">2</span>
                    <span class="tabs__title">Configuración de la cuenta</span>
                </button>
            </div>

            <form id="register-form" class="register-form" novalidate>
                <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                <section class="form-step form-step--active" data-step="0">
                    <div class="register-section register-section--spacing">
                        <div class="form__wrapper-inputs">
                            <div class="form__input-container">
                                <input
                                    id="register_email"
                                    type="email"
                                    class="form__input"
                                    placeholder=" "
                                    autocomplete="email"
                                    required
                                >
                                <label for="register_email" class="form__placeholder">Correo electrónico</label>
                            </div>
                            <div class="form__input-container">
                                <input
                                    id="register_email_confirm"
                                    type="email"
                                    class="form__input"
                                    placeholder=" "
                                    autocomplete="email"
                                    required
                                >
                                <label for="register_email_confirm" class="form__placeholder">Confirmar correo electrónico</label>
                            </div>
                        </div>

                        <div class="form__wrapper-inputs">
                            <div class="form__input-container form__input-container--password">
                                <input
                                    id="register_password"
                                    type="password"
                                    class="form__input"
                                    placeholder=" "
                                    autocomplete="new-password"
                                    required
                                >
                                <label for="register_password" class="form__placeholder">Contraseña</label>
                                <button
                                    type="button"
                                    class="password-toggle"
                                    aria-label="Mostrar contraseña"
                                    data-toggle-target="register_password"
                                >
                                    <span class="password-toggle__icon password-toggle__icon--show"><?php echo Svg::icon('visibility'); ?></span>
                                    <span class="password-toggle__icon password-toggle__icon--hide"><?php echo Svg::icon('visibility_off'); ?></span>
                                </button>
                                <p class="form__supporting-text">Mínimo 8 caracteres, combinando números y símbolos.</p>
                            </div>
                            <div class="form__input-container form__input-container--password">
                                <input
                                    id="register_password_confirm"
                                    type="password"
                                    class="form__input"
                                    placeholder=" "
                                    autocomplete="new-password"
                                    required
                                >
                                <label for="register_password_confirm" class="form__placeholder">Confirmar contraseña</label>
                                <button
                                    type="button"
                                    class="password-toggle"
                                    aria-label="Mostrar contraseña"
                                    data-toggle-target="register_password_confirm"
                                >
                                    <span class="password-toggle__icon password-toggle__icon--show"><?php echo Svg::icon('visibility'); ?></span>
                                    <span class="password-toggle__icon password-toggle__icon--hide"><?php echo Svg::icon('visibility_off'); ?></span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" data-step-prev disabled aria-disabled="true">Anterior</button>
                        <button type="button" class="btn btn-primary" data-step-next>Siguiente</button>
                    </div>
                </section>

                <section class="form-step" data-step="1">
                    <div class="register-section">
                        <h2 class="register-section__title">Configuración de la cuenta</h2>
                        <div class="form__wrapper-inputs">
                            <div class="form__input-container form__input-container--select">
                                <select id="register_account_type" class="form__select" aria-label="Tipo de cuenta" required>
                                    <option value="" disabled selected>Selecciona el tipo de cuenta</option>
                                    <option value="professional">Profesional</option>
                                    <option value="individual">Particular</option>
                                    <option value="agency">Gestoría</option>
                                </select>
                                <label for="register_account_type" class="form__placeholder form__placeholder--select">Tipo de cuenta</label>
                            </div>
                        </div>
                        <div class="register-channel-legend" aria-hidden="true">
                            <div class="register-channel-legend__item">
                                <?php echo Svg::icon('channel_professional'); ?>
                                <span>Profesional</span>
                            </div>
                            <div class="register-channel-legend__item">
                                <?php echo Svg::icon('channel_individual'); ?>
                                <span>Particular</span>
                            </div>
                            <div class="register-channel-legend__item">
                                <?php echo Svg::icon('channel_agency'); ?>
                                <span>Gestoría</span>
                            </div>
                        </div>
                        <div class="register-collapsible" id="register_company">
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="register_company_name" type="text" class="form__input" placeholder=" ">
                                    <label for="register_company_name" class="form__placeholder">Nombre de la empresa</label>
                                </div>
                            </div>
                        </div>
                        <div class="form__wrapper-inputs">
                            <div class="form__input-container">
                                <input id="register_first_name" type="text" class="form__input" placeholder=" " autocomplete="given-name" required>
                                <label for="register_first_name" class="form__placeholder">Nombre</label>
                            </div>
                            <div class="form__input-container">
                                <input id="register_last_name" type="text" class="form__input" placeholder=" " autocomplete="family-name" required>
                                <label for="register_last_name" class="form__placeholder">Apellidos</label>
                            </div>
                        </div>
                        <div class="form__wrapper-inputs">
                            <div class="form__input-container">
                                <input id="register_phone" type="tel" class="form__input" placeholder=" " autocomplete="tel">
                                <label for="register_phone" class="form__placeholder">Teléfono de contacto</label>
                            </div>
                        </div>
                    </div>

                    <div class="register-section">
                        <div class="register-section__header">
                            <h3 class="register-section__subtitle">Imagen de perfil</h3>
                        </div>
                        <div class="register-upload" id="avatar-upload">
                            <div class="register-upload__content">
                                <span class="register-upload__label">Añadir imagen de perfil</span>
                                <p class="register-upload__hint">Formatos JPG o PNG, máximo 5&nbsp;MB.</p>
                            </div>
                            <input type="file" id="register_avatar" class="register-upload__input" accept="image/*">
                        </div>
                    </div>

                    <div class="register-section">
                        <div class="register-section__header">
                            <h3 class="register-section__subtitle">Datos del taller</h3>
                            <button type="button" class="help-toggle" data-help-target="help-workshop" aria-expanded="false">
                                <?php echo Svg::icon('help'); ?>
                            </button>
                        </div>
                        <p class="register-section__description">Indica si cuentas con taller propio para coordinar reparaciones con nuestro equipo.</p>
                        <div class="form__input-container form__input-container--acceptance">
                            <input id="has_workshop" type="checkbox" class="form__checkbox">
                            <label for="has_workshop" class="form__checkbox-label">Dispongo de taller asociado a mi actividad</label>
                        </div>
                        <div class="help-message" id="help-workshop" hidden>
                            <button type="button" class="help-message__close" data-help-close>
                                <?php echo Svg::icon('close'); ?>
                            </button>
                            <p>Al detallar tu taller podremos asignarte avisos de incidencias y agilizar la comunicación con tus clientes.</p>
                        </div>
                        <div class="register-collapsible" id="workshop-fields">
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="workshop_name" type="text" class="form__input" placeholder=" ">
                                    <label for="workshop_name" class="form__placeholder">Nombre del taller</label>
                                </div>
                                <div class="form__input-container">
                                    <input id="workshop_contact" type="text" class="form__input" placeholder=" ">
                                    <label for="workshop_contact" class="form__placeholder">Persona de contacto</label>
                                </div>
                            </div>
                            <div class="form__wrapper-inputs">
                                <div class="form__input-container">
                                    <input id="workshop_phone" type="tel" class="form__input" placeholder=" ">
                                    <label for="workshop_phone" class="form__placeholder">Teléfono del taller</label>
                                </div>
                                <div class="form__input-container">
                                    <input id="workshop_email" type="email" class="form__input" placeholder=" ">
                                    <label for="workshop_email" class="form__placeholder">Correo electrónico del taller</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="register-section">
                        <div class="register-section__header">
                            <h3 class="register-section__subtitle">Documentación</h3>
                            <button type="button" class="help-toggle" data-help-target="help-signature" aria-expanded="false">
                                <?php echo Svg::icon('help'); ?>
                            </button>
                        </div>
                        <p class="register-section__description">
                            Añade tu firma y sello para que los certificados se generen listos para enviar y firmar digitalmente.
                            Si prefieres hacerlo más tarde, podrás cargar los documentos desde tu área de usuario.
                        </p>
                        <div class="help-message" id="help-signature" hidden>
                            <button type="button" class="help-message__close" data-help-close>
                                <?php echo Svg::icon('close'); ?>
                            </button>
                            <p>Automatizar la firma evita imprimir, escanear y reenviar contratos, reduciendo tiempos de activación.</p>
                        </div>
                        <div class="form__input-container form__input-container--acceptance">
                            <input id="auto_signature" type="checkbox" class="form__checkbox">
                            <label for="auto_signature" class="form__checkbox-label">Añadir firma y sello automáticamente a los certificados</label>
                        </div>
                        <div class="register-collapsible" id="signature-fields">
                            <div class="register-upload">
                                <div class="register-upload__content">
                                    <span class="register-upload__label">Subir firma digital</span>
                                    <p class="register-upload__hint">Formato JPG o PNG, máximo 5&nbsp;MB.</p>
                                </div>
                                <input type="file" id="signature_file" class="register-upload__input" accept="image/*">
                            </div>
                            <div class="register-upload">
                                <div class="register-upload__content">
                                    <span class="register-upload__label">Subir sello de empresa</span>
                                    <p class="register-upload__hint">Formato JPG o PNG, máximo 5&nbsp;MB.</p>
                                </div>
                                <input type="file" id="stamp_file" class="register-upload__input" accept="image/*">
                            </div>
                        </div>
                    </div>

                    <div class="register-section">
                        <div class="register-section__header">
                            <h3 class="register-section__subtitle">Pagos</h3>
                        </div>
                        <div class="register-section register-section--inner">
                            <div class="register-section__header">
                                <h4 class="register-section__subtitle">Preparar domiciliación SEPA</h4>
                                <button type="button" class="help-toggle" data-help-target="help-sepa" aria-expanded="false">
                                    <?php echo Svg::icon('help'); ?>
                                </button>
                            </div>
                            <p class="register-section__description">
                                Activar la domiciliación bancaria permite que tus garantías se activen automáticamente sin esperas ni comprobaciones manuales.
                            </p>
                            <ul class="register-list">
                                <li>Te olvidas de realizar transferencias urgentes en 48&nbsp;h.</li>
                                <li>No necesitas justificar el pago manualmente.</li>
                                <li>Solo se configura una vez y podrás modificarlo cuando quieras.</li>
                            </ul>
                            <div class="help-message" id="help-sepa" hidden>
                                <button type="button" class="help-message__close" data-help-close>
                                    <?php echo Svg::icon('close'); ?>
                                </button>
                                <p>Completar estos datos ahora asegura que las pólizas se activen en cuanto las envíes a tus clientes.</p>
                            </div>
                            <div class="form__input-container form__input-container--acceptance">
                                <input id="enable_sepa" type="checkbox" class="form__checkbox">
                                <label for="enable_sepa" class="form__checkbox-label">Quiero activar la domiciliación bancaria (SEPA)</label>
                            </div>
                            <div class="register-collapsible" id="sepa-fields">
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="sepa_name" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_name" class="form__placeholder">Titular de la cuenta</label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="sepa_address" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_address" class="form__placeholder">Dirección completa</label>
                                    </div>
                                </div>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="sepa_state" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_state" class="form__placeholder">Provincia</label>
                                    </div>
                                    <div class="form__input-container">
                                        <input id="sepa_country" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_country" class="form__placeholder">País</label>
                                    </div>
                                </div>
                                <div class="form__wrapper-inputs">
                                    <div class="form__input-container">
                                        <input id="sepa_swift" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_swift" class="form__placeholder">Swift BIC</label>
                                    </div>
                                    <div class="form__input-container form__input-container--icon">
                                        <input id="sepa_iban" type="text" class="form__input" placeholder=" ">
                                        <label for="sepa_iban" class="form__placeholder">Número de cuenta IBAN</label>
                                        <span class="form__input-icon"><?php echo Svg::icon('iban'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="register-section register-section--terms">
                        <div class="form__input-container form__input-container--acceptance">
                            <input id="accept_terms" type="checkbox" class="form__checkbox" required>
                            <label for="accept_terms" class="form__checkbox-label">
                                He leído y acepto los <a href="#">términos y condiciones</a> y la <a href="#">política de privacidad</a>.
                            </label>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" data-step-prev>Anterior</button>
                        <button type="submit" class="btn btn-primary" data-register-submit>Crear cuenta</button>
                    </div>
                </section>
            </form>
        </div>
    </section>
</main>

<?php TemplateLoader::load_part('footer', compact('is_register_page')); ?>

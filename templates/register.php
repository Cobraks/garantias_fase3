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
    <div class="container">
        <div class="info-panel">
            <span class="badge">Registro</span>
            <h1>Crea tu cuenta en Garantías Online</h1>
            <p>Activa tu panel para contratar, seguir y gestionar todas tus garantías desde un único lugar, con soporte experto y trazabilidad completa.</p>

            <ul class="features-list">
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Lanza garantías en minutos y consulta su evolución en tiempo real.</span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Centraliza comunicaciones, documentos y pagos en una misma plataforma.</span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Automatiza certificados con firma y sello y mantén informados a tus clientes.</span>
                </li>
            </ul>

        </div>

        <div class="form-panel">
            <div class="progress-container">
                <div class="progress-steps">
                    <div class="progress-bar" id="progress-bar"></div>
                    <div class="step active" data-step="1">
                        1
                        <span class="step-label">Crea tu cuenta</span>
                    </div>
                    <div class="step" data-step="2">
                        2
                        <span class="step-label">Completa el perfil</span>
                    </div>
                    <div class="step" data-step="3">
                        3
                        <span class="step-label">Confirmación</span>
                    </div>
                </div>
            </div>

            <form id="register-form">
                <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                <div class="form-step active" id="step-1">
                    <div class="form-group">
                        <div class="channel-selector">
                            <div class="channel-btn" data-channel="professional">
                                <div class="channel-icon"><?php echo Svg::icon('professional'); ?></div>
                                <div class="channel-name">Profesional</div>
                                <div class="channel-desc">Compraventas, concesionarios</div>
                            </div>
                            <div class="channel-btn" data-channel="individual">
                                <div class="channel-icon"><?php echo Svg::icon('individual'); ?></div>
                                <div class="channel-name">Particular</div>
                                <div class="channel-desc">Usuarios individuales</div>
                            </div>
                            <div class="channel-btn" data-channel="agency">
                                <div class="channel-icon"><?php echo Svg::icon('agency'); ?></div>
                                <div class="channel-name">Gestoría</div>
                                <div class="channel-desc">Asesores y gestores</div>
                            </div>
                        </div>
                    </div>

                    <div class="conditional-field" id="company-field">
                        <div class="input-container">
                            <input type="text" id="company_name" class="form-input" placeholder=" ">
                            <label for="company_name" class="form-label">Nombre de la empresa</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <input type="text" id="first_name" class="form-input" placeholder=" " required>
                            <label for="first_name" class="form-label">Nombre</label>
                        </div>

                        <div class="input-container">
                            <input type="text" id="last_name" class="form-input" placeholder=" " required>
                            <label for="last_name" class="form-label">Apellidos</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <input type="email" id="email" class="form-input" placeholder=" " required>
                            <label for="email" class="form-label">Correo electrónico</label>
                        </div>

                        <div class="input-container">
                            <input type="tel" id="phone" class="form-input" placeholder=" " required>
                            <label for="phone" class="form-label">Teléfono de contacto</label>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-container">
                            <input type="password" id="password" class="form-input input-with-icon" placeholder=" " required>
                            <label for="password" class="form-label">Contraseña</label>
                            <button
                                type="button"
                                class="password-toggle"
                                id="toggle-password"
                                aria-label="Mostrar contraseña"
                                data-target="password"
                            >
                                <span class="password-toggle__icon password-toggle__icon--show"><?php echo Svg::icon('visibility'); ?></span>
                                <span class="password-toggle__icon password-toggle__icon--hide"><?php echo Svg::icon('visibility_off'); ?></span>
                                <span class="screen-reader-text">Alternar visibilidad de la contraseña</span>
                            </button>
                            <p class="form-hint">Mínimo 8 caracteres con números y símbolos</p>
                        </div>

                        <div class="input-container">
                            <input type="password" id="confirm_password" class="form-input input-with-icon" placeholder=" " required>
                            <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                            <button
                                type="button"
                                class="password-toggle"
                                id="toggle-confirm-password"
                                aria-label="Mostrar contraseña"
                                data-target="confirm_password"
                            >
                                <span class="password-toggle__icon password-toggle__icon--show"><?php echo Svg::icon('visibility'); ?></span>
                                <span class="password-toggle__icon password-toggle__icon--hide"><?php echo Svg::icon('visibility_off'); ?></span>
                                <span class="screen-reader-text">Alternar visibilidad de la contraseña</span>
                            </button>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <div></div>
                        <button type="button" class="btn btn-primary" data-next-step>Siguiente</button>
                    </div>
                </div>

                <div class="form-step" id="step-2">
                    <div class="info-text">
                        Esta información no es obligatoria. Puedes completarla más adelante desde tu área de usuario.
                    </div>

                    <div class="profile-section">
                        <div>
                            <h3 class="subsection-title">Imagen de perfil</h3>
                            <div class="input-container">
                                <div class="profile-media">
                                    <div class="avatar-preview" id="avatar-preview" hidden aria-hidden="true">
                                        <img src="" alt="Previsualización de la imagen de perfil">
                                    </div>
                                    <div class="file-upload" id="avatar-upload">
                                        <div class="file-label">+ Añadir imagen de perfil</div>
                                        <p class="file-hint">Haz clic para subir una imagen (opcional)</p>
                                        <input type="file" id="avatar" class="file-input" accept="image/*">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="subsection-title">Información profesional</h3>
                            <div class="checkbox-row">
                                <div class="checkbox-container checkbox-wrapper-14">
                                    <input type="checkbox" id="has_workshop">
                                    <label for="has_workshop" class="checkbox-label">Dispongo de taller</label>
                                </div>

                                <div class="checkbox-container checkbox-wrapper-14">
                                    <input type="checkbox" id="has_web">
                                    <label for="has_web" class="checkbox-label">Tiene web con 360VO</label>
                                </div>
                            </div>

                            <div class="conditional-field" id="web-field">
                                <h4 class="subsection-title">URL de la web con 360VO</h4>
                                <div class="input-container">
                                    <input type="url" id="web_url" class="form-input" placeholder=" ">
                                    <label for="web_url" class="form-label">URL de tu web</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="conditional-field" id="workshop-fields">
                        <div class="subsection-heading">
                            <h3 class="subsection-title">Datos del taller</h3>
                            <button
                                type="button"
                                class="help-trigger"
                                aria-expanded="false"
                                aria-controls="help-workshop"
                                data-help-target="help-workshop"
                            >
                                <?php echo Svg::icon('help'); ?>
                                <span class="screen-reader-text">Mostrar ayuda sobre los datos del taller</span>
                            </button>
                        </div>
                        <div class="help-panel" id="help-workshop" hidden>
                            <button type="button" class="help-panel__close" aria-label="Cerrar ayuda" data-help-dismiss>
                                <?php echo Svg::icon('close'); ?>
                            </button>
                            <p>Completa esta información si cuentas con taller propio para atender a tus clientes de garantías.</p>
                        </div>
                        <div class="form-row">
                            <div class="input-container">
                                <input type="text" id="workshop_name" class="form-input" placeholder=" ">
                                <label for="workshop_name" class="form-label">Nombre del taller</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="workshop_contact" class="form-input" placeholder=" ">
                                <label for="workshop_contact" class="form-label">Persona de contacto</label>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <input type="tel" id="workshop_phone" class="form-input" placeholder=" ">
                                <label for="workshop_phone" class="form-label">Teléfono del taller</label>
                            </div>

                            <div class="input-container">
                                <input type="email" id="workshop_email" class="form-input" placeholder=" ">
                                <label for="workshop_email" class="form-label">Email del taller</label>
                            </div>
                        </div>
                    </div>

                    <div class="documents-payments-section">
                        <h3 class="subsection-title">Documentación y gestión de pagos</h3>

                        <div class="documents-payments-row">
                            <div class="checkbox-container checkbox-wrapper-14">
                                <input type="checkbox" id="auto_signature">
                                <label for="auto_signature" class="checkbox-label">Firma y sello para certificados</label>
                            </div>

                            <div class="checkbox-container checkbox-wrapper-14">
                                <input type="checkbox" id="enable_sepa">
                                <label for="enable_sepa" class="checkbox-label">Domiciliación Bancaria</label>
                            </div>
                        </div>

                        <div class="conditional-field" id="signature-fields">
                            <div class="subsection-heading">
                                <h4 class="subsection-title">Firma y sello para certificados</h4>
                                <button
                                    type="button"
                                    class="help-trigger"
                                    aria-expanded="false"
                                    aria-controls="help-signature"
                                    data-help-target="help-signature"
                                >
                                    <?php echo Svg::icon('help'); ?>
                                    <span class="screen-reader-text">Mostrar ayuda sobre firma y sello</span>
                                </button>
                            </div>
                            <div class="help-panel" id="help-signature" hidden>
                                <button type="button" class="help-panel__close" aria-label="Cerrar ayuda" data-help-dismiss>
                                    <?php echo Svg::icon('close'); ?>
                                </button>
                                <p>Sube la firma y el sello oficiales que se incluirán en los certificados generados automáticamente.</p>
                            </div>
                            <div class="form-row">
                                <div class="input-container">
                                    <div class="file-upload">
                                        <div class="file-label">+ Subir imagen de firma</div>
                                        <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                        <input type="file" id="signature" class="file-input" accept="image/*">
                                    </div>
                                </div>

                                <div class="input-container">
                                    <div class="file-upload">
                                        <div class="file-label">+ Subir imagen de sello</div>
                                        <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                        <input type="file" id="stamp" class="file-input" accept="image/*">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="conditional-field" id="sepa-fields">
                            <div class="subsection-heading">
                                <h4 class="subsection-title">Datos SEPA</h4>
                                <button
                                    type="button"
                                    class="help-trigger"
                                    aria-expanded="false"
                                    aria-controls="help-sepa"
                                    data-help-target="help-sepa"
                                >
                                    <?php echo Svg::icon('help'); ?>
                                    <span class="screen-reader-text">Mostrar ayuda sobre los datos SEPA</span>
                                </button>
                            </div>
                            <div class="help-panel" id="help-sepa" hidden>
                                <button type="button" class="help-panel__close" aria-label="Cerrar ayuda" data-help-dismiss>
                                    <?php echo Svg::icon('close'); ?>
                                </button>
                                <p>Introduce los datos del titular bancario tal como aparecen en el contrato de domiciliación.</p>
                            </div>
                            <div class="info-text info-text--sepa">
                                Rellena la siguiente información para cumplimentar la Orden de domiciliación de adeudo directo SEPA B2B
                            </div>

                            <div class="input-container">
                                <input type="text" id="sepa_name" class="form-input" placeholder=" ">
                                <label for="sepa_name" class="form-label">Nombre completo*</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="sepa_address" class="form-input" placeholder=" ">
                                <label for="sepa_address" class="form-label">Dirección completa</label>
                                <p class="form-hint">Incluye calle, número, código postal y población</p>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <input type="text" id="sepa_state" class="form-input" placeholder=" ">
                                    <label for="sepa_state" class="form-label">Provincia</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_country" class="form-input" placeholder=" ">
                                    <label for="sepa_country" class="form-label">País</label>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="input-container">
                                    <input type="text" id="sepa_swift" class="form-input" placeholder=" ">
                                    <label for="sepa_swift" class="form-label">Swift BIC</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_iban" class="form-input input-with-icon" placeholder=" ">
                                    <label for="sepa_iban" class="form-label">Número de cuenta IBAN</label>
                                    <span class="iban-icon"><?php echo Svg::icon('iban'); ?></span>
                                </div>
                            </div>

                            <div class="input-container">
                                <input type="text" id="sepa_date" class="form-input" placeholder=" " value="Madrid, <?php echo esc_attr(date_i18n('j \d\e F \d\e Y')); ?>" readonly>
                                <label for="sepa_date" class="form-label">Fecha-Localidad</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" data-prev-step>Anterior</button>
                        <button type="button" class="btn btn-primary" data-next-step>Siguiente</button>
                    </div>
                </div>

                <div class="form-step" id="step-3">
                    <p class="info-text info-text--notice">
                        <span class="info-text__icon"><?php echo Svg::icon('info'); ?></span>
                        <span>Revisa que toda la información sea correcta antes de completar el registro</span>
                    </p>

                    <div class="summary-container">
                        <div class="summary-group">
                            <div class="summary-title">Datos de cuenta</div>
                            <div class="summary-item">
                                <span class="summary-label">Tipo:</span>
                                <span class="summary-value" id="summary-channel">Profesional</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Empresa:</span>
                                <span class="summary-value" id="summary-company">Auto Solutions SL</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Nombre:</span>
                                <span class="summary-value" id="summary-name">Juan Pérez</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Email:</span>
                                <span class="summary-value" id="summary-email">juan@autosolutions.es</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Teléfono:</span>
                                <span class="summary-value" id="summary-phone">+34 612 345 678</span>
                            </div>
                        </div>

                        <div class="summary-group" id="summary-workshop">
                            <div class="summary-title">Datos del taller</div>
                            <div class="summary-item">
                                <span class="summary-label">Nombre:</span>
                                <span class="summary-value" id="summary-workshop-name">Talleres Pérez</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Contacto:</span>
                                <span class="summary-value" id="summary-workshop-contact">María González</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Teléfono:</span>
                                <span class="summary-value" id="summary-workshop-phone">+34 912 345 678</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Email:</span>
                                <span class="summary-value" id="summary-workshop-email">taller@autosolutions.es</span>
                            </div>
                        </div>

                        <div class="summary-group">
                            <div class="summary-title">Preferencias</div>
                            <div class="summary-item">
                                <span class="summary-label">Web 360VO:</span>
                                <span class="summary-value" id="summary-web">https://autosolutions.es</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Firma automática:</span>
                                <span class="summary-value" id="summary-signature">Activada</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Domiciliación:</span>
                                <span class="summary-value" id="summary-sepa-status">Activada</span>
                            </div>
                        </div>

                        <div class="summary-group" id="summary-sepa">
                            <div class="summary-title">Datos bancarios</div>
                            <div class="summary-item">
                                <span class="summary-label">Titular:</span>
                                <span class="summary-value" id="summary-sepa-name">Juan Pérez García</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Dirección:</span>
                                <span class="summary-value" id="summary-sepa-address">Calle Principal 123, 28001 Madrid</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Provincia:</span>
                                <span class="summary-value" id="summary-sepa-state">Madrid</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">IBAN:</span>
                                <span class="summary-value" id="summary-sepa-iban">ES12 3456 7890 1234 5678 9012</span>
                            </div>
                        </div>
                    </div>

                    <div class="checkbox-container checkbox-wrapper-14 checkbox-container--terms">
                        <input type="checkbox" id="terms" required>
                        <label for="terms" class="checkbox-label">Acepto los <a href="#">términos y condiciones</a> y la <a href="#">política de privacidad</a>.</label>
                    </div>

                    <div class="form-navigation">
                        <button type="button" class="btn btn-secondary" data-prev-step>Anterior</button>
                        <button type="button" class="btn btn-primary" id="register-btn">Registrarme</button>
                    </div>
                </div>

                <div class="form-step" id="step-4">
                    <div class="verification-container">
                        <div class="verification-icon">✓</div>
                        <h2 class="verification-title">¡Cuenta creada con éxito!</h2>
                        <p class="verification-text">Hemos enviado un código de verificación a <strong id="email-sent">juan@autosolutions.es</strong>. Por favor, introdúcelo a continuación para activar tu cuenta.</p>

                        <div class="input-container" style="max-width: 300px; margin: 2rem auto;">
                            <input type="text" id="verification_code" class="form-input" placeholder=" " required>
                            <label for="verification_code" class="form-label">Código de verificación</label>
                        </div>

                        <div class="form-navigation" style="justify-content: center;">
                            <button type="button" class="btn btn-primary" id="verify-btn">Verificar cuenta</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<?php TemplateLoader::load_part('footer', compact('is_register_page')); ?>

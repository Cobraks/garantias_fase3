<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_register_page = true;
$is_auth_page     = true;
TemplateLoader::load_part('header', compact('is_register_page', 'is_auth_page'));
?>

<script>
    window.__GO_REGISTER__ = {
        rest: {
            root: "<?php echo esc_url(rest_url('go/public/v1/')); ?>"
        }
    };
</script>

<main class="register-page" style="view-transition-name: register">
    <div class="container">
        <div class="info-panel" style="view-transition-name: header">
            <span class="badge"><?php esc_html_e('Registro', 'garantias-online-360vo'); ?></span>
            <div class="brand-logo" style="view-transition-name: logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img" focusable="false">
                    <style>.st0{fill:#ffffff;}.st1{fill:#c5444e;}</style>
                    <g id="Capa_2">
                        <polygon class="st0" points="191.4,51.6 151.7,15.6 103.6,1 59.8,16.8 16.5,49.6 7.1,97.4 11.7,144.3 46,183.6 96.5,199.3 141.9,184.2 188.7,150.4 193.3,97.9" />
                        <path d="M185.5,54.4L148.7,21L103.8,7.3l-41,14.8L22,53l-8.8,44.7l4.3,44.1l31.9,36.6L96.5,193l42.6-14.2l43.9-31.7l4.3-49.3  L185.5,54.4z M160.7,49.1l-40.9-25.5l27.7-0.7L160.7,49.1z M46.8,98.4h53l-24.7,41.7L46.8,98.4z M101,99.2l23.2,49.5l-47.9-7.8  L101,99.2z M154.7,97.1h-52.4l24.1-40.7L154.7,97.1z M127.6,55.6l34.1-3.8l-6.1,44.1L127.6,55.6z M125.2,55.6l-24.1,40.7l-23.3-46  L125.2,55.6z M78.6,49l38.5-24.9l8.3,30.2L78.6,49z M99.9,97.1H46.8l29.8-46.2L99.9,97.1z M45.7,96.1L34.4,57.8l40.8-7.4L45.7,96.1  z M44.6,97.1H15.7l17.7-37.9L44.6,97.1z M44.7,98.4l-8.6,45.7L15.6,98.4H44.7z M45.9,99.5L74,140.9l-36.7,4.8L45.9,99.5z  M74.7,142.2l9.4,33.6l-45.7-28.9L74.7,142.2z M76.1,142.3l47.4,7.7l-37.9,26.2L76.1,142.3z M102.1,98.4h52.7l-29.4,49.7  L102.1,98.4z M155.8,99.5l12,40.5l-41.1,8.7L155.8,99.5z M168.8,138.4l-11.9-39.9h28L168.8,138.4z M156.8,97.1l6.1-43.7l22,43.7  H156.8z M126.8,54.3l-8.2-29.8l41.8,26.1L126.8,54.3z M145.9,21.6l-28.1,0.7L105.2,9.2L145.9,21.6z M103.1,9l12.8,13.3l-50.5,0.3  L103.1,9z M115.1,23.7L76.9,48.4L64.5,24L115.1,23.7z M63.2,24.6l12.3,24.4l-40.2,7.3L63.2,24.6z M60.6,25.4l-27.3,31l-9.4-3.1  L60.6,25.4z M23.1,54.5l9.5,3.2L15.1,94.9L23.1,54.5z M14.8,99.9l20.4,45.5l-16.4-5L14.8,99.9z M19.7,142.2l16.2,4.9l12.7,28.3  L19.7,142.2z M38,148.3l44.8,28.3l-32.2-0.1L38,148.3z M52.5,177.9l32.4,0.1l10.4,13.1L52.5,177.9z M97.2,191.3L86.6,178l49.7,0.2  L97.2,191.3z M87.4,176.7l37.7-26l12.3,26.2L87.4,176.7z M138.6,176.3l-12.2-26.1l40.5-8.6L138.6,176.3z M181.7,146.4l-41,29.6  l28.6-35.1l16.5-40.8L181.7,146.4z M149.4,23.5L184.1,55l1.8,41L149.4,23.5z" />
                    </g>
                    <g id="Capa_3">
                        <g>
                            <g>
                                <g>
                                    <path class="st1" d="M183.3,58.6c3.8,5.5,2.4,13.1,2.7,19.5c0.2,7.2-0.2,15.5-8,18.4c-2.2,0.8-4.7,1-7,1.1      c-3.4,0.1-6.8,0.1-10.1,0.1c-9,0.2-15.9-2.4-16.8-12.3c-0.4-4.7-0.2-9.3-0.3-14.1c0-4.8,0.1-10.5,3.7-14.1c3.7-3.9,9.6-4,14.6-4      C169.2,53.4,178.5,51.8,183.3,58.6L183.3,58.6z M168.7,62.5c-2.7,0-5.5,0-8.3,0c-1.7,0-3.4,0-4.9,0.9c-2.7,1.5-2.4,5.2-2.4,8      c0,3.4,0,6.7,0,10.1c-0.1,5.4,2.2,7.1,7.4,7c2.9,0,5.8,0.1,8.7,0c3.9,0,6.9-0.8,7.1-5.3c0.1-4.7,0.1-9.4,0.1-14.2      C176.4,63.7,173.8,62.4,168.7,62.5L168.7,62.5z" />
                                    <path class="st1" d="M129.6,62.7c0,0-14.3,0-15.7,0s-2.7,1.2-2.8,2.8c-0.1,1.8,0,3.5,0,5.4c1.9,0,16,0,18.6,0      c4.5,0,8.8,3.3,9.3,8.8c0.3,3,0.3,6.1,0,9.2c-0.2,4.4-3.2,7.3-7.4,8.3c-6,0.4-12.1,0.6-18.1,0.3c-1.4-0.1-2.9-0.1-4.3-0.3      c-2.3-0.7-4.6-1.8-6-3.7c-1.1-1.8-1.6-3.8-1.6-6c0-8-0.1-16,0-24c0-7.9,5.4-10.2,11.2-10.2h26.1L129.6,62.7z M110.9,84.5      c0,3.2,1.9,3.9,3.1,3.9s11,0,12.5,0s2.9-0.4,2.9-2.4s0,0,0-3c0-3-2-3-6.1-3c-2.6,0-12.5,0-12.5,0S110.9,83.9,110.9,84.5z" />
                                    <path class="st1" d="M86.4,97.7c-7.8,0-27.1,0-27.1,0l9.4-9.2c0,0,14,0,15.9,0c1.9,0,2.5-0.9,2.6-2.6c0,0,0-3.8,0-5.9H59.4      c0.3-0.3,9.2-9.2,9.2-9.2s9.3,0,13.7,0c2.6,0.1,4.9-1,5-3.9c0.3-3.4-2-4.3-4.8-4.4c-4.5,0-20.4,0-23.2,0      c3.1-3.1,9.4-9.4,9.4-9.4s12.5,0,18.1,0c5.6,0,9.3,4.2,9.9,8.5c0.2,1.7,0.2,4.3,0.2,5.7c0,0.6-0.2,1.3-0.7,1.7      c-2.8,3.3-5.5,6.1-5.7,6.4h6.3c0,0.2-0.2,14.5-0.2,14.5S95.9,97.7,86.4,97.7z" />
                                </g>
                            </g>
                        </g>
                        <g>
                            <path d="M118.1,142.4C117.9,141.9,105,107,105,107s-9.6,0-10,0c0,0,0,0,0,0c0.1,0.4,16.5,45,16.5,45c2.6,0,12.8,0.1,12.8,0.1    l16.8-45h-10.2C127.3,117,118.6,141,118.1,142.4z" />
                            <path d="M183.8,132.2c-0.3-6.4,1.1-14.1-2.7-19.6l-0.1-0.1c-4.8-6.8-14.1-5.3-21.4-5.4c-5.1,0-11,0.2-14.7,4.1    c-3.6,3.6-3.8,9.3-3.8,14.2c0.1,4.8-0.2,9.5,0.3,14.2c0.9,10,7.9,12.6,17,12.4c3.4,0,6.8,0.1,10.2-0.1c2.4-0.1,4.8-0.3,7.1-1.2    c1.7-0.6,3-1.5,4.1-2.6l0.4,0.2l2.4-1.5l-0.5-1.9C183.8,141.2,183.9,136.5,183.8,132.2z M174.1,137.3c-0.3,4.5-3.2,5.4-7.2,5.4    c-2.9,0.1-5.8,0-8.7,0c-0.5,0-0.9,0-1.4,0v0l-6.1,5.7v-15.1l0,0c0-2.6,0-5.3,0-7.8c0.1-2.8-0.3-6.6,2.4-8.1c1.5-0.8,3.3-0.9,5-0.9    h8.4c5-0.1,7.7,1.2,7.6,6.5C174.2,127.9,174.2,132.6,174.1,137.3z" />
                        </g>
                    </g>
                </svg>
                <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
            </div>
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

            <p class="info-cta">
                <?php esc_html_e('¿Ya tienes una cuenta?', 'garantias-online-360vo'); ?>
                <a href="<?php echo esc_url(home_url('/garantias-online/')); ?>">
                    <?php esc_html_e('Inicia sesión', 'garantias-online-360vo'); ?>
                </a>
            </p>

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
                    <section class="form-section">
                        <h3 class="subsection-title">Selecciona el canal de venta</h3>
                        <div class="channel-selector">
                            <div class="channel-btn" data-channel="compraventa">
                                <div class="channel-icon"><?php echo Svg::icon('professional'); ?></div>
                                <div class="channel-name">Compraventa</div>
                            </div>
                            <div class="channel-btn" data-channel="concesionario">
                                <div class="channel-icon"><?php echo Svg::icon('car_tag'); ?></div>
                                <div class="channel-name">Concesionario oficial</div>
                            </div>
                            <div class="channel-btn" data-channel="individual">
                                <div class="channel-icon"><?php echo Svg::icon('individual'); ?></div>
                                <div class="channel-name">Particular</div>
                            </div>
                            <div class="channel-btn" data-channel="agency">
                                <div class="channel-icon"><?php echo Svg::icon('agency'); ?></div>
                                <div class="channel-name">Gestoría</div>
                            </div>
                        </div>
                        <p class="field-error" id="channel-error" role="alert" hidden></p>
                    </section>

                    <section class="form-section">
                        <h3 class="subsection-title">Datos personales</h3>
                        <div class="form-grid form-grid--three">
                            <div class="input-container">
                                <input type="text" id="first_name" class="form-input" placeholder=" " required>
                                <label for="first_name" class="form-label">Nombre</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="last_name" class="form-input" placeholder=" " required>
                                <label for="last_name" class="form-label">Apellidos</label>
                            </div>

                            <div class="input-container">
                                <input type="tel" id="phone" class="form-input" placeholder=" " required>
                                <label for="phone" class="form-label">Teléfono de contacto</label>
                            </div>

                            <div class="input-container">
                                <input type="email" id="email" class="form-input" placeholder=" " required>
                                <label for="email" class="form-label">Correo electrónico</label>
                            </div>

                            <div class="input-container input-container--with-toggle">
                                <input type="password" id="password" class="form-input input-with-icon" placeholder=" " required autocomplete="new-password">
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

                            <div class="input-container input-container--with-toggle">
                                <input type="password" id="confirm_password" class="form-input input-with-icon" placeholder=" " required autocomplete="new-password">
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
                    </section>

                    <section class="form-section" id="company-section" hidden aria-hidden="true">
                        <div class="subsection-heading">
                            <h3 class="subsection-title">Datos de la empresa</h3>
                            <button
                                type="button"
                                class="help-trigger"
                                aria-expanded="false"
                                aria-controls="company-help"
                                data-help-target="company-help"
                            >
                                <?php echo Svg::icon('help'); ?>
                                <span class="screen-reader-text">Mostrar ayuda sobre los datos de la empresa</span>
                            </button>
                        </div>
                        <div class="help-panel" id="company-help" hidden>
                            <button type="button" class="help-panel__close" aria-label="Cerrar ayuda" data-help-dismiss>
                                <?php echo Svg::icon('close'); ?>
                            </button>
                            <p><strong>Nombre comercial:</strong> Nombre por el que tus clientes reconocen tu empresa. Ej. Mi Empresa.</p>
                            <p><strong>Razón social:</strong> Denominación jurídica oficial registrada, por ejemplo, Mi Empresa Solutions S.A.</p>
                        </div>
                        <div class="form-grid form-grid--two">
                            <div class="input-container">
                                <input type="text" id="company_trade_name" class="form-input" placeholder=" ">
                                <label for="company_trade_name" class="form-label">Nombre comercial</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="company_legal_name" class="form-input" placeholder=" ">
                                <label for="company_legal_name" class="form-label">Razón social</label>
                            </div>
                        </div>

                        <div class="form-grid form-grid--two">
                            <div class="input-container">
                                <input type="text" id="company_cif" class="form-input" placeholder=" ">
                                <label for="company_cif" class="form-label">CIF</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="company_address" class="form-input" placeholder=" ">
                                <label for="company_address" class="form-label">Dirección</label>
                            </div>
                        </div>

                        <div class="form-row form-row--four">
                            <div class="input-container">
                                <input type="text" id="company_postal_code" class="form-input" placeholder=" ">
                                <label for="company_postal_code" class="form-label">Código postal</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="company_city" class="form-input" placeholder=" ">
                                <label for="company_city" class="form-label">Población</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="company_province" class="form-input" placeholder=" ">
                                <label for="company_province" class="form-label">Provincia</label>
                            </div>
                        </div>
                    </section>

                    <div class="form-navigation">
                        <div></div>
                        <button
                            type="button"
                            class="btn btn-primary"
                            data-next-step
                            disabled
                            aria-disabled="true"
                        >Siguiente</button>
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
                                    <label for="has_web" class="checkbox-label">Tengo web con 360VO</label>
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
                            <p>Completa esta información si cuentas con taller propio o uno habitual de confianza para atender a tus clientes de garantías.</p>
                        </div>
                        <div class="form-row">
                            <div class="input-container">
                                <input type="text" id="workshop_name" class="form-input" placeholder=" ">
                                <label for="workshop_name" class="form-label">Nombre del taller</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="workshop_fiscal_name" class="form-input" placeholder=" ">
                                <label for="workshop_fiscal_name" class="form-label">Denominación fiscal</label>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="input-container">
                                <input type="text" id="workshop_tax_id" class="form-input" placeholder=" ">
                                <label for="workshop_tax_id" class="form-label">CIF del taller</label>
                            </div>

                            <div class="input-container">
                                <input type="text" id="workshop_address" class="form-input" placeholder=" ">
                                <label for="workshop_address" class="form-label">Dirección del taller</label>
                            </div>
                        </div>

                        <div class="form-row form-row--three">
                            <div class="input-container">
                                <input type="text" id="workshop_contact" class="form-input" placeholder=" ">
                                <label for="workshop_contact" class="form-label">Persona de contacto</label>
                            </div>

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
                                    <div class="file-upload" id="signature-upload">
                                        <div class="file-label">+ Subir imagen de firma</div>
                                        <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                        <input type="file" id="signature" class="file-input" accept="image/*">
                                        <div class="file-preview" id="signature-preview" hidden aria-hidden="true">
                                            <img src="" alt="Previsualización de la firma" loading="lazy">
                                        </div>
                                    </div>
                                </div>

                                <div class="input-container">
                                    <div class="file-upload" id="stamp-upload">
                                        <div class="file-label">+ Subir imagen de sello</div>
                                        <p class="file-hint">Formatos: JPG, PNG (máx. 5MB)</p>
                                        <input type="file" id="stamp" class="file-input" accept="image/*">
                                        <div class="file-preview" id="stamp-preview" hidden aria-hidden="true">
                                            <img src="" alt="Previsualización del sello" loading="lazy">
                                        </div>
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
                                <p>Completa los siguientes campos para gestionar el pago mediante domiciliación bancaria.</p>
                                <p><strong>Ventajas:</strong></p>
                                <ul>
                                    <li>Las garantías se activan automáticamente.</li>
                                    <li>No necesitas realizar una transferencia en las 48&nbsp;horas siguientes.</li>
                                    <li>No tendrás que contactar con 360VO para justificar el pago.</li>
                                </ul>
                            </div>
                            <div class="info-text info-text--sepa">
                                Rellena la siguiente información para cumplimentar la Orden de domiciliación de adeudo directo SEPA B2B
                            </div>

                            <div class="form-row form-row--two">
                                <div class="input-container">
                                    <input type="text" id="sepa_name" class="form-input" placeholder=" ">
                                    <label for="sepa_name" class="form-label">Nombre completo</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_address" class="form-input" placeholder=" ">
                                    <label for="sepa_address" class="form-label">Dirección</label>
                                </div>
                            </div>

                            <div class="form-row form-row--four">
                                <div class="input-container">
                                    <input type="text" id="sepa_postal_code" class="form-input" placeholder=" ">
                                    <label for="sepa_postal_code" class="form-label">Código postal</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_city" class="form-input" placeholder=" ">
                                    <label for="sepa_city" class="form-label">Población</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_state" class="form-input" placeholder=" ">
                                    <label for="sepa_state" class="form-label">Provincia</label>
                                </div>

                                <div class="input-container">
                                    <input type="text" id="sepa_country" class="form-input" placeholder=" " value="España">
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
                                <span class="summary-value" id="summary-channel">—</span>
                            </div>
                            <div class="summary-item" id="summary-trade-name-item">
                                <span class="summary-label">Nombre comercial:</span>
                                <span class="summary-value" id="summary-trade-name">—</span>
                            </div>
                            <div class="summary-item" id="summary-legal-name-item">
                                <span class="summary-label">Razón social:</span>
                                <span class="summary-value" id="summary-legal-name">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Nombre:</span>
                                <span class="summary-value" id="summary-name">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Email:</span>
                                <span class="summary-value" id="summary-email">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Teléfono:</span>
                                <span class="summary-value" id="summary-phone">—</span>
                            </div>
                        </div>

                        <div class="summary-group" id="summary-workshop" hidden aria-hidden="true">
                            <div class="summary-title">Datos del taller</div>
                            <div class="summary-item">
                                <span class="summary-label">Nombre:</span>
                                <span class="summary-value" id="summary-workshop-name">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Denominación fiscal:</span>
                                <span class="summary-value" id="summary-workshop-fiscal-name">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">CIF:</span>
                                <span class="summary-value" id="summary-workshop-tax-id">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Contacto:</span>
                                <span class="summary-value" id="summary-workshop-contact">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Dirección:</span>
                                <span class="summary-value" id="summary-workshop-address">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Teléfono:</span>
                                <span class="summary-value" id="summary-workshop-phone">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">Email:</span>
                                <span class="summary-value" id="summary-workshop-email">—</span>
                            </div>
                        </div>

                        <div class="summary-group" id="summary-preferences">
                            <div class="summary-title">Preferencias</div>
                            <div class="summary-item" id="summary-web-item">
                                <span class="summary-label">Web 360VO:</span>
                                <span class="summary-value" id="summary-web">—</span>
                            </div>
                            <div class="summary-item" id="summary-signature-item">
                                <span class="summary-label">Firma automática:</span>
                                <span class="summary-value" id="summary-signature">—</span>
                            </div>
                            <div class="summary-item" id="summary-sepa-status-item">
                                <span class="summary-label">Domiciliación:</span>
                                <span class="summary-value" id="summary-sepa-status">—</span>
                            </div>
                        </div>

                    <div class="summary-group" id="summary-sepa" hidden aria-hidden="true">
                        <div class="summary-title">Datos para el SEPA</div>
                        <div class="summary-item">
                            <span class="summary-label">Titular:</span>
                            <span class="summary-value" id="summary-sepa-name">—</span>
                        </div>
                            <div class="summary-item">
                                <span class="summary-label">Dirección:</span>
                                <span class="summary-value" id="summary-sepa-address">—</span>
                            </div>
                            <div class="summary-item">
                                <span class="summary-label">IBAN:</span>
                                <span class="summary-value" id="summary-sepa-iban">—</span>
                            </div>
                        </div>
                    </div>

                    <p class="form-error" id="register-error" hidden></p>

                    <div class="checkbox-container checkbox-wrapper-14 checkbox-container--terms" id="terms-container">
                        <input type="checkbox" id="terms" required>
                        <label for="terms" class="checkbox-label">Acepto los <a href="#">términos y condiciones</a> y la <a href="#">política de privacidad</a>.</label>
                        <p class="checkbox-error" id="terms-error" hidden>Debes aceptar los términos y condiciones para continuar.</p>
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
                        <p class="verification-text">Hemos enviado un código de verificación a <strong id="email-sent">juan@autosolutions.es</strong>. Introduce el código para activar tu cuenta. <span id="verification-expiry">Caduca en 24&nbsp;horas.</span></p>

                        <div class="verification-feedback" id="verification-feedback" role="alert" hidden></div>

                        <div class="input-container input-container--verification">
                            <input type="text" id="verification_code" class="form-input" placeholder=" " inputmode="numeric" autocomplete="one-time-code" required>
                            <label for="verification_code" class="form-label">Código de verificación</label>
                        </div>

                        <div class="verification-actions">
                            <button type="button" class="btn btn-primary" id="verify-btn">Verificar cuenta</button>
                            <button type="button" class="btn btn-link" id="resend-code-btn">Reenviar código</button>
                        </div>
                        <p class="verification-hint" id="resend-countdown" hidden></p>

                        <div class="verification-success" id="verification-success" hidden>
                            <p>Cuenta verificada. Ya puedes acceder a tu área de usuario.</p>
                            <a href="<?php echo esc_url(home_url('/garantias-online/')); ?>" class="btn btn-secondary">Entrar a Mis garantías</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<?php TemplateLoader::load_part('footer', compact('is_register_page')); ?>


<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

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

            <p class="login-redirect">
                ¿Ya tienes cuenta? <a href="<?php echo esc_url('http://garantas-fase-iii.local/garantias-online/'); ?>">Inicia sesión aquí</a>
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
                    <div class="form-group">
                        <div class="channel-selector">
                            <div class="channel-btn" data-channel="professional">
                                <div class="channel-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M841-518v318q0 33-23.5 56.5T761-120H201q-33 0-56.5-23.5T121-200v-318q-23-21-35.5-54t-.5-72l42-136q8-26 28.5-43t47.5-17h556q27 0 47 16.5t29 43.5l42 136q12 39-.5 71T841-518Zm-272-42q27 0 41-18.5t11-41.5l-22-140h-78v148q0 21 14 36.5t34 15.5Zm-180 0q23 0 37.5-15.5T441-612v-148h-78l-22 140q-4 24 10.5 42t37.5 18Zm-178 0q18 0 31.5-13t16.5-33l22-154h-78l-40 134q-6 20 6.5 43t41.5 23Zm540 0q29 0 42-23t6-43l-42-134h-76l22 154q3 20 16.5 33t31.5 13ZM201-200h560v-282q-5 2-6.5 2H751q-27 0-47.5-9T663-518q-18 18-41 28t-49 10q-27 0-50.5-10T481-518q-17 18-39.5 28T393-480q-29 0-52.5-10T299-518q-21 21-41.5 29.5T211-480h-4.5q-2.5 0-5.5-2v282Zm560 0H201h560Z"/></svg></div>
                                <div class="channel-name">Profesional</div>
                                <div class="channel-desc">Compraventas, concesionarios</div>
                            </div>
                            <div class="channel-btn" data-channel="individual">
                                <div class="channel-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M40-160v-112q0-34 17.5-62.5T104-378q62-31 126-46.5T360-440q66 0 130 15.5T616-378q29 15 46.5 43.5T680-272v112H40Zm720 0v-120q0-44-24.5-84.5T666-434q51 6 96 20.5t84 35.5q36 20 55 44.5t19 53.5v120H760ZM360-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47Zm400-160q0 66-47 113t-113 47q-11 0-28-2.5t-28-5.5q27-32 41.5-71t14.5-81q0-42-14.5-81T544-792q14-5 28-6.5t28-1.5q66 0 113 47t47 113ZM120-240h480v-32q0-11-5.5-20T580-306q-54-27-109-40.5T360-360q-56 0-111 13.5T140-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T440-640q0-33-23.5-56.5T360-720q-33 0-56.5 23.5T280-640q0 33 23.5 56.5T360-560Zm0 320Zm0-400Z"/></svg></div>
                                <div class="channel-name">Particular</div>
                                <div class="channel-desc">Usuarios individuales</div>
                            </div>
                            <div class="channel-btn" data-channel="agency">
                                <div class="channel-icon"><svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-280v-280h80v280h-80Zm240 0v-280h80v280h-80ZM80-120v-80h800v80H80Zm600-160v-280h80v280h-80ZM80-640v-80l400-200 400 200v80H80Zm178-80h444-444Zm0 0h444L480-830 258-720Z"/></svg></div>
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
                            <button type="button" class="password-toggle" id="toggle-password">👁️</button>
                            <p class="form-hint">Mínimo 8 caracteres con números y símbolos</p>
                        </div>

                        <div class="input-container">
                            <input type="password" id="confirm_password" class="form-input input-with-icon" placeholder=" " required>
                            <label for="confirm_password" class="form-label">Confirmar contraseña</label>
                            <button type="button" class="password-toggle" id="toggle-confirm-password">👁️</button>
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
                                <div class="file-upload" id="avatar-upload">
                                    <div class="file-label">+ Añadir imagen de perfil</div>
                                    <p class="file-hint">Haz clic para subir una imagen (opcional)</p>
                                    <input type="file" id="avatar" class="file-input" accept="image/*">
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3 class="subsection-title">Información profesional</h3>
                            <div class="checkbox-row">
                                <div class="checkbox-container">
                                    <input type="checkbox" id="has_workshop">
                                    <label for="has_workshop" class="checkbox-label">Dispongo de taller</label>
                                </div>

                                <div class="checkbox-container">
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
                        <h3 class="subsection-title">Datos del taller</h3>
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
                            <div class="checkbox-container">
                                <input type="checkbox" id="auto_signature">
                                <label for="auto_signature" class="checkbox-label">Firma y sello para certificados</label>
                            </div>

                            <div class="checkbox-container">
                                <input type="checkbox" id="enable_sepa">
                                <label for="enable_sepa" class="checkbox-label">Domiciliación Bancaria</label>
                            </div>
                        </div>

                        <div class="conditional-field" id="signature-fields">
                            <h4 class="subsection-title">Firma y sello para certificados</h4>
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
                            <h4 class="subsection-title">Datos SEPA</h4>
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
                                    <span class="iban-icon">🏦</span>
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
                    <p class="form-hint" style="margin-bottom: 2rem;">Revisa que toda la información sea correcta antes de completar el registro</p>

                    <div class="summary-container">
                        <div class="summary-column">
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
                        </div>

                        <div class="summary-column">
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
                    </div>

                    <div class="checkbox-container" style="margin-top: 2rem;">
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

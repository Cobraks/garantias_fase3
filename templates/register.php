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
        <aside class="register-page__info">
            <span class="register-page__badge">Registro de nuevo usuario</span>
            <h1>Activa tu panel de Garantías Online</h1>
            <p class="register-page__intro">Gestiona tus operaciones con un único acceso profesional, centraliza todo el
                seguimiento y mantén a tus clientes informados en cada paso.</p>

            <ul class="register-page__features">
                <li>
                    <span class="register-page__feature-icon">✓</span>
                    <span>Controla tus garantías desde un panel claro y actualizado en tiempo real.</span>
                </li>
                <li>
                    <span class="register-page__feature-icon">✓</span>
                    <span>Simplifica la relación con tus clientes integrando avisos, documentación y
                        pagos.</span>
                </li>
                <li>
                    <span class="register-page__feature-icon">✓</span>
                    <span>Trabaja con plantillas optimizadas para ahorrar tiempo en cada contratación.</span>
                </li>
            </ul>

            <div class="register-page__support">
                <h2>Documentación y pagos</h2>
                <p>Añade tu firma digital y deja preparada la domiciliación SEPA para activar garantías sin
                    esperas ni gestiones adicionales.</p>
            </div>

            <p class="register-page__login">¿Ya tienes cuenta? <a href="<?php echo esc_url(home_url('/garantias-online/acceder/')); ?>">Inicia sesión aquí</a></p>
        </aside>

        <div class="register-page__card form-container">
            <div class="tabs register-page__tabs">
                <div class="tabs__connector">
                    <div class="connector connector-1"></div>
                    <div class="connector connector-2"></div>
                </div>
                <button type="button" class="tabs__link active" data-step="1">
                    <span class="tabs__circle">1</span>
                    <span class="tabs__title">Datos de acceso</span>
                </button>
                <button type="button" class="tabs__link" data-step="2">
                    <span class="tabs__circle">2</span>
                    <span class="tabs__title">Configuración de la cuenta</span>
                </button>
                <button type="button" class="tabs__link" data-step="3">
                    <span class="tabs__circle">3</span>
                    <span class="tabs__title">Revisión</span>
                </button>
            </div>

            <div class="register-page__scroll">
                <form id="register-form" class="register-form" method="post" novalidate>
                    <?php wp_nonce_field('go360_register_user', 'go360_register_nonce'); ?>

                    <section class="form-step active" id="step-1" data-step="1">
                        <header class="register-step__header">
                            <h2>Datos de acceso</h2>
                            <p>Define el correo y la contraseña que utilizarás para entrar en la plataforma.</p>
                        </header>

                        <div class="register-grid register-grid--two">
                            <div class="register-field">
                                <label for="access_email">Correo electrónico</label>
                                <input type="email" id="access_email" autocomplete="email" required>
                            </div>
                            <div class="register-field">
                                <label for="confirm_email">Confirmar correo electrónico</label>
                                <input type="email" id="confirm_email" autocomplete="email" required>
                            </div>
                            <div class="register-field">
                                <label for="access_password">Contraseña</label>
                                <div class="register-field__control">
                                    <input type="password" id="access_password" autocomplete="new-password" required>
                                    <button type="button" class="password-toggle" data-target="access_password" aria-label="Mostrar contraseña">👁️</button>
                                </div>
                                <p class="register-field__hint">Debe contener al menos 8 caracteres, números y símbolos.</p>
                            </div>
                            <div class="register-field">
                                <label for="confirm_password">Confirmar contraseña</label>
                                <div class="register-field__control">
                                    <input type="password" id="confirm_password" autocomplete="new-password" required>
                                    <button type="button" class="password-toggle" data-target="confirm_password" aria-label="Mostrar contraseña">👁️</button>
                                </div>
                            </div>
                        </div>

                        <div class="nav-buttons">
                            <button type="button" class="btn btn-primary" data-nav="next">Siguiente</button>
                        </div>
                    </section>

                    <section class="form-step" id="step-2" data-step="2">
                        <header class="register-step__header">
                            <h2>Configuración de la cuenta</h2>
                            <p>Cuéntanos quién eres para personalizar tu espacio de trabajo desde el primer acceso.</p>
                        </header>

                        <div class="register-grid register-grid--two">
                            <div class="register-field">
                                <label for="account_type">Tipo de cuenta</label>
                                <select id="account_type">
                                    <option value="professional">Profesional</option>
                                    <option value="individual">Particular</option>
                                    <option value="agency">Gestoría</option>
                                </select>
                            </div>
                            <div class="register-field register-field--company" id="company_field">
                                <label for="company_name">Nombre de la empresa</label>
                                <input type="text" id="company_name">
                            </div>
                            <div class="register-field">
                                <label for="first_name">Nombre</label>
                                <input type="text" id="first_name" autocomplete="given-name">
                            </div>
                            <div class="register-field">
                                <label for="last_name">Apellidos</label>
                                <input type="text" id="last_name" autocomplete="family-name">
                            </div>
                            <div class="register-field">
                                <label for="phone">Teléfono</label>
                                <input type="tel" id="phone" autocomplete="tel">
                            </div>
                            <div class="register-field register-field--full">
                                <label for="profile_image">Imagen de perfil</label>
                                <div class="register-upload" id="profile_upload">
                                    <div class="register-upload__content">
                                        <span class="register-upload__label">+ Añadir imagen de perfil</span>
                                        <p>Formatos JPG o PNG (máx. 5&nbsp;MB). Puedes actualizarla cuando quieras.</p>
                                    </div>
                                    <input type="file" id="profile_image" accept="image/*">
                                </div>
                            </div>
                        </div>

                        <div class="register-divider"></div>

                        <section class="register-section">
                            <h3>Documentación</h3>
                            <p class="register-section__intro">Los certificados se generan automáticamente con tu firma y sello,
                                listos para enviarlos al cliente para su firma digital.</p>
                            <ul class="register-section__list">
                                <li>Si no lo configuras ahora tendrás que imprimir, firmar y escanear cada certificado.</li>
                                <li>Puedes activarlo más adelante desde tus ajustes sin perder el histórico.</li>
                            </ul>
                            <div class="checkbox-wrapper-14 register-toggle">
                                <input type="checkbox" id="auto_signature">
                                <label for="auto_signature">Añadir firma y sello automáticamente</label>
                            </div>
                            <div class="register-toggle-fields" id="signature_fields">
                                <div class="register-grid register-grid--two">
                                    <div class="register-field register-field--full">
                                        <label for="signature_file">Firma digital</label>
                                        <div class="register-upload">
                                            <div class="register-upload__content">
                                                <span class="register-upload__label">+ Subir imagen de firma</span>
                                                <p>Formatos JPG o PNG (máx. 5&nbsp;MB).</p>
                                            </div>
                                            <input type="file" id="signature_file" accept="image/*">
                                        </div>
                                    </div>
                                    <div class="register-field register-field--full">
                                        <label for="stamp_file">Sello corporativo</label>
                                        <div class="register-upload">
                                            <div class="register-upload__content">
                                                <span class="register-upload__label">+ Subir imagen de sello</span>
                                                <p>Formatos JPG o PNG (máx. 5&nbsp;MB).</p>
                                            </div>
                                            <input type="file" id="stamp_file" accept="image/*">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="register-section">
                            <h3>Pagos</h3>
                            <p class="register-section__intro">Prepara la domiciliación SEPA ahora para activar automáticamente
                                tus garantías en cuanto se confirmen.</p>
                            <ul class="register-section__list">
                                <li>Olvídate de enviar justificantes o realizar transferencias en menos de 48&nbsp;horas.</li>
                                <li>Solo tendrás que completar el formulario una vez. También puedes hacerlo después desde tu
                                    perfil.</li>
                            </ul>
                            <div class="checkbox-wrapper-14 register-toggle">
                                <input type="checkbox" id="enable_sepa">
                                <label for="enable_sepa">Preparar domiciliación SEPA</label>
                            </div>
                            <div class="register-toggle-fields" id="sepa_fields">
                                <div class="register-info register-info--sepa">
                                    Rellena los datos del titular de la cuenta para generar la orden SEPA B2B.
                                </div>
                                <div class="register-grid register-grid--two">
                                    <div class="register-field register-field--full">
                                        <label for="sepa_name">Nombre completo</label>
                                        <input type="text" id="sepa_name">
                                    </div>
                                    <div class="register-field register-field--full">
                                        <label for="sepa_address">Dirección completa</label>
                                        <input type="text" id="sepa_address">
                                    </div>
                                    <div class="register-field">
                                        <label for="sepa_state">Provincia</label>
                                        <input type="text" id="sepa_state">
                                    </div>
                                    <div class="register-field">
                                        <label for="sepa_country">País</label>
                                        <input type="text" id="sepa_country" value="España">
                                    </div>
                                    <div class="register-field">
                                        <label for="sepa_swift">Swift / BIC</label>
                                        <input type="text" id="sepa_swift">
                                    </div>
                                    <div class="register-field">
                                        <label for="sepa_iban">Número de cuenta IBAN</label>
                                        <input type="text" id="sepa_iban">
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" data-nav="prev">Anterior</button>
                            <button type="button" class="btn btn-primary" data-nav="next">Siguiente</button>
                        </div>
                    </section>

                    <section class="form-step" id="step-3" data-step="3">
                        <header class="register-step__header">
                            <h2>Revisa tus datos</h2>
                            <p>Comprueba que todo está correcto antes de completar el registro.</p>
                        </header>

                        <div class="register-summary">
                            <div class="register-summary__group">
                                <h3>Acceso</h3>
                                <dl>
                                    <div>
                                        <dt>Correo</dt>
                                        <dd id="summary_email">—</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="register-summary__group">
                                <h3>Configuración</h3>
                                <dl>
                                    <div>
                                        <dt>Tipo de cuenta</dt>
                                        <dd id="summary_account_type">Profesional</dd>
                                    </div>
                                    <div id="summary_company_row">
                                        <dt>Empresa</dt>
                                        <dd id="summary_company">—</dd>
                                    </div>
                                    <div>
                                        <dt>Nombre completo</dt>
                                        <dd id="summary_full_name">—</dd>
                                    </div>
                                    <div>
                                        <dt>Teléfono</dt>
                                        <dd id="summary_phone">—</dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="register-summary__group">
                                <h3>Documentación y pagos</h3>
                                <dl>
                                    <div>
                                        <dt>Firma y sello</dt>
                                        <dd id="summary_signature">No añadido</dd>
                                    </div>
                                    <div>
                                        <dt>Domiciliación SEPA</dt>
                                        <dd id="summary_sepa">No preparada</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        <div class="register-terms">
                            <div class="checkbox-wrapper-14">
                                <input type="checkbox" id="terms">
                                <label for="terms">Acepto los <a href="#">términos y condiciones</a> y la <a href="#">política de privacidad</a>.</label>
                            </div>
                        </div>

                        <div class="nav-buttons">
                            <button type="button" class="btn btn-secondary" data-nav="prev">Anterior</button>
                            <button type="button" class="btn btn-primary" id="register-btn">Registrar cuenta</button>
                        </div>
                    </section>
                </form>
            </div>
        </div>
    </div>
</main>

<?php TemplateLoader::load_part('footer', compact('is_register_page')); ?>

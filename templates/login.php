<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

$is_auth_page = true;
TemplateLoader::load_part('header', compact('is_auth_page'));
?>

<main class="register-page register-page--auth" style="view-transition-name: auth">
    <div class="container">
        <div class="info-panel">
            <span class="badge">Acceso</span>
            <h1>Gestiona tus garantías de forma sencilla</h1>
            <p>Accede a tu panel para crear, consultar y seguir tus garantías con total trazabilidad y soporte experto.</p>

            <ul class="features-list">
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Consulta el estado de cada garantía al instante.</span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Centraliza comunicaciones y documentación clave.</span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span>Gestiona renovaciones y avisos desde un mismo panel.</span>
                </li>
            </ul>

            <p class="login-redirect">
                ¿No tienes cuenta? <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">Regístrate</a>
            </p>
        </div>

        <div class="form-panel">
            <div class="form-card">
                <h1><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h1>

                <?php if (isset($_GET['login']) && $_GET['login'] === 'failed') : ?>
                    <div class="auth-error">
                        <?php esc_html_e('Usuario o contraseña incorrectos', 'garantias-online-360vo'); ?>
                    </div>
                <?php endif; ?>

                <form
                    name="loginform"
                    id="loginform"
                    action="<?php echo esc_url(wp_login_url(home_url('/garantias-online/'))); ?>"
                    method="post"
                    class="auth-form"
                >
                    <div class="input-container">
                        <input
                            name="log"
                            id="user_login"
                            type="text"
                            class="form-input"
                            placeholder=" "
                            required
                            autocomplete="username"
                        >
                        <label for="user_login" class="form-label">
                            <?php esc_html_e('Usuario o correo', 'garantias-online-360vo'); ?>
                        </label>
                    </div>

                    <div class="input-container">
                        <input
                            name="pwd"
                            id="user_pass"
                            type="password"
                            class="form-input"
                            placeholder=" "
                            required
                            autocomplete="current-password"
                        >
                        <label for="user_pass" class="form-label">
                            <?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?>
                        </label>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <?php esc_html_e('Entrar', 'garantias-online-360vo'); ?>
                        </button>
                        <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/garantias-online/')); ?>">
                    </div>
                </form>

                <p class="form-switch">
                    <?php esc_html_e('¿Necesitas una cuenta?', 'garantias-online-360vo'); ?>
                    <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                        <?php esc_html_e('Regístrate aquí', 'garantias-online-360vo'); ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
</main>

<?php TemplateLoader::load_part('footer', compact('is_auth_page')); ?>

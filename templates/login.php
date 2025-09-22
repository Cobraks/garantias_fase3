<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;

$is_embedded   = ! empty($is_embedded);
$is_login_page = ! $is_embedded;
$is_auth_page  = true;

if ($is_login_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page'));
}
?>

<main class="register-page login-page" style="view-transition-name: login">
    <div class="container">
        <div class="info-panel">
            <span class="badge"><?php esc_html_e('Acceso', 'garantias-online-360vo'); ?></span>
            <h1><?php esc_html_e('Gestiona tus garantías de forma sencilla', 'garantias-online-360vo'); ?></h1>
            <p><?php esc_html_e('Centraliza tus contratos, haz seguimiento y mantén bajo control cada garantía desde un único panel.', 'garantias-online-360vo'); ?></p>

            <ul class="features-list">
                <li>
                    <span class="feature-icon">✓</span>
                    <span><?php esc_html_e('Consulta historiales y estados en tiempo real.', 'garantias-online-360vo'); ?></span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span><?php esc_html_e('Coordina tus equipos con avisos y trazabilidad completa.', 'garantias-online-360vo'); ?></span>
                </li>
                <li>
                    <span class="feature-icon">✓</span>
                    <span><?php esc_html_e('Descarga certificados y documentación con un clic.', 'garantias-online-360vo'); ?></span>
                </li>
            </ul>

            <p class="info-cta">
                <?php esc_html_e('¿No tienes cuenta?', 'garantias-online-360vo'); ?>
                <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                    <?php esc_html_e('Regístrate', 'garantias-online-360vo'); ?>
                </a>
            </p>
        </div>

        <div class="form-panel">
            <div class="form-header">
                <h2><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h2>
                <p class="form-description"><?php esc_html_e('Introduce tu usuario o correo electrónico y tu contraseña para acceder.', 'garantias-online-360vo'); ?></p>
            </div>

            <?php if (isset($_GET['login']) && $_GET['login'] === 'failed') : ?>
                <div class="form-alert form-alert--error">
                    <?php esc_html_e('Usuario o contraseña incorrectos. Inténtalo de nuevo.', 'garantias-online-360vo'); ?>
                </div>
            <?php endif; ?>

            <form
                name="loginform"
                id="loginform"
                action="<?php echo esc_url(wp_login_url(home_url('/garantias-online/'))); ?>"
                method="post"
                class="auth-form"
            >
                <div class="form-row single">
                    <div class="input-container">
                        <input
                            name="log"
                            id="user_login"
                            type="text"
                            required
                            autocomplete="username"
                            class="form-input"
                            placeholder=" "
                        >
                        <label for="user_login" class="form-label">
                            <?php esc_html_e('Usuario o correo', 'garantias-online-360vo'); ?>
                        </label>
                    </div>
                </div>

                <div class="form-row single">
                    <div class="input-container">
                        <input
                            name="pwd"
                            id="user_pass"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="form-input"
                            placeholder=" "
                        >
                        <label for="user_pass" class="form-label">
                            <?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?>
                        </label>
                    </div>
                </div>

                <div class="form-footer">
                    <button type="submit" class="btn btn-primary">
                        <?php esc_html_e('Entrar', 'garantias-online-360vo'); ?>
                    </button>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/garantias-online/')); ?>">
                </div>
            </form>
        </div>
    </div>
</main>

<?php
if ($is_login_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}


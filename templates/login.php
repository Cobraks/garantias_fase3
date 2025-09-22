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

<main class="login-page" style="view-transition-name: login">
    <style>
        .login-page {
            --login-gradient: radial-gradient(120% 120% at 50% 0%, rgba(99, 102, 241, 0.16) 0%, rgba(59, 130, 246, 0.08) 45%, rgba(15, 23, 42, 0.05) 100%), linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            min-height: 100vh;
            margin: 0;
            padding: clamp(2.5rem, 6vw, 5rem) clamp(1.25rem, 6vw, 3.75rem);
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--login-gradient);
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
            box-sizing: border-box;
        }

        body.body--auth {
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--login-gradient, linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%));
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, 'Helvetica Neue', Arial, sans-serif;
            color: #0f172a;
        }

        body.body--auth .login-page {
            background: transparent;
            width: 100%;
        }

        .login-page * {
            box-sizing: border-box;
        }

        .login-page__container {
            width: min(100%, 420px);
        }

        .login-card {
            display: flex;
            flex-direction: column;
            gap: 1.75rem;
            padding: clamp(2rem, 4vw, 3rem);
            background: rgba(255, 255, 255, 0.92);
            border-radius: 24px;
            box-shadow: 0 28px 60px -35px rgba(15, 23, 42, 0.45), 0 18px 30px -20px rgba(99, 102, 241, 0.25);
            backdrop-filter: blur(8px);
        }

        .login-card__logo {
            display: flex;
            justify-content: center;
        }

        .login-card__logo img {
            width: clamp(150px, 35vw, 190px);
            height: auto;
        }

        .login-card__header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
        }

        .login-card__title {
            margin: 0;
            font-size: clamp(1.75rem, 3vw, 2rem);
            font-weight: 700;
            letter-spacing: -0.015em;
        }

        .login-card__subtitle {
            margin: 0;
            font-size: 0.975rem;
            line-height: 1.6;
            color: #475569;
        }

        .login-alert {
            margin: 0;
            padding: 0.85rem 1rem;
            border-radius: 16px;
            border: 1px solid rgba(239, 68, 68, 0.35);
            background: rgba(254, 226, 226, 0.85);
            color: #b91c1c;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.15rem;
        }

        .login-form__field {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .login-form__label {
            font-weight: 600;
            font-size: 0.95rem;
            color: #1e293b;
        }

        .login-form__input {
            appearance: none;
            border: 1px solid rgba(148, 163, 184, 0.65);
            border-radius: 14px;
            padding: 0.85rem 1rem;
            font-size: 1rem;
            color: #0f172a;
            background: #fff;
            transition: border 0.2s ease, box-shadow 0.2s ease;
        }

        .login-form__input::placeholder {
            color: rgba(100, 116, 139, 0.65);
        }

        .login-form__input:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.9);
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.18);
        }

        .login-form__options {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            font-size: 0.9rem;
            color: #475569;
        }

        .login-form__checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            cursor: pointer;
            user-select: none;
        }

        .login-form__checkbox input {
            width: 1.1rem;
            height: 1.1rem;
            border-radius: 6px;
            border: 1px solid rgba(148, 163, 184, 0.75);
            accent-color: #2563eb;
        }

        .login-form__link {
            color: #2563eb;
            font-weight: 600;
            text-decoration: none;
            transition: color 0.2s ease, text-decoration-color 0.2s ease;
        }

        .login-form__link:focus,
        .login-form__link:hover {
            color: #1d4ed8;
            text-decoration: underline;
        }

        .login-form__submit {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.95rem 1rem;
            border: none;
            border-radius: 14px;
            background: linear-gradient(135deg, #2563eb 0%, #6366f1 100%);
            color: #fff;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .login-form__submit:hover,
        .login-form__submit:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 15px 35px -15px rgba(37, 99, 235, 0.55);
        }

        .login-form__submit:focus-visible {
            outline: none;
        }

        .login-form__submit:active {
            transform: translateY(0);
        }

        .login-card__footer {
            margin: 0;
            text-align: center;
            font-size: 0.95rem;
            color: #475569;
        }

        .login-card__footer a {
            color: #1d4ed8;
            font-weight: 600;
            text-decoration: none;
        }

        .login-card__footer a:focus,
        .login-card__footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 640px) {
            body.body--auth {
                padding: 1.5rem;
            }

            .login-page {
                padding: 2.25rem 1.5rem;
            }

            .login-card {
                padding: clamp(1.75rem, 8vw, 2.25rem);
                border-radius: 20px;
                box-shadow: 0 18px 40px -30px rgba(15, 23, 42, 0.55), 0 12px 22px -18px rgba(99, 102, 241, 0.25);
            }

            .login-form__options {
                gap: 0.5rem;
            }
        }
    </style>

    <div class="login-page__container">
        <div class="login-card">
            <div class="login-card__logo">
                <img src="<?php echo esc_url(plugins_url('assets/images/logo.png', GARANTIAS360VO__FILE__)); ?>" alt="<?php esc_attr_e('Garantías Online 360VO', 'garantias-online-360vo'); ?>">
            </div>

            <header class="login-card__header">
                <h1 class="login-card__title"><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h1>
                <p class="login-card__subtitle"><?php esc_html_e('Introduce tu correo electrónico y tu contraseña para acceder al panel.', 'garantias-online-360vo'); ?></p>
            </header>

            <?php if (isset($_GET['login']) && $_GET['login'] === 'failed') : ?>
                <div class="login-alert" role="alert" aria-live="polite">
                    <?php esc_html_e('Correo electrónico o contraseña incorrectos. Inténtalo de nuevo.', 'garantias-online-360vo'); ?>
                </div>
            <?php endif; ?>

            <form
                name="loginform"
                id="loginform"
                action="<?php echo esc_url(wp_login_url(home_url('/garantias-online/'))); ?>"
                method="post"
                class="login-form">
                <div class="login-form__field">
                    <label class="login-form__label" for="user_login"><?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?></label>
                    <input
                        name="log"
                        id="user_login"
                        type="text"
                        required
                        autocomplete="username"
                        class="login-form__input"
                        placeholder="<?php esc_attr_e('tu@empresa.com', 'garantias-online-360vo'); ?>">
                </div>

                <div class="login-form__field">
                    <label class="login-form__label" for="user_pass"><?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?></label>
                    <input
                        name="pwd"
                        id="user_pass"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="login-form__input"
                        placeholder="<?php esc_attr_e('••••••••', 'garantias-online-360vo'); ?>">
                </div>

                <div class="login-form__options">
                    <label class="login-form__checkbox" for="rememberme">
                        <input type="checkbox" name="rememberme" id="rememberme" value="forever">
                        <span><?php esc_html_e('Recordarme', 'garantias-online-360vo'); ?></span>
                    </label>
                    <a class="login-form__link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                        <?php esc_html_e('He olvidado mi contraseña', 'garantias-online-360vo'); ?>
                    </a>
                </div>

                <button type="submit" class="login-form__submit">
                    <?php esc_html_e('Entrar', 'garantias-online-360vo'); ?>
                </button>
                <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/garantias-online/')); ?>">
            </form>

            <p class="login-card__footer">
                <?php esc_html_e('¿No tienes cuenta?', 'garantias-online-360vo'); ?>
                <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                    <?php esc_html_e('Regístrate', 'garantias-online-360vo'); ?>
                </a>
            </p>
        </div>
    </div>
</main>

<?php
if ($is_login_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}

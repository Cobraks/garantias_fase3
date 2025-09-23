<?php
if (! defined('ABSPATH')) {
    exit;
}

use GarantiasOnline360VO\TemplateLoader;
use GarantiasOnline360VO\Svg;

$is_embedded   = ! empty($is_embedded);
$is_login_page = ! $is_embedded;
$is_auth_page  = true;

if ($is_login_page) {
    TemplateLoader::load_part('header', compact('is_auth_page', 'is_login_page'));
}
?>

<main class="register-page login-page" style="view-transition-name: login">
    <style>
        :root {
            color-scheme: light;
        }

        body.body--auth {
            margin: 0;
            height: calc(100dvh - 36px);
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: #f1f5f9;
            color: #1f2937;
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        .container.container--auth {
            flex: 1;
            height: calc(100dvh - 36px);
            display: flex;
            flex-direction: column;
            padding-inline: clamp(1.5rem, 5vw, 3rem);
        }

        .main-grid.main-grid--auth {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding-block: clamp(2rem, 6vh, 4rem);
        }

        .main-grid--auth > .register-page {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        footer.footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 10;
        }

        footer.footer .footer__wrapper {
            height: 36px;
            margin: 0 auto;
            max-width: 600px;
            width: 100%;
            padding-block: calc(var(--spacing-1, 1rem) * 0.75);
            padding-inline: clamp(1.5rem, 5vw, 3rem);
            border-radius: 1rem 1rem 0 0;
            align-items: center;
            background: transparent;
        }

        .login-page .container {
            width: min(100%, 460px);
            margin: 0 auto;
        }

        .login-card {
            position: relative;
            background: #ffffff;
            border-radius: 22px;
            padding: clamp(2.75rem, 6vw, 3.5rem);
            padding-top: clamp(3.25rem, 7vw, 4rem);
            box-shadow: 0 22px 45px -24px rgba(15, 23, 42, 0.4);
            display: flex;
            flex-direction: column;
            gap: 0;
            overflow: visible;
        }

        .login-card__badge {
            position: absolute;
            top: 0;
            left: 50%;
            transform: translate(-50%, -50%);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1.25rem;
            border-radius: 999px;
            background: #ffffff;
            border: 1px solid rgba(15, 23, 42, 0.1);
            box-shadow: 0 18px 34px -22px rgba(15, 23, 42, 0.35);
            color: #000000;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            white-space: nowrap;
            overflow: hidden;
            isolation: isolate;
        }

        .login-card__badge::after {
            content: "";
            position: absolute;
            inset: -120% -40%;
            background: linear-gradient(
                115deg,
                rgba(255, 255, 255, 0) 10%,
                rgba(255, 255, 255, 0.6) 45%,
                rgba(255, 255, 255, 0) 70%
            );
            transform: translateX(-120%) rotate(18deg);
            animation: badge-sheen 4.5s ease-in-out infinite;
            pointer-events: none;
            mix-blend-mode: screen;
        }

        .login-card__badge-icon {
            width: 1.5rem;
            height: 1.5rem;
        }

        .login-card__badge-status {
            position: relative;
            display: inline-block;
            width: 0.55rem;
            height: 0.55rem;
            border-radius: 50%;
            background: radial-gradient(circle at center, #fff7f7 0%, #ffe4e4 70%, rgba(255, 255, 255, 0.8) 100%);
            box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.2);
            animation: badge-status-breathe 2.6s ease-in-out infinite;
            margin-left: 0.35rem;
            overflow: hidden;
        }

        .login-card__badge-status::after {
            content: "";
            position: absolute;
            inset: -45%;
            border-radius: inherit;
            background: radial-gradient(circle at center, rgba(248, 113, 113, 0.55) 0%, rgba(248, 113, 113, 0) 70%);
            opacity: 0;
            transform: scale(0.7);
            transition: opacity 0.3s ease;
            animation: badge-status-glow 2.6s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes badge-status-breathe {
            0% {
                transform: scale(0.82);
                background: radial-gradient(circle at center, #fff7f7 0%, #ffe4e4 70%, rgba(255, 255, 255, 0.85) 100%);
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.15);
            }

            18% {
                transform: scale(1);
                background: radial-gradient(circle at center, #ff6961 0%, #dc2626 68%, #7f1d1d 100%);
                box-shadow: 0 0 12px 2px rgba(248, 113, 113, 0.4);
            }

            34% {
                transform: scale(0.96);
                background: radial-gradient(circle at center, #ff7b72 0%, #ef4444 70%, #991b1b 100%);
                box-shadow: 0 0 10px 1px rgba(248, 113, 113, 0.32);
            }

            58% {
                transform: scale(0.86);
                background: radial-gradient(circle at center, #fff3f3 0%, #ffe2e2 68%, rgba(255, 255, 255, 0.8) 100%);
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.14);
            }

            100% {
                transform: scale(0.82);
                background: radial-gradient(circle at center, #fff7f7 0%, #ffe4e4 70%, rgba(255, 255, 255, 0.85) 100%);
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.15);
            }
        }

        @keyframes badge-status-glow {
            0% {
                opacity: 0;
                transform: scale(0.7);
            }

            20% {
                opacity: 0.55;
                transform: scale(1.6);
            }

            35% {
                opacity: 0.28;
                transform: scale(1.9);
            }

            65% {
                opacity: 0.08;
                transform: scale(1.2);
            }

            100% {
                opacity: 0;
                transform: scale(0.7);
            }
        }

        @keyframes badge-sheen {
            0%,
            55% {
                transform: translateX(-120%) rotate(18deg);
                opacity: 0;
            }

            65% {
                opacity: 0.2;
            }

            80% {
                transform: translateX(120%) rotate(18deg);
                opacity: 0.4;
            }

            100% {
                transform: translateX(140%) rotate(18deg);
                opacity: 0;
            }
        }

        .login-card__logo {
            display: flex;
            justify-content: center;
        }

        .login-card__logo svg,
        .login-card__logo img {
            width: min(200px, 60vw);
            height: auto;
        }

        .login-card__header {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            text-align: center;
            margin-bottom: clamp(24px, 36.76px, 32px);
        }

        .login-card__title {
            margin: 0;
            font-size: clamp(1.75rem, 2.6vw, 2.1rem);
            font-weight: 600;
            color: #0f172a;
        }

        .login-card__subtitle {
            margin: 0;
            font-size: 0.95rem;
            color: #475569;
        }

        .form-alert {
            border-radius: 14px;
            padding: 0.9rem 1rem;
            font-size: 0.95rem;
            font-weight: 500;
            color: #b91c1c;
            background: rgba(248, 113, 113, 0.16);
            text-align: center;
            margin-bottom: clamp(1rem, 2.6vh, 1.4rem);
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: clamp(1rem, 2.8vh, 1.5rem);
        }

        .form-row {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .input-container {
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .form-input {
            width: 100%;
            border-radius: 14px;
            border: 1px solid #d4ddeb;
            padding: 0.95rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: #0f172a;
            background: #ffffff;
            transition: border-color 0.2s ease, color 0.2s ease, background-color 0.2s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: none;
            background: #fff;
        }

        .form-label {
            position: absolute;
            top: 50%;
            left: 1rem;
            background: #ffffff;
            padding: 0 4px;
            font-size: 0.95rem;
            color: #64748b;
            pointer-events: none;
            transform: translateY(-50%);
            transition: transform 0.2s ease, top 0.2s ease, left 0.2s ease, font-size 0.2s ease, color 0.2s ease;
        }

        .form-input:focus ~ .form-label,
        .form-input:not(:placeholder-shown) ~ .form-label {
            top: -0.6rem;
            left: 0.85rem;
            font-size: 0.75rem;
            transform: none;
        }

        .form-input:focus ~ .form-label {
            color: #000000;
        }

        .form-input:not(:placeholder-shown) ~ .form-label {
            color: #1f2937;
        }

        .auth-form__meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .remember-me {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.95rem;
            color: #1f2937;
        }

        .remember-me input {
            width: 1rem;
            height: 1rem;
            accent-color: #0f172a;
        }

        .toggle-password {
            position: absolute;
            top: 50%;
            right: 0.75rem;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            padding: 0.35rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #64748b;
            transition: color 0.2s ease;
        }

        .toggle-password:hover,
        .toggle-password:focus-visible {
            color: #111827;
            outline: none;
        }

        .toggle-password__icon {
            width: 1.15rem;
            height: 1.15rem;
        }

        .toggle-password__icon--off {
            display: none;
        }

        .toggle-password[aria-pressed="true"] .toggle-password__icon--on {
            display: none;
        }

        .toggle-password[aria-pressed="true"] .toggle-password__icon--off {
            display: block;
        }

        .form-link {
            margin-left: auto;
            font-size: 0.95rem;
            color: #c5444e;
            font-weight: 400;
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color 0.2s ease, text-decoration-color 0.2s ease;
        }

        .form-link:hover,
        .form-link:focus-visible {
            color: #a6343d;
            text-decoration: underline;
            outline: none;
        }

        .auth-form__footer {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .btn {
            display: inline-flex;
            justify-content: center;
            align-items: center;
            border-radius: 14px;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.95rem 1rem;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #1f2937, #111827);
            color: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
        }

        .btn-primary:hover,
        .btn-primary:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px -18px rgba(15, 23, 42, 0.65);
            background: linear-gradient(135deg, #111827, #0f172a);
            outline: none;
        }

        .info-cta {
            margin: 0;
            text-align: center;
            font-size: 1rem;
            color: #475569;
        }

        .info-cta a {
            color: #c5444e;
            font-weight: 600;
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color 0.2s ease, text-decoration-color 0.2s ease;
        }

        .info-cta a:hover,
        .info-cta a:focus-visible {
            color: #a6343d;
            text-decoration: underline;
            outline: none;
        }

        .screen-reader-text {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0 0 0 0);
            white-space: nowrap;
            border: 0;
        }

        @media (max-width: 640px) {
            .container.container--auth {
                padding-inline: clamp(1rem, 6vw, 1.5rem);
            }

            .login-card {
                padding: clamp(2rem, 8vw, 2.5rem);
                gap: 1.5rem;
            }

            .auth-form__meta {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-link {
                margin-left: 0;
            }
        }
    </style>

    <div class="container">
        <div class="login-card" style="view-transition-name: header">
            <span class="login-card__badge">
                <?php echo Svg::icon('shield', 'login-card__badge-icon'); ?>
                <span class="login-card__badge-text"><?php esc_html_e('Garantías Online', 'garantias-online-360vo'); ?></span>
                <span class="login-card__badge-status" aria-hidden="true"></span>
            </span>
            <div class="login-card__logo">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" role="img" focusable="false">
                    <style>
                        .st0 {
                            fill: #ffffff;
                        }

                        .st1 {
                            fill: #c5444e;
                        }
                    </style>
                    <g id="Capa_2">
                        <polygon class="st0" points="191.4,51.6 151.7,15.6 103.6,1 59.8,16.8 16.5,49.6 7.1,97.4 11.7,144.3 46,183.6 96.5,199.3 141.9,184.2 188.7,150.4 193.3,97.9" />
                        <path d="M185.5,54.4L148.7,21L103.8,7.3l-41,14.8L22,53l-8.8,44.7l4.3,44.1l31.9,36.6L96.5,193l42.6-14.2l43.9-31.7l4.3-49.3  L185.5,54.4z M160.7,49.1l-40.9-25.5l27.7-0.7L160.7,49.1z M46.8,98.4h53l-24.7,41.7L46.8,98.4z M101,99.2l23.2,49.5l-47.9-7.8  L101,99.2z M154.7,97.1h-52.4l24.1-40.7L154.7,97.1z M127.6,55.6l34.1-3.8l-6.1,44.1L127.6,55.6z M125.2,55.6l-24.1,40.7l-23.3-46  L125.2,55.6z M78.6,49l38.5-24.9l8.3,30.2L78.6,49z M99.9,97.1H46.8l29.8-46.2L99.9,97.1z M45.7,96.1L34.4,57.8l40.8-7.4L45.7,96.1  z M44.6,97.1H15.7l17.7-37.9L44.6,97.1z M44.7,98.4l-8.6,45.7L15.6,98.4H44.7z M45.9,99.5L74,140.9l-36.7,4.8L45.9,99.5z M74.7,142.2l9.4,33.6l-45.7-28.9L74.7,142.2z M76.1,142.3l47.4,7.7l-37.9,26.2L76.1,142.3z M102.1,98.4h52.7l-29.4,49.7  L102.1,98.4z M155.8,99.5l12,40.5l-41.1,8.7L155.8,99.5z M168.8,138.4l-11.9-39.9h28L168.8,138.4z M156.8,97.1l6.1-43.7l22,43.7  H156.8z M126.8,54.3l-8.2-29.8l41.8,26.1L126.8,54.3z M145.9,21.6l-28.1,0.7L105.2,9.2L145.9,21.6z M103.1,9l12.8,13.3l-50.5,0.3  L103.1,9z M115.1,23.7L76.9,48.4L64.5,24L115.1,23.7z M63.2,24.6l12.3,24.4l-40.2,7.3L63.2,24.6z M60.6,25.4l-27.3,31l-9.4-3.1  L60.6,25.4z M23.1,54.5l9.5,3.2L15.1,94.9L23.1,54.5z M14.8,99.9l20.4,45.5l-16.4-5L14.8,99.9z M19.7,142.2l16.2,4.9l12.7,28.3  L19.7,142.2z M38,148.3l44.8,28.3l-32.2-0.1L38,148.3z M52.5,177.9l32.4,0.1l10.4,13.1L52.5,177.9z M97.2,191.3L86.6,178l49.7,0.2  L97.2,191.3z M87.4,176.7l37.7-26l12.3,26.2L87.4,176.7z M138.6,176.3l-12.2-26.1l40.5-8.6L138.6,176.3z M181.7,146.4l-41,29.6  l28.6-35.1l16.5-40.8L181.7,146.4z M149.4,23.5L184.1,55l1.8,41L149.4,23.5z" />
                    </g>
                    <g id="Capa_3">
                        <g>
                            <g>
                                <g>
                                    <path class="st1" d="M183.3,58.6c3.8,5.5,2.4,13.1,2.7,19.5c0.2,7.2-0.2,15.5-8,18.4c-2.2,0.8-4.7,1-7,1.1      c-3.4,0.1-6.8,0.1-10.1,0.1c-9,0.2-15.9-2.4-16.8-12.3c-0.4-4.7-0.2-9.3-0.3-14.1c0-4.8,0.1-10.5,3.7-14.1c3.7-3.9,9.6-4,14.6-4      C169.2,53.4,178.5,51.8,183.3,58.6L183.3,58.6z M168.7,62.5c-2.7,0-5.5,0-8.3,0c-1.7,0-3.4,0-4.9,0.9c-2.7,1.5-2.4,5.2-2.4,8      c0,3.4,0,6.7,0,10.1c-0.1,5.4,2.2,7.1,7.4,7c2.9,0,5.8,0.1,8.7,0c3.9,0,6.9-0.8,7.1-5.3c0.1-4.7,0.1-9.4,0.1-14.2      C176.4,63.7,173.8,62.4,168.7,62.5L168.7,62.5z" />
                                    <path class="st1" d="M129.6,62.7c0,0-14.3,0-15.7,0s-2.7,1.2-2.8,2.8c-0.1,1.8,0,3.5,0,5.4c1.9,0,16,0,18.6,0      c4.5,0,8.8,3.3,9.3,8.8c0.3,3,0.3,6.1,0,9.2c-0.2,4.4-3.2,7.3-7.4,8.3c-6,0.4-12.1,0.6-18.1,0.3c-1.4-0.1-2.9-0.1-4.3-0.3      c-2.3-0.7-4.6-1.8-6-3.7c-1.1-1.8-1.6-3.8-1.6-6c0-8-0.1-16,0-24c0-7.9,5.4-10.2,11.2-10.2h26.1L129.6,62.7z M110.9,84.5     c0,3.2,1.9,3.9,3.1,3.9s11,0,12.5,0s2.9-0.4,2.9-2.4s0,0,0-3c0-3-2-3-6.1-3c-2.6,0-12.5,0-12.5,0S110.9,83.9,110.9,84.5z" />
                                    <path class="st1" d="M86.4,97.7c-7.8,0-27.1,0-27.1,0l9.4-9.2c0,0,14,0,15.9,0c1.9,0,2.5-0.9,2.6-2.6c0,0,0-3.8,0-5.9H59.4      c0.3-0.3,9.2-9.2,9.2-9.2s9.3,0,13.7,0c2.6,0.1,4.9-1,5-3.9c0.3-3.4-2-4.3-4.8-4.4c-4.5,0-20.4,0-23.2,0      c3.1-3.1,9.4-9.4,9.4-9.4s12.5,0,18.1,0c5.6,0,9.3,4.2,9.9,8.5c0.2,1.7,0.2,4.3,0.2,5.7c0,0.6-0.2,1.3-0.7,1.7      c-2.8,3.3-5.5,6.1-5.7,6.4h6.3c0,0.2-0.2,14.5-0.2,14.5S95.9,97.7,86.4,97.7z" />
                                </g>
                            </g>
                        </g>
                        <g>
                            <path d="M118.1,142.4C117.9,141.9,105,107,105,107s-9.6,0-10,0c0,0,0,0,0,0c0.1,0.4,16.5,45,16.5,45c2.6,0,12.8,0.1,12.8,0.1    l16.8-45h-10.2C127.3,117,118.6,141,118.1,142.4z" />
                            <path d="M183.8,132.2c-0.3-6.4,1.1-14.1-2.7-19.6l-0.1-0.1c-4.8-6.8-14.1-5.3-21.4-5.4c-5.1,0-11,0.2-14.7,4.1    c-3.6,3.6-3.8,9.3-3.8,14.2c0.1,4.8-0.2,9.5,0.3,14.2c0.9,10,7.9,12.6,17,12.4c3.4,0,6.8,0.1,10.2-0.1c2.4-0.1,4.8-0.3,7.1-1.2    c1.7-0.6,3-1.5,4.1-2.6l0.4,0.2l2.4-1.5l-0.5-1.9C183.8,141.2,183.9,136.5,183.8,132.2z M174.1,137.3c-0.3,4.5-3.2,5.4-7.2,5.4   c-2.9,0.1-5.8,0-8.7,0c-0.5,0-0.9,0-1.4,0v0l-6.1,5.7v-15.1l0,0c0-2.6,0-5.3,0-7.8c0.1-2.8-0.3-6.6,2.4-8.1c1.5-0.8,3.3-0.9,5-0.9    h8.4c5-0.1,7.7,1.2,7.6,6.5C174.2,127.9,174.2,132.6,174.1,137.3z" />
                        </g>
                    </g>
                </svg>
                <span class="screen-reader-text"><?php esc_html_e('Garantías Online 360VO', 'garantias-online-360vo'); ?></span>
            </div>

            <div class="login-card__header">
                <h2 class="login-card__title"><?php esc_html_e('Iniciar sesión', 'garantias-online-360vo'); ?></h2>
                <p class="login-card__subtitle"><?php esc_html_e('Introduce tu correo electrónico y tu contraseña para acceder al panel.', 'garantias-online-360vo'); ?></p>
            </div>

            <?php if (isset($_GET['login']) && $_GET['login'] === 'failed') : ?>
                <div class="form-alert">
                    <?php esc_html_e('Correo electrónico o contraseña incorrectos. Inténtalo de nuevo.', 'garantias-online-360vo'); ?>
                </div>
            <?php endif; ?>

            <form
                name="loginform"
                id="loginform"
                action="<?php echo esc_url(wp_login_url(home_url('/garantias-online/'))); ?>"
                method="post"
                class="auth-form">
                <div class="form-row">
                    <div class="input-container">
                        <input
                            name="log"
                            id="user_login"
                            type="text"
                            required
                            autocomplete="username"
                            class="form-input"
                            placeholder=" ">
                        <label for="user_login" class="form-label">
                            <?php esc_html_e('Correo electrónico', 'garantias-online-360vo'); ?>
                        </label>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-container">
                        <input
                            name="pwd"
                            id="user_pass"
                            type="password"
                            required
                            autocomplete="current-password"
                            class="form-input"
                            placeholder=" ">
                        <label for="user_pass" class="form-label">
                            <?php esc_html_e('Contraseña', 'garantias-online-360vo'); ?>
                        </label>
                        <button
                            type="button"
                            class="toggle-password"
                            data-toggle-target="user_pass"
                            aria-pressed="false">
                            <span class="screen-reader-text"><?php esc_html_e('Mostrar contraseña', 'garantias-online-360vo'); ?></span>
                            <?php echo Svg::icon('visibility', 'toggle-password__icon toggle-password__icon--on'); ?>
                            <?php echo Svg::icon('visibility_off', 'toggle-password__icon toggle-password__icon--off'); ?>
                        </button>
                    </div>
                </div>

                <div class="auth-form__meta">
                    <label class="remember-me" for="rememberme">
                        <input
                            name="rememberme"
                            id="rememberme"
                            type="checkbox"
                            value="forever"
                            <?php checked(isset($_POST['rememberme']) ? $_POST['rememberme'] : 0, 'forever'); ?>>
                        <span><?php esc_html_e('Recordarme', 'garantias-online-360vo'); ?></span>
                    </label>
                    <a class="form-link" href="<?php echo esc_url(wp_lostpassword_url()); ?>">
                        <?php esc_html_e('He olvidado mi contraseña', 'garantias-online-360vo'); ?>
                    </a>
                </div>

                <div class="auth-form__footer">
                    <button type="submit" class="btn btn-primary">
                        <?php esc_html_e('Entrar', 'garantias-online-360vo'); ?>
                    </button>
                    <p class="info-cta">
                        <?php esc_html_e('¿No tienes cuenta?', 'garantias-online-360vo'); ?>
                        <a href="<?php echo esc_url(home_url('/garantias-online/registro/')); ?>">
                            <?php esc_html_e('Regístrate', 'garantias-online-360vo'); ?>
                        </a>
                    </p>
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url(home_url('/garantias-online/')); ?>">
                </div>
            </form>
        </div>
    </div>
</main>

<script>
    (function () {
        const toggles = document.querySelectorAll('.toggle-password');
        toggles.forEach((toggle) => {
            const targetId = toggle.getAttribute('data-toggle-target');
            const input = document.getElementById(targetId);
            if (!input) {
                return;
            }

            toggle.addEventListener('click', () => {
                const isVisible = input.getAttribute('type') === 'text';
                input.setAttribute('type', isVisible ? 'password' : 'text');
                toggle.setAttribute('aria-pressed', String(!isVisible));
                const label = toggle.querySelector('.screen-reader-text');
                if (label) {
                    label.textContent = isVisible
                        ? <?php echo wp_json_encode(esc_html__('Mostrar contraseña', 'garantias-online-360vo')); ?>
                        : <?php echo wp_json_encode(esc_html__('Ocultar contraseña', 'garantias-online-360vo')); ?>;
                }
            });
        });
    })();
</script>

<?php
if ($is_login_page) {
    TemplateLoader::load_part('footer', compact('is_auth_page', 'is_login_page'));
}
?>

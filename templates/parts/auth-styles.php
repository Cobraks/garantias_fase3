<style>
        :root {
            color-scheme: light;
        }

        body.body--auth {
            --auth-surface: #f8fafc;
            --auth-surface-contrast: #ffffff;
            --auth-accent: var(--go-color-2563eb);
            --auth-accent-dark: var(--go-color-1d4ed8);
            --auth-field-bg: #ffffff;
            --auth-field-border: #e2e8f0;
            --auth-field-text: #0f172a;
            --auth-muted: #64748b;
            --auth-toggle-track: #e2e8f0;
            --auth-button-disabled-bg: var(--go-color-e2e8f0);
            --auth-button-disabled-text: var(--go-color-94a3b8);
            margin: 0;
            height: calc(100dvh - 36px);
            min-height: 0;
            display: flex;
            flex-direction: column;
            background: var(--auth-surface);
            color: var(--auth-field-text);
            font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        :root[data-theme='dark'] body.body--auth {
            color-scheme: dark;
            --auth-surface: var(--background, #090a0d);
            --auth-surface-contrast: var(--surface, #1d1f26);
            --auth-accent: var(--primary-color, #5b7bff);
            --auth-accent-dark: var(--primary-color-strong, #3f63f0);
            --auth-field-bg: var(--surface-alt, #222530);
            --auth-field-border: var(--card-border, rgba(148, 163, 184, 0.35));
            --auth-field-text: var(--text-color, rgba(226, 232, 240, 0.96));
            --auth-muted: var(--text-tertiary, rgba(148, 163, 184, 0.75));
            --auth-toggle-track: rgba(148, 163, 184, 0.32);
            --auth-button-disabled-bg: rgba(148, 163, 184, 0.18);
            --auth-button-disabled-text: rgba(148, 163, 184, 0.65);
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
            background: transparent;
        }

        footer.footer .footer__wrapper {
            height: 36px;
            margin: 0 auto;
            width: 100%;
            padding-block: calc(var(--spacing-1, 1rem) * 0.75);
            padding-inline: clamp(1.5rem, 5vw, 3rem);
            border-radius: 1rem 1rem 0 0;
            align-items: center;
            background: transparent;
        }

        :root[data-theme='dark'] footer.footer .footer__wrapper {
            background: transparent;
            box-shadow: none;
        }

        .login-page .container {
            width: min(100%, 460px);
            margin: 0 auto;
        }

        .login-card {
            position: relative;
            background: var(--auth-surface-contrast, #ffffff);
            border-radius: 22px;
            padding: clamp(2.75rem, 6vw, 3.5rem);
            padding-top: clamp(3.25rem, 7vw, 4rem);
            box-shadow: 0 22px 45px -24px rgba(15, 23, 42, 0.4);
            border: 1px solid var(--auth-field-border);
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
            background: var(--auth-surface-contrast, #ffffff);
            border: 1px solid var(--auth-field-border);
            box-shadow: 0 18px 34px -26px rgba(15, 23, 42, 0.32);
            color: var(--auth-field-text);
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
            background: radial-gradient(circle at center, #fff7f7 0%, #ffe7e7 70%, rgba(255, 255, 255, 0.86) 100%);
            box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.18);
            animation: badge-status-breathe 2.8s cubic-bezier(0.68, 0, 0.32, 1) infinite;
            margin-left: 0.35rem;
            overflow: hidden;
        }

        .login-card__badge-status::after {
            content: "";
            position: absolute;
            inset: -50%;
            border-radius: inherit;
            background: radial-gradient(circle at center, rgba(248, 113, 113, 0.55) 0%, rgba(248, 113, 113, 0) 70%);
            opacity: 0;
            transform: scale(0.65);
            animation: badge-status-glow 2.8s cubic-bezier(0.68, 0, 0.32, 1) infinite;
            pointer-events: none;
        }

        @keyframes badge-status-breathe {
            0% {
                transform: scale(0.84);
                background: radial-gradient(circle at center, #fff8f8 0%, #ffeaea 72%, rgba(255, 255, 255, 0.88) 100%);
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.16);
            }

            28% {
                transform: scale(0.96);
                background: radial-gradient(circle at center, #ffc8c5 0%, #f87171 70%, #dc2626 100%);
                box-shadow: 0 0 10px 1px rgba(248, 113, 113, 0.35);
            }

            45% {
                transform: scale(1.04);
                background: radial-gradient(circle at center, #ff9b95 0%, #ef4444 68%, #991b1b 100%);
                box-shadow: 0 0 14px 3px rgba(248, 113, 113, 0.42);
            }

            70% {
                transform: scale(0.92);
                background: radial-gradient(circle at center, #ffd7d5 0%, #feb2b2 70%, rgba(255, 255, 255, 0.84) 100%);
                box-shadow: 0 0 4px 0 rgba(248, 113, 113, 0.22);
            }

            100% {
                transform: scale(0.84);
                background: radial-gradient(circle at center, #fff8f8 0%, #ffeaea 72%, rgba(255, 255, 255, 0.88) 100%);
                box-shadow: 0 0 0 0 rgba(248, 113, 113, 0.16);
            }
        }

        @keyframes badge-status-glow {
            0% {
                opacity: 0;
                transform: scale(0.65);
            }

            30% {
                opacity: 0.5;
                transform: scale(1.4);
            }

            48% {
                opacity: 0.3;
                transform: scale(1.75);
            }

            72% {
                opacity: 0.08;
                transform: scale(1.15);
            }

            100% {
                opacity: 0;
                transform: scale(0.65);
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

        .form-alert--success {
            color: #047857;
            background: rgba(16, 185, 129, 0.14);
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
            display: block;
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

        .input-container.is-error .form-input,
        .form-input[aria-invalid="true"] {
            border-color: #dc2626;
        }

        .form-input:focus {
            outline: none;
            border-color: #000000;
            box-shadow: none;
            background: #fff;
        }

        footer.footer .footer__theme-toggle {
            background: transparent;
            border: none;
            padding: 0;
        }

        :root[data-theme='dark'] .login-card {
            background: rgba(18, 23, 36, 0.92);
            border-color: rgba(148, 163, 184, 0.28);
            box-shadow: 0 28px 48px -22px rgba(15, 23, 42, 0.55);
        }

        :root[data-theme='dark'] .login-card__badge {
            background: rgba(24, 30, 44, 0.95);
            border-color: rgba(148, 163, 184, 0.3);
            color: rgba(226, 232, 240, 0.92);
        }

        :root[data-theme='dark'] .login-card__title {
            color: #ffffff;
        }

        .input-container.is-error .form-label,
        .form-input[aria-invalid="true"] ~ .form-label {
            color: #dc2626;
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

        .form-hint {
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .form-field-error {
            margin-top: 0.4rem;
            font-size: 0.875rem;
            color: #dc2626;
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
            gap: 0.5rem;
            border-radius: 14px;
            font-weight: 600;
            font-size: 1rem;
            padding: 0.95rem 1rem;
            cursor: pointer;
            border: none;
        }

        .btn__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn__icon svg {
            width: 1.25rem;
            height: 1.25rem;
        }

        .btn-primary {
            background: var(--auth-accent);
            color: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            border: none;
        }

        .btn-primary:hover,
        .btn-primary:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px -18px rgba(59, 130, 246, 0.45);
            background: var(--auth-accent-dark);
            outline: none;
        }

        :root[data-theme='dark'] .btn-primary {
            box-shadow: 0 16px 30px -18px rgba(79, 70, 229, 0.55);
        }

        :root[data-theme='dark'] .btn-primary:disabled,
        :root[data-theme='dark'] .btn-primary[disabled] {
            background: rgba(148, 163, 184, 0.18);
            color: rgba(148, 163, 184, 0.65);
            box-shadow: none;
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

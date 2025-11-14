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
            --auth-link: #c5444e;
            --auth-link-hover: #a6343d;
            --auth-label-bg: #ffffff;
            --auth-label-bg-floating: var(--auth-surface-contrast, #ffffff);
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
            --auth-muted: var(--text-tertiary, rgba(203, 213, 225, 0.82));
            --auth-toggle-track: rgba(148, 163, 184, 0.32);
            --auth-button-disabled-bg: rgba(148, 163, 184, 0.18);
            --auth-button-disabled-text: rgba(148, 163, 184, 0.65);
            --auth-link: #f87171;
            --auth-link-hover: #fb7185;
            --auth-label-bg: rgba(28, 33, 48, 0.96);
            --auth-label-bg-floating: rgba(24, 30, 44, 0.98);
        }

        .container.container--auth {
            flex: 1;
            height: calc(100dvh - 36px);
            display: flex;
            flex-direction: column;
            padding: 0;
            background: var(--auth-surface-contrast, #ffffff);
        }

        .main-grid.main-grid--auth {
            flex: 1;
            display: flex;
            justify-content: center;
            padding-block: 0;
        }

        .main-grid--auth > .register-page {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        .body--auth-flow .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100%;
            z-index: 10;
            background: transparent;
        }

        .body--auth-flow .footer .footer__wrapper {
            height: fit-content;
            margin: 0 auto;
            width: 100%;
            padding-block: calc(var(--spacing-1, 1rem) * 0.75);
            padding-inline: clamp(1.5rem, 5vw, 3rem);
            border-radius: 0;
            align-items: center;
            background: var(--surface-alt);
            row-gap: 0.5rem;
        }

        :root[data-theme='dark'] footer.footer .footer__wrapper {
            background: rgba(18, 23, 36, 0.92);
            box-shadow: none;
        }

        .body--auth-flow .login-page .container {
            width: 100%;
            margin: 0;
            padding: clamp(2.25rem, 12vw, 3.25rem) clamp(1.25rem, 6vw, 1.75rem);
            padding-top: 1.5rem;
        }

        .login-card {
            position: relative;
            background: var(--auth-surface-contrast, #ffffff);
            border-radius: 22px;
            padding: clamp(2rem, 8vw, 2.75rem);
            padding-top: clamp(2.5rem, 10vw, 3rem);
            box-shadow: 0 22px 45px -24px rgba(15, 23, 42, 0.4);
            border: 1px solid var(--auth-field-border);
            color: var(--auth-field-text);
            display: flex;
            flex-direction: column;
            gap: 0;
            overflow: visible;
        }

        .body--auth-flow .login-card {
            box-shadow: none;
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

        .login-card__brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.75rem;
        }

        .login-card__logo {
            display: flex;
            justify-content: flex-start;
            flex-shrink: 0;
        }

        .login-card__logo--mobile {
            display: block;
            text-align: center;
            margin: 0 auto clamp(1.5rem, 8vw, 2rem);
        }

        .login-card__logo--mobile svg,
        .login-card__logo--mobile img {
            margin: 0 auto;
        }

        .login-card__logo--desktop {
            display: none;
        }

        .body--auth-flow .login-card__logo svg,
        .body--auth-flow .login-card__logo img {
            width: 7rem;
            height: auto;
        }

        .body--auth-flow .login-card__layout {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .login-card__intro,
        .login-card__body {
            display: flex;
            flex-direction: column;
            gap: clamp(1.5rem, 5vw, 2rem);
        }

        .login-card__body {
            gap: clamp(1.25rem, 4vw, 1.75rem);
        }

        .login-card__header {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            text-align: left;
            align-items: flex-start;
            margin: 0;
        }

        .login-card__title {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--auth-field-text);
            line-height: 1.15;
        }

        .login-card__subtitle {
            margin: 0;
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--auth-muted);
            text-wrap: pretty;
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

        :root[data-theme='dark'] .form-alert {
            color: #fca5a5;
            background: rgba(248, 113, 113, 0.24);
        }

        :root[data-theme='dark'] .form-alert--success {
            color: #34d399;
            background: rgba(16, 185, 129, 0.24);
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
            border: 1px solid var(--auth-field-border);
            padding: 0.95rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: var(--auth-field-text);
            background: var(--auth-field-bg);
            transition: border-color 0.2s ease, color 0.2s ease, background-color 0.2s ease;
        }

        .input-container.is-error .form-input,
        .form-input[aria-invalid="true"] {
            border-color: #dc2626;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--auth-accent);
            box-shadow: none;
            background: var(--auth-field-bg);
        }

        footer.footer .footer__theme-toggle {
            background: transparent;
            border: none;
            padding: 0;
        }

        :root[data-theme='dark'] .login-card {
            background: rgba(18, 23, 36, 0.92);
            border-color: rgba(148, 163, 184, 0.28);
        }

        :root[data-theme='dark'] .body--auth-flow .login-card {
            box-shadow: none;
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
            background: var(--auth-label-bg);
            padding: 0 4px;
            font-size: 0.95rem;
            color: var(--auth-muted);
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
            background: var(--auth-label-bg-floating);
        }

        .form-input:focus ~ .form-label {
            color: var(--auth-accent-dark);
        }

        .form-input:not(:placeholder-shown) ~ .form-label {
            color: var(--auth-field-text);
        }

        .form-hint {
            margin-top: 0.6rem;
            font-size: 0.85rem;
            color: var(--auth-muted);
        }

        .form-field-error {
            margin-top: 0.4rem;
            font-size: 0.875rem;
            color: #dc2626;
        }

        .auth-form__meta {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .remember-me {
            display: inline-flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.95rem;
            color: var(--auth-field-text);
        }

        .remember-me input {
            width: 1rem;
            height: 1rem;
            accent-color: var(--auth-field-text);
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
            color: var(--auth-muted);
            transition: color 0.2s ease;
        }

        .toggle-password:hover,
        .toggle-password:focus-visible {
            color: var(--auth-field-text);
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
            margin-left: 0;
            font-size: 0.95rem;
            color: var(--auth-link);
            font-weight: 400;
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color 0.2s ease, text-decoration-color 0.2s ease;
        }

        .form-link:hover,
        .form-link:focus-visible {
            color: var(--auth-link-hover);
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
            border-radius: 999px;
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

        .body--auth-flow .btn__icon svg {
            width: 1.75rem;
            height: auto;
        }

        .body--auth-flow .btn-primary {
            background: var(--auth-accent);
            color: #ffffff;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease;
            border: none;
            height: 3rem;
            padding-block: 0;
        }

        .body--auth-flow .btn-primary:hover,
        .body--auth-flow .btn-primary:focus-visible {
            transform: translateY(-1px);
            box-shadow: 0 14px 32px -18px rgba(59, 130, 246, 0.45);
            background: var(--auth-accent-dark);
            outline: none;
        }

        :root[data-theme='dark'] .body--auth-flow .btn-primary {
            box-shadow: 0 16px 30px -18px rgba(79, 70, 229, 0.55);
        }

        :root[data-theme='dark'] .body--auth-flow .btn-primary:disabled,
        :root[data-theme='dark'] .body--auth-flow .btn-primary[disabled] {
            background: rgba(148, 163, 184, 0.18);
            color: rgba(148, 163, 184, 0.65);
            box-shadow: none;
        }

        .info-cta {
            margin: 0;
            text-align: center;
            font-size: 1rem;
            color: var(--auth-muted);
        }

        .info-cta a {
            display: block;
            margin-top: 0.35rem;
            color: var(--auth-link);
            font-weight: 600;
            text-decoration: none;
            text-underline-offset: 3px;
            transition: color 0.2s ease, text-decoration-color 0.2s ease;
        }

        .info-cta--login {
            display: flex;
            align-items: center;
            justify-content: space-between;
            column-gap: 0.5rem;
        }

        .info-cta--login a {
            margin-top: 0;
            display: inline-flex;
        }

        .info-cta--login-desktop {
            display: none;
        }

        .info-cta a:hover,
        .info-cta a:focus-visible {
            color: var(--auth-link-hover);
            text-decoration: underline;
            outline: none;
        }

        .body--auth-flow .footer__brand {
            width: 100%;
        }

        .body--auth-flow .footer__text {
            margin-right: auto;
        }

        .body--auth-flow .footer__legal ul {
            flex-wrap: wrap;
            row-gap: 0.25rem;
            column-gap: 0.75rem;
        }

        .body--auth-flow .footer__legal a {
            font-size: 0.7rem;
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

        @media (min-width: 420px) {
            .info-cta--login {
                column-gap: 0.75rem;
            }

            .info-cta--login a {
                align-items: center;
            }
        }

        @media (min-width: 520px) {
            .info-cta--login {
                justify-content: flex-start;
            }

            .auth-form__meta {
                flex-direction: row;
                justify-content: space-between;
            }

            .body--auth-flow .login-card__header,
            .body--auth-flow .login-card__body > .auth-form {
                max-width: 360px;
                width: 100%;
                margin-inline: auto;
            }
        }

        @media (min-width: 640px) {
            .main-grid.main-grid--auth {
                padding-block: clamp(2rem, 6vh, 4rem);
            }

            .container.container--auth {
                padding-inline: clamp(1.5rem, 5vw, 3rem);
                background: transparent;
            }

            .login-page .container {
                width: min(100%, 460px);
                margin: 0 auto;
                padding: clamp(3rem, 8vw, 3.5rem) clamp(1.75rem, 5vw, 2.5rem);
            }

            .login-card {
                padding: var(--spacing-3, 3rem);
            }

            .login-card__brand {
                flex-direction: column;
                align-items: center;
                gap: 0.5rem;
                margin-bottom: clamp(24px, 36.76px, 32px);
            }

            .login-card__logo--mobile {
                display: none;
            }

            .login-card__logo--desktop {
                display: flex;
            }

            .login-card__logo {
                justify-content: center;
            }

            .login-card__logo svg,
            .login-card__logo img {
                width: min(200px, 60vw);
            }

            .login-card__header {
                align-items: center;
                text-align: center;
            }

            .login-card__title {
                font-size: clamp(1.75rem, 2.6vw, 2.1rem);
            }

            .login-card__subtitle {
                font-size: 0.95rem;
            }

            .info-cta a {
                display: inline;
                margin-top: 0;
            }

            .info-cta--lost,
            .info-cta--reset {
                display: flex;
                flex-direction: column;
                align-items: flex-end;
                justify-content: center;
                gap: 0.5rem;
                text-align: right;
            }

            .info-cta--lost a,
            .info-cta--reset a {
                margin-top: 0;
            }

            .auth-form__meta {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }

            .form-link {
                margin-left: auto;
            }
        }

        @media (min-width: 720px) {
            .body--auth-flow .footer {
                position: fixed;
                bottom: 0;
                left: 0;
                width: 100%;
            }

            .body--auth-flow .footer__brand {
                width: auto;
            }

            .body--auth-flow .footer__text {
                margin-right: 0;
            }

            .body--auth-flow .footer__legal ul {
                flex-wrap: nowrap;
            }

            .body--auth-flow .footer__legal a {
                font-size: 0.85rem;
            }
        }

        @media (min-width: 1024px) {
            .login-card {
                padding-block: 4rem;
            }

            .body--auth-flow .login-page .container {
                width: min(100%, 1080px);
            }

            .body--auth-flow .login-card__layout {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                align-items: center;
                justify-items: stretch;
                gap: 3rem;
            }

            .body--auth-flow .login-card__intro {
                gap: 2rem;
                padding-inline-end: 2.5rem;
                border-inline-end: 1px solid var(--auth-field-border);
                align-self: stretch;
                justify-self: center;
                max-width: 440px;
                width: 100%;
            }

            :root[data-theme='dark'] .body--auth-flow .login-card__intro {
                border-inline-end-color: rgba(148, 163, 184, 0.4);
            }

            .body--auth-flow .login-card__header,
            .body--auth-flow form#loginform {
                max-width: 420px;
                width: 100%;
                margin-inline: auto;
            }

            .body--auth-flow .login-card__brand {
                margin-bottom: 0;
                align-items: flex-start;
                text-align: left;
            }

            .body--auth-flow .login-card__header {
                align-items: flex-start;
                text-align: left;
            }

            .body--auth-flow .login-card__body {
                gap: clamp(1.5rem, 4vw, 2.25rem);
                justify-self: center;
                align-items: center;
                max-width: 440px;
                width: 100%;
            }

            .body--auth-flow .login-card__body > .auth-form {
                width: 100%;
            }

            .info-cta--login-mobile {
                display: none;
            }

            .info-cta--login-desktop {
                display: flex;
                align-items: center;
                justify-content: flex-start;
                column-gap: 0.5rem;
            }

            .body--login-recovery .login-card__title {
                font-size: 1.5rem;
            }
        }
    </style>

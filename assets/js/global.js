// assets/js/global.js

console.log("GO360 script cargado");

(function () {
        const themeStorageKey = "go_theme";
        const logoExitStorageKey = "go_logo_exit_state";
        const root = document.documentElement;
        const prefersDark = window.matchMedia("(prefers-color-scheme: dark)");

        const getStoredTheme = () => {
                try {
                        const stored = localStorage.getItem(themeStorageKey);
                        return stored === "dark" || stored === "light" ? stored : null;
                } catch (error) {
                        return null;
                }
        };

        const setStoredTheme = (value) => {
                try {
                        localStorage.setItem(themeStorageKey, value);
                } catch (error) {
                        // Ignorar almacenamiento no disponible
                }
        };

        const applyThemeAttribute = (theme) => {
                if (theme === "dark") {
                        root.setAttribute("data-theme", "dark");
                } else {
                        root.removeAttribute("data-theme");
                }
        };

        const initialTheme = getStoredTheme() || (prefersDark.matches ? "dark" : "light");
        applyThemeAttribute(initialTheme);

        document.addEventListener("DOMContentLoaded", function () {
                const themeToggle = document.querySelector("[data-theme-toggle]");
                const statusOn = themeToggle ? themeToggle.getAttribute("data-theme-status-on") || "Activado" : "Activado";
                const statusOff = themeToggle ? themeToggle.getAttribute("data-theme-status-off") || "Desactivado" : "Desactivado";
                const labelOn = themeToggle ? themeToggle.getAttribute("data-theme-label-on") || "Cambiar a modo claro" : "Cambiar a modo claro";
                const labelOff = themeToggle ? themeToggle.getAttribute("data-theme-label-off") || "Activar modo oscuro" : "Activar modo oscuro";

                const updateThemeToggle = (theme) => {
                        if (!themeToggle) {
                                return;
                        }
                        const isDark = theme === "dark";
                        themeToggle.setAttribute("aria-checked", String(isDark));
                        const statusNode = themeToggle.querySelector("[data-theme-status]");
                        if (statusNode) {
                                statusNode.textContent = isDark ? statusOn : statusOff;
                        }
                        const label = isDark ? labelOn : labelOff;
                        themeToggle.setAttribute("title", label);
                        themeToggle.setAttribute("aria-label", label);
                };

                updateThemeToggle(initialTheme);

                if (themeToggle) {
                        themeToggle.addEventListener("click", () => {
                                const isDark = root.getAttribute("data-theme") === "dark";
                                const nextTheme = isDark ? "light" : "dark";
                                applyThemeAttribute(nextTheme);
                                updateThemeToggle(nextTheme);
                                setStoredTheme(nextTheme);
                        });
                }

                const handlePreferenceChange = (event) => {
                        if (getStoredTheme()) {
                                return;
                        }
                        const nextTheme = event.matches ? "dark" : "light";
                        applyThemeAttribute(nextTheme);
                        updateThemeToggle(nextTheme);
                };

                if (typeof prefersDark.addEventListener === "function") {
                        prefersDark.addEventListener("change", handlePreferenceChange);
                } else if (typeof prefersDark.addListener === "function") {
                        prefersDark.addListener(handlePreferenceChange);
                }

                // Toggle menú móvil
                const btn = document.querySelector(".top-bar__hamburger");
                const menu = document.querySelector(".mobile-menu");
                if (btn && menu) {
                        btn.addEventListener("click", () => {
                                menu.style.display = menu.style.display === "block" ? "none" : "block";
                        });
                }

                // Toggle menú de perfil
                const profileBtn = document.querySelector(".top-bar__profile-link");
                const profileMenu = document.querySelector(".top-bar__profile-menu");
                let closeProfileMenu = null;

                if (profileBtn && profileMenu) {
                        const openMenu = () => {
                                profileMenu.classList.add("visible");
                                profileMenu.setAttribute("aria-hidden", "false");
                                profileBtn.setAttribute("aria-expanded", "true");
                        };

                        const closeMenu = () => {
                                profileMenu.classList.remove("visible");
                                profileMenu.setAttribute("aria-hidden", "true");
                                profileBtn.setAttribute("aria-expanded", "false");
                        };

                        closeProfileMenu = closeMenu;

                        profileBtn.addEventListener("click", (event) => {
                                event.preventDefault();
                                event.stopPropagation();

                                if (profileMenu.classList.contains("visible")) {
                                        closeMenu();
                                } else {
                                        openMenu();
                                }
                        });

                        document.addEventListener("click", (event) => {
                                if (
                                        profileMenu.classList.contains("visible") &&
                                        !profileMenu.contains(event.target) &&
                                        event.target !== profileBtn
                                ) {
                                        closeMenu();
                                }
                        });

                        document.addEventListener("keydown", (event) => {
                                if (event.key === "Escape" && profileMenu.classList.contains("visible")) {
                                        closeMenu();
                                        profileBtn.focus();
                                }
                        });

                        profileMenu.addEventListener("click", (event) => {
                                event.stopPropagation();
                        });
                }

                const clearStoredLogoExit = () => {
                        try {
                                sessionStorage.removeItem(logoExitStorageKey);
                        } catch (error) {
                                // Ignorar errores de sessionStorage (modo incógnito, etc.)
                        }
                };

                const markLogoExitForNextLoad = () => {
                        try {
                                sessionStorage.setItem(logoExitStorageKey, "pending");
                        } catch (error) {
                                // Ignorar errores de sessionStorage (modo incógnito, etc.)
                        }
                };

                // Nota: para volver a un logo estático elimina todo el bloque relacionado con
                // logoTransition (helpers de sessionStorage, clases CSS y listeners) y borra la
                // animación en assets/css/global.css. Deja el SVG sin clases de animación.

                const logo = document.querySelector(".top-bar__logo--svg");
                if (logo) {
                        const logoPaths = Array.from(logo.querySelectorAll("path"));
                        const pathMeta = logoPaths.map((path) => {
                                const length = path.getTotalLength();
                                const fill = path.closest(".top-bar__logo-vo")
                                        ? "var(--logo-vo-fill)"
                                        : "var(--logo-360-fill)";
                                path.style.setProperty("--path-length", `${length}`);
                                path.style.strokeDasharray = `${length}`;
                                path.style.strokeDashoffset = "0";
                                path.style.setProperty("--logo-path-fill", fill);
                                path.style.stroke = fill;
                                path.style.fill = fill;
                                return { path, length, fill };
                        });

                        const resetPathsToFill = () => {
                                pathMeta.forEach(({ path, fill }) => {
                                        path.style.strokeDashoffset = "0";
                                        path.style.fill = fill;
                                });
                        };

                        const prepPathsForEnter = () => {
                                pathMeta.forEach(({ path, length }) => {
                                        path.style.strokeDashoffset = `${length}`;
                                        path.style.fill = "transparent";
                                });
                        };

                        const prepPathsForExit = () => {
                                pathMeta.forEach(({ path, fill }) => {
                                        path.style.strokeDashoffset = "0";
                                        path.style.fill = fill;
                                });
                        };

                        logo.classList.add("top-bar__logo--animate");
                        resetPathsToFill();

                        const runLogoEnter = () => {
                                prepPathsForEnter();
                                document.documentElement.classList.remove("logo-transition-pending");
                                clearStoredLogoExit();
                                logo.classList.remove("top-bar__logo--exit");
                                logo.classList.remove("top-bar__logo--enter");
                                void logo.offsetWidth;
                                logo.classList.add("top-bar__logo--enter");
                        };

                        const runLogoExit = () => {
                                prepPathsForExit();
                                markLogoExitForNextLoad();
                                logo.classList.remove("top-bar__logo--enter");
                                logo.classList.remove("top-bar__logo--exit");
                                void logo.offsetWidth;
                                logo.classList.add("top-bar__logo--exit");
                        };

                        const waitForLogoExit = () =>
                                new Promise((resolve) => {
                                        let fallback;
                                        const handle = (event) => {
                                                if (event.animationName === "top-bar-logo-container-out") {
                                                        window.clearTimeout(fallback);
                                                        logo.removeEventListener("animationend", handle);
                                                        resolve();
                                                }
                                        };

                                        fallback = window.setTimeout(() => {
                                                logo.removeEventListener("animationend", handle);
                                                resolve();
                                        }, 1200);

                                        logo.addEventListener("animationend", handle);
                                });

                        const clearAnimationClass = (className) => {
                                requestAnimationFrame(() => {
                                        logo.classList.remove(className);
                                });
                        };

                        logo.addEventListener("animationend", (event) => {
                                if (event.animationName === "top-bar-logo-container-in") {
                                        clearAnimationClass("top-bar__logo--enter");
                                        resetPathsToFill();
                                }
                        });

                        const runEnter = () => {
                                runLogoEnter();
                        };

                        runEnter();
                        window.addEventListener("pageshow", runEnter);

                        const shouldIgnoreClick = (event, anchor) => {
                                if (event.defaultPrevented) {
                                        return true;
                                }

                                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                                        return true;
                                }

                                if (!anchor || anchor.hasAttribute("data-no-logo-transition")) {
                                        return true;
                                }

                                if (anchor.target && anchor.target !== "" && anchor.target !== "_self") {
                                        return true;
                                }

                                if (anchor.hasAttribute("download")) {
                                        return true;
                                }

                                const rel = anchor.getAttribute("rel");
                                if (rel && rel.split(" ").includes("external")) {
                                        return true;
                                }

                                const hrefValue = anchor.getAttribute("href");
                                if (!hrefValue || hrefValue.startsWith("#")) {
                                        return true;
                                }

                                if (hrefValue.startsWith("mailto:") || hrefValue.startsWith("tel:")) {
                                        return true;
                                }

                                try {
                                        const url = new URL(anchor.href);
                                        if (url.origin !== window.location.origin) {
                                                return true;
                                        }
                                } catch (error) {
                                        return true;
                                }

                                return false;
                        };

                        document.addEventListener("click", (event) => {
                                const anchor = event.target.closest("a");
                                if (shouldIgnoreClick(event, anchor)) {
                                        return;
                                }

                                event.preventDefault();

                                const destination = anchor.href;
                                const exitPromise = waitForLogoExit();

                                runLogoExit();

                                if (closeProfileMenu && profileMenu && profileMenu.classList.contains("visible")) {
                                        closeProfileMenu();
                                }

                                const navigate = () => {
                                        window.location.href = destination;
                                };

                                exitPromise.then(() => {
                                        const { startViewTransition } = document;
                                        if (typeof startViewTransition === "function") {
                                                try {
                                                        startViewTransition(() => {
                                                                navigate();
                                                        });
                                                        return;
                                                } catch (error) {
                                                        navigate();
                                                        return;
                                                }
                                        }

                                        navigate();
                                });
                        });
                } else {
                        document.documentElement.classList.remove("logo-transition-pending");
                        clearStoredLogoExit();
                }

                const newLinks = document.querySelectorAll('[data-reset-draft]');
                newLinks.forEach((link) => {
                        link.addEventListener("click", () => {
                                localStorage.removeItem("go_draft_id");
                                localStorage.removeItem("go_draft_uuid");
                        });
                });
        });
})();

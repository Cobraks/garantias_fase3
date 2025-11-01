// assets/js/global.js

console.log("GO360 script cargado");

(function () {
        const themeStorageKey = "go_theme";
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

        const broadcastThemeChange = (theme) => {
                if (typeof window === "undefined" || typeof window.dispatchEvent !== "function") {
                        return;
                }

                let event;
                if (typeof window.CustomEvent === "function") {
                        event = new CustomEvent("go:theme-change", { detail: { theme } });
                } else if (
                        typeof document !== "undefined" &&
                        typeof document.createEvent === "function"
                ) {
                        event = document.createEvent("CustomEvent");
                        event.initCustomEvent("go:theme-change", false, false, { theme });
                }

                if (event) {
                        window.dispatchEvent(event);
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
                broadcastThemeChange(initialTheme);

                if (themeToggle) {
                        themeToggle.addEventListener("click", () => {
                                const isDark = root.getAttribute("data-theme") === "dark";
                                const nextTheme = isDark ? "light" : "dark";
                                applyThemeAttribute(nextTheme);
                                updateThemeToggle(nextTheme);
                                setStoredTheme(nextTheme);
                                broadcastThemeChange(nextTheme);
                        });
                }

                const handlePreferenceChange = (event) => {
                        if (getStoredTheme()) {
                                return;
                        }
                        const nextTheme = event.matches ? "dark" : "light";
                        applyThemeAttribute(nextTheme);
                        updateThemeToggle(nextTheme);
                        broadcastThemeChange(nextTheme);
                };

                if (typeof prefersDark.addEventListener === "function") {
                        prefersDark.addEventListener("change", handlePreferenceChange);
                } else if (typeof prefersDark.addListener === "function") {
                        prefersDark.addListener(handlePreferenceChange);
                }

                // Toggle menú móvil
                const mobileToggle = document.querySelector(".top-bar__hamburger");
                const mobileMenu = document.querySelector(".mobile-menu");
                if (mobileToggle && mobileMenu) {
                        const isMenuOpen = () => mobileMenu.classList.contains("mobile-menu--visible");

                        const openMenu = () => {
                                if (isMenuOpen()) {
                                        return;
                                }
                                mobileMenu.classList.add("mobile-menu--visible");
                                mobileMenu.setAttribute("aria-hidden", "false");
                                mobileToggle.setAttribute("aria-expanded", "true");
                        };

                        const closeMenu = () => {
                                if (!isMenuOpen()) {
                                        return;
                                }
                                mobileMenu.classList.remove("mobile-menu--visible");
                                mobileMenu.setAttribute("aria-hidden", "true");
                                mobileToggle.setAttribute("aria-expanded", "false");
                        };

                        const handleToggle = (event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                if (isMenuOpen()) {
                                        closeMenu();
                                } else {
                                        openMenu();
                                }
                        };

                        const handleDocumentClick = (event) => {
                                if (!isMenuOpen()) {
                                        return;
                                }
                                if (!mobileMenu.contains(event.target) && event.target !== mobileToggle && !mobileToggle.contains(event.target)) {
                                        closeMenu();
                                }
                        };

                        const handleKeydown = (event) => {
                                if (event.key === "Escape" && isMenuOpen()) {
                                        closeMenu();
                                        mobileToggle.focus();
                                }
                        };

                        const handleResize = () => {
                                if (window.innerWidth >= 1024) {
                                        closeMenu();
                                }
                        };

                        mobileToggle.addEventListener("click", handleToggle);
                        mobileMenu.addEventListener("click", (event) => {
                                event.stopPropagation();
                        });
                        document.addEventListener("click", handleDocumentClick);
                        document.addEventListener("keydown", handleKeydown);
                        window.addEventListener("resize", handleResize);
                }

                // Toggle menú de perfil
                const profileBtn = document.querySelector(".top-bar__profile-link");
                const profileMenu = document.querySelector(".top-bar__profile-menu");

                if (profileBtn && profileMenu) {
                        const emitProfileEvent = (name) => {
                                if (typeof window === "undefined") {
                                        return;
                                }
                                if (typeof window.CustomEvent === "function") {
                                        window.dispatchEvent(new CustomEvent(name));
                                } else if (typeof document !== "undefined" && typeof document.createEvent === "function") {
                                        const custom = document.createEvent("CustomEvent");
                                        custom.initCustomEvent(name, false, false, {});
                                        window.dispatchEvent(custom);
                                }
                        };

                        const openMenu = () => {
                                if (profileMenu.classList.contains("visible")) {
                                        return;
                                }
                                emitProfileEvent("go360:notifications:close");
                                profileMenu.classList.add("visible");
                                profileMenu.setAttribute("aria-hidden", "false");
                                profileBtn.setAttribute("aria-expanded", "true");
                                emitProfileEvent("go360:profile:opened");
                        };

                        const closeMenu = () => {
                                if (!profileMenu.classList.contains("visible")) {
                                        return;
                                }
                                profileMenu.classList.remove("visible");
                                profileMenu.setAttribute("aria-hidden", "true");
                                profileBtn.setAttribute("aria-expanded", "false");
                                emitProfileEvent("go360:profile:closed");
                        };

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

                        window.addEventListener("go360:notifications:opened", () => {
                                closeMenu();
                        });

                        window.addEventListener("go360:profile:close", () => {
                                closeMenu();
                        });
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

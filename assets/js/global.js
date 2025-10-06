// assets/js/global.js

console.log("GO360 script cargado");

(function () {
        document.addEventListener("DOMContentLoaded", function () {
                const rootElement = document.documentElement;
                const THEME_STORAGE_KEY = "go360-theme";
                const registeredToggles = new WeakSet();
                const systemQuery = window.matchMedia ? window.matchMedia("(prefers-color-scheme: dark)") : null;

                const syncToggleState = (theme) => {
                        document
                                .querySelectorAll('[data-theme-toggle] input[type="checkbox"]')
                                .forEach((input) => {
                                        input.checked = theme === "dark";
                                        input.setAttribute("aria-checked", input.checked ? "true" : "false");
                                });
                };

                const applyTheme = (theme) => {
                        const normalized = theme === "dark" ? "dark" : "light";
                        if (rootElement.getAttribute("data-theme") !== normalized) {
                                rootElement.setAttribute("data-theme", normalized);
                        }
                        syncToggleState(normalized);
                };

                const storedPreference = (function () {
                        try {
                                const stored = localStorage.getItem(THEME_STORAGE_KEY);
                                return stored === "dark" || stored === "light" ? stored : null;
                        } catch (error) {
                                console.warn("Unable to read stored theme preference", error);
                                return null;
                        }
                })();

                const initialTheme = storedPreference || (systemQuery && systemQuery.matches ? "dark" : "light");
                applyTheme(initialTheme);

                const persistTheme = (theme) => {
                        try {
                                localStorage.setItem(THEME_STORAGE_KEY, theme);
                        } catch (error) {
                                console.warn("Unable to persist theme preference", error);
                        }
                };

                const handleSystemChange = (event) => {
                        if (!event) {
                                return;
                        }

                        try {
                                const stored = localStorage.getItem(THEME_STORAGE_KEY);
                                if (stored === "dark" || stored === "light") {
                                        return;
                                }
                        } catch (error) {
                                console.warn("Unable to read stored theme preference", error);
                        }

                        applyTheme(event.matches ? "dark" : "light");
                };

                if (systemQuery) {
                        if (typeof systemQuery.addEventListener === "function") {
                                systemQuery.addEventListener("change", handleSystemChange);
                        } else if (typeof systemQuery.addListener === "function") {
                                systemQuery.addListener(handleSystemChange);
                        }
                }

                const registerToggle = (toggle) => {
                        if (!toggle) {
                                return;
                        }

                        const input = toggle.matches("input[type='checkbox']")
                                ? toggle
                                : toggle.querySelector("input[type='checkbox']");

                        if (!input || registeredToggles.has(input)) {
                                return;
                        }

                        registeredToggles.add(input);
                        input.checked = rootElement.getAttribute("data-theme") === "dark";
                        input.setAttribute("aria-checked", input.checked ? "true" : "false");
                        input.addEventListener("change", (event) => {
                                const isDark = Boolean(event.target.checked);
                                const nextTheme = isDark ? "dark" : "light";
                                persistTheme(nextTheme);
                                applyTheme(nextTheme);
                        });
                };

                document.querySelectorAll("[data-theme-toggle]").forEach(registerToggle);

                if (typeof MutationObserver === "function") {
                        const observer = new MutationObserver((mutations) => {
                                mutations.forEach((mutation) => {
                                        mutation.addedNodes.forEach((node) => {
                                                if (!(node instanceof HTMLElement)) {
                                                        return;
                                                }

                                                if (node.matches && node.matches("[data-theme-toggle]")) {
                                                        registerToggle(node);
                                                }

                                                node
                                                        .querySelectorAll?.("[data-theme-toggle]")
                                                        .forEach((element) => registerToggle(element));
                                        });
                                });
                        });

                        observer.observe(document.body, { childList: true, subtree: true });
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

                const newLinks = document.querySelectorAll('[data-reset-draft]');
                newLinks.forEach((link) => {
                        link.addEventListener("click", () => {
                                localStorage.removeItem("go_draft_id");
                                localStorage.removeItem("go_draft_uuid");
                        });
                });
        });
})();

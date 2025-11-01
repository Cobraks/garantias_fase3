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

        const PANEL_HISTORY_KEY = "__go360Panel";
        const panelHistory = (() => {
                if (typeof window === "undefined") {
                        return null;
                }
                const existing = window.go360PanelHistory;
                if (
                        existing &&
                        typeof existing === "object" &&
                        typeof existing.push === "function" &&
                        typeof existing.close === "function"
                ) {
                        return existing;
                }

                const stack = [];
                const mediaQuery =
                        typeof window.matchMedia === "function"
                                ? window.matchMedia("(max-width: 1279px)")
                                : null;

                const isEnabled = () => !mediaQuery || mediaQuery.matches;

                const readState = () =>
                        history.state && typeof history.state === "object"
                                ? history.state
                                : {};

                const writeState = (depth, panelId = null) => {
                        if (!isEnabled() || typeof history.replaceState !== "function") {
                                return;
                        }
                        const currentState = readState();
                        const nextState = { ...currentState };
                        nextState[PANEL_HISTORY_KEY] = { depth };
                        if (panelId) {
                                nextState[PANEL_HISTORY_KEY].panelId = panelId;
                        }
                        history.replaceState(
                                nextState,
                                document.title,
                                window.location.href
                        );
                };

                const push = (panelId, closeCallback) => {
                        if (
                                !isEnabled() ||
                                typeof history.pushState !== "function" ||
                                typeof panelId !== "string" ||
                                panelId === ""
                        ) {
                                return;
                        }
                        const top = stack[stack.length - 1];
                        if (top && top.id === panelId) {
                                top.close =
                                        typeof closeCallback === "function"
                                                ? closeCallback
                                                : top.close;
                                writeState(stack.length, panelId);
                                return;
                        }
                        const entry = {
                                id: panelId,
                                close:
                                        typeof closeCallback === "function"
                                                ? closeCallback
                                                : null,
                        };
                        stack.push(entry);
                        const currentState = readState();
                        const nextState = {
                                ...currentState,
                                [PANEL_HISTORY_KEY]: {
                                        depth: stack.length,
                                        panelId,
                                },
                        };
                        history.pushState(nextState, document.title, window.location.href);
                };

                const close = (panelId) => {
                        const index = stack.findIndex((entry) => entry.id === panelId);
                        if (index === -1) {
                                return;
                        }
                        stack.splice(index, 1);
                        if (!isEnabled()) {
                                return;
                        }
                        const top = stack[stack.length - 1] || null;
                        writeState(stack.length, top ? top.id : null);
                };

                const clear = () => {
                        stack.length = 0;
                        if (!isEnabled()) {
                                return;
                        }
                        writeState(0, null);
                };

                const handlePopState = (event) => {
                        if (!isEnabled()) {
                                return;
                        }
                        const state =
                                event.state && typeof event.state === "object"
                                        ? event.state
                                        : {};
                        const meta = state[PANEL_HISTORY_KEY];
                        const targetDepth =
                                meta && typeof meta.depth === "number" ? meta.depth : 0;
                        const targetId =
                                meta && typeof meta.panelId === "string"
                                        ? meta.panelId
                                        : null;

                        while (stack.length > targetDepth) {
                                const entry = stack.pop();
                                if (entry && typeof entry.close === "function") {
                                        entry.close({ fromHistory: true, silent: true });
                                }
                        }

                        if (
                                stack.length > 0 &&
                                targetId &&
                                stack[stack.length - 1].id !== targetId
                        ) {
                                const seen = new Set();
                                while (
                                        stack.length > 0 &&
                                        stack[stack.length - 1].id !== targetId &&
                                        !seen.has(stack[stack.length - 1].id)
                                ) {
                                        const entry = stack.pop();
                                        if (!entry) {
                                                break;
                                        }
                                        seen.add(entry.id);
                                        if (typeof entry.close === "function") {
                                                entry.close({ fromHistory: true, silent: true });
                                        }
                                }
                        }

                        const top = stack[stack.length - 1] || null;
                        writeState(stack.length, top ? top.id : null);
                };

                if (typeof window !== "undefined") {
                        window.addEventListener("popstate", handlePopState);
                        if (mediaQuery) {
                                const handleChange = () => {
                                        if (!isEnabled()) {
                                                stack.length = 0;
                                                if (typeof history.replaceState === "function") {
                                                        const currentState = readState();
                                                        const nextState = { ...currentState };
                                                        if (nextState[PANEL_HISTORY_KEY]) {
                                                                nextState[PANEL_HISTORY_KEY] = { depth: 0 };
                                                        }
                                                        history.replaceState(
                                                                nextState,
                                                                document.title,
                                                                window.location.href
                                                        );
                                                }
                                        } else {
                                                const top = stack[stack.length - 1] || null;
                                                writeState(stack.length, top ? top.id : null);
                                        }
                                };
                                if (typeof mediaQuery.addEventListener === "function") {
                                        mediaQuery.addEventListener("change", handleChange);
                                } else if (typeof mediaQuery.addListener === "function") {
                                        mediaQuery.addListener(handleChange);
                                }
                        }
                        const top = stack[stack.length - 1] || null;
                        writeState(stack.length, top ? top.id : null);
                }

                const api = {
                        push,
                        close,
                        clear,
                        _stack: stack,
                };
                window.go360PanelHistory = api;
                return api;
        })();

        document.addEventListener("DOMContentLoaded", function () {
                const themeToggle = document.querySelector("[data-theme-toggle]");
                const statusOn = themeToggle ? themeToggle.getAttribute("data-theme-status-on") || "Activado" : "Activado";
                const statusOff = themeToggle ? themeToggle.getAttribute("data-theme-status-off") || "Desactivado" : "Desactivado";
                const labelOn = themeToggle ? themeToggle.getAttribute("data-theme-label-on") || "Cambiar a modo claro" : "Cambiar a modo claro";
                const labelOff = themeToggle ? themeToggle.getAttribute("data-theme-label-off") || "Activar modo oscuro" : "Activar modo oscuro";
                const panelHistoryInstance =
                        panelHistory && typeof panelHistory === "object"
                                ? panelHistory
                                : null;

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
                        const dispatchWindowEvent = (name) => {
                                if (typeof window === "undefined") {
                                        return;
                                }
                                let customEvent = null;
                                if (typeof window.CustomEvent === "function") {
                                        customEvent = new CustomEvent(name);
                                } else if (
                                        typeof document !== "undefined" &&
                                        typeof document.createEvent === "function"
                                ) {
                                        customEvent = document.createEvent("CustomEvent");
                                        customEvent.initCustomEvent(name, false, false, {});
                                }
                                if (customEvent) {
                                        window.dispatchEvent(customEvent);
                                }
                        };

                        const isMenuOpen = () => mobileMenu.classList.contains("mobile-menu--visible");

                        const closeOtherPanels = () => {
                                dispatchWindowEvent("go360:notifications:close");
                                dispatchWindowEvent("go360:profile:close");
                        };

                        const openMenu = () => {
                                if (isMenuOpen()) {
                                        return;
                                }
                                closeOtherPanels();
                                mobileMenu.classList.add("mobile-menu--visible");
                                mobileMenu.setAttribute("aria-hidden", "false");
                                mobileToggle.setAttribute("aria-expanded", "true");
                                dispatchWindowEvent("go360:mobile-menu:opened");
                                if (
                                        panelHistoryInstance &&
                                        typeof panelHistoryInstance.push === "function"
                                ) {
                                        panelHistoryInstance.push("mobile-menu", () => {
                                                closeMenu({ silent: true });
                                        });
                                }
                        };

                        const closeMenu = (options = {}) => {
                                const opts =
                                        options &&
                                        typeof options === "object" &&
                                        !Array.isArray(options)
                                                ? options
                                                : {};
                                if (!isMenuOpen()) {
                                        return;
                                }
                                mobileMenu.classList.remove("mobile-menu--visible");
                                mobileMenu.setAttribute("aria-hidden", "true");
                                mobileToggle.setAttribute("aria-expanded", "false");
                                dispatchWindowEvent("go360:mobile-menu:closed");
                                if (
                                        panelHistoryInstance &&
                                        typeof panelHistoryInstance.close === "function" &&
                                        !opts.silent
                                ) {
                                        panelHistoryInstance.close("mobile-menu");
                                }
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
                        window.addEventListener("go360:notifications:opened", () => {
                                closeMenu();
                        });
                        window.addEventListener("go360:profile:opened", () => {
                                closeMenu();
                        });
                        window.addEventListener("go360:mobile-menu:close", () => {
                                closeMenu();
                        });
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
                                if (
                                        panelHistoryInstance &&
                                        typeof panelHistoryInstance.push === "function"
                                ) {
                                        panelHistoryInstance.push("profile-menu", () => {
                                                closeMenu({ silent: true });
                                        });
                                }
                        };

                        const closeMenu = (options = {}) => {
                                const opts =
                                        options &&
                                        typeof options === "object" &&
                                        !Array.isArray(options)
                                                ? options
                                                : {};
                                if (!profileMenu.classList.contains("visible")) {
                                        return;
                                }
                                profileMenu.classList.remove("visible");
                                profileMenu.setAttribute("aria-hidden", "true");
                                profileBtn.setAttribute("aria-expanded", "false");
                                emitProfileEvent("go360:profile:closed");
                                if (
                                        panelHistoryInstance &&
                                        typeof panelHistoryInstance.close === "function" &&
                                        !opts.silent
                                ) {
                                        panelHistoryInstance.close("profile-menu");
                                }
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

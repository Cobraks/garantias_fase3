// assets/js/global.js

console.log("GO360 script cargado");

(function () {
        const LOGO_INTRO_KEY = "go360-logo-intro";
        const LOGO_DURATION = 1200;
        const NAVIGATION_DELAY = 40;
        const prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
        let isLogoAnimating = false;

        document.addEventListener("DOMContentLoaded", () => {
                initMobileMenu();
                initProfileMenu();
                initDraftReset();
                initLogoOrchestration();
        });

        function initMobileMenu() {
                const btn = document.querySelector(".top-bar__hamburger");
                const menu = document.querySelector(".mobile-menu");

                if (!btn || !menu) {
                        return;
                }

                btn.addEventListener("click", () => {
                        menu.style.display = menu.style.display === "block" ? "none" : "block";
                });
        }

        function initProfileMenu() {
                const profileBtn = document.querySelector(".top-bar__profile-link");
                const profileMenu = document.querySelector(".top-bar__profile-menu");

                if (!profileBtn || !profileMenu) {
                        return;
                }

                profileBtn.addEventListener("click", (event) => {
                        event.stopPropagation();
                        profileMenu.classList.toggle("visible");
                });

                document.addEventListener("click", () => {
                        profileMenu.classList.remove("visible");
                });

                profileMenu.addEventListener("click", (event) => {
                        event.stopPropagation();
                });
        }

        function initDraftReset() {
                const newLinks = document.querySelectorAll("[data-reset-draft]");

                newLinks.forEach((link) => {
                        link.addEventListener("click", () => {
                                localStorage.removeItem("go_draft_id");
                                localStorage.removeItem("go_draft_uuid");
                        });
                });
        }

        function initLogoOrchestration() {
                const svg = document.querySelector(".site-logo__svg");

                if (svg) {
                        updateLogoVariables(svg);
                }

                const wrapper = document.querySelector(".site-logo-wrapper--auth");

                if (!wrapper) {
                        return;
                }

                const shouldAnimate = shouldAnimateIntro();

                if (shouldAnimate && !prefersReducedMotion) {
                        animateLogoIntro(wrapper);
                } else {
                        wrapper.classList.remove("is-horizontal");
                        wrapper.classList.remove("is-preload");
                }

                const form = document.querySelector(".auth-form");

                if (form) {
                        form.addEventListener("submit", (event) => {
                                if (event.defaultPrevented || isLogoAnimating) {
                                        return;
                                }

                                event.preventDefault();

                                startLogoNavigation(wrapper, () => {
                                        const nativeSubmit = HTMLFormElement.prototype.submit;
                                        nativeSubmit.call(form);
                                });
                        });
                }

                const links = document.querySelectorAll(".login-page a[href], .register-page a[href]");

                links.forEach((link) => {
                        if (link.closest(".site-logo-wrapper")) {
                                return;
                        }

                        if (link.dataset.logoTransition === "skip") {
                                return;
                        }

                        if (link.target && link.target !== "_self") {
                                return;
                        }

                        const url = toAbsoluteURL(link.getAttribute("href"));

                        if (!url) {
                                return;
                        }

                        if (url.origin !== window.location.origin) {
                                return;
                        }

                        if (url.href === window.location.href) {
                                return;
                        }

                        link.addEventListener("click", (event) => {
                                if (event.defaultPrevented || isLogoAnimating) {
                                        return;
                                }

                                event.preventDefault();

                                startLogoNavigation(wrapper, () => {
                                        window.location.href = url.href;
                                });
                        });
                });
        }

        function shouldAnimateIntro() {
                if (prefersReducedMotion) {
                        sessionStorage.removeItem(LOGO_INTRO_KEY);
                        return false;
                }

                const stored = sessionStorage.getItem(LOGO_INTRO_KEY);

                if (stored === "1") {
                        sessionStorage.removeItem(LOGO_INTRO_KEY);
                        return true;
                }

                try {
                        if (document.referrer) {
                                const referrerUrl = new URL(document.referrer);

                                if (referrerUrl.origin === window.location.origin) {
                                        return true;
                                }
                        }
                } catch (error) {
                        // Ignored: cross-origin referrer
                }

                return false;
        }

        function animateLogoIntro(wrapper) {
                wrapper.classList.add("is-preload");
                wrapper.classList.add("is-horizontal");

                requestAnimationFrame(() => {
                        wrapper.classList.remove("is-preload");

                        requestAnimationFrame(() => {
                                wrapper.classList.remove("is-horizontal");
                        });
                });
        }

        function animateLogoForward(wrapper) {
                return new Promise((resolve) => {
                        wrapper.classList.remove("is-horizontal");
                        wrapper.classList.remove("is-preload");
                        // Force reflow so the transition restarts reliably
                        void wrapper.offsetWidth;

                        requestAnimationFrame(() => {
                                wrapper.classList.add("is-horizontal");
                        });

                        window.setTimeout(resolve, LOGO_DURATION);
                });
        }

        function startLogoNavigation(wrapper, navigate) {
                sessionStorage.setItem(LOGO_INTRO_KEY, "1");

                if (prefersReducedMotion || !wrapper) {
                        if (wrapper) {
                                wrapper.classList.add("is-horizontal");
                                wrapper.classList.remove("is-preload");
                        }

                        navigate();
                        return;
                }

                if (isLogoAnimating) {
                        return;
                }

                isLogoAnimating = true;

                const finish = () => {
                        isLogoAnimating = false;
                };

                const afterAnimation = () => {
                        window.setTimeout(() => {
                                navigate();
                                finish();
                        }, NAVIGATION_DELAY);
                };

                const startTransition = document.startViewTransition
                        ? document.startViewTransition.bind(document)
                        : null;

                if (startTransition) {
                        startTransition(() => new Promise((resolve) => {
                                animateLogoForward(wrapper).then(() => {
                                        resolve();
                                        afterAnimation();
                                });
                        })).finished.catch(() => {
                                finish();
                        });
                } else {
                        animateLogoForward(wrapper).then(afterAnimation);
                }
        }

        function toAbsoluteURL(href) {
                if (!href) {
                        return null;
                }

                try {
                        return new URL(href, window.location.href);
                } catch (error) {
                        return null;
                }
        }

        function updateLogoVariables(svg) {
                try {
                        const clone = svg.cloneNode(true);
                        clone.style.position = "absolute";
                        clone.style.opacity = "0";
                        clone.style.pointerEvents = "none";
                        clone.style.left = "-9999px";
                        clone.style.top = "-9999px";
                        document.body.appendChild(clone);

                        const text360 = clone.querySelector("#texto-360");
                        const textVO = clone.querySelector("#texto-VO");

                        if (!text360 || !textVO) {
                                clone.remove();
                                return;
                        }

                        const box360 = text360.getBBox();
                        const boxVO = textVO.getBBox();
                        const viewBox = svg.viewBox.baseVal;
                        const defaultScale = parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--logo-scale")) || 0.88;
                        const baseWidth = box360.width + boxVO.width;
                        const availableWidth = viewBox.width - 40;
                        const scale = Math.min(defaultScale, availableWidth / baseWidth);
                        const remainingSpace = Math.max(0, viewBox.width - baseWidth * scale);
                        const gap = Math.max(14, Math.min(48, remainingSpace * 0.5));
                        const combinedWidth = baseWidth * scale + gap;
                        const startX = viewBox.x + (viewBox.width - combinedWidth) / 2;
                        const centerY = (box360.y + box360.height / 2 + boxVO.y + boxVO.height / 2) / 2 - Math.max(box360.height, boxVO.height) * 0.08;

                        const center360 = {
                                x: box360.x + box360.width / 2,
                                y: box360.y + box360.height / 2,
                        };
                        const centerVO = {
                                x: boxVO.x + boxVO.width / 2,
                                y: boxVO.y + boxVO.height / 2,
                        };

                        const dx360 = startX + (box360.width * scale) / 2 - center360.x;
                        const dy360 = centerY - center360.y;
                        const dxVO = startX + box360.width * scale + gap + (boxVO.width * scale) / 2 - centerVO.x;
                        const dyVO = centerY - centerVO.y;

                        const rootStyle = document.documentElement.style;
                        rootStyle.setProperty("--logo-scale", scale.toFixed(3));
                        rootStyle.setProperty("--logo-dx-360", `${dx360.toFixed(2)}px`);
                        rootStyle.setProperty("--logo-dy-360", `${dy360.toFixed(2)}px`);
                        rootStyle.setProperty("--logo-dx-vo", `${dxVO.toFixed(2)}px`);
                        rootStyle.setProperty("--logo-dy-vo", `${dyVO.toFixed(2)}px`);

                        clone.remove();
                } catch (error) {
                        console.error("GO360 logo metrics", error);
                }
        }
})();

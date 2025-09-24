// assets/js/global.js

console.log("GO360 script cargado");

(function () {
        document.addEventListener("DOMContentLoaded", function () {
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

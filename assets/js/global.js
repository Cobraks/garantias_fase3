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
                        // Abrir/cerrar al click en el avatar
                        profileBtn.addEventListener("click", (e) => {
                                e.stopPropagation();
                                profileMenu.classList.toggle("visible");
                        });
                        // Cerrar al click fuera
                        document.addEventListener("click", () => {
                                profileMenu.classList.remove("visible");
                        });
                        // Evitar cierre al clicar dentro del menú
                        profileMenu.addEventListener("click", (e) => {
                                e.stopPropagation();
                        });
                }

                const newLinks = document.querySelectorAll('a[href*="nueva-garantia"]');
                newLinks.forEach((link) => {
                        link.addEventListener("click", () => {
                                localStorage.removeItem("go_draft_id");
                                localStorage.removeItem("go_draft_uuid");
                        });
                });
        });
})();

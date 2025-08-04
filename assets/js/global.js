// assets/js/global.js

console.log("GO360 script cargado");

<<<<<<< Updated upstream
document.addEventListener("DOMContentLoaded", () => {
  // Toggle menú móvil
  const hamburger = document.querySelector(".top-bar__hamburger");
  const mobileMenu = document.querySelector(".mobile-menu");
  if (hamburger && mobileMenu) {
    const closeMobile = () => mobileMenu.classList.remove("open");
    hamburger.addEventListener("click", (e) => {
      e.stopPropagation();
      mobileMenu.classList.toggle("open");
    });
    document.addEventListener("click", closeMobile);
    document.addEventListener("keyup", (e) => {
      if (e.key === "Escape") {
        closeMobile();
      }
    });
    mobileMenu.addEventListener("click", (e) => e.stopPropagation());
  }

  // Toggle menú de perfil
  const profileBtn = document.querySelector(".top-bar__profile-link");
  const profileMenu = document.querySelector(".top-bar__profile-menu");
  if (profileBtn && profileMenu) {
    const closeProfile = () => profileMenu.classList.remove("visible");
    profileBtn.addEventListener("click", (e) => {
      e.stopPropagation();
      profileMenu.classList.toggle("visible");
    });
    document.addEventListener("click", closeProfile);
    document.addEventListener("keyup", (e) => {
      if (e.key === "Escape") {
        closeProfile();
      }
    });
    profileMenu.addEventListener("click", (e) => e.stopPropagation());
  }
});
=======
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
	});
})();
>>>>>>> Stashed changes

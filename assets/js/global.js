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

const rootElement = document.documentElement;
const themeStorageKey = "go360-theme";
const themeToggle = document.querySelector('[data-theme-toggle]');
const prefersDark = window.matchMedia?.("(prefers-color-scheme: dark)");

const applyTheme = (theme) => {
const normalized = theme === "dark" ? "dark" : "light";
rootElement.setAttribute("data-theme", normalized);
if (themeToggle) {
themeToggle.checked = normalized === "dark";
themeToggle.setAttribute(
"aria-label",
normalized === "dark"
? "Cambiar a modo claro"
: "Cambiar a modo oscuro"
);
themeToggle.dataset.themeState = normalized;
}
};

const savedTheme = localStorage.getItem(themeStorageKey);
if (savedTheme) {
applyTheme(savedTheme);
} else if (prefersDark?.matches) {
applyTheme("dark");
} else {
applyTheme("light");
}

if (prefersDark) {
prefersDark.addEventListener("change", (event) => {
if (!localStorage.getItem(themeStorageKey)) {
applyTheme(event.matches ? "dark" : "light");
}
});
}

if (themeToggle) {
themeToggle.addEventListener("change", (event) => {
const isDark = event.currentTarget.checked;
const targetTheme = isDark ? "dark" : "light";
applyTheme(targetTheme);
localStorage.setItem(themeStorageKey, targetTheme);
});

themeToggle.addEventListener("keydown", (event) => {
if (event.key === "Enter") {
event.preventDefault();
themeToggle.checked = !themeToggle.checked;
themeToggle.dispatchEvent(new Event("change"));
}
});
}
});
})();

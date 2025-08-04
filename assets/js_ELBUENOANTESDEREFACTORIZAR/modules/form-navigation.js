// assets/js/modules/form-navigation.js
"use strict";

/*
    - Controla la lógica de cambio de pestaña, activación/desactivación de botones y gestión de navegación por pasos.
    - Utiliza FormCache para acceder al estado del wizard.
    - Minimiza globals: solo deja exposiciones legacy al final.
*/

import FormCache from "./form-cache.js";
import { validateField } from "./form-validation.js";

// === Helpers de UI: colores y conectores ===
function updateConnectors() {
	document
		.querySelectorAll(".tabs__connector .connector")
		.forEach((connector, i) => {
			const leftTab = FormCache.tabs[i];
			const rightTab = FormCache.tabs[i + 1];
			const styles = getComputedStyle(document.documentElement);
			const leftColor = leftTab.classList.contains("completed")
				? "#000"
				: leftTab.classList.contains("active") && FormCache.tabCompletion[i]
				? styles.getPropertyValue("--step-active")
				: styles.getPropertyValue("--step-inactive");
			const rightColor = rightTab?.classList.contains("completed")
				? "#000"
				: rightTab?.classList.contains("active") &&
				  FormCache.tabCompletion[i + 1]
				? styles.getPropertyValue("--step-active")
				: styles.getPropertyValue("--step-inactive");
			if (connector && leftColor && rightColor) {
				connector.style.background =
					leftColor === rightColor
						? leftColor
						: `linear-gradient(to right, ${leftColor}, ${rightColor})`;
			}
		});
}

function markTabAsCompleted(index) {
	if (typeof index !== "number") return;
	const tab = FormCache.tabs[index];
	if (!tab) return;
	tab.classList.add("completed");
	const circle = tab.querySelector(".tabs__circle");
	if (circle) circle.textContent = "✓";
	FormCache.setTabCompletion(index, true);
	updateConnectors();
}

function unmarkTabAsCompleted(index) {
	if (typeof index !== "number") return;
	const tab = FormCache.tabs[index];
	if (!tab) return;
	tab.classList.remove("completed");
	const circle = tab.querySelector(".tabs__circle");
	if (circle) circle.textContent = index + 1;
	FormCache.setTabCompletion(index, false);
	updateConnectors();
}

// === Errores específicos de garantía ===
function showPlanSelectionError() {
	const plansContainer = document.getElementById("formPlans");
	if (!plansContainer) return;
	plansContainer.classList.add("form__plans--error");
	// Evita duplicar el mensaje
	let existing = plansContainer.querySelector(
		"p.form__error-message.plan-selection"
	);
	if (!existing) {
		const p = document.createElement("p");
		p.className = "form__error-message plan-selection";
		p.textContent = "Selecciona una garantía";
		plansContainer.insertAdjacentElement("afterend", p);
	}
}

function clearPlanSelectionError() {
	const plansContainer = document.getElementById("formPlans");
	if (!plansContainer) return;
	plansContainer.classList.remove("form__plans--error");
	const existing = document.querySelector(
		"p.form__error-message.plan-selection"
	);
	if (existing) existing.remove();
}

// === Estado visual + saltos de pestaña ===
function showTab(index) {
	FormCache.fieldsets.forEach((fs, i) =>
		fs.classList.toggle("form__tab-content--active", i === index)
	);
	FormCache.tabs.forEach((tab, i) =>
		tab.classList.toggle("active", i === index)
	);
	if (FormCache.prevButton) {
		FormCache.prevButton.style.display = index === 0 ? "none" : "";
	}
	if (FormCache.nextButton) {
		FormCache.nextButton.textContent =
			index === FormCache.fieldsets.length - 1 ? "Contratar" : "Siguiente";
	}

	// Actualiza estado
	FormCache.currentTab = index;

	// --- Forzar recálculo de modalidades/planes al mostrar pasos relacionados ---
	const currentFieldset = FormCache.fieldsets[index];
	if (
		currentFieldset &&
		(currentFieldset.id === "seleccionar-garantia" ||
			currentFieldset.id === "resumen-garantia")
	) {
		if (typeof window.filtrarModalidades === "function") {
			window.filtrarModalidades();
		}
	}

	// Limpia error de plan si ya hay uno seleccionado
	if (currentFieldset.id === "seleccionar-garantia") {
		if (document.querySelector(".form__plan.selected")) {
			clearPlanSelectionError();
		}
	}

	// Actualiza UI dependientes
	if (typeof window.updateNextButtonState === "function") {
		window.updateNextButtonState();
	}
	if (typeof window.debouncedUpdateSummary === "function") {
		window.debouncedUpdateSummary();
	}
}

// === Validación de la pestaña actual ===
function isCurrentTabValid() {
	const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
	if (!currentFieldset) return false;
	const inputs = currentFieldset.querySelectorAll(
		".form__input, .form__select[required], .form__checkbox[required]"
	);
	let valid = true;
	inputs.forEach((input) => {
		if (!validateField(input, true, true)) valid = false;
	});

	if (currentFieldset.id === "seleccionar-garantia") {
		if (!document.querySelector(".form__plan.selected")) valid = false;

		const canalVenta = document.getElementById("canal-venta");
		const usuarioRol = document.getElementById("usuario-rol");
		if (canalVenta && canalVenta.offsetParent !== null && !canalVenta.value) {
			validateField(canalVenta, true, true);
			valid = false;
		}
		if (usuarioRol && usuarioRol.offsetParent !== null && !usuarioRol.value) {
			validateField(usuarioRol, true, true);
			valid = false;
		}
	}
	return valid;
}

// === Activa/desactiva botón "Siguiente" y gestiona estado de pestañas completadas ===
function updateNextButtonState() {
	const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
	if (!currentFieldset) return;
	const requiredInputs = currentFieldset.querySelectorAll(
		".form__input[required], .form__select[required], .form__checkbox[required]"
	);
	let allValid = true;
	requiredInputs.forEach((input) => {
		if (!validateField(input, false, true)) allValid = false;
	});

	if (currentFieldset.id === "seleccionar-garantia") {
		if (!document.querySelector(".form__plan.selected")) allValid = false;
		const canalVenta = document.getElementById("canal-venta");
		const usuarioRol = document.getElementById("usuario-rol");
		if (canalVenta && canalVenta.offsetParent !== null && !canalVenta.value)
			allValid = false;
		if (usuarioRol && usuarioRol.offsetParent !== null && !usuarioRol.value)
			allValid = false;
	}

	if (FormCache.nextButton) {
		FormCache.nextButton.classList.toggle("disabled", !allValid);
		FormCache.nextButton.style.cursor = allValid ? "pointer" : "not-allowed";
	}

	// Marcar/desmarcar pestaña completada en cache
	if (allValid && !FormCache.tabCompletion[FormCache.currentTab]) {
		markTabAsCompleted(FormCache.currentTab);
	} else if (!allValid && FormCache.tabCompletion[FormCache.currentTab]) {
		unmarkTabAsCompleted(FormCache.currentTab);
	}

	// Legacy: exponer (se puede eliminar en siguientes fases)
	window.tabCompletion = FormCache.tabCompletion;

	updateConnectors();
}

// === Navegación por tabs y botones ===
function setupTabNavigation() {
	if (!FormCache.tabs) return;

	FormCache.tabs.forEach((tab, index) => {
		tab.addEventListener("click", () => {
			if (
				index < FormCache.currentTab || // retroceder siempre
				(index > FormCache.currentTab && isCurrentTabValid())
			) {
				FormCache.currentTab = index;
				showTab(index);
			}
		});
	});

	if (FormCache.prevButton) {
		FormCache.prevButton.addEventListener("click", () => {
			if (FormCache.currentTab > 0) {
				FormCache.currentTab--;
				showTab(FormCache.currentTab);
			}
		});
	}

	if (FormCache.nextButton) {
		FormCache.nextButton.addEventListener("click", () => {
			if (document.querySelector(".form__plan.selected")) {
				clearPlanSelectionError();
			}

			if (!isCurrentTabValid()) {
				const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
				if (currentFieldset && currentFieldset.id === "seleccionar-garantia") {
					if (!document.querySelector(".form__plan.selected")) {
						showPlanSelectionError();
					}
					// canal-venta / usuario-rol se limpian vía validateField dentro de isCurrentTabValid
				}
				alert("Completa todos los campos antes de continuar.");
				return;
			}
			if (typeof window.showSummarySectionForTab === "function") {
				window.showSummarySectionForTab(FormCache.currentTab);
			}
			if (FormCache.currentTab < FormCache.fieldsets.length - 1) {
				FormCache.currentTab++;
				showTab(FormCache.currentTab);
			} else {
				// último paso: delega en otro módulo (ej. form-submission)
			}
		});
	}
}

// === EXPORTS ===
export {
	showTab,
	setupTabNavigation,
	updateNextButtonState,
	isCurrentTabValid,
	updateConnectors,
	markTabAsCompleted,
	unmarkTabAsCompleted,
};

function initNavigation() {
	// Exponer helpers globales para compatibilidad temporal
	window.showTab = showTab;
	window.updateNextButtonState = updateNextButtonState;
	window.isCurrentTabValid = isCurrentTabValid;
	window.updateConnectors = updateConnectors;
	window.markTabAsCompleted = markTabAsCompleted;
	window.unmarkTabAsCompleted = unmarkTabAsCompleted;
	setupTabNavigation();
	showTab(FormCache.currentTab);
}
export default initNavigation;

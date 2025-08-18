// assets/js/modules/form-navigation.js
"use strict";

/*
    - Controla la lógica de cambio de pestaña, activación/desactivación de botones y gestión de navegación por pasos.
    - Utiliza FormCache para acceder al estado del wizard.
    - Minimiza globals: solo deja exposiciones legacy al final.
*/

import FormCache from "./form-cache.js";
import { validateField } from "./form-validation.js";
import { filtrarModalidades } from "./form-calculations.js";
import {
        debouncedUpdateSummary,
        showSummarySectionForTab,
} from "./form-summary.js";

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
        const previousIndex = FormCache.currentTab;
        const prevFieldset = FormCache.fieldsets[previousIndex];
        const newFieldset = FormCache.fieldsets[index];

        if (!newFieldset) return;

        // Animación de salida
        if (prevFieldset && prevFieldset !== newFieldset) {
                prevFieldset.classList.add("tab-exit");
                requestAnimationFrame(() => {
                        prevFieldset.classList.add("tab-exit-active");
                });
                prevFieldset.addEventListener(
                        "transitionend",
                        function handleExit(e) {
                                if (e.target !== prevFieldset) return;
                                prevFieldset.classList.remove(
                                        "tab-exit",
                                        "tab-exit-active",
                                        "form__tab-content--active"
                                );
                                prevFieldset.style.display = "none";
                                prevFieldset.removeEventListener(
                                        "transitionend",
                                        handleExit
                                );
                        }
                );
        }

        // Animación de entrada
        const displayMode = newFieldset.id === "finalizar" ? "flex" : "block";
        newFieldset.style.display = displayMode;
        newFieldset.classList.add("tab-enter");
        requestAnimationFrame(() => {
                newFieldset.classList.add("tab-enter-active");
        });
        newFieldset.addEventListener(
                "transitionend",
                function handleEnter(e) {
                        if (e.target !== newFieldset) return;
                        newFieldset.classList.remove("tab-enter", "tab-enter-active");
                        newFieldset.classList.add("form__tab-content--active");
                        newFieldset.style.display = "";
                        newFieldset.removeEventListener("transitionend", handleEnter);
                }
        );

        // Tabs visuales
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
        const currentFieldset = newFieldset;
        if (
                currentFieldset &&
                (currentFieldset.id === "seleccionar-garantia" ||
                        currentFieldset.id === "resumen-garantia")
        ) {
                if (typeof filtrarModalidades === "function") {
                        filtrarModalidades();
                }
        }

        // Limpia error de plan si ya hay uno seleccionado
        if (currentFieldset.id === "seleccionar-garantia") {
                if (document.querySelector(".form__plan.selected")) {
                        clearPlanSelectionError();
                }
        }

        // Actualiza UI dependientes
        updateNextButtonState();
        if (typeof debouncedUpdateSummary === "function") {
                debouncedUpdateSummary();
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
        }

        // Marcar/desmarcar pestaña completada en cache
        if (allValid && !FormCache.tabCompletion[FormCache.currentTab]) {
                markTabAsCompleted(FormCache.currentTab);
        } else if (!allValid && FormCache.tabCompletion[FormCache.currentTab]) {
                unmarkTabAsCompleted(FormCache.currentTab);
        }

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
                                showTab(index);
                        }
                });
        });

	if (FormCache.prevButton) {
                FormCache.prevButton.addEventListener("click", () => {
                        if (FormCache.currentTab > 0) {
                                showTab(FormCache.currentTab - 1);
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
                        if (typeof showSummarySectionForTab === "function") {
                                showSummarySectionForTab(FormCache.currentTab);
                        }
                        if (FormCache.currentTab < FormCache.fieldsets.length - 1) {
                                showTab(FormCache.currentTab + 1);
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
        setupTabNavigation();
        showTab(FormCache.currentTab);
}
export default initNavigation;

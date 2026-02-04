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
        const tabs = FormCache.tabs;
        if (!Array.isArray(tabs) || tabs.length === 0) {
                return;
        }

        const completionState = Array.isArray(FormCache.tabCompletion)
                ? FormCache.tabCompletion
                : [];

        document
                .querySelectorAll(".tabs__connector .connector")
                .forEach((connector, i) => {
                        const leftTab = tabs[i];
                        const rightTab = tabs[i + 1];
                        const styles = getComputedStyle(document.documentElement);
                        const idleColor = styles.getPropertyValue("--wizard-connector-idle").trim() ||
                                styles.getPropertyValue("--step-inactive").trim() ||
                                "#d1d5db";
                        const activeColor = styles.getPropertyValue("--wizard-connector-active").trim() ||
                                styles.getPropertyValue("--step-active").trim() ||
                                styles.getPropertyValue("--primary-color").trim() ||
                                "#2563eb";
                        const completedColor = styles.getPropertyValue("--wizard-connector-complete").trim() || activeColor;
                        const leftIsCompleted = leftTab?.classList?.contains("completed");
                        const leftIsActiveAndComplete =
                                leftTab?.classList?.contains("active") && completionState[i];
                        const leftColor = leftIsCompleted
                                ? completedColor
                                : leftIsActiveAndComplete
                                ? activeColor
                                : idleColor;
                        const rightIsCompleted = rightTab?.classList?.contains("completed");
                        const rightIsActiveAndComplete =
                                rightTab?.classList?.contains("active") && completionState[i + 1];
                        const rightColor = rightIsCompleted
                                ? completedColor
                                : rightIsActiveAndComplete
                                ? activeColor
                                : idleColor;
                        if (connector && leftColor && rightColor) {
                                connector.style.background =
                                        leftColor === rightColor
                                                ? leftColor
                                                : `linear-gradient(to right, ${leftColor}, ${rightColor})`;
			}
                });
}

if (typeof window !== "undefined" && typeof window.addEventListener === "function") {
        window.addEventListener("go:theme-change", () => {
                updateConnectors();
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

function evaluateCurrentTab({ showErrors = false, hardCheck = false, focusInvalid = false } = {}) {
        const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
        if (!currentFieldset) {
                return { valid: false, invalidInputs: [] };
        }

        const inputs = currentFieldset.querySelectorAll(
                ".form__input, .form__select, .form__checkbox"
        );
        let valid = true;
        const invalidInputs = [];

        inputs.forEach((input) => {
                if (!input) return;
                if (input.type === "hidden" || input.disabled) return;
                const isVisible = input.offsetParent !== null;
                if (!isVisible) {
                        return;
                }
                const result = validateField(input, showErrors, hardCheck);
                if (!result) {
                        valid = false;
                        invalidInputs.push(input);
                }
        });

        if (currentFieldset.id === "seleccionar-garantia") {
                const planSelected = Boolean(document.querySelector(".form__plan.selected"));
                if (!planSelected) {
                        valid = false;
                        if (showErrors) {
                                showPlanSelectionError();
                        }
                } else {
                        clearPlanSelectionError();
                }

                const canalVenta = document.getElementById("canal-venta");
                if (canalVenta && canalVenta.offsetParent !== null) {
                        if (!validateField(canalVenta, showErrors, hardCheck)) {
                                valid = false;
                                invalidInputs.push(canalVenta);
                        }
                }

                const usuarioRol = document.getElementById("usuario-rol");
                if (usuarioRol && usuarioRol.offsetParent !== null) {
                        if (!validateField(usuarioRol, showErrors, hardCheck)) {
                                valid = false;
                                invalidInputs.push(usuarioRol);
                        }
                }
        }

        if (focusInvalid && invalidInputs.length > 0) {
                const focusTarget = invalidInputs.find(
                        (input) => typeof input.focus === "function"
                );
                if (focusTarget) {
                        focusTarget.focus({ preventScroll: false });
                }
        }

        return { valid, invalidInputs };
}

// === Validación de la pestaña actual ===
function isCurrentTabValid({ focusInvalid = false } = {}) {
        const evaluation = evaluateCurrentTab({
                showErrors: true,
                hardCheck: true,
                focusInvalid,
        });
        updateNextButtonState(true, evaluation);
        return evaluation.valid;
}

// === Activa/desactiva botón "Siguiente" y gestiona estado de pestañas completadas ===
function updateNextButtonState(showErrors = false, precomputedResult = null) {
        const evaluation =
                precomputedResult ||
                evaluateCurrentTab({ showErrors, hardCheck: true, focusInvalid: false });
        const allValid = evaluation.valid;

        if (FormCache.nextButton) {
                FormCache.nextButton.classList.toggle("disabled", !allValid);
                FormCache.nextButton.style.cursor = allValid ? "pointer" : "not-allowed";
        }

        if (allValid && !FormCache.tabCompletion[FormCache.currentTab]) {
                markTabAsCompleted(FormCache.currentTab);
        } else if (!allValid && FormCache.tabCompletion[FormCache.currentTab]) {
                unmarkTabAsCompleted(FormCache.currentTab);
        }

        updateConnectors();

        return allValid;
}

// === Navegación por tabs y botones ===
function setupTabNavigation() {
	if (!FormCache.tabs) return;

        FormCache.tabs.forEach((tab, index) => {
                tab.addEventListener("click", () => {
                        if (index === FormCache.currentTab) return;
                        if (index < FormCache.currentTab) {
                                FormCache.currentTab = index;
                                showTab(index);
                                return;
                        }

                        const evaluation = evaluateCurrentTab({
                                showErrors: true,
                                hardCheck: true,
                                focusInvalid: true,
                        });
                        const canAdvance = updateNextButtonState(true, evaluation);
                        if (!canAdvance) {
                                alert("Completa todos los campos antes de continuar.");
                                return;
                        }

                        FormCache.currentTab = index;
                        showTab(index);
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
                        const evaluation = evaluateCurrentTab({
                                showErrors: true,
                                hardCheck: true,
                                focusInvalid: true,
                        });
                        const canAdvance = updateNextButtonState(true, evaluation);
                        if (!canAdvance) {
                                alert("Completa todos los campos antes de continuar.");
                                return;
                        }
                        if (typeof showSummarySectionForTab === "function") {
                                showSummarySectionForTab(FormCache.currentTab);
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
        setupTabNavigation();
        showTab(FormCache.currentTab);
}
export default initNavigation;

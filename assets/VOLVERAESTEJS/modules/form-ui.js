//assets/js/modules/form-ui.js
"use strict";

/*
  - Gestión de UI del formulario: clears, selección de planes, fechas, shimmer, etc.
  - Usa FormCache para persistir la modalidad seleccionada en vez de globals.
*/

import { clearError, clearInfoMessage } from "./form-utils.js";
import FormCache from "./form-cache.js";
import { updateNextButtonState } from "./form-navigation.js";
import { debouncedUpdateSummary } from "./form-summary.js";
import { filtrarModalidades } from "./form-calculations.js";
import { getIcon } from "./config.js";

/**
 * Muestra u oculta el botón de limpiar dentro de un contenedor de input
 */
function toggleClearButton(input) {
	const container = input.closest(".form__input-container");
	const clearBtn = container?.querySelector(".form__clear-btn");
	if (clearBtn) {
		clearBtn.style.display = input.value.trim() ? "flex" : "none";
	}
}

/**
 * Configura los listeners para todos los botones "clear" de campos
 */
function setupClearButtons() {
	document.addEventListener("click", (event) => {
		const clearBtn = event.target.closest(".form__clear-btn");
		if (!clearBtn) return;

		const input = clearBtn
			.closest(".form__input-container")
			?.querySelector(".form__input");

		if (input) {
			input.value = "";
			clearError(input);
			clearInfoMessage(input);
			input.dispatchEvent(new Event("input"));

                        updateNextButtonState();
                        debouncedUpdateSummary();
		}
	});

	document.querySelectorAll(".form__input").forEach((input) => {
		input.addEventListener("input", () => toggleClearButton(input));
	});
}

/**
 * Pone el valor y el mínimo de fecha de la garantía al día de hoy
 */
function setTodayForGarantia() {
	const fechaInput = document.getElementById("fecha_inicio_garantia");
	if (!fechaInput) return;

	const today = new Date().toISOString().split("T")[0];
	fechaInput.value = today;
	fechaInput.min = today;
}

/**
 * Abre el picker nativo al click y actualiza estado al cambio
 */
function handleDateInputs() {
	document.querySelectorAll('input[type="date"]').forEach((input) => {
		input.addEventListener("click", () => input.showPicker?.());
		input.addEventListener("change", () => {
                updateNextButtonState();
                debouncedUpdateSummary();
		});
	});
}

/**
 * Actualiza el texto del botón de un plan (con icono si está seleccionado)
 */
function setButtonState(plan, isSelected) {
        const btnText = plan.querySelector(".form__plan-button-text");
        if (!btnText) return;
        if (isSelected) {
                const checkIcon =
                        getIcon("check") ||
                        '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><path d="M6 10.5L3.5 8l-1 1 3.5 3.5L14.5 4l-1-1L6 10.5z"/></svg>';
                btnText.innerHTML = `<span class="plan-button-icon" aria-hidden="true">${checkIcon}</span> <span class="form__plan-button-text--label">Seleccionada</span>`;
        } else {
                btnText.innerHTML = `<span class="form__plan-button-text--label">Seleccionar</span>`;
        }
}

/**
 * Selecciona o deselecciona una tarjeta de plan.
 * @param {HTMLElement} planCard
 */
function togglePlan(planCard) {
	if (!planCard) return;
	const wasSelected = planCard.classList.contains("selected");
	const plans = Array.from(document.querySelectorAll(".form__plan"));

	// Limpia todo primero (quita selected / no-selected y actualiza botones)
	plans.forEach((p) => {
		p.classList.remove("selected", "form__plan--no-selected");
		setButtonState(p, false);
	});

	if (!wasSelected) {
		// Seleccionar este
		planCard.classList.add("selected");
		setButtonState(planCard, true);

		// Persistir selección
		const modalidadId = planCard.getAttribute("data-modalidad-id");
		if (modalidadId != null) {
			FormCache.selectedModalidadId = String(modalidadId);
		}

		// Marcar resto como no seleccionados
		plans
			.filter((p) => p !== planCard)
			.forEach((p) => p.classList.add("form__plan--no-selected"));

		// Animación 'pop' de la tarjeta
		planCard.classList.add("form__plan--animate");
		planCard.addEventListener(
			"animationend",
			() => planCard.classList.remove("form__plan--animate"),
			{ once: true }
		);
		// Animación en el botón
		const planBtn = planCard.querySelector(".form__plan-button");
		if (planBtn) {
			planBtn.classList.add("just-selected");
			setTimeout(() => planBtn.classList.remove("just-selected"), 600);
		}
	} else {
		// Se deseleccionó manualmente
		FormCache.selectedModalidadId = null;
	}

	// Limpia error de selección si existía
	const plansContainer = document.getElementById("formPlans");
	if (plansContainer) {
		plansContainer.classList.remove("form__plans--error");
		const prevError = document.querySelector(
			"p.form__error-message.plan-selection"
		);
		if (prevError) prevError.remove();
	}

	// Refresca estado siguiente/resumen
        updateNextButtonState();
        debouncedUpdateSummary();
}

/**
 * Configura la lógica de selección de planes con delegación:
 * - click en cualquier parte de `.form__plan` (excepto enlaces)
 * - teclado (Enter / Space)
 */
function setupPlanSelection() {
	const container = document.getElementById("formPlans");
	if (!container) return;

	// Solo enganchar listeners una vez
	if (!container._planSelectionInit) {
		// Delegación click sobre la tarjeta completa (excepto enlaces)
		container.addEventListener("click", (e) => {
			const planCard = e.target.closest(".form__plan");
			if (planCard) {
				if (e.target.closest("a.form__plan-link")) return;
				togglePlan(planCard);
			}
		});

		// Teclado: accesibilidad (Enter / Space)
		container.addEventListener("keydown", (e) => {
			if (e.key !== "Enter" && e.key !== " " && e.key !== "Spacebar") return;
			const planCard = e.target.closest(".form__plan");
			if (planCard) {
				e.preventDefault();
				togglePlan(planCard);
			}
		});

		container._planSelectionInit = true;
	}

	// Estado inicial: si hay una seleccionada en DOM (preseleccionada de ACF) y no hay persistida, respetarla
	document.querySelectorAll(".form__plan").forEach((plan) => {
		if (plan.classList.contains("selected")) {
			setButtonState(plan, true);
			document.querySelectorAll(".form__plan").forEach((p) => {
				if (p !== plan && !p.classList.contains("selected")) {
					p.classList.add("form__plan--no-selected");
				}
			});
			const modalidadId = plan.getAttribute("data-modalidad-id");
			if (modalidadId != null && !FormCache.selectedModalidadId) {
				FormCache.selectedModalidadId = String(modalidadId);
			}
		}
	});

	// Reaplicar selección persistida (prioritaria) en cada llamada
	if (FormCache.selectedModalidadId != null) {
		const persisted = document.querySelector(
			`.form__plan[data-modalidad-id="${FormCache.selectedModalidadId}"]`
		);
		if (persisted) {
			// Si ya está marcada, aseguramos la visualización; si no, reseteamos y la aplicamos
			if (!persisted.classList.contains("selected")) {
				document.querySelectorAll(".form__plan").forEach((p) => {
					p.classList.remove("selected", "form__plan--no-selected");
					setButtonState(p, false);
				});
				persisted.classList.add("selected");
				setButtonState(persisted, true);
				document.querySelectorAll(".form__plan").forEach((p) => {
					if (!p.classList.contains("selected")) {
						p.classList.add("form__plan--no-selected");
					}
				});
			} else {
				setButtonState(persisted, true);
			}
		}
	}
}

// Exponer para render dinámico
export { setupPlanSelection };

/**
 * Crea un párrafo de mensaje de info dentro de un contenedor dado
 */
function createInfoMessage(container) {
	const message = document.createElement("p");
	message.classList.add("form__info-message");
	container.appendChild(message);
	return message;
}

/**
 * Shimmer: animación temporal al cambiar duración
 */
function shimmerOnDurationChange() {
	const wrappers = document.querySelectorAll(".form__plan-price-wrapper");
	wrappers.forEach((wrapper) => {
		const text = wrapper.querySelector(".form__plan-price-text");
		const skeleton = wrapper.querySelector(".plan-price-skeleton");
		if (text && skeleton) {
			text.style.display = "none";
			skeleton.style.display = "inline-block";
			skeleton.style.opacity = "1";
		}
	});

	setTimeout(() => {
		wrappers.forEach((wrapper) => {
			const text = wrapper.querySelector(".form__plan-price-text");
			const skeleton = wrapper.querySelector(".plan-price-skeleton");
			if (text && skeleton) {
				skeleton.style.opacity = "0";
				setTimeout(() => {
					skeleton.style.display = "none";
					text.style.visibility = "visible";
				}, 120);
			}
		});
                if (typeof filtrarModalidades === "function") {
                        filtrarModalidades();
                }
	}, 500);
}

/**
 * Inicializador global del módulo de UI del formulario
 */
function init() {
	setupClearButtons();
	setupPlanSelection();
	setTodayForGarantia();
	handleDateInputs();

	// Limpia error visual de vendedor si cambia selección
	const usuarioSelect = document.getElementById("usuario-rol");
	if (usuarioSelect) {
		usuarioSelect.addEventListener("change", () => {
                        clearError(usuarioSelect);
                        updateNextButtonState();
                        debouncedUpdateSummary();
                });
        }
	const canalSelect = document.getElementById("canal-venta");
	if (canalSelect) {
		canalSelect.addEventListener("change", () => {
                        clearError(canalSelect);
                        updateNextButtonState();
                        debouncedUpdateSummary();
                });
        }

	// Cambio de duración: shimmer + render
        const duracionSelect = document.getElementById("duracion");
        if (duracionSelect) {
                const shimmerDurationListener = (e) => {
                        e.preventDefault();
                        shimmerOnDurationChange();
                };
                duracionSelect.addEventListener("change", shimmerDurationListener);
        }
}

export default {
	init,
	toggleClearButton,
	createInfoMessage,
	setupPlanSelection,
};

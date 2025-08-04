"use strict";

import { clearError, clearInfoMessage } from "./form-utils.js";
import FormCache from "../form-cache.js";

function toggleClearButton(input) {
	const container = input.closest(".form__input-container");
	const clearBtn = container?.querySelector(".form__clear-btn");
	if (clearBtn) {
		clearBtn.style.display = input.value.trim() ? "flex" : "none";
	}
}

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

			if (typeof window.updateNextButtonState === "function") {
				window.updateNextButtonState();
			}
			if (typeof window.debouncedUpdateSummary === "function") {
				window.debouncedUpdateSummary();
			}
		}
	});

	document.querySelectorAll(".form__input").forEach((input) => {
		input.addEventListener("input", () => toggleClearButton(input));
	});
}

function setTodayForGarantia() {
	const fechaInput = document.getElementById("fecha_inicio_garantia");
	if (!fechaInput) return;

	const today = new Date().toISOString().split("T")[0];
	fechaInput.value = today;
	fechaInput.min = today;
}

function handleDateInputs() {
	document.querySelectorAll('input[type="date"]').forEach((input) => {
		input.addEventListener("click", () => input.showPicker?.());
		input.addEventListener("change", () => {
			if (typeof window.updateNextButtonState === "function") {
				window.updateNextButtonState();
			}
			if (typeof window.debouncedUpdateSummary === "function") {
				window.debouncedUpdateSummary();
			}
		});
	});
}

function setButtonState(plan, isSelected) {
	const btnText = plan.querySelector(".form__plan-button-text");
	if (!btnText) return;
	if (isSelected) {
		const checkIcon =
			(window.GO_ICONS && window.GO_ICONS.check) ||
			'<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><path d="M6 10.5L3.5 8l-1 1 3.5 3.5L14.5 4l-1-1L6 10.5z"/></svg>';
		btnText.innerHTML = `<span class="plan-button-icon" aria-hidden="true">${checkIcon}</span> <span class="form__plan-button-text--label">Seleccionada</span>`;
	} else {
		btnText.innerHTML = `<span class="form__plan-button-text--label">Seleccionar</span>`;
	}
}

function togglePlan(planCard) {
	if (!planCard) return;
	const wasSelected = planCard.classList.contains("selected");
	const plans = Array.from(document.querySelectorAll(".form__plan"));

	plans.forEach((p) => {
		p.classList.remove("selected", "form__plan--no-selected");
		setButtonState(p, false);
	});

	if (!wasSelected) {
		planCard.classList.add("selected");
		setButtonState(planCard, true);
		const modalidadId = planCard.getAttribute("data-modalidad-id");
		if (modalidadId != null) {
			FormCache.selectedModalidadId = String(modalidadId);
		}
		plans
			.filter((p) => p !== planCard)
			.forEach((p) => p.classList.add("form__plan--no-selected"));

		planCard.classList.add("form__plan--animate");
		planCard.addEventListener(
			"animationend",
			() => planCard.classList.remove("form__plan--animate"),
			{ once: true }
		);
		const planBtn = planCard.querySelector(".form__plan-button");
		if (planBtn) {
			planBtn.classList.add("just-selected");
			setTimeout(() => planBtn.classList.remove("just-selected"), 600);
		}
	} else {
		FormCache.selectedModalidadId = null;
	}

	const plansContainer = document.getElementById("formPlans");
	if (plansContainer) {
		plansContainer.classList.remove("form__plans--error");
		const prevError = document.querySelector(
			"p.form__error-message.plan-selection"
		);
		if (prevError) prevError.remove();
	}

	if (typeof window.updateNextButtonState === "function") {
		window.updateNextButtonState();
	}
	if (typeof window.debouncedUpdateSummary === "function") {
		window.debouncedUpdateSummary();
	}
}

function setupPlanSelection() {
	const container = document.getElementById("formPlans");
	if (!container) return;

	if (!container._planSelectionInit) {
		container.addEventListener("click", (e) => {
			const planCard = e.target.closest(".form__plan");
			if (planCard) {
				if (e.target.closest("a.form__plan-link")) return;
				togglePlan(planCard);
			}
		});

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

	if (FormCache.selectedModalidadId != null) {
		const persisted = document.querySelector(
			`.form__plan[data-modalidad-id="${FormCache.selectedModalidadId}"]`
		);
		if (persisted) {
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

window.setupPlanSelection = setupPlanSelection;

function createInfoMessage(container) {
	const message = document.createElement("p");
	message.classList.add("form__info-message");
	container.appendChild(message);
	return message;
}

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
		if (typeof window.filtrarModalidades === "function") {
			window.filtrarModalidades();
		}
	}, 500);
}

function init() {
	setupClearButtons();
	setupPlanSelection();
	setTodayForGarantia();
	handleDateInputs();

	const usuarioSelect = document.getElementById("usuario-rol");
	if (usuarioSelect) {
		usuarioSelect.addEventListener("change", () => {
			clearError(usuarioSelect);
			if (typeof window.updateNextButtonState === "function") {
				window.updateNextButtonState();
			}
			if (typeof window.debouncedUpdateSummary === "function") {
				window.debouncedUpdateSummary();
			}
		});
	}
	const canalSelect = document.getElementById("canal-venta");
	if (canalSelect) {
		canalSelect.addEventListener("change", () => {
			clearError(canalSelect);
			if (typeof window.updateNextButtonState === "function") {
				window.updateNextButtonState();
			}
			if (typeof window.debouncedUpdateSummary === "function") {
				window.debouncedUpdateSummary();
			}
		});
	}

	const duracionSelect = document.getElementById("duracion");
	if (duracionSelect) {
		duracionSelect.removeEventListener(
			"__shimmerDuracion",
			window.__shimmerDurationListener
		);
		window.__shimmerDurationListener = (e) => {
			e.preventDefault();
			shimmerOnDurationChange();
		};
		duracionSelect.addEventListener("change", window.__shimmerDurationListener);
	}
}

export default {
	init,
	toggleClearButton,
	createInfoMessage,
	setupPlanSelection,
};

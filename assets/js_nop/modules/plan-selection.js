// assets/js/modules/plan-selection.js
"use strict";

import FormCache from "./form-cache.js";
import { updateNextButtonState } from "./form-navigation.js";
import { debouncedUpdateSummary } from "./form-summary.js";
import { getIcon } from "./config.js";

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

export { setupPlanSelection };
export default { setupPlanSelection };

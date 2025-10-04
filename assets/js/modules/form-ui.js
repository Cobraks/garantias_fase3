//assets/js/modules/form-ui.js
"use strict";

/*
  - Gestión de UI del formulario: botones de limpiar, fechas, shimmer, etc.
*/

import { clearError, clearInfoMessage } from "./form-utils.js";
import { updateNextButtonState } from "./form-navigation.js";
import { debouncedUpdateSummary } from "./form-summary.js";
import { filtrarModalidades } from "./form-calculations.js";

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
 * Crea un párrafo de mensaje de info dentro de un contenedor dado
 */
function createInfoMessage(container) {
	const message = document.createElement("p");
	message.classList.add("form__info-message");
	container.appendChild(message);
	return message;
}

/**
 * Inicializador global del módulo de UI del formulario
 */
function init() {
        setupClearButtons();
        setTodayForGarantia();
        handleDateInputs();

	// Limpia error visual de vendedor si cambia selección
        const usuarioHidden = document.getElementById("usuario-rol");
        const usuarioDisplay = document.getElementById("usuario-rol-display");
        const handleUserPickerUpdate = () => {
                clearError(usuarioDisplay || usuarioHidden);
                updateNextButtonState();
                debouncedUpdateSummary();
        };
        if (usuarioHidden) {
                usuarioHidden.addEventListener("change", handleUserPickerUpdate);
        }
        if (usuarioDisplay) {
                usuarioDisplay.addEventListener("change", handleUserPickerUpdate);
                usuarioDisplay.addEventListener("input", () => {
                        if (!usuarioHidden || !usuarioHidden.value) {
                                clearError(usuarioDisplay);
                        }
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

}

export default {
        init,
        toggleClearButton,
        createInfoMessage,
};

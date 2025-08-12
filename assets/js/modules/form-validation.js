// assets/js/modules/form-validation.js
"use strict";

import {
	debounce,
	setError,
	clearError,
	clearInfoMessage,
	formatNumber,
	numericLimits,
	PROVINCIAS,
	validateNumeric,
	validateDNIField,
	validateTelefonoField,
	validateCodigoPostalField,
	validateEmailField,
	validateRequiredField,
	validateNumeroBastidorField,
	validateMatriculaField,
} from "./form-utils.js";

import FormCache from "./form-cache.js";
import FormUI from "./form-ui.js";
import { updateNextButtonState } from "./form-navigation.js";
import { getLimitesDinamicos } from "./form-state.js";
import { getUserRole } from "./config.js";

// === Helpers ===
function isTipoCamion() {
	const tipo = document.getElementById("tipo_vehiculo");
	return tipo && tipo.value === "camion";
}

function getDynamicLimit(field) {
        const limits = getLimitesDinamicos()[field] || null;
        if (
                limits &&
                typeof limits.min === "number" &&
                typeof limits.max === "number"
        ) {
                return limits;
        }
        if (field === "cilindrada") return { min: 0, max: 9000 };
        if (field === "potencia") return { min: 0, max: 3000 };
        if (field === "kilometros") return { min: 0, max: Infinity };
        return { min: 0, max: 999999 };
}

function validateWithDynamicLimit(input, showError) {
        const field = input.id;
        const raw = input.value.replace(/\./g, "").trim();
        const value = parseInt(raw, 10);

        if (raw === "") {
                if (showError) setError(input, "Este campo es obligatorio.");
                return false;
        }
        if (isNaN(value)) {
                if (showError) setError(input, "Introduce un valor válido.");
                return false;
        }
        if (field === "potencia" && isTipoCamion()) {
                if (value <= 0) {
                        if (showError) setError(input, "Introduce un valor válido.");
                        return false;
                }
                clearError(input);
                return true;
        }
        const limits = getDynamicLimit(field);
        if (value < limits.min) {
                if (showError) {
                        let msg;
                        if (field === "potencia") msg = `Potencia mínima ${limits.min} CV`;
                        else if (field === "cilindrada")
                                msg = `Cilindrada mínima ${limits.min} CC`;
                        else msg = `Kilometraje mínimo ${limits.min}`;
                        setError(input, msg);
                }
                return false;
        }
        if (value > limits.max) {
                if (showError) {
                        let msg;
                        if (field === "potencia") msg = `Potencia máxima ${limits.max} CV`;
                        else if (field === "cilindrada")
                                msg = `Cilindrada máxima ${limits.max} CC`;
                        else msg = `Kilometraje máximo ${limits.max}`;
                        setError(input, msg);
                }
                return false;
        }
        clearError(input);
        return true;
}

// === Sanitizadores / límites visuales por campo ===
const inputLimitsApplier = {
	numero_bastidor: (input) => {
		input.value = input.value
			.toUpperCase()
			.replace(/[^A-HJ-NPR-Z0-9]/g, "")
			.slice(0, 17);
	},
	dni: (input) => {
		input.value = input.value.replace(/[^A-Za-z0-9]/g, "").slice(0, 9);
	},
	telefono: (input) => {
		input.value = input.value.replace(/\D/g, "").slice(0, 9);
	},
        codigo_postal: (input) => {
                input.value = input.value.replace(/\D/g, "").slice(0, 5);
        },
        kilometros: (input) => {
                input.value = input.value.replace(/\D/g, "").slice(0, 7);
        },
        precio_venta: (input) => {
                input.value = input.value.replace(/\D/g, "").slice(0, 6);
        },
	cilindrada: (input) => {
		input.value = input.value.replace(/\D/g, "").slice(0, 4);
	},
	potencia: (input) => {
		input.value = input.value.replace(/\D/g, "").slice(0, 4);
	},
	matricula: (input) => {
		let value = input.value.toUpperCase();
		if (/^\d/.test(value)) {
			input.value = value.slice(0, 7);
		} else if (/^[A-Z]/.test(value)) {
			if (value.length >= 2 && PROVINCIAS.has(value.slice(0, 2))) {
				input.value = value.slice(0, 8);
			} else {
				input.value = value.slice(0, 7);
			}
		}
	},
};

// === Validadores especiales mapeados ===
const specialValidators = {
	numero_bastidor: (input, showError, isHardCheck) =>
		validateNumeroBastidorField(input, showError, isHardCheck),
	matricula: (input, showError, isHardCheck) =>
		validateMatriculaField(input, showError, isHardCheck),
	dni: (input, showError, isHardCheck) =>
		validateDNIField(input, showError, isHardCheck),
	telefono: (input, showError, isHardCheck) =>
		validateTelefonoField(input, showError, isHardCheck),
	codigo_postal: (input, showError, isHardCheck) =>
		validateCodigoPostalField(input, showError, isHardCheck),
	correo: (input, showError, isHardCheck) =>
		validateEmailField(input, showError, isHardCheck),
};

// === Función principal de validación ===
export function validateField(input, showError = false, isHardCheck = false) {
	const id = input.id;

	// Vendedor / profesional (usuario-rol) visible
	if (id === "usuario-rol") {
		const isVisible = input.offsetParent !== null;
		if (isVisible && !input.value) {
			if (showError) {
				const userRole =
                                        getUserRole() || document.body.dataset.userRole || "";
				const msg =
					userRole === "comercial" || userRole === "profesional"
						? "Selecciona profesional"
						: "Selecciona vendedor";
				setError(input, msg);
			}
			return false;
		}
		clearError(input);
		return true;
	}

	// Canal de venta (admin), si está visible y vacío
	if (id === "canal-venta") {
		const isVisible = input.offsetParent !== null;
		if (isVisible && !input.value) {
			if (showError) setError(input, "Selecciona canal de venta");
			return false;
		}
		clearError(input);
		return true;
	}

        // Tracción / camión
        if (id === "traccion" || id === "traccion_camion") {
                if (isTipoCamion()) {
                        if (id === "traccion_camion") {
                                if (!input.value) {
                                        if (showError) setError(input, "Este campo es obligatorio.");
                                        return false;
                                }
                                clearError(input);
                                return true;
                        }
                        if (id === "traccion") {
                                clearError(input);
                                return true;
                        }
                } else {
                        if (id === "traccion") {
                                if (!input.value) {
                                        if (showError) setError(input, "Este campo es obligatorio.");
                                        return false;
                                }
                                clearError(input);
                                return true;
                        }
                        if (id === "traccion_camion") {
                                clearError(input);
                                return true;
                        }
                }
        }

        // Potencia / cilindrada / kilometros con límites dinámicos
        if (id === "potencia" || id === "cilindrada" || id === "kilometros") {
                return validateWithDynamicLimit(input, showError);
        }

	// Doble motor: solo obligatorio si el combustible lo requiere
	if (id === "doble_motor") {
		const combustible = document.getElementById("combustible")?.value || "";
		const requiere = ["electrico", "hibrido", "gpl_gnc"].includes(
			combustible.toLowerCase()
		);
		if (requiere) {
			if (!input.value) {
				if (showError) setError(input, "Este campo es obligatorio.");
				return false;
			}
			clearError(input);
			return true;
		}
		clearError(input);
		return true;
	}

	// SELECT: placeholder flotado (solo visual)
	if (input.tagName === "SELECT") {
		const container = input.closest(".form__input-container");
		const isDefaultOption = input.options[input.selectedIndex]?.disabled;
		if (input.id === "provincia") {
			container?.classList.add("has-value");
		} else if (container) {
			container.classList.toggle("has-value", !isDefaultOption);
		}
	}

	// CHECKBOX
	if (input.type === "checkbox") {
		if (!input.checked) {
			if (showError) setError(input, "Debes aceptar los términos.");
			return false;
		}
		clearError(input);
		return true;
	}

	// Validadores específicos
	if (specialValidators[id]) {
		return specialValidators[id](input, showError, isHardCheck);
	}

	// Numéricos con límites fijos
	if (numericLimits[id]) {
		return validateNumeric(input, showError);
	}

	// Requerido genérico
	if (input.hasAttribute("required")) {
		return validateRequiredField(input, showError);
	}

	return true;
}

// === Exports auxiliares ===
export function forceDynamicFieldsValidation() {
        const cil = document.getElementById("cilindrada");
        const pot = document.getElementById("potencia");
        const km = document.getElementById("kilometros");
        if (cil) validateWithDynamicLimit(cil, true);
        if (pot) validateWithDynamicLimit(pot, true);
        if (km) validateWithDynamicLimit(km, true);
        updateNextButtonState();
}

export function removeAllDynamicErrors() {
        ["cilindrada", "potencia", "kilometros"].forEach((id) => {
                const input = document.getElementById(id);
                if (input) {
                        clearError(input);
                        clearInfoMessage(input);
                }
        });
}

// === Inicialización del comportamiento de inputs ===
function setupInputValidationBehavior(input) {
	const id = input.id;

	const handler = debounce(() => {
		// Aplicar sanitización si toca
		if (inputLimitsApplier[id]) {
			inputLimitsApplier[id](input);
		}
		// Formatear número cuando toca
		if (numericLimits[id]) {
			formatNumber(input);
		}
		// Validación ligera (sin hard check)
		validateField(input, true, false);
		// UI updates
		FormUI.toggleClearButton(input);
		updateNextButtonState();
	}, 200);

	input.addEventListener("input", handler);

	input.addEventListener("blur", () => {
		if (inputLimitsApplier[id]) {
			inputLimitsApplier[id](input);
		}
		if (numericLimits[id]) {
			formatNumber(input);
		}
		validateField(input, true, true);
		FormUI.toggleClearButton(input);
		updateNextButtonState();
	});

	if (input.tagName === "SELECT") {
		input.addEventListener("change", () => {
			validateField(input, true, true);
			updateNextButtonState();
		});
	}
}

// Inicializador
function initValidation() {
	const inputs =
		FormCache.inputs ||
		document.querySelectorAll(".form__input, .form__select, .form__checkbox");

	inputs.forEach((input) => {
		setupInputValidationBehavior(input);
	});

	// Revalidar dinámicos cuando cambian dependencias clave
	[
                "tipo_vehiculo",
                "combustible",
                "traccion_camion",
                "doble_motor",
        ].forEach((id) => {
		const el = document.getElementById(id);
		if (el) {
			el.addEventListener("change", () => {
				forceDynamicFieldsValidation();
			});
		}
	});
}

export default initValidation;

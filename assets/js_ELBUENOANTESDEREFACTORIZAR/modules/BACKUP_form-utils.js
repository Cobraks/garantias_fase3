"use strict";

/*
    - Helpers y validadores universales del formulario multistep.
    - TODO el flujo pasa por aquí. No accede a lógica de UI salvo para errores.
*/

// ===============================
// 0. Depuración y configuración
// ===============================
export const isDebug = true;
export function logDebug(...args) {
	// Oculta logs de clearError para no ensuciar la consola.
	if (isDebug && !(args[0] && args[0].startsWith("clearError"))) {
		console.log(...args);
	}
	// Si algún día quieres ver solo clearError:
	// if (isDebug && args[0] && args[0].startsWith("clearError")) console.log(...args);
}

// ===============================
// 1. Límites y constantes globales
// ===============================
export const numericLimits = {
	kilometros: { min: 0, max: 400000 },
	precio_venta: { min: 0, max: 999999 },
	cilindrada: { min: 0, max: 9000 },
	potencia: { min: 0, max: 3000 },
};

export const PROVINCIAS = new Set([
	"VI",
	"AB",
	"A",
	"AL",
	"O",
	"AV",
	"BA",
	"B",
	"BU",
	"CC",
	"CA",
	"S",
	"CS",
	"CE",
	"CR",
	"C",
	"CO",
	"CU",
	"GI",
	"GC",
	"GR",
	"GU",
	"SS",
	"H",
	"HU",
	"IB",
	"J",
	"LO",
	"LE",
	"LU",
	"L",
	"M",
	"ML",
	"MU",
	"MA",
	"NA",
	"OU",
	"P",
	"PO",
	"SA",
	"SG",
	"SE",
	"SO",
	"T",
	"TF",
	"TE",
	"TO",
	"V",
	"VA",
	"BI",
	"ZA",
	"Z",
]);

// ===============================
// 2. Helpers universales
// ===============================
export function debounce(fn, delay) {
	let timeout;
	return function (...args) {
		clearTimeout(timeout);
		timeout = setTimeout(() => fn.apply(this, args), delay);
	};
}

// ===============================
// 3. Funciones de errores/mensajes UI
// ===============================
export function setError(input, message) {
	const container = input.closest(".form__input-container");
	if (!container) return;
	container.classList.add("error");
	clearInfoMessage(input);
	let errorElem = container.querySelector(".form__error-message");
	if (!errorElem) {
		errorElem = document.createElement("p");
		errorElem.classList.add("form__error-message");
		container.appendChild(errorElem);
	}
	errorElem.textContent = message;
	input.setAttribute("aria-invalid", "true");
	logDebug(`setError(${input.id}): ${message}`);
}

export function clearError(input) {
	const container = input.closest(".form__input-container");
	if (!container) return;
	container.classList.remove("error");
	const errorElem = container.querySelector(".form__error-message");
	if (errorElem) errorElem.remove();
	input.removeAttribute("aria-invalid");
	// Ya no se loguea nada aquí, para no ensuciar la consola
}

export function clearInfoMessage(input) {
	const container = input.closest(".form__input-container");
	if (!container) return;
	const infoElem = container.querySelector(".form__info-message");
	if (infoElem) infoElem.remove();
}

export function formatNumber(input) {
	let value = input.value.replace(/\./g, "").replace(/[^\d]/g, "");
	if (value) {
		value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
		input.value = value;
		logDebug(`formatNumber(${input.id}): ${value}`);
	}
}

// ===============================
// 4. Validaciones de campos básicos y específicos
// ===============================

export function validateNumeric(input, showError) {
	const rawValue = input.value.replace(/\./g, "");
	if (rawValue === "") {
		if (showError) setError(input, "Este campo es obligatorio.");
		return false;
	}
	const num = parseInt(rawValue, 10);
	const limits = numericLimits[input.id];
	if (isNaN(num)) {
		if (showError) setError(input, "Debe ser un número.");
		return false;
	}
	if (num < limits.min) {
		if (showError) setError(input, `Debe ser mayor o igual a ${limits.min}.`);
		return false;
	}
	if (num > limits.max) {
		if (showError)
			setError(
				input,
				`El valor no puede exceder ${limits.max.toLocaleString()}.`
			);
		return false;
	}
	clearError(input);
	return true;
}

// -- DNI
export function validateDNI(value) {
	const dniRegex = /^(?:\d{8}|[XYZ]\d{7})[A-Z]$/i;
	if (!dniRegex.test(value)) return false;
	let dni = value.toUpperCase();
	dni = dni.replace(/^X/, "0").replace(/^Y/, "1").replace(/^Z/, "2");
	const numbers = parseInt(dni.slice(0, -1), 10);
	const validLetter = "TRWAGMYFPDXBNJZSQVHLCKE"[numbers % 23];
	return dni.slice(-1) === validLetter;
}

export function validateDNIField(input, showError, isHardCheck = false) {
	const value = input.value.trim().toUpperCase();
	if (value.length > 0) {
		if (/^[0-9]/.test(value)) {
			const digitsPart = value.slice(0, Math.min(value.length, 8));
			if (!/^\d*$/.test(digitsPart)) {
				if (showError) setError(input, "DNI incorrecto");
				return false;
			}
		} else if (/^[XYZ]/.test(value)) {
			const numberPart = value.slice(1, Math.min(value.length, 8));
			if (!/^\d*$/.test(numberPart)) {
				if (showError) setError(input, "DNI incorrecto");
				return false;
			}
		} else {
			if (showError) setError(input, "Formato inicial inválido");
			return false;
		}
		if (value.length === 9 && !validateDNI(value)) {
			if (showError) setError(input, "DNI incorrecto");
			return false;
		}
	}
	if (isHardCheck) {
		if (value === "") {
			if (showError) setError(input, "Este campo es obligatorio.");
			return false;
		}
		if (value.length !== 9) {
			if (showError) setError(input, "Debe tener 9 caracteres");
			return false;
		}
		if (!validateDNI(value)) {
			if (showError) setError(input, "Letra de control incorrecta.");
			return false;
		}
	}
	clearError(input);
	return true;
}

// -- TELÉFONO
export function validateTelefonoField(input, showError, isHardCheck = false) {
	const value = input.value.trim();
	const firstDigit = value[0] || "";
	if (value.length > 0 && !/^[6-9]$/.test(firstDigit)) {
		if (showError) setError(input, "Debe empezar con 6-9");
		return false;
	}
	if (isHardCheck) {
		if (value === "") {
			if (showError) setError(input, "Este campo es obligatorio.");
			return false;
		}
		if (!/^[6-9]\d{8}$/.test(value)) {
			if (showError) setError(input, "Teléfono inválido (9 dígitos)");
			return false;
		}
	}
	clearError(input);
	return true;
}

// -- CÓDIGO POSTAL
export function validateCodigoPostalField(
	input,
	showError,
	isHardCheck = false
) {
	const value = input.value.trim();
	if (value.length === 1) {
		if (!/^[0-5]$/.test(value)) {
			if (showError) setError(input, "Código postal inválido.");
			return false;
		} else {
			clearError(input);
			return true;
		}
	}
	if (value.length > 0) {
		if (value.length >= 2) {
			if (!/^(0[1-9]|[1-4]\d|5[0-3])/.test(value)) {
				if (showError) setError(input, "Código postal inválido.");
				return false;
			}
		}
	}
	if (isHardCheck) {
		if (!/^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/.test(value)) {
			if (showError) setError(input, "Código postal inválido.");
			return false;
		}
	}
	clearError(input);
	return true;
}

// -- EMAIL
export function validateEmailField(input, showError, isHardCheck = false) {
	const value = input.value.trim();
	if (isHardCheck) {
		const emailRegex = /^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/;
		if (!emailRegex.test(value)) {
			if (showError) setError(input, "Correo electrónico inválido.");
			return false;
		}
	}
	clearError(input);
	return true;
}

// -- CAMPO OBLIGATORIO
export function validateRequiredField(input, showError) {
	if (input.value.trim() === "") {
		if (showError) setError(input, "Este campo es obligatorio.");
		return false;
	}
	clearError(input);
	return true;
}

// -- BASTIDOR
export function validateNumeroBastidorField(
	input,
	showError,
	isHardCheck = false
) {
	let value = input.value.trim().toUpperCase();
	if (isHardCheck) {
		if (value.length < 17) {
			if (showError)
				setError(input, "Faltan " + (17 - value.length) + " carácteres.");
			return false;
		}
		if (!/^[A-HJ-NPR-Z0-9]{17}$/.test(value)) {
			if (showError) setError(input, "Caracteres inválidos en bastidor.");
			return false;
		}
	}
	clearError(input);
	return true;
}

// -- MATRÍCULA
export function validateMatriculaField(input, showError, isHardCheck = false) {
	let value = input.value.toUpperCase();
	input.value = value;
	if (/^\d/.test(value)) {
		if (isHardCheck) {
			if (!/^\d{4}[BCDFGHJKLMNPSTVWXYZ]{3}$/i.test(value)) {
				if (showError) setError(input, "Matrícula no válida.");
				return false;
			}
		}
	} else if (/^[A-Z]/.test(value)) {
		const prefix = PROVINCIAS.has(value.slice(0, 2))
			? value.slice(0, 2)
			: value.charAt(0);
		const expectedLength = prefix.length === 2 ? 8 : 7;
		if (isHardCheck) {
			if (value.length !== expectedLength) {
				if (showError)
					//setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					setError(input, `Matrícula incompleta`);
				return false;
			}
			const remainderComplete = value.slice(prefix.length);
			if (!/^\d{4}$/.test(remainderComplete.slice(0, 4))) {
				if (showError)
					//setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					setError(input, `Matrícula no válida`);
				return false;
			}
			const letters = remainderComplete.slice(4);
			if (letters.length !== 2) {
				if (showError) setError(input, `Matrícula clásica no válida`);
				//setError(input, `Formato clásico inválido para prefijo ${prefix}`);
				return false;
			}
			const letter1 = letters.charAt(0);
			const letter2 = letters.charAt(1);
			if (/[QRÑ]/i.test(letter1)) {
				if (showError)
					setError(
						input,
						`Formato clásico inválido para prefijo ${prefix}: letra no permitida.`
					);
				return false;
			}
			if (/[QRÑ]/i.test(letter2)) {
				if (showError)
					setError(
						input,
						`Formato clásico inválido para prefijo ${prefix}: letra no permitida.`
					);
				return false;
			}
			if (!(/[AEIOU]/.test(letter1) || /[BCDFGHJKLMNPSTVWXYZ]/.test(letter1))) {
				if (showError)
					//	setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					setError(input, `Eror en matrícula. Revisa los números`);
				return false;
			}
			if (!(letter2 === "U" || /[BCDFGHJKLMNPSTVWXYZ]/.test(letter2))) {
				if (showError)
					//setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					setError(
						input,
						`La última letra no puede ser A, E, I, O ni un número`
					);
				return false;
			}
			if (letter1 === "W" && letter2 === "C") {
				if (showError) setError(input, `Combinación WC no permitida`);
				//setError(input, `Formato clásico inválido para prefijo ${prefix}`);
				return false;
			}
		}
	} else {
		if (showError) setError(input, "Revisa la matrícula.");
		return false;
	}
	clearError(input);
	return true;
}

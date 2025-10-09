// assets/js/modules/form-utils.js
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
	if (
		isDebug &&
		!(
			args[0] &&
			typeof args[0] === "string" &&
			args[0].startsWith("clearError")
		)
	) {
		console.log(...args);
	}
}

// ===============================
// 1. Límites y constantes globales
// ===============================
export const numericLimits = {
        kilometros: { min: 0, max: Infinity },
        precio_venta: { min: 0, max: 999999 },
        cilindrada: { min: 0, max: 9000 },
        potencia: { min: 0, max: 3000 },
};

export const IVA_PORCENTAJE = 21;

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

// Cache auxiliar para startsWith sobre provincias clásicas
const PROVINCIAS_ARRAY = Array.from(PROVINCIAS);
export function provinciaEmpiezaPor(prefijo) {
	if (!prefijo) return false;
	const upper = prefijo.toUpperCase();
	return PROVINCIAS_ARRAY.some((p) => p.startsWith(upper));
}

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
// 3. Formateo / parsing común
// ===============================
function redondearEuros(valor) {
	if (typeof valor !== "number") valor = parseFloat(valor);
	if (isNaN(valor)) return 0;
	return Math.round(valor * 100) / 100;
}

export function eurosString(valor) {
	const num = redondearEuros(
		typeof valor === "number" ? valor : parseFloat(valor)
	);
	return num.toLocaleString("es-ES", {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
		useGrouping: true,
	});
}

export function parseNumericFormValue(val) {
        if (typeof val === "number") return val;
        if (val === null || val === undefined) return 0;

        let str = String(val).trim();
        if (!str) return 0;

        // Elimina espacios y separadores no numéricos comunes (salvo signo y separadores decimales)
        str = str.replace(/\s/g, "");

        const lastComma = str.lastIndexOf(",");
        const lastDot = str.lastIndexOf(".");
        let decimalSeparator = null;

        if (lastComma !== -1 && lastDot !== -1) {
                decimalSeparator = lastComma > lastDot ? "," : ".";
        } else if (lastComma !== -1) {
                const decimalsLength = str.length - lastComma - 1;
                decimalSeparator = decimalsLength === 3 && lastComma > 0 ? null : ",";
        } else if (lastDot !== -1) {
                const decimalsLength = str.length - lastDot - 1;
                decimalSeparator = decimalsLength === 3 && lastDot > 0 ? null : ".";
        }

        const thousandSeparator = decimalSeparator === "," ? "." : ",";

        if (decimalSeparator) {
                const thousandRegex = new RegExp(`\\${thousandSeparator}`, "g");
                str = str.replace(thousandRegex, "");
                if (decimalSeparator !== ".") {
                        const decimalRegex = new RegExp(`\\${decimalSeparator}`, "g");
                        str = str.replace(decimalRegex, ".");
                }
        } else {
                // No se detectó separador decimal; elimina ambos separadores comunes como miles.
                str = str.replace(/[.,]/g, "");
        }

        // Elimina cualquier carácter que no sea dígito o signo negativo.
        str = str.replace(/[^0-9.-]/g, "");

        const parsed = Number(str);
        return Number.isNaN(parsed) ? 0 : parsed;
}

export function getAntiguedadFromDate(fechaISO) {
        if (!fechaISO) return null;
        const partes = typeof fechaISO === "string" ? fechaISO.split("-") : null;
        let fecha = null;
        if (
                Array.isArray(partes) &&
                partes.length >= 3 &&
                partes.every((p) => /^\d+$/.test(p))
        ) {
                const [anio, mes, dia] = partes.map((p) => Number.parseInt(p, 10));
                fecha = new Date(anio, (mes || 1) - 1, dia || 1);
        } else {
                fecha = new Date(fechaISO);
        }
        if (isNaN(fecha.getTime())) return null;

        const hoy = new Date();
        const fechaMatriculacion = new Date(
                fecha.getFullYear(),
                fecha.getMonth(),
                fecha.getDate()
        );

        if (hoy < fechaMatriculacion) return 0;

        let anos = hoy.getFullYear() - fechaMatriculacion.getFullYear();
        let meses = hoy.getMonth() - fechaMatriculacion.getMonth();
        let dias = hoy.getDate() - fechaMatriculacion.getDate();
        let baseDias = 0;

        if (dias < 0) {
                const mesAnterior = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
                baseDias = mesAnterior.getDate();
                dias += baseDias;
                meses -= 1;
        } else {
                baseDias = new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0).getDate();
        }

        if (meses < 0) {
                meses += 12;
                anos -= 1;
        }

        const mesesTotales = anos * 12 + meses;
        const fraccionMes = baseDias > 0 ? dias / baseDias : 0;
        const antiguedad = (mesesTotales + fraccionMes) / 12;

        const normalizado = antiguedad < 0 ? 0 : antiguedad;
        return Number(normalizado.toFixed(6));
}

// ===============================
// 4. Funciones de errores/mensajes UI
// ===============================
export function setError(input, message) {
	if (!input) return;
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
	if (!input) return;
	const container = input.closest(".form__input-container");
	if (!container) return;
	container.classList.remove("error");
	const errorElem = container.querySelector(".form__error-message");
	if (errorElem) errorElem.remove();
	input.removeAttribute("aria-invalid");
}

export function clearInfoMessage(input) {
	if (!input) return;
	const container = input.closest(".form__input-container");
	if (!container) return;
	const infoElem = container.querySelector(".form__info-message");
	if (infoElem) infoElem.remove();
}

export function formatNumber(input) {
        if (!input) return;
        let value = input.value.replace(/\./g, "").replace(/[^\d]/g, "");
        if (value) {
                value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
                input.value = value;
                logDebug(`formatNumber(${input.id}): ${value}`);
        }
}

export function formatCurrency(input, keepTrailingComma = false) {
        if (!input) return;
        let value = input.value.replace(/\./g, "").replace(/[^0-9,]/g, "");
        const endsWithComma = value.endsWith(",");
        const parts = value.split(",");
        const intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        const decPart = parts.slice(1).join("");
        if (decPart) {
                input.value = `${intPart},${decPart}`;
        } else if (keepTrailingComma && endsWithComma) {
                input.value = `${intPart},`;
        } else {
                input.value = intPart;
        }
        logDebug(`formatCurrency(${input.id}): ${input.value}`);
}

// ===============================
// 5. Validaciones de campos básicos y específicos
// ===============================

export function validateNumeric(input, showError) {
	if (!input) return false;
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
	if (!input) return false;
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
	if (!input) return false;
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
	if (!input) return false;
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
	if (value.length >= 2) {
		if (!/^(0[1-9]|[1-4]\d|5[0-3])/.test(value)) {
			if (showError) setError(input, "Código postal inválido.");
			return false;
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
	if (!input) return false;
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
	if (!input) return false;
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
	if (!input) return false;
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

// ---------- AYUDAS PARA MATRÍCULA ----------

// Reglas en tiempo real para matrícula moderna
function getRealtimeModernError(value) {
	// Solo letras y números
	if (/[^A-Za-z0-9]/.test(value)) {
		return "Usa solo letras y números, sin espacios";
	}
	// Hasta 4 dígitos al principio
	if (value.length <= 4) {
		for (let i = 0; i < value.length; i++) {
			const ch = value.charAt(i);
			if (i < 4 && /\D/.test(ch)) {
				return "Se espera un número, no una letra";
			}
		}
		return null;
	}
	// Más de 4: los primeros 4 deben ser dígitos
	const first4 = value.slice(0, 4);
	if (!/^\d{4}/.test(first4)) {
		for (let i = 0; i < 4; i++) {
			const ch = value.charAt(i);
			if (!/\d/.test(ch)) {
				return "Se espera un número, no una letra";
			}
		}
	}
	// Parte de letras después de 4 dígitos (hasta 3)
	const lettersPart = value.slice(4);
	for (let i = 0; i < lettersPart.length; i++) {
		const ch = lettersPart.charAt(i);
		if (/\d/.test(ch)) {
			return "Se espera una letra, no un número";
		}
	}
	return null;
}

// Comprueba si el valor parcial puede derivar en una matrícula válida
export function isPotentiallyValidMatricula(value) {
	if (/[^A-Za-z0-9]/.test(value)) {
		return false;
	}
	// Moderna: 4 dígitos seguidos de hasta 3 letras válidas
	if (/^\d{0,4}$/.test(value)) {
		return true;
	}
        if (/^\d{4}[BCDFGHJKLMNPRSTVWXYZ]{0,3}$/i.test(value)) {
		return true;
	}
	// Clásica
	let upper = value.toUpperCase();
	let prefixOptions = [];
	const two = upper.slice(0, 2);
	const one = upper.charAt(0);
	if (PROVINCIAS.has(two))
		prefixOptions.push({ prefix: two, rest: upper.slice(2) });
	if (PROVINCIAS.has(one))
		prefixOptions.push({ prefix: one, rest: upper.slice(1) });

	if (prefixOptions.length === 0) {
		if (upper.length === 1) return PROVINCIAS.has(one);
		return false;
	}

	for (const { prefix, rest } of prefixOptions) {
		if (rest === "") return true;
		if (/^\d{1,4}$/.test(rest)) return true;
		if (!/^\d{4}/.test(rest.slice(0, 4))) continue;
		const tail = rest.slice(4);
                if (/^[BCDFGHJKLMNPRSTVWXYZ]{1,2}$/i.test(tail)) return true;
	}
	return false;
}

// -- MATRÍCULA
export function validateMatriculaField(input, showError, isHardCheck = false) {
	if (!input) return false;
	let value = input.value.toUpperCase();
	input.value = value;

	// Moderna completa: 4 dígitos + 3 letras válidas
        const modernFullRegex = /^\d{4}[BCDFGHJKLMNPRSTVWXYZ]{3}$/i;

	if (!isHardCheck) {
		// Caracteres no permitidos
		if (/[^A-Za-z0-9]/.test(value)) {
			if (showError)
				setError(input, "Carácter no permitido. Usa solo letras y números");
			return false;
		}

		// Moderna parcial / en tiempo real
		if (/^\d/.test(value)) {
			const modernError = getRealtimeModernError(value);
			if (modernError) {
				if (showError) setError(input, modernError);
				return false;
			}
			clearError(input);
			return true;
		}

		// Clásica / general progresiva
		if (!isPotentiallyValidMatricula(value)) {
			const first = value.charAt(0);
			const two = value.slice(0, 2);
			if (
				value.length >= 1 &&
				!PROVINCIAS.has(first) &&
				!provinciaEmpiezaPor(first)
			) {
				if (showError) setError(input, "Prefijo provincial no válido");
				return false;
			}
			if (value.length >= 2) {
				if (!PROVINCIAS.has(two) && PROVINCIAS.has(first)) {
					if (!/^\d$/.test(value.charAt(1))) {
						if (showError) setError(input, "Prefijo provincial no reconocido");
						return false;
					}
				}
			}
			if (showError) setError(input, "Matrícula inválida");
			return false;
		}
		clearError(input);
		return true;
	}

	// Hard check completo
	if (/^\d/.test(value)) {
		// Moderna
		if (!modernFullRegex.test(value)) {
			if (showError) setError(input, "Formato de matrícula moderna inválido.");
			return false;
		}
	} else if (/^[A-Z]/.test(value)) {
		// Clásica
		let prefix;
		const two = value.slice(0, 2);
		const one = value.charAt(0);
		if (PROVINCIAS.has(two)) {
			prefix = two;
		} else if (PROVINCIAS.has(one)) {
			prefix = one;
		} else {
			if (showError) setError(input, "Prefijo provincial no válido");
			return false;
		}
		const expectedLength = prefix.length === 2 ? 8 : 7;
		if (value.length !== expectedLength) {
			if (showError) setError(input, "Matrícula incompleta");
			return false;
		}
		const remainderComplete = value.slice(prefix.length);
		if (!/^\d{4}/.test(remainderComplete)) {
			if (showError)
				setError(
					input,
					"Los 4 caracteres después del prefijo deben ser números"
				);
			return false;
		}
		const letters = remainderComplete.slice(4);
		if (letters.length !== 2) {
			if (showError) setError(input, "Formato clásico inválido: faltan letras");
			return false;
		}
		const letter1 = letters.charAt(0);
		const letter2 = letters.charAt(1);
		if (/[QRÑ]/i.test(letter1)) {
			if (showError)
				setError(
					input,
					`Formato clásico inválido para prefijo ${prefix}: primera letra no permitida.`
				);
			return false;
		}
		if (/[QRÑ]/i.test(letter2)) {
			if (showError)
				setError(
					input,
					`Formato clásico inválido para prefijo ${prefix}: segunda letra no permitida.`
				);
			return false;
		}
                if (!(/[AEIOU]/.test(letter1) || /[BCDFGHJKLMNPRSTVWXYZ]/.test(letter1))) {
			if (showError)
				setError(input, `Error en la parte numérica de la matrícula clásica.`);
			return false;
		}
                if (!(letter2 === "U" || /[BCDFGHJKLMNPRSTVWXYZ]/.test(letter2))) {
			if (showError)
				setError(input, `La última letra no puede ser A, E, I, O ni un número`);
			return false;
		}
		if (letter1 === "W" && letter2 === "C") {
			if (showError) setError(input, `Combinación WC no permitida`);
			return false;
		}
	} else {
		if (showError) setError(input, "Revisa la matrícula.");
		return false;
	}

	clearError(input);
	return true;
}

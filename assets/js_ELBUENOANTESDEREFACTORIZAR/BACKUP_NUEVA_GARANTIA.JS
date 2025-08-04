(function () {
	"use strict";

	/* =====================================================
       0. Depuración
    ===================================================== */
	const isDebug = true;
	function logDebug(...args) {
		if (isDebug) console.log(...args);
	}

	/* =====================================================
       1. Variables Globales y Configuración (FormCache)
    ===================================================== */
	const FormCache = {
		currentTab: 0,
		tabs: document.querySelectorAll(".tabs__link"),
		fieldsets: document.querySelectorAll(".form__tab-content"),
		prevButton: document.querySelector(".nav-buttons .btn.btn-secondary"),
		nextButton: document.querySelector(".nav-buttons .btn.btn-primary"),
		inputs: document.querySelectorAll(
			".form__input, .form__select, .form__checkbox"
		),
	};

	// Límites para campos numéricos
	const numericLimits = {
		kilometros: { min: 0, max: 400000 },
		precio_venta: { min: 0, max: 999999 },
		cilindrada: { min: 0, max: 2000 },
		potencia: { min: 0, max: 1000 },
	};

	// Conjunto de prefijos provinciales válidos (para matrícula clásica)
	const PROVINCIAS = new Set([
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

	/* =====================================================
       2. Utilidades
    ===================================================== */
	function debounce(fn, delay) {
		let timeout;
		return function (...args) {
			clearTimeout(timeout);
			timeout = setTimeout(() => fn.apply(this, args), delay);
		};
	}

	function setValidating(input, state) {
		const container = input.closest(".form__input-container");
		if (!container) return;
		container.classList.toggle("validating", state);
		logDebug(`setValidating(${input.id}): ${state}`);
	}

	function setError(input, message) {
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

	function clearError(input) {
		const container = input.closest(".form__input-container");
		if (!container) return;
		container.classList.remove("error");
		const errorElem = container.querySelector(".form__error-message");
		if (errorElem) errorElem.remove();
		input.removeAttribute("aria-invalid");
		logDebug(`clearError(${input.id})`);
	}

	function clearInfoMessage(input) {
		const container = input.closest(".form__input-container");
		if (!container) return;
		const infoElem = container.querySelector(".form__info-message");
		if (infoElem) infoElem.remove();
	}

	function formatNumber(input) {
		let value = input.value.replace(/\./g, "").replace(/[^\d]/g, "");
		if (value) {
			value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
			input.value = value;
			logDebug(`formatNumber(${input.id}): ${value}`);
		}
	}

	/* =====================================================
       3. Validaciones Específicas
    ===================================================== */
	function validateNumeric(input, showError) {
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

	function validateDNI(value) {
		const dniRegex = /^(?:\d{8}|[XYZ]\d{7})[A-Z]$/i;
		if (!dniRegex.test(value)) return false;
		let dni = value.toUpperCase();
		dni = dni.replace(/^X/, "0").replace(/^Y/, "1").replace(/^Z/, "2");
		const numbers = parseInt(dni.slice(0, -1), 10);
		const validLetter = "TRWAGMYFPDXBNJZSQVHLCKE"[numbers % 23];
		return dni.slice(-1) === validLetter;
	}

	function validateDNIField(input, showError, isHardCheck = false) {
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

	function validateTelefonoField(input, showError, isHardCheck = false) {
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

	function validateCodigoPostalField(input, showError, isHardCheck = false) {
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

	function validateEmailField(input, showError, isHardCheck = false) {
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

	function validateRequiredField(input, showError) {
		if (input.value.trim() === "") {
			if (showError) setError(input, "Este campo es obligatorio.");
			return false;
		}
		clearError(input);
		return true;
	}

	function validateNumeroBastidorField(input, showError, isHardCheck = false) {
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

	function validateMatriculaField(input, showError, isHardCheck = false) {
		let value = input.value.toUpperCase();
		input.value = value;
		if (/^\d/.test(value)) {
			if (isHardCheck) {
				if (!/^\d{4}[BCDFGHJKLMNPSTVWXYZ]{3}$/i.test(value)) {
					if (showError) setError(input, "Formato moderno inválido: 1234ABC");
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
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					return false;
				}
				const remainderComplete = value.slice(prefix.length);
				if (!/^\d{4}$/.test(remainderComplete.slice(0, 4))) {
					if (showError)
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					return false;
				}
				const letters = remainderComplete.slice(4);
				if (letters.length !== 2) {
					if (showError)
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
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
				if (
					!(/[AEIOU]/.test(letter1) || /[BCDFGHJKLMNPSTVWXYZ]/.test(letter1))
				) {
					if (showError)
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					return false;
				}
				if (!(letter2 === "U" || /[BCDFGHJKLMNPSTVWXYZ]/.test(letter2))) {
					if (showError)
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					return false;
				}
				if (letter1 === "W" && letter2 === "C") {
					if (showError)
						setError(input, `Formato clásico inválido para prefijo ${prefix}`);
					return false;
				}
			}
		} else {
			if (showError) setError(input, "Formato de matrícula desconocido.");
			return false;
		}
		clearError(input);
		return true;
	}

	/* =====================================================
         4. Funciones de UI/Interacción
    ===================================================== */
	function toggleClearButton(input) {
		const container = input.closest(".form__input-container");
		const clearBtn = container?.querySelector(".form__clear-btn");
		if (clearBtn) clearBtn.style.display = input.value.trim() ? "flex" : "none";
	}

	function handleClearButtons() {
		document.addEventListener("click", (event) => {
			const clearBtn = event.target.closest(".form__clear-btn");
			if (!clearBtn) return;
			const input = clearBtn
				.closest(".form__input-container")
				?.querySelector(".form__input");
			if (input) {
				input.value = "";
				clearError(input);
				input.dispatchEvent(new Event("input"));
			}
		});
	}

	const inputLimits = {
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
			input.value = input.value.replace(/\D/g, "").slice(0, 6);
		},
		precio_venta: (input) => {
			input.value = input.value.replace(/\D/g, "").slice(0, 6);
		},
		cilindrada: (input) => {
			input.value = input.value.replace(/\D/g, "").slice(0, 4);
		},
		potencia: (input) => {
			input.value = input.value.replace(/\D/g, "").slice(0, 3);
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

	function createInfoMessage(container) {
		const message = document.createElement("p");
		message.classList.add("form__info-message");
		container.appendChild(message);
		return message;
	}

	/* =====================================================
         6. Funciones Adicionales y de información en tiempo real
    ===================================================== */
	function handleBastidorInput(input) {
		let value = input.value.toUpperCase();
		input.value = value;
		const container = input.closest(".form__input-container");
		let infoMessage =
			container.querySelector(".form__info-message") ||
			createInfoMessage(container);
		if (value === "") {
			infoMessage.textContent = "0 de 17 carácteres";
			clearError(input);
			return;
		}
		if (!/^[A-HJ-NPR-Z0-9]*$/.test(value)) {
			infoMessage.textContent = "";
			setError(input, "Carácter inválido en el bastidor.");
			return;
		}
		if (value.length < 17) {
			infoMessage.textContent = `${value.length} de 17 carácteres`;
			clearError(input);
			return;
		}
		if (value.length === 17) {
			if (/^[A-HJ-NPR-Z0-9]{17}$/.test(value)) {
				infoMessage.textContent = "";
				clearError(input);
			} else {
				infoMessage.textContent = "";
				setError(input, "Número de bastidor contiene caracteres inválidos.");
			}
		}
	}

	function handleMatriculaInput(input, isBlur = false) {
		let value = input.value.trim().toUpperCase();
		input.value = value;
		const container = input.closest(".form__input-container");
		let infoMessage =
			container.querySelector(".form__info-message") ||
			createInfoMessage(container);
		clearError(input);
		infoMessage.textContent = "";
		if (value === "") return;
		if (/^\d/.test(value)) {
			const numPart = value.substring(0, 4);
			const letterPart = value.substring(4);
			if (!isBlur) {
				if (numPart.length < 4) {
					infoMessage.textContent = `Números ${numPart.length} de 4`;
					if (!/^\d*$/.test(numPart)) {
						setError(
							input,
							"Formato moderno inválido: solo dígitos en los primeros 4 caracteres."
						);
					}
					return;
				}
				if (letterPart.length < 3) {
					infoMessage.textContent = `Letras ${letterPart.length} de 3`;
					if (letterPart && !/^[BCDFGHJKLMNPSTVWXYZ]*$/i.test(letterPart)) {
						setError(input, "Formato moderno inválido: letras incorrectas.");
					}
					return;
				}
			} else {
				if (value.length !== 7) {
					setError(
						input,
						"La matrícula moderna debe tener 7 caracteres (ej. 1234ABC)."
					);
					return;
				}
				if (!/^\d{4}[BCDFGHJKLMNPSTVWXYZ]{3}$/i.test(value)) {
					setError(
						input,
						"Matrícula moderna no válida: 4 dígitos seguidos de 3 consonantes."
					);
					return;
				}
			}
		} else if (/^[A-Z]/.test(value)) {
			const prefix = PROVINCIAS.has(value.slice(0, 2))
				? value.slice(0, 2)
				: value.charAt(0);
			const expectedLength = prefix.length === 2 ? 8 : 7;
			if (value.length > expectedLength) {
				input.value = value.slice(0, expectedLength);
				value = input.value;
			}
			const rest = value.slice(prefix.length);
			const numPart = rest.substring(0, 4);
			const letterPart = rest.substring(4);
			if (!isBlur) {
				if (!/^\d*$/.test(numPart)) {
					setError(
						input,
						"Formato clásico inválido: la parte numérica debe ser dígitos."
					);
					return;
				}
				if (numPart.length < 4) {
					infoMessage.textContent = `Números ${numPart.length} de 4`;
					return;
				}
				if (!/^[A-Z]*$/.test(letterPart)) {
					setError(
						input,
						"Formato clásico inválido: la parte final debe ser letras."
					);
					return;
				}
				if (letterPart.length < 2) {
					infoMessage.textContent = `Letras ${letterPart.length} de 2`;
					return;
				}
				if (letterPart.length >= 1 && /[QRÑ]/i.test(letterPart.charAt(0))) {
					setError(
						input,
						"Matrícula clásica no válida: primera letra final no permitida."
					);
					return;
				}
				if (letterPart.length >= 2) {
					if (/[QRÑ]/i.test(letterPart.charAt(1))) {
						setError(
							input,
							"Matrícula clásica no válida: segunda letra final no permitida."
						);
						return;
					}
					if (
						/^[AEIO]$/i.test(letterPart.charAt(1)) &&
						letterPart.charAt(1).toUpperCase() !== "U"
					) {
						setError(
							input,
							"Matrícula clásica no válida: segunda letra final incorrecta."
						);
						return;
					}
					if (letterPart.toUpperCase() === "WC") {
						setError(input, "Matrícula clásica no válida: 'WC' no permitido.");
						return;
					}
				}
			} else {
				if (value.length !== expectedLength) {
					setError(
						input,
						`La matrícula clásica debe tener ${expectedLength} caracteres (ej. ${prefix}1234AB).`
					);
					return;
				}
				const num = value.substring(prefix.length, prefix.length + 4);
				const letters = value.substring(prefix.length + 4);
				if (!/^\d{4}$/.test(num)) {
					setError(input, "Después del prefijo deben venir 4 dígitos.");
					return;
				}
				if (!/^[A-Z]{2}$/.test(letters)) {
					setError(input, "La matrícula clásica debe terminar en 2 letras.");
					return;
				}
				if (/[QRÑ]/i.test(letters.charAt(0))) {
					setError(
						input,
						"Matrícula clásica no válida: primera letra final no permitida."
					);
					return;
				}
				if (/[QRÑ]/i.test(letters.charAt(1))) {
					setError(
						input,
						"Matrícula clásica no válida: segunda letra final no permitida."
					);
					return;
				}
				if (
					/^[AEIO]$/i.test(letters.charAt(1)) &&
					letters.charAt(1).toUpperCase() !== "U"
				) {
					setError(
						input,
						"Matrícula clásica no válida: segunda letra final incorrecta."
					);
					return;
				}
				if (letters.toUpperCase() === "WC") {
					setError(input, "Matrícula clásica no válida: 'WC' no permitido.");
					return;
				}
			}
		} else {
			setError(input, "Formato de matrícula desconocido.");
			return;
		}
	}

	function handleCodigoPostalInput(input) {
		let value = input.value.replace(/\D/g, "");
		input.value = value;
		const container = input.closest(".form__input-container");
		let infoMessage =
			container.querySelector(".form__info-message") ||
			createInfoMessage(container);
		if (value === "") {
			infoMessage.textContent = "0 de 5 dígitos";
			clearError(input);
			return;
		}
		if (value.length === 1) {
			if (!/^[0-5]$/.test(value)) {
				setError(input, "Código postal inválido.");
				return;
			} else {
				infoMessage.textContent = "1 de 5 dígitos";
				clearError(input);
				return;
			}
		}
		if (value.length < 5) {
			infoMessage.textContent = `${value.length} de 5 dígitos`;
			clearError(input);
			return;
		}
		if (value.length === 5) {
			if (/^(0[1-9]|[1-4]\d|5[0-3])\d{3}$/.test(value)) {
				infoMessage.textContent = "";
				clearError(input);
			} else {
				infoMessage.textContent = "";
				setError(input, "Código postal inválido.");
			}
		}
	}

	/* =====================================================
         5. Gestión de Pestañas y Sumarios
    ===================================================== */
	function getTabColor(tab) {
		const index = Array.from(FormCache.tabs).indexOf(tab);
		const styles = getComputedStyle(document.documentElement);
		return tab.classList.contains("completed")
			? "#000"
			: tab.classList.contains("active") && window.tabCompletion[index]
			? styles.getPropertyValue("--step-active")
			: styles.getPropertyValue("--step-inactive");
	}

	function updateConnectors() {
		document
			.querySelectorAll(".tabs__connector .connector")
			.forEach((connector, i) => {
				const leftTab = FormCache.tabs[i];
				const rightTab = FormCache.tabs[i + 1];
				if (!leftTab || !rightTab) return;
				const leftColor = getTabColor(leftTab);
				const rightColor = getTabColor(rightTab);
				connector.style.background =
					leftColor === rightColor
						? leftColor
						: `linear-gradient(to right, ${leftColor}, ${rightColor})`;
			});
	}

	function markTabAsCompleted(index) {
		const tab = FormCache.tabs[index];
		if (!tab) return;
		tab.classList.add("completed");
		tab.querySelector(".tabs__circle").textContent = "✓";
		updateConnectors();
	}

	function unmarkTabAsCompleted(index) {
		const tab = FormCache.tabs[index];
		if (!tab) return;
		tab.classList.remove("completed");
		tab.querySelector(".tabs__circle").textContent = index + 1;
		updateConnectors();
	}

	function showSummarySectionForTab(index) {
		const sections = {
			0: "summary-section--vehiculo",
			1: "summary-section--cliente",
			2: "summary-section--garantia",
		};
		const selector = sections[index];
		if (!selector) return;
		const section = document.querySelector(`.${selector}`);
		if (section) section.classList.add("summary-visible");
	}

	// Actualización del sumario
	// Se asume que cada item del sumario es un <li class="summary__item"> que contiene un <span data-summary-field="..."></span>
	let summaryElements = [];
	function updateSummary() {
		summaryElements.forEach((elem) => {
			// 'elem' es el span con data-summary-field; obtenemos su li contenedor
			const container = elem.closest("li.summary__item");
			const fieldId = elem.getAttribute("data-summary-field");
			let summaryText = "";
			let addErrorClass = false;
			if (fieldId === "direccion") {
				const direccionInput = document.getElementById("direccion");
				const cpInput = document.getElementById("codigo_postal");
				const localidadInput = document.getElementById("localidad");
				const provinciaInput = document.getElementById("provincia");
				if (!direccionInput.value.trim()) {
					summaryText = "Falta Dirección";
					addErrorClass = true;
				} else if (!cpInput.value.trim()) {
					summaryText = "Falta Código postal";
					addErrorClass = true;
				} else if (cpInput.getAttribute("aria-invalid") === "true") {
					summaryText = "Código postal incorrecto";
					addErrorClass = true;
				} else if (!localidadInput.value.trim()) {
					summaryText = "Falta Localidad";
					addErrorClass = true;
				} else {
					let fullAddress =
						direccionInput.value +
						", " +
						cpInput.value +
						" " +
						localidadInput.value;
					if (
						provinciaInput.value &&
						localidadInput.value.trim().toLowerCase() !==
							provinciaInput.value.trim().toLowerCase()
					) {
						fullAddress += ", " + provinciaInput.value;
					}
					summaryText = fullAddress;
				}
			} else if (fieldId === "plan_seleccionado") {
				const plan = document.querySelector(".form__plan.selected");
				if (plan) {
					const title =
						plan.querySelector(".form__plan-title")?.textContent || "";
					const price =
						plan.querySelector(".form__plan-price-text")?.textContent || "";
					summaryText = title + (price ? " (" + price + "€/mes)" : "");
				} else {
					summaryText = "Falta seleccionar Garantía";
					addErrorClass = true;
				}
			} else if (fieldId === "vencimiento_contrato") {
				// Cálculo del vencimiento a partir de fecha inicio y duración
				const fechaInput = document.getElementById("fecha_inicio_garantia");
				const duracionInput = document.getElementById("duracion");
				if (!fechaInput.value.trim()) {
					summaryText = "Falta fecha inicio garantía";
					addErrorClass = true;
				} else if (!duracionInput.value.trim()) {
					summaryText = "Falta duración";
					addErrorClass = true;
				} else if (fechaInput.getAttribute("aria-invalid") === "true") {
					summaryText = "Fecha inicio incorrecta";
					addErrorClass = true;
				} else {
					let startDate = new Date(fechaInput.value);
					let months = 0;
					if (duracionInput.value === "6_meses") {
						months = 6;
					} else if (duracionInput.value === "12_meses") {
						months = 12;
					}
					let expDate = new Date(startDate);
					expDate.setMonth(expDate.getMonth() + months);
					expDate.setDate(expDate.getDate() - 1);
					const monthNames = [
						"enero",
						"febrero",
						"marzo",
						"abril",
						"mayo",
						"junio",
						"julio",
						"agosto",
						"septiembre",
						"octubre",
						"noviembre",
						"diciembre",
					];
					summaryText =
						expDate.getDate() +
						" de " +
						monthNames[expDate.getMonth()] +
						" de " +
						expDate.getFullYear();
				}
			} else {
				const input = document.getElementById(fieldId);
				if (input) {
					if (!input.value.trim()) {
						const label = document.querySelector(`label[for="${fieldId}"]`);
						const labelText = label ? label.textContent : fieldId;
						summaryText = "Falta " + labelText;
						addErrorClass = true;
					} else if (input.getAttribute("aria-invalid") === "true") {
						const label = document.querySelector(`label[for="${fieldId}"]`);
						const labelText = label ? label.textContent : fieldId;
						summaryText = labelText + " incorrecto";
						addErrorClass = true;
					} else if (input.tagName === "SELECT") {
						summaryText = input.options[input.selectedIndex].text || "";
					} else if (input.type === "date") {
						if (input.value) {
							let d = new Date(input.value);
							const monthNames = [
								"enero",
								"febrero",
								"marzo",
								"abril",
								"mayo",
								"junio",
								"julio",
								"agosto",
								"septiembre",
								"octubre",
								"noviembre",
								"diciembre",
							];
							summaryText =
								d.getDate() +
								" de " +
								monthNames[d.getMonth()] +
								" de " +
								d.getFullYear();
						} else {
							summaryText = "";
						}
					} else {
						summaryText = input.value;
					}
				}
			}
			elem.textContent = summaryText;
			if (addErrorClass) {
				container.classList.add("summary__item--error");
			} else {
				container.classList.remove("summary__item--error");
			}
		});
		// Revisamos cada sección del sumario para marcar la sección completa en error
		document.querySelectorAll(".summary-section").forEach((section) => {
			if (section.querySelector("li.summary__item--error")) {
				section.classList.add("summary-section--error");
			} else {
				section.classList.remove("summary-section--error");
			}
		});
	}
	const debouncedUpdateSummary = debounce(updateSummary, 200);

	// Botones "Editar datos…" en el sumario
	function setupSummaryButtons() {
		document.querySelectorAll(".summary-button").forEach((btn) => {
			btn.addEventListener("click", () => {
				const parentSection = btn.closest(".summary-section");
				let targetTabIndex;
				if (parentSection.classList.contains("summary-section--vehiculo")) {
					targetTabIndex = 0;
				} else if (
					parentSection.classList.contains("summary-section--cliente")
				) {
					targetTabIndex = 1;
				} else if (
					parentSection.classList.contains("summary-section--garantia")
				) {
					targetTabIndex = 2;
				} else {
					return;
				}
				if (targetTabIndex > FormCache.currentTab && !validateCurrentTab()) {
					alert("Completa todos los campos antes de continuar.");
					return;
				}
				FormCache.currentTab = targetTabIndex;
				showTab(FormCache.currentTab);
			});
		});
	}

	/* =====================================================
         5. Gestión de Pestañas y Sumarios
    ===================================================== */
	function setupTabNavigation() {
		FormCache.tabs.forEach((tab, index) => {
			tab.addEventListener("click", () => {
				if (
					index < FormCache.currentTab ||
					(index > FormCache.currentTab && validateCurrentTab())
				) {
					FormCache.currentTab = index;
					showTab(FormCache.currentTab);
				}
			});
		});
		FormCache.prevButton.addEventListener("click", () => {
			if (FormCache.currentTab > 0) {
				FormCache.currentTab--;
				showTab(FormCache.currentTab);
			}
		});
		FormCache.nextButton.addEventListener("click", () => {
			if (!validateCurrentTab()) {
				alert("Completa todos los campos antes de continuar.");
				return;
			}
			showSummarySectionForTab(FormCache.currentTab);
			if (FormCache.currentTab < FormCache.fieldsets.length - 1) {
				FormCache.currentTab++;
				showTab(FormCache.currentTab);
			} else {
				alert("Formulario completado. Procediendo a contratación.");
			}
		});
	}

	function showTab(index) {
		FormCache.fieldsets.forEach((fs, i) =>
			fs.classList.toggle("form__tab-content--active", i === index)
		);
		FormCache.tabs.forEach((tab, i) =>
			tab.classList.toggle("active", i === index)
		);
		updateNextButtonState();
		debouncedUpdateSummary();
		FormCache.prevButton.style.display = index === 0 ? "none" : "";
		FormCache.nextButton.textContent =
			index === FormCache.fieldsets.length - 1 ? "Contratar" : "Siguiente";
	}

	function validateCurrentTab() {
		const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
		const inputs = currentFieldset.querySelectorAll(
			".form__input, .form__select[required], .form__checkbox[required]"
		);
		let valid = true;
		inputs.forEach((input) => {
			if (!validateField(input, true, true)) valid = false;
		});
		if (
			currentFieldset.id === "seleccionar-garantia" &&
			!document.querySelector(".form__plan.selected")
		) {
			valid = false;
		}
		return valid;
	}

	function updateNextButtonState() {
		const currentFieldset = FormCache.fieldsets[FormCache.currentTab];
		const requiredInputs = currentFieldset.querySelectorAll(
			".form__input[required], .form__select[required], .form__checkbox[required]"
		);
		let allValid = true;
		requiredInputs.forEach((input) => {
			if (!validateField(input, false, true)) allValid = false;
		});
		if (
			currentFieldset.id === "seleccionar-garantia" &&
			!document.querySelector(".form__plan.selected")
		) {
			allValid = false;
		}
		FormCache.nextButton.classList.toggle("disabled", !allValid);
		FormCache.nextButton.style.cursor = allValid ? "pointer" : "not-allowed";
		if (allValid && !window.tabCompletion[FormCache.currentTab]) {
			window.tabCompletion[FormCache.currentTab] = true;
			markTabAsCompleted(FormCache.currentTab);
		} else if (!allValid && window.tabCompletion[FormCache.currentTab]) {
			window.tabCompletion[FormCache.currentTab] = false;
			unmarkTabAsCompleted(FormCache.currentTab);
		}
		updateConnectors();
	}

	/* =====================================================
         6. Funciones Adicionales
    ===================================================== */
	function rellenarDatosEjemplo() {
		const datos = {
			tipo_vehiculo: "sedan",
			marca_modelo: "Toyota Corolla",
			kilometros: "50.000",
			fecha_primera_matriculacion: "2015-06-15",
			matricula: "1234BCD",
			numero_bastidor: "JMZKA18C301123456",
			precio_venta: "15.000",
			combustible: "gasolina",
			cambio: "manual",
			potencia: "120",
			cilindrada: "1.600",
			dni: "12345678Z",
			nombre_apellidos: "Juan Pérez",
			telefono: "612345678",
			correo: "ejemplo@dominio.com",
			direccion: "Calle Ejemplo 123",
			localidad: "Madrid",
			provincia: "Madrid",
			codigo_postal: "28001",
		};
		Object.entries(datos).forEach(([id, valor]) => {
			const elem = document.getElementById(id);
			if (elem) {
				elem.value = valor;
				elem.dispatchEvent(new Event("input"));
				if (elem.tagName === "SELECT") elem.dispatchEvent(new Event("change"));
			}
		});
	}

	function setupPlanSelection() {
		document.querySelectorAll(".form__plan-button").forEach((btn) => {
			btn.addEventListener("click", (e) => {
				e.stopPropagation();
				const plan = btn.closest(".form__plan");
				const isSelected = plan.classList.toggle("selected");
				document.querySelectorAll(".form__plan.selected").forEach((p) => {
					if (p !== plan) p.classList.remove("selected");
				});
				btn.textContent = isSelected ? "Seleccionado" : "Seleccionar";
				updateNextButtonState();
				debouncedUpdateSummary();
			});
		});
	}

	function handleDateInputs() {
		document.querySelectorAll('input[type="date"]').forEach((input) => {
			input.addEventListener("focus", () => input.showPicker());
			input.addEventListener("change", () => validateField(input, true, true));
		});
	}

	function setTodayForGarantia() {
		const fechaInput = document.getElementById("fecha_inicio_garantia");
		if (fechaInput) {
			const today = new Date().toISOString().split("T")[0];
			fechaInput.value = today;
			fechaInput.min = today; // Evita seleccionar fechas anteriores a hoy
		}
	}

	/* =====================================================
         7. Inicialización
    ===================================================== */
	document.addEventListener("DOMContentLoaded", () => {
		window.tabCompletion = [false, false, false, false];
		FormCache.tabs = document.querySelectorAll(".tabs__link");
		FormCache.fieldsets = document.querySelectorAll(".form__tab-content");
		FormCache.inputs = document.querySelectorAll(
			".form__input, .form__select, .form__checkbox"
		);
		FormCache.prevButton = document.querySelector(
			".nav-buttons .btn.btn-secondary"
		);
		FormCache.nextButton = document.querySelector(
			".nav-buttons .btn.btn-primary"
		);
		summaryElements = Array.from(
			document.querySelectorAll("[data-summary-field]")
		);
		setupSummaryButtons();
		document
			.querySelectorAll(".summary-section:not(.summary-section--header)")
			.forEach((s) =>
				s.classList.remove("summary-section--error", "summary-visible")
			);
		setupInputListeners();
		setupTabNavigation();
		setupPlanSelection();
		handleDateInputs();
		setTodayForGarantia();
		handleClearButtons();
		showTab(0);
		document
			.getElementById("rellenar_ejemplo")
			?.addEventListener("click", rellenarDatosEjemplo);
	});

	/* =====================================================
         8. Función de Validación Principal
    ===================================================== */
	function validateField(input, showError, isHardCheck = false) {
		if (input.tagName === "SELECT") {
			const container = input.closest(".form__input-container");
			const isDefaultOption = input.options[input.selectedIndex].disabled;
			if (input.id === "provincia") {
				container.classList.add("has-value");
			} else {
				container.classList.toggle("has-value", !isDefaultOption);
			}
		}
		if (input.type === "checkbox") {
			if (!input.checked) {
				if (showError) setError(input, "Debes aceptar los términos.");
				return false;
			}
			clearError(input);
			return true;
		}
		const validators = {
			numero_bastidor: (i, s) => validateNumeroBastidorField(i, s, isHardCheck),
			matricula: (i, s) => validateMatriculaField(i, s, isHardCheck),
			dni: (i, s) => validateDNIField(i, s, isHardCheck),
			telefono: (i, s) => validateTelefonoField(i, s, isHardCheck),
			codigo_postal: (i, s) => validateCodigoPostalField(i, s, isHardCheck),
			correo: (i, s) => validateEmailField(i, s, isHardCheck),
		};
		if (validators[input.id]) return validators[input.id](input, showError);
		if (numericLimits[input.id]) return validateNumeric(input, showError);
		if (input.hasAttribute("required"))
			return validateRequiredField(input, showError);
		return true;
	}

	function setupInputListeners() {
		FormCache.inputs.forEach((input) => {
			const handler = debounce(() => {
				if (input.value.trim() === "") {
					clearError(input);
					clearInfoMessage(input);
				} else {
					if (input.id === "numero_bastidor") {
						if (inputLimits.hasOwnProperty(input.id)) {
							inputLimits[input.id](input);
						}
						handleBastidorInput(input);
					} else if (input.id === "matricula") {
						if (inputLimits.hasOwnProperty(input.id)) {
							inputLimits[input.id](input);
						}
						handleMatriculaInput(input, false);
					} else if (input.id === "codigo_postal") {
						if (inputLimits.hasOwnProperty(input.id)) {
							inputLimits[input.id](input);
						}
						handleCodigoPostalInput(input);
					} else {
						if (inputLimits.hasOwnProperty(input.id)) {
							inputLimits[input.id](input);
						}
						if (numericLimits[input.id]) {
							formatNumber(input);
							validateNumeric(input, true);
						} else {
							validateField(input, true, false);
						}
					}
				}
				toggleClearButton(input);
				updateNextButtonState();
				debouncedUpdateSummary();
			}, 200);

			input.addEventListener("input", handler);
			input.addEventListener("blur", () => {
				if (input.value.trim() === "") {
					clearError(input);
					clearInfoMessage(input);
					return;
				}
				if (input.id === "numero_bastidor") {
					validateNumeroBastidorField(input, true, true);
				} else if (input.id === "matricula") {
					handleMatriculaInput(input, true);
				} else if (input.id === "codigo_postal") {
					validateCodigoPostalField(input, true, true);
				} else if (numericLimits[input.id]) {
					validateNumeric(input, true);
				} else {
					validateField(input, true, true);
				}
			});
			if (input.tagName === "SELECT") {
				input.addEventListener("change", () =>
					validateField(input, true, true)
				);
			}
		});
	}
})();

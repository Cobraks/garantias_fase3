//assets/js/modules/form-summary.js
"use strict";

/*
    - Controla la lógica de refresco y visualización del resumen (panel derecho) según los datos del formulario.
    - El resumen detecta campos vacíos/erróneos y marca visualmente los errores.
    - No realiza validaciones, solo lee valores y estado visual.
*/

import { debounce } from "./form-utils.js";
import { getUserRole } from "./config.js";
import { showTab, isCurrentTabValid } from "./form-navigation.js";
import FormCache from "./form-cache.js";

// Helper para saber si el tipo de vehículo es "camion"
function isTipoCamion() {
	const tipo = document.getElementById("tipo_vehiculo");
	return tipo && tipo.value === "camion";
}

// === Helpers de campos especiales ===
function getSummaryText(fieldId) {
	// Dirección
	if (fieldId === "direccion") {
		const direccion = document.getElementById("direccion");
		const cp = document.getElementById("codigo_postal");
		const localidad = document.getElementById("localidad");
		const provincia = document.getElementById("provincia");
		if (!direccion.value.trim())
			return { text: "Falta Dirección", error: true };
		if (!cp.value.trim()) return { text: "Falta Código postal", error: true };
		if (cp.getAttribute("aria-invalid") === "true")
			return { text: "Código postal incorrecto", error: true };
		if (!localidad.value.trim())
			return { text: "Falta Localidad", error: true };
		let full = `${direccion.value}, ${cp.value} ${localidad.value}`;
		if (
			provincia.value &&
			localidad.value.trim().toLowerCase() !==
				provincia.value.trim().toLowerCase()
		) {
			full += ", " + provincia.value;
		}
		return { text: full, error: false };
	}

	// === MODALIDAD ahora muestra Canal de venta ===
        if (fieldId === "modalidad") {
                const userRole = getUserRole() || "user";
		let canalText = "";
		if (userRole === "admin") {
			const canalSelect = document.getElementById("canal-venta");
			if (canalSelect && canalSelect.value) {
				const option = canalSelect.options[canalSelect.selectedIndex];
				canalText = option ? option.textContent : "";
			} else {
				canalText = "No seleccionado";
			}
		} else if (userRole === "comercial" || userRole === "profesional") {
			canalText = "Profesional";
		} else {
			canalText = "-";
		}
		return { text: canalText, error: false };
	}

	// Plan seleccionado
	if (fieldId === "plan_seleccionado") {
		const plan = document.querySelector(".form__plan.selected");
		if (!plan) return { text: "Falta seleccionar Garantía", error: true };
		const title = plan.querySelector(".form__plan-title")?.textContent || "";
	let price = "";
        const withIvaEl = plan.querySelector(".plan-price-value");
        const noIvaEl = plan.querySelector(".plan-price-value-noiva");
        if (withIvaEl && getComputedStyle(withIvaEl).display !== "none") {
                price = withIvaEl.textContent.trim();
        } else if (noIvaEl && getComputedStyle(noIvaEl).display !== "none") {
                price = noIvaEl.textContent.trim();
        }

		return {
			text: title + (price ? ` (${price}€)` : ""),
			error: false,
		};
	}

	// Vencimiento contrato
	if (fieldId === "vencimiento_contrato") {
                const fecha = document.getElementById("fecha_inicio_garantia");
                const duracion = document.getElementById("duracion");
                if (!fecha.value.trim())
                        return { text: "Falta fecha inicio garantía", error: true };
                if (!duracion.value.trim())
                        return { text: "Falta duración", error: true };
                if (fecha.getAttribute("aria-invalid") === "true")
                        return { text: "Fecha inicio incorrecta", error: true };

                const startDate = new Date(fecha.value);
                const months = parseInt(duracion.value, 10);
                if (isNaN(months) || months <= 0)
                        return { text: "Falta duración", error: true };

                const expDate = new Date(startDate);
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
		return {
			text: `${expDate.getDate()} de ${
				monthNames[expDate.getMonth()]
			} de ${expDate.getFullYear()}`,
			error: false,
		};
	}

	// Marca individual
	if (fieldId === "marca") {
		const marca = document.getElementById("marca")?.value.trim() || "";
		if (!marca) return { text: "Falta Marca", error: true };
		return { text: marca, error: false };
	}

	// Modelo individual
	if (fieldId === "modelo") {
		const modelo = document.getElementById("modelo")?.value.trim() || "";
		if (!modelo) return { text: "Falta Modelo", error: true };
		return { text: modelo, error: false };
	}

	// --- CAMBIOS CLAVE PARA CAMION ---
	// Tracción (solo valor, sin prefijo)
	if (fieldId === "traccion") {
		if (isTipoCamion()) {
			return { text: "No aplica", error: false };
		}
		const traccion = document.getElementById("traccion");
		if (!traccion || !traccion.value)
			return { text: "Falta Tracción", error: true };
		const selectedText =
			traccion.options[traccion.selectedIndex]?.text?.trim() || "";
		return { text: selectedText, error: false };
	}

	// Tracción camión
	if (fieldId === "traccion_camion") {
		if (!isTipoCamion()) {
			return { text: "No aplica", error: false };
		}
		const traccionCamion = document.getElementById("traccion_camion");
		if (!traccionCamion || !traccionCamion.value)
			return { text: "Falta Tracción camión", error: true };
		const selectedText =
			traccionCamion.options[traccionCamion.selectedIndex]?.text?.trim() || "";
		return { text: selectedText, error: false };
	}

        // Doble motor (depende de combustible)
        if (fieldId === "doble_motor") {
                const combustible = document.getElementById("combustible")?.value;
                const aplica = ["electrico", "hibrido", "gpl_gnc"].includes(combustible);
                if (!aplica) {
                        return { text: "No aplica", error: false };
                }
                const doble = document.getElementById("doble_motor");
                if (!doble || !doble.value)
                        return { text: "Falta Doble motor", error: true };
                const selectedText = doble.options[doble.selectedIndex]?.text?.trim() || "";
                return { text: selectedText, error: false };
        }

        if (fieldId === "potencia") {
                const potencia = document.getElementById("potencia");
                const unit = document.getElementById("summary-potencia-unit");
                if (!potencia || !potencia.value.trim()) {
                        return { text: "Falta Potencia", error: true };
                }
                const combustible = document.getElementById("combustible")?.value;
                if (combustible === "electrico") {
                        const kw = parseFloat(
                                potencia.value.replace(/\./g, "").replace(",", ".")
                        );
                        const cv = Math.round(kw * 1.3596);
                        if (unit) unit.textContent = "CV";
                        return { text: isNaN(cv) ? potencia.value : cv.toString(), error: false };
                }
                if (unit) unit.textContent = "CV";
                return { text: potencia.value, error: false };
        }

        // Por defecto: campo simple
        const input = document.getElementById(fieldId);
        if (input) {
                if (!input.value.trim()) {
			const label = document.querySelector(`label[for="${fieldId}"]`);
			return { text: "Falta " + (label?.textContent || fieldId), error: true };
		}
		if (input.getAttribute("aria-invalid") === "true") {
			const label = document.querySelector(`label[for="${fieldId}"]`);
			return {
				text: (label?.textContent || fieldId) + " incorrecto",
				error: true,
			};
		}
		if (input.tagName === "SELECT") {
			return {
				text: input.options[input.selectedIndex].text || "",
				error: false,
			};
		}
		if (input.type === "date") {
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
				return {
					text: `${d.getDate()} de ${
						monthNames[d.getMonth()]
					} de ${d.getFullYear()}`,
					error: false,
				};
			}
			return { text: "", error: false };
		}
		return { text: input.value, error: false };
	}
	return { text: "", error: false };
}

// === CABECERA DE RESUMEN: CANAL Y VENDEDOR (nuevo) ===
function updateSummaryHeader() {
	const canalSpan = document.querySelector("[data-summary-canal]");
	const vendedorSpan = document.querySelector("[data-summary-vendedor]");
        const userRole = getUserRole() || "user";

	// Por defecto, escondemos ambos
	const canalP = document.getElementById("summary-canal-venta");
	const vendedorP = document.getElementById("summary-vendedor");
	if (canalP) canalP.style.display = "none";
	if (vendedorP) vendedorP.style.display = "none";

	if (userRole === "admin") {
		if (canalP) canalP.style.display = "";
		if (vendedorP) vendedorP.style.display = "";

		// Canal de venta
		const canalSelect = document.getElementById("canal-venta");
		let canalText = "";
		if (canalSelect && canalSelect.value) {
			const option = canalSelect.options[canalSelect.selectedIndex];
			canalText = option ? option.textContent : "";
		} else {
			canalText = "No seleccionado";
		}
		if (canalSpan) canalSpan.textContent = canalText;

		// Vendedor
		const vendedorSelect = document.getElementById("usuario-rol");
		let vendedorText = "";
		if (vendedorSelect && vendedorSelect.value) {
			const option = vendedorSelect.options[vendedorSelect.selectedIndex];
			vendedorText = option ? option.textContent : "";
		} else {
			vendedorText = "No seleccionado";
		}
		if (vendedorSpan) vendedorSpan.textContent = vendedorText;
	} else if (userRole === "comercial" || userRole === "profesional") {
		if (canalP) canalP.style.display = "";
		if (vendedorP) vendedorP.style.display = "none";
		if (canalSpan) canalSpan.textContent = "Profesional";
		if (vendedorSpan) vendedorSpan.textContent = "";
	} else {
		// Usuario normal, no mostrar nada
		if (canalSpan) canalSpan.textContent = "-";
		if (vendedorSpan) vendedorSpan.textContent = "-";
	}
}

// Refresca el resumen
function updateSummary() {
	// Mostrar/ocultar items según tipo_vehiculo
	const isCamion = isTipoCamion();
	const liTraccion = document.getElementById("summary-item-traccion");
	const liTraccionCamion = document.getElementById(
		"summary-item-traccion-camion"
	);
        const liDobleMotor = document.getElementById("summary-item-doble_motor");

        if (liTraccion) liTraccion.style.display = isCamion ? "none" : "";
        if (liTraccionCamion) liTraccionCamion.style.display = isCamion ? "" : "none";

	if (liDobleMotor) {
		const combustible = document.getElementById("combustible")?.value;
		const aplica = ["electrico", "hibrido", "gpl_gnc"].includes(combustible);
		liDobleMotor.style.display = aplica ? "" : "none";
	}

	document.querySelectorAll("[data-summary-field]").forEach((elem) => {
		const container = elem.closest("li.summary__item") || elem.parentElement;
		const fieldId = elem.getAttribute("data-summary-field");
		const { text, error } = getSummaryText(fieldId);
		elem.textContent = text;
		if (container && container.classList) {
			if (error) container.classList.add("summary__item--error");
			else container.classList.remove("summary__item--error");
		}
	});
	document.querySelectorAll(".summary-section").forEach((section) => {
		if (section.querySelector("li.summary__item--error")) {
			section.classList.add("summary-section--error");
		} else {
			section.classList.remove("summary-section--error");
		}
	});

	// Actualiza cabecera resumen canal/vendedor
	updateSummaryHeader();
}

const debouncedUpdateSummary = debounce(updateSummary, 120);

// Lanza el resumen al cambiar datos del vehículo
function setupSummaryRefresh() {
	[
		"marca",
		"modelo",
                "traccion",
                "traccion_camion",
                "tipo_vehiculo",
                "kilometros",
                "fecha_primera_matriculacion",
                "fecha_inicio_garantia",
                "duracion",
                "matricula",
		"numero_bastidor",
		"precio_venta",
		"combustible",
		"cambio",
		"potencia",
		"cilindrada",
		"doble_motor",
		// Añadimos canal y usuario para el resumen cabecera:
		"canal-venta",
		"usuario-rol",
	].forEach((id) => {
		const el = document.getElementById(id);
		if (el) {
			const ev = el.tagName === "SELECT" ? "change" : "input";
			el.addEventListener(ev, debouncedUpdateSummary);
		}
	});
	// Por si acaso, refresca al cambiar de tab (garantiza update)
	document.querySelectorAll(".tabs__link").forEach((tab) => {
		tab.addEventListener("click", debouncedUpdateSummary);
	});
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

// Botones “Editar datos…”
function setupSummaryButtons() {
	document.querySelectorAll(".summary-button").forEach((btn) => {
		btn.addEventListener("click", () => {
			const parentSection = btn.closest(".summary-section");
			let targetTabIndex;
			if (parentSection.classList.contains("summary-section--vehiculo")) {
				targetTabIndex = 0;
			} else if (parentSection.classList.contains("summary-section--cliente")) {
				targetTabIndex = 1;
			} else if (
				parentSection.classList.contains("summary-section--garantia")
			) {
				targetTabIndex = 2;
			} else {
				return;
			}
                        if (
                                typeof isCurrentTabValid === "function" &&
                                targetTabIndex > FormCache.currentTab &&
                                !isCurrentTabValid()
                        ) {
                                alert("Completa todos los campos antes de continuar.");
                                return;
                        }
                        if (typeof showTab === "function") {
                                showTab(targetTabIndex);
                        }
                });
        });
}

// Inicializador principal
export default function initSummary() {
        updateSummary();
        setupSummaryButtons();
        setupSummaryRefresh();
}

export { updateSummary, debouncedUpdateSummary, showSummarySectionForTab };

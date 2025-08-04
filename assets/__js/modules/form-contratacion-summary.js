"use strict";

import {
	fetchModalidades,
	calcularPrecioBase,
	calcularRecargos,
	getDescuentosAplicables,
	getDescuentoTotal,
	parseNumericFormValue,
} from "./form-calculations.js";

const IVA_PORCENTAJE = 21;

function eurosString(valor) {
	const num =
		Math.round((typeof valor === "number" ? valor : parseFloat(valor)) * 100) /
		100;
	return num.toLocaleString("es-ES", {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
		useGrouping: true,
	});
}

function getValoresForm() {
	return {
		cilindrada: document.getElementById("cilindrada")?.value || 0,
		potencia: document.getElementById("potencia")?.value || 0,
		duracion: Number(document.getElementById("duracion")?.value) || 0,
		traccion_camion: document.getElementById("traccion_camion")?.value || null,
		mma: document.getElementById("mma")?.value || null,
		combustible: document.getElementById("combustible")?.value || null,
		cambio: document.getElementById("cambio")?.value || null,
		doble_motor: document.getElementById("doble_motor")?.value || null,
		fecha_primera_matriculacion:
			document.getElementById("fecha_primera_matriculacion")?.value || "",
	};
}

function getSelectedPlanElement() {
	return (
		document.querySelector(".form__plan.selected") ||
		document.querySelector(".form__plan:not(.form__plan--no-selected)")
	);
}

function describeTramo(modalidad, valoresForm) {
	const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
	const cm = cg?.condiciones_modalidad || {};
	const condicionGeneral = Array.isArray(cm.condicion_general)
		? cm.condicion_general.map((x) => (typeof x === "object" ? x.value : x))
		: [
				typeof cm.condicion_general === "object"
					? cm.condicion_general.value
					: cm.condicion_general,
		  ];
	const tarifas = cg?.tarifas || [];

	let valorComparar = 0;
	let tipo = "";
	if (condicionGeneral.includes("cilindrada")) {
		valorComparar = parseNumericFormValue(valoresForm.cilindrada);
		tipo = "cilindrada";
	} else if (condicionGeneral.includes("potencia")) {
		valorComparar = parseNumericFormValue(valoresForm.potencia);
		tipo = "potencia";
	} else {
		return "Precio base";
	}

	const meses = Number(valoresForm.duracion);
	let matched = null;
	let maximosReales = [];

	for (const tarifa of tarifas) {
		const duracionMeses = Number(tarifa.duracion_meses);
		if (duracionMeses !== meses) continue;

		const minRaw = tarifa.valor_min ?? tarifa.valor_minimo ?? "";
		const maxRaw = tarifa.valor_max ?? tarifa.valor_maximo ?? "";

		const min = parseNumericFormValue(minRaw);
		let max =
			maxRaw === "" || maxRaw == null
				? Infinity
				: parseNumericFormValue(maxRaw);

		if (maxRaw !== "" && maxRaw != null) {
			maximosReales.push(max);
		}

		if (valorComparar >= min && valorComparar <= max) {
			matched = { min, max };
		}
	}

	if (!matched) return "Precio base";

	const overallMax =
		maximosReales.length > 0 ? Math.max(...maximosReales) : matched.max;

	if (tipo === "cilindrada") {
		if (matched.min === 0) {
			return `Precio base hasta ${matched.max.toLocaleString()}cc`;
		}
		if (matched.max === overallMax || matched.max === Infinity) {
			return `Precio base más de ${matched.min.toLocaleString()}cc`;
		}
		return `Precio base de ${matched.min.toLocaleString()}cc a ${matched.max.toLocaleString()}cc`;
	} else if (tipo === "potencia") {
		if (matched.min === 0) {
			return `Precio base hasta ${matched.max.toLocaleString()} CV`;
		}
		if (matched.max === overallMax || matched.max === Infinity) {
			return `Precio base más de ${matched.min.toLocaleString()} CV`;
		}
		return `Precio base de ${matched.min.toLocaleString()} CV a ${matched.max.toLocaleString()} CV`;
	}
	return "Precio base";
}

function buildSummaryHTML() {
	const container = document.getElementById("final-summary");
	if (!container) {
		console.warn(
			"[contratacion-summary] #final-summary no está presente, reintentando."
		);
		setTimeout(buildSummaryHTML, 100);
		return;
	}

	console.debug("[contratacion-summary] buildSummaryHTML ejecutado");

	let planEl = getSelectedPlanElement();
	if (!planEl) {
		container.innerHTML = `<div class="form__contrato-prices--empty">Selecciona una garantía para ver el desglose.</div>`;
		return;
	}

	const title =
		planEl.querySelector(".form__plan-title")?.textContent?.trim() ||
		"Garantía";
	const duracionVal = document.getElementById("duracion")?.value || "";
	const duracionLabel = duracionVal ? `${duracionVal} meses` : "";

	const modalidadId = planEl.getAttribute("data-modalidad-id");
	let modalidad = null;
	const valoresFormBase = getValoresForm();

	const valoresForm = {
		...valoresFormBase,
		fecha_primera_matriculacion:
			document.getElementById("fecha_primera_matriculacion")?.value || "",
		kilometros: document.getElementById("kilometros")?.value || 0,
		traccion: document.getElementById("traccion")?.value || "",
		cambio: document.getElementById("cambio")?.value || "",
		doble_motor: document.getElementById("doble_motor")?.value || null,
	};

	container.innerHTML = `<div class="form__contrato-prices--loading">Calculando...</div>`;

	fetchModalidades()
		.then((modalidades) => {
			if (modalidadId) {
				modalidad = modalidades.find(
					(m) => String(m.ID) === String(modalidadId)
				);
			}
			if (!modalidad) {
				container.innerHTML = `<div class="form__contrato-prices--error">No se ha encontrado la modalidad seleccionada.</div>`;
				return;
			}

			const precioBase = calcularPrecioBase(modalidad, valoresFormBase);
			const breakdown = calcularRecargos(modalidad, valoresForm);
			const descuentoTotal = getDescuentoTotal(modalidad);

			const precioConRecargos =
				precioBase !== null
					? Math.round(precioBase * (1 + breakdown.recargoTotal) * 100) / 100
					: null;
			const precioFinal =
				precioConRecargos !== null
					? Math.round(precioConRecargos * (1 - descuentoTotal) * 100) / 100
					: null;
			const iva =
				precioFinal !== null
					? Math.round(precioFinal * (IVA_PORCENTAJE / 100) * 100) / 100
					: null;
			const totalConIva =
				precioFinal !== null && iva !== null
					? Math.round((precioFinal + iva) * 100) / 100
					: null;

			let html = `<div class="contratacion-summary">
    <h2>Certificado de Garantía</h2>
    <h3>${title} ${duracionLabel}</h3>
    <ul class="contratacion-summary__list">`;

			const tramoTexto = describeTramo(modalidad, valoresFormBase);
			if (precioBase !== null) {
				html += `<li class="item"><span class="concepto">${tramoTexto}</span><span class="valor">${eurosString(
					precioBase
				)}€</span></li>`;
			} else {
				html += `<li class="item"><span class="concepto">${tramoTexto}</span><span class="valor">--</span></li>`;
			}

			if (breakdown.recargoTotal > 0) {
				html += `<li class="item"><span class="concepto">Recargos totales</span><span class="valor">${Math.round(
					breakdown.recargoTotal * 100
				)}%</span></li>`;

				const grupos = breakdown.grupos;
				for (const grupo in grupos) {
					const arr = grupos[grupo];
					const esAcumulable = arr.some((s) => s.acumulable);
					if (!arr.length) continue;

					if (esAcumulable || arr.length === 1) {
						for (const sup of arr) {
							const recargoEuros =
								precioBase !== null
									? Math.round(precioBase * sup.recargo * 100) / 100
									: null;
							const labelContent =
								sup.descripcion || `${Math.round(sup.recargo * 100)}%`;
							const label = `Recargo ${Math.round(
								sup.recargo * 100
							)}%: ${labelContent}`;
							html += `<li class="item"><span class="concepto">${label}</span><span class="valor">${
								recargoEuros !== null ? `${eurosString(recargoEuros)}€` : "--"
							}</span></li>`;
						}
					} else {
						const maxRecargo = Math.max(...arr.map((s) => s.recargo));
						const descripciones = arr.map((s) => s.descripcion).filter(Boolean);
						let descripcionesTxt = "";
						if (descripciones.length === 2)
							descripcionesTxt = descripciones.join(" y ");
						else if (descripciones.length > 2)
							descripcionesTxt =
								descripciones.slice(0, -1).join(", ") +
								" y " +
								descripciones[descripciones.length - 1];
						else descripcionesTxt = descripciones[0] || "";
						const label = `Recargo ${Math.round(
							maxRecargo * 100
						)}%: ${descripcionesTxt}`;
						const recargoEuros =
							precioBase !== null
								? Math.round(precioBase * maxRecargo * 100) / 100
								: null;
						html += `<li class="item"><span class="concepto">${label}</span><span class="valor">${
							recargoEuros !== null ? `${eurosString(recargoEuros)}€` : "--"
						}</span></li>`;
					}
				}

				const hasGrupoLimit = Object.entries(breakdown.topePorGrupo).some(
					([grupo, tope]) => breakdown.recargosPorGrupo[grupo] === tope
				);
				const hasTotalLimit =
					breakdown.maximoAcumulableTotal &&
					breakdown.recargoTotal * 100 >= breakdown.maximoAcumulableTotal;
				if (hasGrupoLimit) {
					for (const [grupo, tope] of Object.entries(breakdown.topePorGrupo)) {
						if (breakdown.recargosPorGrupo[grupo] === tope) {
							html += `<li class="item"><span class="concepto">Límite máximo grupo ${grupo}</span><span class="valor">${Math.round(
								tope * 100
							)}%</span></li>`;
						}
					}
				}
				if (hasTotalLimit) {
					html += `<li class="item"><span class="concepto">Límite máximo recargos</span><span class="valor">${breakdown.maximoAcumulableTotal}%</span></li>`;
				}
			}

			const descuentos = getDescuentosAplicables(modalidad);
			if (descuentos?.length) {
				for (const desc of descuentos) {
					const descuentoEuros =
						precioConRecargos !== null
							? Math.round(precioConRecargos * desc.porcentaje * 100) / 100
							: null;
					const nombre = desc.nombre || "";
					const label = `Descuento${nombre ? " " + nombre : ""}`;
					html += `<li class="item"><span class="concepto">${label}</span><span class="valor">-${
						descuentoEuros !== null ? eurosString(descuentoEuros) + "€" : "--"
					}</span></li>`;
				}
			}

			if (iva !== null) {
				html += `<li class="item"><span class="concepto">IVA (${IVA_PORCENTAJE}%)</span><span class="valor">${eurosString(
					iva
				)}€</span></li>`;
			}

			if (totalConIva !== null) {
				html += `<li class="item item--destacado"><span class="concepto">Precio total</span><span class="valor">${eurosString(
					totalConIva
				)}€</span></li>`;
			}

			html += `</ul></div>`;

			container.innerHTML = html;
		})
		.catch((err) => {
			console.error("Error construyendo resumen de contratación:", err);
			container.innerHTML = `<div class="form__contrato-prices--error">Error calculando el desglose.</div>`;
		});
}

let scheduled = null;
function scheduleUpdate() {
	if (scheduled) clearTimeout(scheduled);
	scheduled = setTimeout(() => {
		if (typeof window.filtrarModalidades === "function") {
			window.filtrarModalidades().finally(() => {
				buildSummaryHTML();
			});
		} else {
			buildSummaryHTML();
		}
	}, 100);
}

function setupListeners() {
	[
		"duracion",
		"usuario-rol",
		"canal-venta",
		"cilindrada",
		"potencia",
		"combustible",
		"cambio",
		"traccion_camion",
		"doble_motor",
		"traccion",
		"mma",
		"fecha_primera_matriculacion",
	].forEach((id) => {
		const el = document.getElementById(id);
		if (!el) return;
		const ev =
			el.tagName === "SELECT" || el.type === "date" ? "change" : "input";
		el.addEventListener(ev, scheduleUpdate);
	});

	document.addEventListener("ofertas:actualizadas", scheduleUpdate);

	document.querySelectorAll(".tabs__link").forEach((tab) => {
		tab.addEventListener("click", scheduleUpdate);
	});
	document.addEventListener("form:tab-changed", scheduleUpdate);

	const plansContainer = document.getElementById("formPlans");
	if (plansContainer) {
		const mo = new MutationObserver((records) => {
			for (const rec of records) {
				if (
					rec.type === "attributes" &&
					rec.attributeName === "class" &&
					rec.target.classList.contains("selected")
				) {
					scheduleUpdate();
					break;
				}
			}
		});
		mo.observe(plansContainer, {
			subtree: true,
			attributes: true,
			attributeFilter: ["class"],
		});
	}
}

export default function initContratacionSummary() {
	buildSummaryHTML();
	setupListeners();
}

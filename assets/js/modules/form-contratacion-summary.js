// assets/js/modules/form-contratacion-summary.js
"use strict";

/*
  Resumen detallado para la pestaña de finalización (contratación).
  No debe colisionar con form-summary.js (panel lateral).
  Escucha cambios de plan, duración, vendedor/canal y recalcula.
*/

import {
        fetchModalidades,
        calcularPrecioBase,
        calcularRecargos,
        getDescuentosAplicables,
// i1d36g-codex/refactor-import-in-nueva_garantia.js
// =======
        getDescuentoTotal,
// >>>>>>> main
        getAntiguedadFromDate,
        parseNumericFormValue,
        filtrarModalidades,
        getOfertaSinSuplementosAplicable,
} from "./form-calculations.js";
import { eurosString, IVA_PORCENTAJE } from "./form-utils.js";

let lastSummaryBreakdown = [];

export async function getEconomicBreakdownSnapshot() {
        return lastSummaryBreakdown;
}

// Garantiza que haya un desglose disponible reconstruyendo el resumen si es necesario
export async function ensureEconomicBreakdownReady() {
        const needsSnapshot =
                !Array.isArray(lastSummaryBreakdown) || lastSummaryBreakdown.length === 0;
        if (needsSnapshot) {
                await buildSummaryHTML();
        }

        if (!Array.isArray(lastSummaryBreakdown) || lastSummaryBreakdown.length === 0) {
                await new Promise((resolve) => setTimeout(resolve, 120));
                await buildSummaryHTML();
        }

        return lastSummaryBreakdown || [];
}

function getValoresForm() {
        return {
                cilindrada: document.getElementById("cilindrada")?.value || 0,
                potencia: document.getElementById("potencia")?.value || 0,
                duracion: Number(document.getElementById("duracion")?.value) || 0,
                traccion_camion: document.getElementById("traccion_camion")?.value || null,
                combustible: document.getElementById("combustible")?.value || null,
                cambio: document.getElementById("cambio")?.value || null,
                // mantener el mismo formato que en renderPlans / filtrarModalidades
                doble_motor: document.getElementById("doble_motor")?.value || null,
                fecha_primera_matriculacion:
                        document.getElementById("fecha_primera_matriculacion")?.value || "",
                tipo_vehiculo: document.getElementById("tipo_vehiculo")?.value || "",
        };
}

function getSelectedPlanElement() {
	return document.querySelector(".form__plan.selected");
}

function escapeHtml(value) {
        if (value == null) return "";
        return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
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

	// Para comparar si el matched.max es el máximo "real" de ese tramo (por duración y tipo)
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

		// Guardamos para cómputo de “tope real” (solo los que tienen max definido)
		if (maxRaw !== "" && maxRaw != null) {
			maximosReales.push(max);
		}

		if (valorComparar >= min && valorComparar <= max) {
			matched = { min, max };
			// no break: queremos tener todos los máximosReales para comparar después
		}
	}

	if (!matched) return "Precio base";

	// Determinar si matched.max es el máximo real disponible (y no infinito implícito)
	const overallMax =
		maximosReales.length > 0 ? Math.max(...maximosReales) : matched.max;

        if (tipo === "cilindrada") {
                if (matched.min === 0) {
                        return `Precio base hasta ${matched.max.toLocaleString()} CC`;
                }
                // Si el matched.max es el tope real (igual a overallMax), lo tratamos como infinito
                if (matched.max === overallMax || matched.max === Infinity) {
                        return `Precio base más de ${matched.min.toLocaleString()} CC`;
                }
                return `Precio base de ${matched.min.toLocaleString()} CC a ${matched.max.toLocaleString()} CC`;
        } else if (tipo === "potencia") {
                const unit =
                        document.getElementById("combustible")?.value === "electrico"
                                ? "kW"
                                : "CV";
                if (matched.min === 0) {
                        return `Precio base hasta ${matched.max.toLocaleString()} ${unit}`;
                }
                if (matched.max === overallMax || matched.max === Infinity) {
                        return `Precio base más de ${matched.min.toLocaleString()} ${unit}`;
                }
                return `Precio base de ${matched.min.toLocaleString()} ${unit} a ${matched.max.toLocaleString()} ${unit}`;
        }
        return "Precio base";
}

async function buildSummaryHTML() {
        const container = document.getElementById("final-summary");
        lastSummaryBreakdown = [];
        if (!container) {
                console.warn(
                        "[contratacion-summary] #final-summary no está presente, reintentando."
		);
		setTimeout(buildSummaryHTML, 100);
		return;
	}

	// diagnóstico
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
        const planPriceWithIvaText =
                planEl.querySelector(".plan-price-value")?.textContent?.trim() || "";
        const planPriceNoIvaText =
                planEl.querySelector(".plan-price-value-noiva")?.textContent?.trim() || "";
        const planPriceWithIva =
                planPriceWithIvaText && planPriceWithIvaText !== "Consultar"
                        ? parseNumericFormValue(planPriceWithIvaText)
                        : null;
        const planPriceSinIva =
                planPriceNoIvaText && planPriceNoIvaText !== "Consultar"
                        ? parseNumericFormValue(planPriceNoIvaText)
                        : null;
        const valoresFormBase = getValoresForm();

	// extender igual que en renderPlans para que se apliquen todos los recargos
        const valoresForm = {
                ...valoresFormBase,
                fecha_primera_matriculacion:
                        document.getElementById("fecha_primera_matriculacion")?.value || "",
                kilometros: document.getElementById("kilometros")?.value || 0,
                traccion: document.getElementById("traccion")?.value || "",
                cambio: document.getElementById("cambio")?.value || "",
                // mantener consistencia: no forzamos booleano para doble_motor aquí,
                // porque en calcularRecargos se trata como string normalmente
                doble_motor: document.getElementById("doble_motor")?.value || null,
        };

        const antiguedadValor = getAntiguedadFromDate(valoresForm.fecha_primera_matriculacion);
        valoresForm.antiguedad =
                typeof antiguedadValor === "number" && !Number.isNaN(antiguedadValor)
                        ? antiguedadValor
                        : null;

        container.innerHTML = `<div class="form__contrato-prices--loading">Calculando...</div>`;

        try {
                const modalidades = await fetchModalidades();
                if (modalidadId) {
                        modalidad = modalidades.find(
                                (m) => String(m.ID) === String(modalidadId)
                        );
                }
                if (!modalidad) {
                        container.innerHTML = `<div class="form__contrato-prices--error">No se ha encontrado la modalidad seleccionada.</div>`;
                        return;
                }

                let precioBase = calcularPrecioBase(modalidad, valoresFormBase);
                if (precioBase === null) {
                        if (typeof planPriceSinIva === "number" && !Number.isNaN(planPriceSinIva)) {
                                precioBase = planPriceSinIva;
                        } else if (
                                typeof planPriceWithIva === "number" &&
                                !Number.isNaN(planPriceWithIva)
                        ) {
                                const divisor = 1 + IVA_PORCENTAJE / 100;
                                precioBase =
                                        divisor > 0
                                                ? Math.round((planPriceWithIva / divisor) * 100) / 100
                                                : planPriceWithIva;
                        }
                }
                const breakdown = calcularRecargos(modalidad, valoresForm);

                const descuentos = await getDescuentosAplicables(modalidad);
                let multiplicador = 1;
                descuentos.forEach((d) => {
                        multiplicador *= 1 - d.porcentaje;
                });
                const descuentoTotal = 1 - multiplicador;

                const ofertaSinSuplementos = await getOfertaSinSuplementosAplicable(modalidad);
                const sinSuplementos =
                        !!ofertaSinSuplementos && breakdown.recargoTotal > 0;

                const factorRecargos = 1 + breakdown.recargoTotal;
                const factorAplicado = sinSuplementos ? 1 : factorRecargos;

                const precioConRecargos =
                        precioBase !== null
                                ? Math.round(precioBase * factorRecargos * 100) / 100
                                : null;

                const precioAntesDescuento =
                        precioBase !== null
                                ? Math.round(precioBase * factorAplicado * 100) / 100
                                : null;

                const precioFinal =
                        precioAntesDescuento !== null
                                ? Math.round(precioAntesDescuento * (1 - descuentoTotal) * 100) / 100
                                : null;

                const iva =
                        precioFinal !== null
                                ? Math.round(precioFinal * (IVA_PORCENTAJE / 100) * 100) / 100
                                : null;

                const totalConIva =
                        precioFinal !== null && iva !== null
                                ? Math.round((precioFinal + iva) * 100) / 100
                                : null;

                const summaryItems = [];
                let orden = 1;

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

                summaryItems.push({
                        concepto: tramoTexto,
                        tipo: "base",
                        importe: precioBase !== null ? precioBase : "",
                        porcentaje: "",
                        razon: "",
                        orden: orden++,
                        destacado: false,
                        base_calculo: precioBase !== null ? precioBase : "",
                });

                if (breakdown.detalles && breakdown.detalles.length) {
                        breakdown.detalles.forEach((sup) => {
                                const recargoEuros =
                                        precioBase !== null
                                                ? Math.round(precioBase * sup.porcentajeAplicado * 100) / 100
                                                : null;
                                let label = sup.descripcion
                                        ? `Recargo: ${sup.descripcion}`
                                        : "Recargo";
                                if (sup.porcentajeAplicado < sup.porcentajeOriginal) label += "*";
                                const itemClasses = ["item"];
                                if (sinSuplementos) itemClasses.push("item--sin-suplementos");
                                html += `<li class="${itemClasses.join(" ")}"><span class="concepto">${label}</span><span class="valor">${
                                        recargoEuros !== null ? `${eurosString(recargoEuros)}€` : "--"
                                }</span></li>`;

                                summaryItems.push({
                                        concepto: label,
                                        tipo: "recargo",
                                        importe: recargoEuros !== null ? recargoEuros : "",
                                        porcentaje:
                                                typeof sup.porcentajeAplicado === "number"
                                                        ? Math.round(sup.porcentajeAplicado * 10000) / 100
                                                        : "",
                                        razon: sup.descripcion || "",
                                        orden: orden++,
                                        destacado: false,
                                        base_calculo: precioBase !== null ? precioBase : "",
                                        sin_suplementos: sinSuplementos,
                                });
                        });
                        if (sinSuplementos) {
                                const etiquetaOferta =
                                        ofertaSinSuplementos?.nombre ||
                                        ofertaSinSuplementos?.etiqueta ||
                                        "Sin suplementos";
                                html += `<li class="item item--nota"><span class="concepto concepto--nota">Suplementos no aplicados por la oferta “${escapeHtml(
                                        etiquetaOferta
                                )}”.</span><span class="valor"></span></li>`;

                                summaryItems.push({
                                        concepto: `Suplementos no aplicados por la oferta “${etiquetaOferta}”.`,
                                        tipo: "oferta",
                                        importe: "",
                                        porcentaje: "",
                                        razon: etiquetaOferta,
                                        orden: orden++,
                                        destacado: false,
                                        base_calculo: "",
                                });
                        }
                }

                if (descuentos.length) {
                        for (const desc of descuentos) {
                                const descuentoEuros =
                                        precioAntesDescuento !== null
                                                ? Math.round(precioAntesDescuento * desc.porcentaje * 100) / 100
                                                : null;
                                const nombre = desc.nombre || "";
                                const label = nombre.toLowerCase().startsWith("descuento")
                                        ? nombre
                                        : `Descuento${nombre ? " " + nombre : ""}`;
                                html += `<li class="item"><span class="concepto">${label}</span><span class="valor">-${
                                        descuentoEuros !== null ? eurosString(descuentoEuros) + "€" : "--"
                                }</span></li>`;

                                summaryItems.push({
                                        concepto: label,
                                        tipo: "descuento",
                                        importe: descuentoEuros !== null ? descuentoEuros : "",
                                        porcentaje:
                                                typeof desc.porcentaje === "number"
                                                        ? Math.round(desc.porcentaje * 10000) / 100
                                                        : "",
                                        razon: nombre,
                                        orden: orden++,
                                        destacado: false,
                                        base_calculo: precioAntesDescuento !== null ? precioAntesDescuento : "",
                                });
                        }
                }

                if (iva !== null) {
                        html += `<li class="item"><span class="concepto">IVA (${IVA_PORCENTAJE}%)</span><span class="valor">${eurosString(
                                iva
                        )}€</span></li>`;

                        summaryItems.push({
                                concepto: `IVA (${IVA_PORCENTAJE}%)`,
                                tipo: "iva",
                                importe: iva,
                                porcentaje: IVA_PORCENTAJE,
                                razon: "IVA",
                                orden: orden++,
                                destacado: false,
                                base_calculo: precioFinal !== null ? precioFinal : "",
                        });
                }

                if (totalConIva !== null) {
                        html += `<li class="item item--destacado"><span class="concepto">Precio total</span><span class="valor">${eurosString(
                                totalConIva
                        )}€</span></li>`;

                        summaryItems.push({
                                concepto: "Precio total",
                                tipo: "total",
                                importe: totalConIva,
                                porcentaje: "",
                                razon: "",
                                orden: orden++,
                                destacado: true,
                                base_calculo: "",
                        });
                }

                  html += `</ul>`;
                                                                        if (breakdown.limiteTotalAlcanzado) {
                                                                                html += `<p class="contratacion-summary__limite">*Límite máximo recargos ${breakdown.maximoAcumulableTotal}%</p>`;
                                                                        }
                                                                        html += `</div>`;

                lastSummaryBreakdown = summaryItems;

                container.innerHTML = html;
        } catch (err) {
                console.error("Error construyendo resumen de contratación:", err);
                container.innerHTML = `<div class="form__contrato-prices--error">Error calculando el desglose.</div>`;
        }
}

let scheduled = null;
function scheduleUpdate() {
	if (scheduled) clearTimeout(scheduled);
        scheduled = setTimeout(() => {
                if (typeof filtrarModalidades === "function") {
                        // actualizar modalidades antes de reconstruir resumen para que recargos reflejen el estado
                        filtrarModalidades();
                        setTimeout(buildSummaryHTML, 150);
                } else {
                        buildSummaryHTML();
                }
        }, 100);
}

function setupListeners() {
	// Inputs relevantes
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
                "fecha_primera_matriculacion",
	].forEach((id) => {
		const el = document.getElementById(id);
		if (!el) return;
		const ev =
			el.tagName === "SELECT" || el.type === "date" ? "change" : "input";
		el.addEventListener(ev, scheduleUpdate);
	});

	// Escuchar actualización de ofertas
	document.addEventListener("ofertas:actualizadas", scheduleUpdate);

	// Cambio de pestaña: escucha clicks y evento custom si se lanza
	document.querySelectorAll(".tabs__link").forEach((tab) => {
		tab.addEventListener("click", scheduleUpdate);
	});
	document.addEventListener("form:tab-changed", scheduleUpdate);

	// Observer para selección externa
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
	// arrancar y asegurar que hay un poco de margen si el DOM no está completamente interactivo
	buildSummaryHTML();
	setupListeners();
}
/*ANTES DE REF*/
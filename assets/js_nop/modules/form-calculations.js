// assets/js/modules/form-calculations.js
"use strict";

import {
	eurosString,
	parseNumericFormValue,
	getAntiguedadFromDate,
	IVA_PORCENTAJE,
} from "./form-utils.js";
import {
	fetchOfertas,
	updateOfertasList,
	ofertaAplicaAmodalidad,
} from "./form-ofertas.js";
import {
        getEffectiveUserRole,
        getEffectiveProfessionalId,
        isProfesional,
        ensureCurrentUserIdReady,
} from "./form-role-utils.js";
import { getRestRoot, getRestNonce, getIcon } from "./config.js";
import {
        getCurrentOfertas,
        setCurrentOfertas,
        getVisibleModalidades,
        setVisibleModalidades,
        getLimitesDinamicos,
        setLimitesDinamicos,
        getSelectedModalidadId,
} from "./form-state.js";
import { setupPlanSelection } from "./form-ui.js";

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-calculations]", ...args);
}

const MODALIDAD_CACHE_TTL = 5 * 60 * 1000; // 5 minutos de cache

// --------- Helpers de formato auxiliares locales ---------
function redondearEuros(valor) {
	if (typeof valor !== "number") valor = parseFloat(valor);
	if (isNaN(valor)) return 0;
	return Math.round(valor * 100) / 100;
}
function getValorInput(id) {
	const el = document.getElementById(id);
	return el ? el.value : "";
}
function getValoresModalidadCampo(campo) {
	if (Array.isArray(campo)) {
		return campo.map((v) => (typeof v === "string" ? v : v.value));
	}
	if (typeof campo === "string") return [campo];
	if (typeof campo === "object" && campo !== null && campo.value)
		return [campo.value];
	return [];
}

// --------- Condiciones / comparadores ---------
const tipoCondicion = {
	antiguedad: "num",
	kilometros: "num",
	potencia: "num",
	traccion: "string",
	cambio: "string",
	doble_motor: "string",
};
const comparadores = {
	num: {
		">": (input, val) => Number(input) > Number(val),
		"=": (input, val) => Number(input) === Number(val),
	},
	string: {
		"=": (input, val) =>
			String(input || "").toLowerCase() === String(val || "").toLowerCase(),
	},
	bool: {
		"=": (input, val) =>
			Boolean(input) ===
			(val === true || val === "true" || val === 1 || val === "1"),
	},
};

// --------- Cálculo de recargos (suplementos) ---------
function calcularRecargos(modalidad, valoresForm) {
	const suplementos = modalidad.acf?.suplementos || [];
	const maximosGrupo = modalidad.acf?.maximo_acumulable_por_grupo || [];
	const maximoAcumulableTotal =
		Number(modalidad.acf?.maximo_acumulable_total) || null;

	const topePorGrupo = {};
	maximosGrupo.forEach((g) => {
		if (g.selec_grupo && g.valor_max_grupo)
			topePorGrupo[g.selec_grupo] = Number(g.valor_max_grupo) / 100;
	});

	const suplementosAplicados = [];

	for (const sup of suplementos) {
		const condicion = sup.condicion;
		const tipo = tipoCondicion[condicion];
		const comparador = comparadores[tipo]?.[sup.comparador];
		if (!comparador) continue;

		let inputValor = null;
		switch (condicion) {
			case "antiguedad":
				inputValor = getAntiguedadFromDate(
					valoresForm.fecha_primera_matriculacion
				);
				break;
			case "kilometros":
				inputValor = parseNumericFormValue(valoresForm.kilometros);
				break;
			case "potencia":
				inputValor = parseNumericFormValue(valoresForm.potencia);
				break;
			case "traccion":
				inputValor = valoresForm.traccion || "";
				break;
			case "cambio":
				inputValor = valoresForm.cambio || "";
				break;
			case "doble_motor":
				inputValor = valoresForm.doble_motor;
				break;
			default:
				inputValor = null;
		}

		const matches = comparador(inputValor, sup.valor);
		if (matches) {
			suplementosAplicados.push({
				condicion: sup.condicion,
				valorCondicion: inputValor,
				comparador: sup.comparador,
				valorComparar: sup.valor,
				recargo: Number(sup.recargo) / 100,
				grupo: sup.grupo_acumulabilidad,
				acumulable: !!sup.acumulable,
				descripcion: sup.descripcion,
			});
		}
	}

	const grupos = {};
	for (const sup of suplementosAplicados) {
		const grupo = sup.grupo || "X";
		grupos[grupo] = grupos[grupo] || [];
		grupos[grupo].push(sup);
	}

	const recargosPorGrupo = {};
	for (const grupo in grupos) {
		const arr = grupos[grupo];
		const esAcumulable = arr.some((s) => s.acumulable);
		let sumaGrupo = 0;
		if (esAcumulable) {
			sumaGrupo = arr.reduce((acc, s) => acc + s.recargo, 0);
		} else {
			sumaGrupo = Math.max(...arr.map((s) => s.recargo));
		}
		if (topePorGrupo[grupo]) {
			sumaGrupo = Math.min(sumaGrupo, topePorGrupo[grupo]);
		}
		recargosPorGrupo[grupo] = sumaGrupo;
	}

	let recargoTotal = Object.values(recargosPorGrupo).reduce(
		(acc, v) => acc + v,
		0
	);
	if (maximoAcumulableTotal !== null)
		recargoTotal = Math.min(recargoTotal, maximoAcumulableTotal / 100);

	return {
		suplementosAplicados,
		grupos,
		recargosPorGrupo,
		recargoTotal,
		topePorGrupo,
		maximoAcumulableTotal,
	};
}

// --------- DESCUENTOS AVANZADOS ---------
async function ensureOfertasLoaded() {
	const hasProf = getEffectiveProfessionalId();
	if (!hasProf) return;
    if (!Array.isArray(getCurrentOfertas())) {
		await ensureCurrentUserIdReady();
		const uid = getEffectiveProfessionalId();
		if (uid) {
                    setCurrentOfertas(await fetchOfertas(uid));
		}
	}
}

async function getDescuentosAplicables(
	modalidad,
	{ incluirCaducadas = false } = {}
) {
        if (!modalidad) return [];
        await ensureOfertasLoaded();
        if (!Array.isArray(getCurrentOfertas())) return [];

	const now = Date.now() / 1000;
	const modalidadID = modalidad.ID;
	const nivelGarantia = Array.isArray(modalidad.nivel_garantia)
		? modalidad.nivel_garantia[0]
		: modalidad.nivel_garantia;

        const descuentos = [];

        (getCurrentOfertas() || []).forEach((oferta) => {
		if (!oferta.porcentaje_descuento && oferta.porcentaje_descuento !== 0)
			return;
		if (oferta.estado === false) return;
		const caducada =
			oferta.timestamp_caducidad && now > oferta.timestamp_caducidad;
		if (!incluirCaducadas && caducada) return;

		if (!ofertaAplicaAmodalidad(oferta, modalidad)) return;

		descuentos.push({
			porcentaje: oferta.porcentaje_descuento / 100,
			nombre: oferta.nombre,
			caducada,
		});
	});

	if (ENABLE_LOGS) {
		log(
			"[Descuentos] Modalidad:",
			modalidad?.title,
			"ID:",
			modalidadID,
			"nivel:",
			nivelGarantia,
			"→ Aplicados:",
			descuentos
		);
	}
	return descuentos;
}

async function getDescuentoTotal(modalidad) {
        const descuentos = await getDescuentosAplicables(modalidad);
        let multiplicador = 1;
        descuentos.forEach((d) => {
                multiplicador *= 1 - d.porcentaje;
        });
        return 1 - multiplicador;
}

// Versión síncrona que reutiliza las ofertas ya cargadas para evitar esperas
function getDescuentosAplicablesSync(
        modalidad,
        { incluirCaducadas = false } = {}
) {
        if (!modalidad) return [];
        const ofertas = getCurrentOfertas();
        if (!Array.isArray(ofertas)) return [];

        const now = Date.now() / 1000;
        const descuentos = [];
        ofertas.forEach((oferta) => {
                if (!oferta.porcentaje_descuento && oferta.porcentaje_descuento !== 0)
                        return;
                if (oferta.estado === false) return;
                const caducada =
                        oferta.timestamp_caducidad && now > oferta.timestamp_caducidad;
                if (!incluirCaducadas && caducada) return;
                if (!ofertaAplicaAmodalidad(oferta, modalidad)) return;
                descuentos.push({
                        porcentaje: oferta.porcentaje_descuento / 100,
                        nombre: oferta.nombre,
                        caducada,
                });
        });
        return descuentos;
}

// --------- RENDERIZADO DE RECARGOS Y DESCUENTOS ---------
function renderRecargosHTML({
	precioBase,
	breakdown,
	precioFinal,
	precioIVA,
	descuentoTotal,
	modalidad,
}) {
	let html = `<div class="form__plan-recargos"><p class="form__plan-recargos-precios"><b>Precio base:</b> ${eurosString(
		precioBase
	)}€`;

	if (breakdown.recargoTotal > 0)
		html += ` | <b>Recargos:</b> ${Math.round(breakdown.recargoTotal * 100)}%`;
	if (descuentoTotal > 0)
		html += ` | <b>Descuentos:</b> ${Math.round(descuentoTotal * 100)}%`;

	html += ` | <b>Precio final:</b> ${eurosString(
		precioFinal
	)}€ | <b>Precio final + IVA:</b> ${eurosString(precioIVA)}€</p>`;

	const grupos = breakdown.grupos;
	html += `<ul class="form__plan-recargos-list">`;
	for (const grupo in grupos) {
		const arr = grupos[grupo];
		const esAcumulable = arr.some((s) => s.acumulable);
		if (arr.length) {
			if (esAcumulable || arr.length === 1) {
				for (const sup of arr) {
					html += `<li class="form__plan-recargos-item"><span class="form__plan-porcentaje-recargo">Recargo ${Math.round(
						sup.recargo * 100
					)}%:</span> ${sup.descripcion || ""}</li>`;
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
				html += `<li class="form__plan-recargos-item"><span class="form__plan-porcentaje-recargo">Recargo ${Math.round(
					maxRecargo * 100
				)}%:</span> ${descripcionesTxt}</li>`;
			}
		}
	}
	html += `</ul>`;

        // descuentos aplicados (sin await, se asume que ya están precargados cuando se renderiza admin)
        const descuentosAplicadosSync = getDescuentosAplicablesSync(modalidad);

        if (descuentosAplicadosSync.length) {
                html += `<ul class="form__plan-descuentos-list">`;
                for (const desc of descuentosAplicadosSync) {
                        html += `<li class="form__plan-descuentos-item">
                <span class="form__plan-porcentaje-descuento">-${Math.round(
                                                                        desc.porcentaje * 100
                                                                )}%:</span>
                ${desc.nombre}
            </li>`;
                }
                html += `</ul>`;
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
				html += `<p class="form__plan-recargos-precios">Límite máximo grupo ${grupo}: <span class="form__plan-porcentaje-recargo">${Math.round(
					tope * 100
				)}%</span></p>`;
			}
		}
	}
	if (hasTotalLimit) {
		html += `<p class="form__plan-recargos-precios">Límite máximo recargos: <span class="form__plan-porcentaje-recargo">${breakdown.maximoAcumulableTotal}%</span></p>`;
	}
	html += "</div>";
	return html;
}

// --------- OFERTAS: INTEGRACIÓN CON MODALIDADES ---------
let _refreshOfertasPending = null;

async function refreshOfertasDisplay(attempt = 0) {
	if (isProfesional()) {
		await ensureCurrentUserIdReady();
	}

	const usuarioId = getEffectiveProfessionalId();
	if (!usuarioId) {
		if (isProfesional() && attempt < 5) {
			if (ENABLE_LOGS)
				log(
					"[refreshOfertasDisplay] usuarioId aún null, reintentando",
					"intento:",
					attempt + 1
				);
			setTimeout(() => refreshOfertasDisplay(attempt + 1), 200);
		}
		return;
	}

	if (_refreshOfertasPending) clearTimeout(_refreshOfertasPending);
        _refreshOfertasPending = setTimeout(async () => {
                const ofertas = getCurrentOfertas() || (await fetchOfertas(usuarioId));
                setCurrentOfertas(ofertas);
                const modalidadesVisibles = Array.isArray(getVisibleModalidades())
                        ? getVisibleModalidades()
                        : [];
                await updateOfertasList(usuarioId, modalidadesVisibles);
		if (ENABLE_LOGS)
			log("Refresco ofertas tras cambio de filtros:", {
				user: usuarioId,
				modalidadesVisibles,
			});
	}, 50);
}

async function updateOfertas() {
	if (isProfesional()) {
		await ensureCurrentUserIdReady();
	}

	const usuarioId = getEffectiveProfessionalId();
	if (!usuarioId) return;
        const ofertas = await fetchOfertas(usuarioId);
        setCurrentOfertas(ofertas || []);

        const modalidadesVisibles = Array.isArray(getVisibleModalidades())
                ? getVisibleModalidades()
                : [];

	await updateOfertasList(usuarioId, modalidadesVisibles);

        if (ENABLE_LOGS) log("Ofertas activas usuario:", ofertas);

        filtrarModalidades();

	document.dispatchEvent(new Event("ofertas:actualizadas"));
}

// Legacy / compatibilidad temporal

// observar cambio de vendedor
const usuarioRolInput = document.getElementById("usuario-rol");
if (usuarioRolInput) {
	usuarioRolInput.addEventListener("change", updateOfertas);
}

setTimeout(async () => {
	if (isProfesional()) {
		await ensureCurrentUserIdReady();
	}
	updateOfertas();
}, 0);

// --------- MODALIDADES (con cache) ---------
let modalidadesCache = {
	timestamp: 0,
	data: null,
};
async function fetchModalidades() {
	const now = Date.now();
	if (
		modalidadesCache.data &&
		now - modalidadesCache.timestamp < MODALIDAD_CACHE_TTL
	) {
		return modalidadesCache.data;
	}
	const restRoot = getRestRoot();
	const restNonce = getRestNonce();
	try {
		const res = await fetch(`${restRoot}go/v1/modalidades`, {
			method: "GET",
			credentials: "include",
			headers: { "X-WP-Nonce": restNonce, Accept: "application/json" },
		});
		if (!res.ok) throw new Error("Error al obtener modalidades");
		const json = await res.json();
		modalidadesCache = {
			timestamp: now,
			data: json,
		};
		return json;
	} catch (e) {
		log("Error:", e);
		return [];
	}
}

// --------- Reutilizables de modalidad ---------
function getCondicionGeneral(modalidad) {
	const cm =
		modalidad.acf?.condiciones_generales_y_tarifas?.condiciones_modalidad;
	if (!cm) return [];
	const cg = cm.condicion_general;
	if (Array.isArray(cg)) {
		return cg.map((x) => (typeof x === "object" ? x.value : x));
	}
	if (typeof cg === "object") return [cg.value];
	if (typeof cg === "string") return [cg];
	return [];
}

function getCondicionesEspeciales(modalidad) {
	const cm =
		modalidad.acf?.condiciones_generales_y_tarifas?.condiciones_modalidad;
	if (!cm) return [];
	let arr = cm.condiciones_especiales || [];
	if (!Array.isArray(arr)) arr = [arr];
	return arr.map((x) => (typeof x === "object" ? x.value : x));
}

function determineValorComparar(modalidad, valoresForm) {
	const condicionGeneral = getCondicionGeneral(modalidad);
	const cilindrada = parseNumericFormValue(valoresForm.cilindrada);
	const potencia = parseNumericFormValue(valoresForm.potencia);
	if (condicionGeneral.includes("cilindrada")) {
		return { tipo: "cilindrada", valor: cilindrada };
	} else if (condicionGeneral.includes("potencia")) {
		return { tipo: "potencia", valor: potencia };
	}
	return { tipo: "ninguna", valor: 0 };
}

function getDynamicRangeFromTarifas(modalidad) {
	const tarifas = modalidad.acf?.condiciones_generales_y_tarifas?.tarifas || [];
	let min = null;
	let max = null;
	for (const tarifa of tarifas) {
		const rawMin = tarifa.valor_min ?? tarifa.valor_minimo;
		const rawMax = tarifa.valor_max ?? tarifa.valor_maximo;
		const vMin = parseNumericFormValue(rawMin);
		let vMax;
		if (rawMax === "" || rawMax == null) vMax = 9999;
		else vMax = parseNumericFormValue(rawMax);
		if (min === null || vMin < min) min = vMin;
		if (max === null || vMax > max) max = vMax;
	}
	return { min, max };
}

function setDynamicLimits(modalidades, valoresForm) {
        let cilLimits = null;
        let potLimits = null;
        let found = false;

        for (const modalidad of modalidades) {
                const condicionGeneral = getCondicionGeneral(modalidad);
                if (condicionGeneral.includes("cilindrada")) {
                        cilLimits = getDynamicRangeFromTarifas(modalidad);
                        found = true;
                }
                if (condicionGeneral.includes("potencia")) {
                        potLimits = getDynamicRangeFromTarifas(modalidad);
                        found = true;
                }
        }

        if (!found) {
                cilLimits = { min: 0, max: 9000 };
                potLimits = { min: 0, max: 3000 };
        }
        const limits = getLimitesDinamicos();
        limits.cilindrada = cilLimits || { min: 0, max: 9000 };
        limits.potencia = potLimits || { min: 0, max: 3000 };
        setLimitesDinamicos(limits);
}

function getMesesDisponiblesPorModalidad(modalidad, valoresForm) {
	const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
	const tarifas = cg?.tarifas || [];
	const condicionesEspecialesArr = getCondicionesEspeciales(modalidad);
	const esCamion = condicionesEspecialesArr.includes("mma");
	const ejes = esCamion ? valoresForm.traccion_camion : null;

	const { tipo, valor } = determineValorComparar(modalidad, valoresForm);
	const mesesPorGarantia = tarifas
		.filter((tarifa) => {
			const min = parseNumericFormValue(
				tarifa.valor_min ?? tarifa.valor_minimo
			);
			const maxRaw = (tarifa.valor_max ?? tarifa.valor_maximo) || "";
			const max =
				maxRaw === "" || maxRaw == null
					? 99999999
					: parseNumericFormValue(maxRaw);
			const checkValor = tipo === "ninguna" || (valor >= min && valor <= max);
			const checkEjes = !esCamion || (tarifa.ejes && tarifa.ejes == ejes);
			return checkValor && checkEjes;
		})
		.map((tarifa) => Number(tarifa.duracion_meses));

	return [...new Set(mesesPorGarantia)].sort((a, b) => a - b);
}

function calcularPrecioBase(modalidad, valoresForm) {
	const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
	const cm = cg?.condiciones_modalidad || {};
	const tarifas = cg?.tarifas || [];
	const condicionesEspeciales = getCondicionesEspeciales(modalidad);
	const meses = Number(valoresForm.duracion);
	const esCamion = condicionesEspeciales.includes("mma");
	let ejes = null;
	if (esCamion) ejes = valoresForm.traccion_camion;

	const { tipo, valor } = determineValorComparar(modalidad, valoresForm);

	for (const tarifa of tarifas) {
		const min = parseNumericFormValue(tarifa.valor_min ?? tarifa.valor_minimo);
		const maxRaw = (tarifa.valor_max ?? tarifa.valor_maximo) || "";
		const max =
			maxRaw === "" || maxRaw == null
				? 99999999
				: parseNumericFormValue(maxRaw);

		const checkMeses = Number(tarifa.duracion_meses) === meses;
		const checkEjes = !esCamion || (tarifa.ejes && tarifa.ejes === ejes);
		const checkMin = tipo === "ninguna" || valor >= min;
		const checkMax = tipo === "ninguna" || valor <= max;

		if (checkMeses && checkEjes && checkMin && checkMax) {
			return Number(tarifa.precio_base);
		}
	}
	return null;
}

// --------- UI AUXILIARES ---------
function updateDuracionSelect(mesesDisponibles) {
	const select = document.getElementById("duracion");
	if (!select) return;
	const opciones = [
		{ value: 6, label: "6 meses" },
		{ value: 12, label: "12 meses" },
		{ value: 24, label: "24 meses" },
		{ value: 36, label: "36 meses" },
	];
	const valorSeleccionado = select.value;
	select.innerHTML = "";

	if (!mesesDisponibles.length) {
		const opt = document.createElement("option");
		opt.value = "";
		opt.textContent = "Sin opciones disponibles";
		opt.disabled = true;
		select.appendChild(opt);
		return;
	}
	const mesesOrdenados = mesesDisponibles.slice().sort((a, b) => a - b);

	let selectedValue;
	if (mesesOrdenados.map(String).includes(valorSeleccionado)) {
		selectedValue = valorSeleccionado;
	} else if (mesesOrdenados.includes(12)) {
		selectedValue = "12";
	} else {
		selectedValue = String(mesesOrdenados[0]);
	}

	mesesOrdenados.forEach((mes) => {
		const opcion = opciones.find((opt) => Number(opt.value) === Number(mes));
		if (opcion) {
			const opt = document.createElement("option");
			opt.value = opcion.value;
			opt.textContent = opcion.label;
			if (String(opt.value) === String(selectedValue)) opt.selected = true;
			select.appendChild(opt);
		}
	});
	select.value = selectedValue;
}

// --------- RENDERIZADO DE PLANES ---------
function renderPlans(modalidades, valoresForm) {
	const plansContainer = document.getElementById("formPlans");
	if (!plansContainer) return;

	plansContainer.classList.remove("form__plans--featured");

	const preciosConIVA = document.getElementById("check-iva")?.checked !== false;

	if (!modalidades || !modalidades.length) {
		plansContainer.innerHTML =
			"<div>No hay garantías disponibles para estos filtros.</div>";
		return;
	}

	const sorted = modalidades.slice().sort((a, b) => {
		const getOrden = (m) =>
			Number(
				m.acf?.condiciones_generales_y_tarifas?.descripcion_informacion?.estilos
					?.orden
			) || 0;
		return getOrden(b) - getOrden(a);
	});

    const persistedModalidadId = getSelectedModalidadId();
	const anySelectedPre = sorted.some((m) => {
		const estilos =
			m.acf?.condiciones_generales_y_tarifas?.descripcion_informacion
				?.estilos || {};
		return estilos.preseleccionada;
	});
	const anySelectedPersisted =
		persistedModalidadId != null &&
		sorted.some((m) => String(m.ID) === String(persistedModalidadId));
	const anySelected = anySelectedPre || anySelectedPersisted;

	let alreadySelected = false;
	let anyFeatured = false;

	plansContainer.innerHTML = sorted
		.map((m, idx) => {
			const detalles = m.acf?.detalles_modalidad || {};
			const cg = m.acf?.condiciones_generales_y_tarifas || {};
			const descInfo = cg?.descripcion_informacion || {};
			const estilos = descInfo?.estilos || {};

			const title = detalles.nombre_mostrar || m.title || "";
			const description = descInfo.descripcion_garantia || "";
			const pdf = detalles.documentos?.coberturas?.url || null;

			let badge = "";
			if (estilos.etiqueta && estilos.texto_etiqueta) {
				badge = `<div class="form__plan-badge">${estilos.texto_etiqueta}</div>`;
			}

			const planClasses = ["form__plan"];
			if (estilos.destacada) {
				if (estilos.estilo_premium) planClasses.push("form__plan--premium");
				else {
					planClasses.push("form__plan--featured");
					anyFeatured = true;
				}
			}

			let selected = false;
			if (
				persistedModalidadId &&
				String(m.ID) === String(persistedModalidadId)
			) {
				planClasses.push("selected");
				selected = true;
				alreadySelected = true;
			} else if (
				!persistedModalidadId &&
				estilos.preseleccionada &&
				!alreadySelected
			) {
				planClasses.push("selected");
				selected = true;
				alreadySelected = true;
			} else if (anySelected) {
				planClasses.push("form__plan--no-selected");
			}

			const precioBase = calcularPrecioBase(m, valoresForm);

			const breakdown = calcularRecargos(m, {
				...valoresForm,
				fecha_primera_matriculacion: getValorInput(
					"fecha_primera_matriculacion"
				),
				kilometros: getValorInput("kilometros"),
				traccion: getValorInput("traccion"),
				cambio: getValorInput("cambio"),
				doble_motor: getValorInput("doble_motor") || null,
			});

                        const descuentos = getDescuentosAplicablesSync(m);
                        let multiplicador = 1;
                        descuentos.forEach((d) => {
                                multiplicador *= 1 - d.porcentaje;
                        });
                        const descuentoTotalSync = 1 - multiplicador;

			const precioSinDescuento =
				precioBase !== null
					? redondearEuros(precioBase * (1 + breakdown.recargoTotal))
					: null;
			const precioSinDescuentoIVA =
				precioSinDescuento !== null
					? redondearEuros(precioSinDescuento * (1 + IVA_PORCENTAJE / 100))
					: null;

			const precioFinal =
				precioBase !== null
					? redondearEuros(
							precioBase *
								(1 + breakdown.recargoTotal) *
								(1 - descuentoTotalSync)
					  )
					: null;
			const precioIVA =
				precioFinal !== null
					? redondearEuros(precioFinal * (1 + IVA_PORCENTAJE / 100))
					: null;
			const ivaSolo =
				precioFinal !== null ? redondearEuros(precioIVA - precioFinal) : null;

			let recargosHTML = "";
			if (
				getEffectiveUserRole() === "admin" &&
				precioBase !== null &&
				precioFinal !== null
			) {
				recargosHTML = renderRecargosHTML({
					precioBase,
					breakdown,
					precioFinal,
					precioIVA,
					descuentoTotal: descuentoTotalSync,
					modalidad: m,
				});
			}

			let buttonInner;
			if (selected) {
				const checkIcon =
                                    getIcon("check") ||
					'<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16"><path d="M6 10.5L3.5 8l-1 1 3.5 3.5L14.5 4l-1-1L6 10.5z"/></svg>';
				buttonInner = `<span class="plan-button-icon" aria-hidden="true">${checkIcon}</span> <span class="form__plan-button-text--label">Seleccionada</span>`;
			} else {
				buttonInner = `<span class="form__plan-button-text--label">Seleccionar</span>`;
			}

			const priceConIva = precioIVA !== null ? eurosString(precioIVA) : "--";
			const priceSinIva =
				precioFinal !== null ? eurosString(precioFinal) : "--";
			const priceIvaOnly =
				ivaSolo !== null ? `+ ${eurosString(ivaSolo)}€ IVA` : "";

			return `
            <div class="${planClasses.join(
							" "
						)}" tabindex="0" role="button" data-plan-idx="${idx}" data-modalidad-id="${
				m.ID
			}">
                ${badge}
                <div class="form__plan-content">
                    <div class="form__plan-title">${title}</div>
                    <div class="form__plan-price-wrapper">
						${(() => {
							if (descuentoTotalSync > 0 && precioSinDescuento !== null) {
								const anterior = preciosConIVA
									? eurosString(precioSinDescuentoIVA)
									: eurosString(precioSinDescuento);
								return `<span class="form__plan-price-anterior">${anterior}€</span>`;
							}
							return "";
						})()}
                        <span class="plan-price-skeleton"></span>
                        <span class="form__plan-price-text">
                            <span class="plan-price-value" style="display:${
															preciosConIVA ? "inline" : "none"
														}">${priceConIva}</span>
                            <span class="plan-price-value-noiva" style="display:${
															preciosConIVA ? "none" : "inline"
														}">${priceSinIva}</span>
                            <span class="form__plan-price-euro">€</span>
                        </span>
                    </div>
                    <div class="form__plan-iva" style="display:${
											preciosConIVA ? "block" : "none"
										}">IVA incluido</div>
                    <div class="form__plan-iva-no" style="display:${
											preciosConIVA ? "none" : "block"
										}">${priceIvaOnly}</div>
                    <div class="form__plan-description">${description}</div>
                    ${recargosHTML}
                    ${
											pdf
												? `
                    <a class="form__plan-link" href="${pdf}" target="_blank" rel="noopener">
                        <svg class="form__plan-link-icon" width="16" height="16" viewBox="0 0 24 24"></svg>
                        Ver cobertura ${title}
                    </a>
                    `
												: ""
										}
                    <button class="form__plan-button" type="button">
                        <span class="form__plan-button-text">${buttonInner}</span>
                    </button>
                </div>
            </div>
        `;
		})
		.join("");

	if (anyFeatured) plansContainer.classList.add("form__plans--featured");
    setupPlanSelection();
}

// --------- FILTRADO PRINCIPAL ---------
async function filtrarModalidades() {
	const tipoVehiculoSeleccionado = getValorInput("tipo_vehiculo") || "";
	const fechaPrimeraMatriculacion = getValorInput(
		"fecha_primera_matriculacion"
	);
	const antiguedad = getAntiguedadFromDate(fechaPrimeraMatriculacion);

	const valoresForm = {
		cilindrada: getValorInput("cilindrada") || 0,
		potencia: getValorInput("potencia") || 0,
		duracion: Number(getValorInput("duracion")) || 12,
		traccion_camion: getValorInput("traccion_camion") || null,
		mma: getValorInput("mma") || null,
		combustible: getValorInput("combustible") || null,
	};

	const modalidades = await fetchModalidades();
	let disponibles = modalidades.filter(
		(m) => m.tipo_vehiculo && m.tipo_vehiculo.includes(tipoVehiculoSeleccionado)
	);

	let garantiaNoDisponiblePorAntiguedad = false;

	disponibles = disponibles.filter((m) => {
		const cm = m.acf?.condiciones_generales_y_tarifas?.condiciones_modalidad;
		if (!cm) return true;

		const condicionesEspecialesArr = getCondicionesEspeciales(m);

		for (const config of [
			{
				key: "combustible",
				formField: "combustible",
				modalidadField: "combustible",
			},
			{ key: "mma", formField: "mma", modalidadField: "condicion_mma" },
		]) {
			if (condicionesEspecialesArr.includes(config.key)) {
				const valorFormulario = getValorInput(config.formField);
				const valoresModalidad = getValoresModalidadCampo(
					cm[config.modalidadField]
				);
				if (!valorFormulario) return false;
				if (!valoresModalidad.includes(valorFormulario)) return false;
			}
		}

		if (condicionesEspecialesArr.includes("antiguedad")) {
			const grupoAntiguedad = cm.condicion_por_antiguedad || {};
			const desde = Number(grupoAntiguedad.desde || 0);
			const hastaRaw = grupoAntiguedad.hasta;
			const hasta =
				hastaRaw !== "" && hastaRaw !== undefined ? Number(hastaRaw) : null;

			if (antiguedad === null || isNaN(antiguedad)) return false;
			if (antiguedad <= 1) {
				garantiaNoDisponiblePorAntiguedad = true;
				return false;
			}
			if (antiguedad < desde) return false;
			if (hasta !== null && antiguedad > hasta) return false;
		}
		return true;
	});

    setVisibleModalidades(disponibles);

	setDynamicLimits(disponibles, valoresForm);

	let mesesDisponibles = [];
	if (disponibles.length) {
		const mesesPorGarantia = disponibles.map((m) =>
			getMesesDisponiblesPorModalidad(m, valoresForm)
		);
		if (mesesPorGarantia.length) {
			mesesDisponibles = mesesPorGarantia.reduce(
				(acc, curr) => acc.filter((x) => curr.includes(x)),
				mesesPorGarantia[0] || []
			);
		}
	}

	if (!disponibles.length && !garantiaNoDisponiblePorAntiguedad) {
		updateDuracionSelect([]);
		renderPlans([], valoresForm);
	} else if (disponibles.length) {
		updateDuracionSelect(mesesDisponibles);
		renderPlans(disponibles, valoresForm);
	} else {
		updateDuracionSelect([]);
		renderPlans([], valoresForm);
	}

	await refreshOfertasDisplay();

	return disponibles;
}
// --------- INIT ---------
async function initCalculations() {
	const dynamicFields = [
		"tipo_vehiculo",
		"combustible",
		"mma",
		"fecha_primera_matriculacion",
		"cilindrada",
		"potencia",
		"duracion",
		"traccion_camion",
		"kilometros",
		"traccion",
		"cambio",
		"doble_motor",
	];
	dynamicFields.forEach((id) => {
		const input = document.getElementById(id);
		if (input) {
			input.addEventListener("input", filtrarModalidades);
			input.addEventListener("change", filtrarModalidades);
		}
	});
	const inputTipoVehiculo = document.getElementById("tipo_vehiculo");
	if (inputTipoVehiculo) {
		inputTipoVehiculo.addEventListener("change", (e) => {
			const nuevoTipo = e.target.value;
			if (nuevoTipo !== "camion") {
				const campoEjes = document.getElementById("traccion_camion");
				if (campoEjes) campoEjes.value = "";
				const campoMMA = document.getElementById("mma");
				if (campoMMA) campoMMA.value = "";
			}
			[
				"potencia",
				"cilindrada",
				"combustible",
				"mma",
				"traccion_camion",
			].forEach((id) => {
				const campo = document.getElementById(id);
				if (campo) {
					campo.dispatchEvent(new Event("input", { bubbles: true }));
					campo.dispatchEvent(new Event("change", { bubbles: true }));
				}
			});
			setTimeout(filtrarModalidades, 10);
		});
	}
	const inputDuracion = document.getElementById("duracion");
	if (inputDuracion) {
		inputDuracion.addEventListener("change", filtrarModalidades);
	}
	const inputVendedor = document.getElementById("usuario-rol");
	if (inputVendedor) {
		inputVendedor.addEventListener("change", async () => {
			document
				.querySelectorAll(".plan-price-value, .plan-price-value-noiva")
				.forEach((el) => {
					el.textContent = "";
				});
			document.querySelectorAll(".plan-price-skeleton").forEach((el) => {
				el.style.display = "inline-block";
			});
			await updateOfertas();
		});
	}

	const inputCheckIVA = document.getElementById("check-iva");
	if (inputCheckIVA) {
		inputCheckIVA.addEventListener("change", () => {
			document
				.querySelectorAll(".plan-price-value, .plan-price-value-noiva")
				.forEach((el) => {
					el.textContent = "";
				});
			document.querySelectorAll(".plan-price-skeleton").forEach((el) => {
				el.style.display = "inline-block";
			});
			setTimeout(filtrarModalidades, 50);
		});
	}

	if (isProfesional()) {
		await ensureCurrentUserIdReady();
	}
	await filtrarModalidades();
	if (!document.getElementById("usuario-rol") && isProfesional()) {
		await updateOfertas();
	}
}

// === EXPORTS NECESARIOS PARA OTROS MÓDULOS ===
export {
        fetchModalidades,
        calcularPrecioBase,
        calcularRecargos,
        getDescuentosAplicables,
        getDescuentoTotal,
        getAntiguedadFromDate,
        parseNumericFormValue,
        eurosString,
        filtrarModalidades,
        updateOfertas,
};
export default initCalculations;

/*renderPlans hace uso parcial de descuentos sin esperar el await de su cálculo real; considera convertir parte de esa lógica en async/await para que el precio refleje correctamente los descuentos si es necesario.*/

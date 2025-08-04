"use strict";

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-calculations]", ...args);
}

const IVA_PORCENTAJE = 21;

// Redondeo y formateo euros
function redondearEuros(valor) {
	if (typeof valor !== "number") valor = parseFloat(valor);
	return Math.round(valor * 100) / 100;
}
function eurosString(valor) {
	const num = redondearEuros(
		typeof valor === "number" ? valor : parseFloat(valor)
	);
	return num.toLocaleString("es-ES", {
		minimumFractionDigits: 2,
		maximumFractionDigits: 2,
		useGrouping: true, // <--- aseguramos el separador de miles
	});
}

// TipoCondición y comparadores
const tipoCondicion = {
	antiguedad: "num",
	kilometros: "num",
	potencia: "num",
	traccion: "string",
	cambio: "string",
	doble_motor: "bool",
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

// Utilidades
function parseNumericFormValue(val) {
	if (typeof val === "number") return val;
	if (!val) return 0;
	return Number(
		String(val).replace(/\./g, "").replace(/,/g, ".").replace(/\s/g, "")
	);
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
function getAntiguedadFromDate(fechaISO) {
	if (!fechaISO) return null;
	const fecha = new Date(fechaISO);
	if (isNaN(fecha.getTime())) return null;
	const anioMatriculacion = fecha.getFullYear();
	const anioActual = new Date().getFullYear();
	return anioActual - anioMatriculacion;
}

// Recargos (suplementos)
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

	let suplementosAplicados = [];
	for (const sup of suplementos) {
		const condicion = sup.condicion;
		const tipo = tipoCondicion[condicion];
		const comparador = comparadores[tipo]?.[sup.comparador];
		if (!comparador) continue;
		let inputValor = null;
		if (condicion === "antiguedad") {
			inputValor = getAntiguedadFromDate(
				valoresForm.fecha_primera_matriculacion
			);
		} else if (condicion === "kilometros") {
			inputValor = parseNumericFormValue(valoresForm.kilometros);
		} else if (condicion === "potencia") {
			inputValor = parseNumericFormValue(valoresForm.potencia);
		} else if (condicion === "traccion") {
			inputValor = valoresForm.traccion || "";
		} else if (condicion === "cambio") {
			inputValor = valoresForm.cambio || "";
		} else if (condicion === "doble_motor") {
			inputValor = valoresForm.doble_motor;
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

	let recargosPorGrupo = {};
	for (const grupo in grupos) {
		const arr = grupos[grupo];
		const esAcumulable = arr.some((s) => s.acumulable);
		let sumaGrupo = 0;
		if (esAcumulable) {
			sumaGrupo = arr.reduce((acc, s) => acc + s.recargo, 0);
		} else {
			sumaGrupo = Math.max(...arr.map((s) => s.recargo));
		}
		if (topePorGrupo[grupo])
			sumaGrupo = Math.min(sumaGrupo, topePorGrupo[grupo]);
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
function getDescuentosAplicables(modalidad, { incluirCaducadas = false } = {}) {
	if (!Array.isArray(window.currentOfertas) || !modalidad) return [];
	const modalidadID = modalidad.ID;
	const nivelGarantia = Array.isArray(modalidad.nivel_garantia)
		? modalidad.nivel_garantia[0]
		: modalidad.nivel_garantia;
	const now = Date.now() / 1000;

	let descuentos = [];
	window.currentOfertas.forEach((oferta) => {
		if (!oferta.porcentaje_descuento) return;
		if (oferta.estado === false) return;
		const caducada =
			oferta.timestamp_caducidad && now > oferta.timestamp_caducidad;
		if (!incluirCaducadas && caducada) return;
		if (oferta.aplicacion?.value === "todas") {
			descuentos.push({
				porcentaje: oferta.porcentaje_descuento / 100,
				nombre: oferta.nombre,
				caducada,
			});
			return;
		}
		if (
			oferta.aplicacion?.value &&
			nivelGarantia &&
			oferta.aplicacion.value === nivelGarantia
		) {
			descuentos.push({
				porcentaje: oferta.porcentaje_descuento / 100,
				nombre: oferta.nombre,
				caducada,
			});
			return;
		}
		if (
			oferta.aplicacion?.value === "seleccion" &&
			Array.isArray(oferta.seleccion_modalidad) &&
			oferta.seleccion_modalidad.includes(modalidadID)
		) {
			descuentos.push({
				porcentaje: oferta.porcentaje_descuento / 100,
				nombre: oferta.nombre,
				caducada,
			});
			return;
		}
	});
	if (ENABLE_LOGS) {
		log(
			"[Descuentos] Modalidad:",
			modalidad.title,
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
function getDescuentoTotal(modalidad) {
	const descuentos = getDescuentosAplicables(modalidad);
	let multiplicador = 1;
	descuentos.forEach((d) => {
		multiplicador *= 1 - d.porcentaje;
	});
	const descuentoTotal = 1 - multiplicador;
	return descuentoTotal;
}

// Renderiza desglose admin
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

	const descuentosAplicados = getDescuentosAplicables(modalidad);
	if (descuentosAplicados.length) {
		html += `<ul class="form__plan-descuentos-list">`;
		for (const desc of descuentosAplicados) {
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
		html += `<p class="form__plan-recargos-precios">Límite máximo total: <span class="form__plan-porcentaje-recargo">${breakdown.maximoAcumulableTotal}%</span></p>`;
	}
	html += "</div>";
	return html;
}

// --------- OFERTAS (DESCUENTOS) ---------
function getUsuarioRolSeleccionado() {
	const input = document.getElementById("usuario-rol");
	if (!input || !input.value) return null;
	const val = parseInt(input.value, 10);
	return isNaN(val) ? null : val;
}
async function fetchOfertasUsuario() {
	const restRoot = window.GO_REST?.root ?? "/wp-json/";
	const restNonce = window.GO_REST?.nonce ?? "";

	const vendedorId = getUsuarioRolSeleccionado();
	const url = vendedorId
		? `${restRoot}go/v1/ofertas-usuario?id=${vendedorId}`
		: `${restRoot}go/v1/ofertas-usuario`;

	try {
		const res = await fetch(url, {
			method: "GET",
			credentials: "include",
			headers: { "X-WP-Nonce": restNonce, Accept: "application/json" },
			cache: "reload",
		});
		if (!res.ok) throw new Error("Error al obtener ofertas: " + res.status);
		return await res.json();
	} catch (e) {
		log("Error ofertas:", e);
		return [];
	}
}
function renderOfertas(ofertas) {
	const cont = document.querySelector(".form__ofertas");
	if (!cont) return;

	const prevList = cont.querySelector("ul.ofertas__list");
	if (prevList) prevList.remove();

	const now = Date.now() / 1000;

	const ofertasVisibles = ofertas.filter(
		(oferta) =>
			oferta.estado !== false &&
			oferta.porcentaje_descuento > 0 &&
			(!oferta.timestamp_caducidad || now <= oferta.timestamp_caducidad)
	);
	const ofertasCaducadas = ofertas.filter(
		(oferta) =>
			oferta.estado !== false &&
			oferta.porcentaje_descuento > 0 &&
			oferta.timestamp_caducidad &&
			now > oferta.timestamp_caducidad
	);

	if (ofertasVisibles.length || ofertasCaducadas.length) {
		const ul = document.createElement("ul");
		ul.className = "ofertas__list";
		ofertasVisibles.forEach((oferta) => {
			const li = document.createElement("li");
			li.className = "ofertas__item";
			li.innerHTML = `${window.GO_ICONS.clear} ${oferta.nombre}: -${oferta.porcentaje_descuento}%`;
			ul.appendChild(li);
		});
		ofertasCaducadas.forEach((oferta) => {
			const li = document.createElement("li");
			li.className = "ofertas__item ofertas__item--caducada";
			li.innerHTML = `<span class="tachada">${oferta.nombre}: -${oferta.porcentaje_descuento}%</span> <span class="vencida">VENCIDA</span>`;
			ul.appendChild(li);
		});
		const ivaDiv = cont.querySelector(".ofertas__iva");
		if (ivaDiv) cont.insertBefore(ul, ivaDiv);
		else cont.appendChild(ul);
	}
}
async function updateOfertas() {
	const ofertas = await fetchOfertasUsuario();
	window.currentOfertas = ofertas || [];
	renderOfertas(ofertas);
	if (ENABLE_LOGS) log("Ofertas activas usuario:", ofertas);
}
const usuarioRolInput = document.getElementById("usuario-rol");
if (usuarioRolInput) {
	usuarioRolInput.addEventListener("change", updateOfertas);
}
window.updateOfertas = updateOfertas;
setTimeout(updateOfertas, 0);

// --------- MODALIDADES Y FILTROS ---------
async function fetchModalidades() {
	const restRoot = window.GO_REST?.root ?? "/wp-json/";
	const restNonce = window.GO_REST?.nonce ?? "";
	try {
		const res = await fetch(`${restRoot}go/v1/modalidades`, {
			method: "GET",
			credentials: "include",
			headers: { "X-WP-Nonce": restNonce, Accept: "application/json" },
		});
		if (!res.ok) throw new Error("Error al obtener modalidades");
		return await res.json();
	} catch (e) {
		log("Error:", e);
		return [];
	}
}

function getDynamicRangeFromTarifas(modalidad, campo) {
	const tarifas = modalidad.acf?.condiciones_generales_y_tarifas?.tarifas || [];
	let min = null;
	let max = null;
	for (const tarifa of tarifas) {
		let vMin = parseNumericFormValue(tarifa.valor_min || tarifa.valor_minimo);
		let vMax = tarifa.valor_max ?? tarifa.valor_maximo;
		vMax = vMax === "" || vMax == null ? 9999 : parseNumericFormValue(vMax);
		if (min === null || vMin < min) min = vMin;
		if (max === null || vMax > max) max = vMax;
	}
	return { min, max };
}
function setDynamicLimits(modalidades, valoresForm) {
	window.limitesDinamicos = window.limitesDinamicos || {};
	let found = false;
	let cilLimits = null;
	let potLimits = null;
	for (const modalidad of modalidades) {
		const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
		const cm = cg?.condiciones_modalidad || {};
		const condicionGeneral = Array.isArray(cm.condicion_general)
			? cm.condicion_general
			: [cm.condicion_general];
		if (
			condicionGeneral.some(
				(x) => (typeof x === "object" ? x.value : x) === "cilindrada"
			)
		) {
			cilLimits = getDynamicRangeFromTarifas(modalidad, "cilindrada");
			found = true;
		}
		if (
			condicionGeneral.some(
				(x) => (typeof x === "object" ? x.value : x) === "potencia"
			)
		) {
			potLimits = getDynamicRangeFromTarifas(modalidad, "potencia");
			found = true;
		}
	}
	if (!found) {
		cilLimits = { min: 0, max: 9000 };
		potLimits = { min: 0, max: 3000 };
	}
	window.limitesDinamicos.cilindrada = cilLimits || { min: 0, max: 9000 };
	window.limitesDinamicos.potencia = potLimits || { min: 0, max: 3000 };
}
function getMesesDisponiblesPorModalidad(modalidad, valoresForm) {
	const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
	const tarifas = cg?.tarifas || [];
	const cm = cg?.condiciones_modalidad || {};
	const condicionGeneral = Array.isArray(cm.condicion_general)
		? cm.condicion_general
		: [cm.condicion_general];
	let valorComparar = 0;
	if (
		condicionGeneral.some(
			(x) => (typeof x === "object" ? x.value : x) === "cilindrada"
		)
	) {
		valorComparar = parseNumericFormValue(valoresForm.cilindrada);
	} else if (
		condicionGeneral.some(
			(x) => (typeof x === "object" ? x.value : x) === "potencia"
		)
	) {
		valorComparar = parseNumericFormValue(valoresForm.potencia);
	}
	const condicionesEspecialesArr = Array.isArray(cm.condiciones_especiales)
		? cm.condiciones_especiales.map((x) =>
				typeof x === "string" ? x : x.value
		  )
		: [];
	const esCamion = condicionesEspecialesArr.includes("mma");
	const ejes = esCamion ? valoresForm.traccion_camion : null;
	const mesesValidos = tarifas
		.filter((tarifa) => {
			const min = parseNumericFormValue(
				tarifa.valor_min || tarifa.valor_minimo
			);
			const max = parseNumericFormValue(
				tarifa.valor_max || tarifa.valor_maximo || 99999999
			);
			const checkValor = valorComparar >= min && valorComparar <= max;
			const checkEjes = !esCamion || (tarifa.ejes && tarifa.ejes == ejes);
			return checkValor && checkEjes;
		})
		.map((tarifa) => Number(tarifa.duracion_meses));
	return [...new Set(mesesValidos)].sort((a, b) => a - b);
}
function calcularPrecioBase(modalidad, valoresForm) {
	const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
	const cm = cg?.condiciones_modalidad || {};
	const tarifas = cg?.tarifas || [];
	const condicionesEspeciales = (cm.condiciones_especiales || []).map((x) =>
		typeof x === "string" ? x : x.value
	);
	const condicionGeneral = Array.isArray(cm.condicion_general)
		? cm.condicion_general
		: [cm.condicion_general];
	const meses = Number(valoresForm.duracion);
	const esCamion = condicionesEspeciales.includes("mma");
	let valorComparar = 0;
	let condicion = "ninguna";
	const cilindradaLimpia = parseNumericFormValue(valoresForm.cilindrada);
	const potenciaLimpia = parseNumericFormValue(valoresForm.potencia);

	if (
		condicionGeneral.some(
			(x) => (typeof x === "object" ? x.value : x) === "cilindrada"
		)
	) {
		valorComparar = cilindradaLimpia;
		condicion = "cilindrada";
	} else if (
		condicionGeneral.some(
			(x) => (typeof x === "object" ? x.value : x) === "potencia"
		)
	) {
		valorComparar = potenciaLimpia;
		condicion = "potencia";
	}

	let ejes = null;
	if (esCamion) ejes = valoresForm.traccion_camion;

	for (const tarifa of tarifas) {
		const min = parseNumericFormValue(tarifa.valor_min || tarifa.valor_minimo);
		const max = parseNumericFormValue(
			tarifa.valor_max || tarifa.valor_maximo || 99999999
		);

		const checkMeses = Number(tarifa.duracion_meses) === meses;
		const checkEjes = !esCamion || (tarifa.ejes && tarifa.ejes === ejes);
		const checkMin = valorComparar >= min;
		const checkMax = valorComparar <= max;

		if (checkMeses && checkEjes && checkMin && checkMax) {
			return Number(tarifa.precio_base);
		}
	}
	return null;
}
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

// Renderizado de planes con descuentos aplicados
function renderPlans(modalidades, valoresForm) {
	const plansContainer = document.getElementById("formPlans");
	if (!plansContainer) return;

	plansContainer.classList.remove("form__plans--featured");

	const preciosConIVA = document.getElementById("check-iva")?.checked !== false; // checked = true por defecto

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

	const anySelected = sorted.some((m) => {
		const estilos =
			m.acf?.condiciones_generales_y_tarifas?.descripcion_informacion
				?.estilos || {};
		return estilos.preseleccionada;
	});
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

			let planClasses = ["form__plan"];
			if (estilos.destacada) {
				if (estilos.estilo_premium) planClasses.push("form__plan--premium");
				else {
					planClasses.push("form__plan--featured");
					anyFeatured = true;
				}
			}

			let selected = false;
			if (estilos.preseleccionada && !alreadySelected) {
				planClasses.push("selected");
				selected = true;
				alreadySelected = true;
			} else if (anySelected) {
				planClasses.push("form__plan--no-selected");
			}

			const precioBase = calcularPrecioBase(m, valoresForm);

			// ---- Recargos ----
			const breakdown = calcularRecargos(m, {
				...valoresForm,
				fecha_primera_matriculacion: getValorInput(
					"fecha_primera_matriculacion"
				),
				kilometros: getValorInput("kilometros"),
				traccion: getValorInput("traccion"),
				cambio: getValorInput("cambio"),
				doble_motor: !!document.getElementById("doble_motor")?.checked,
			});

			// ---- Descuentos ----
			const descuentoTotal = getDescuentoTotal(m);
			// Precio sin descuento: base + recargos (antes de aplicar descuentos)
			const precioSinDescuento =
				precioBase !== null
					? redondearEuros(precioBase * (1 + breakdown.recargoTotal))
					: null;
			const precioSinDescuentoIVA =
				precioSinDescuento !== null
					? redondearEuros(precioSinDescuento * (1 + IVA_PORCENTAJE / 100))
					: null;

			// Cálculo precios
			const precioFinal =
				precioBase !== null
					? redondearEuros(
							precioBase * (1 + breakdown.recargoTotal) * (1 - descuentoTotal)
					  )
					: null;
			const precioIVA =
				precioFinal !== null
					? redondearEuros(precioFinal * (1 + IVA_PORCENTAJE / 100))
					: null;

			const ivaSolo =
				precioFinal !== null ? redondearEuros(precioIVA - precioFinal) : null;

			// Logs
			if (ENABLE_LOGS) {
				const suplementoLog = breakdown.suplementosAplicados.map((s) => ({
					condicion: s.condicion,
					valor: s.valorCondicion,
					comparador: s.comparador,
					objetivo: s.valorComparar,
					recargo: (s.recargo * 100).toFixed(1) + "%",
					grupo: s.grupo,
					acumulable: s.acumulable,
					descripcion: s.descripcion,
				}));
				const descripcionesSuplementos = breakdown.suplementosAplicados
					.map((s) => s.descripcion)
					.filter(Boolean);
				log(
					`[${title}] Base: ${precioBase ?? "--"} | Recargos: ${Math.round(
						breakdown.recargoTotal * 100
					)}% | Descuentos: ${Math.round(descuentoTotal * 100)}% | Final: ${
						precioFinal ?? "--"
					} | Final+IVA: ${precioIVA ?? "--"}\n  ↳ Suplementos aplicados:`,
					suplementoLog,
					descripcionesSuplementos.length
						? `\n  ↳ Descripciones: ${descripcionesSuplementos.join("; ")}`
						: ""
				);
			}

			// Separador de miles en .plan-price-value SÓLO
			const priceConIva = precioIVA !== null ? eurosString(precioIVA) : "--";
			const priceSinIva =
				precioFinal !== null ? eurosString(precioFinal) : "--";
			const priceIvaOnly =
				ivaSolo !== null ? `+ ${eurosString(ivaSolo)}€ IVA` : "";

			// Recargos HTML solo admin
			let recargosHTML = "";
			if (
				typeof window.userRole !== "undefined" &&
				window.userRole === "admin" &&
				precioBase !== null &&
				precioFinal !== null
			) {
				recargosHTML = renderRecargosHTML({
					precioBase,
					breakdown,
					precioFinal,
					precioIVA,
					descuentoTotal,
					modalidad: m,
				});
			}

			return `
            <div class="${planClasses.join(
							" "
						)}" tabindex="0" role="button" data-plan-idx="${idx}">
                ${badge}
                <div class="form__plan-content">
                    <div class="form__plan-title">${title}</div>
                    <div class="form__plan-price-wrapper">
					${(() => {
						if (descuentoTotal > 0 && precioSinDescuento !== null) {
							const anterior = preciosConIVA
								? eurosString(precioSinDescuentoIVA)
								: eurosString(precioSinDescuento);
							return `<span class="form__plan-price-anterior"><s>${anterior}€</s></span>`;
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
                        <span class="form__plan-button-text">${
													selected ? "Seleccionada" : "Seleccionar"
												}</span>
                    </button>
                </div>
            </div>
        `;
		})
		.join("");

	if (anyFeatured) plansContainer.classList.add("form__plans--featured");
	if (typeof window.setupPlanSelection === "function") {
		window.setupPlanSelection();
	}
}

// ---- Filtrado de modalidades y demás funciones ----
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
		const cg = m.acf?.condiciones_generales_y_tarifas;
		const cm = cg?.condiciones_modalidad;

		if (!cm) return true;

		const condicionesEspecialesArr = Array.isArray(cm.condiciones_especiales)
			? cm.condiciones_especiales.map((c) =>
					typeof c === "string" ? c : c.value
			  )
			: [];

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
			const hasta =
				grupoAntiguedad.hasta !== "" && grupoAntiguedad.hasta !== undefined
					? Number(grupoAntiguedad.hasta)
					: null;

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

	setDynamicLimits(disponibles, valoresForm);

	let mesesDisponibles = [];
	if (disponibles.length) {
		const mesesPorGarantia = disponibles.map((m) =>
			getMesesDisponiblesPorModalidad(m, valoresForm)
		);
		mesesDisponibles = mesesPorGarantia.reduce(
			(acc, curr) => acc.filter((x) => curr.includes(x)),
			mesesPorGarantia[0] || []
		);
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
}
window.filtrarModalidades = filtrarModalidades;

// --------- INIT ---------
function initCalculations() {
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
	const onInputFields = [
		"cilindrada",
		"potencia",
		"kilometros",
		"precio_venta",
		"matricula",
		"numero_bastidor",
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
		inputVendedor.addEventListener("change", () => {
			document.querySelectorAll(".plan-price-value").forEach((el) => {
				el.textContent = "";
			});
			document.querySelectorAll(".plan-price-skeleton").forEach((el) => {
				el.style.display = "inline-block";
			});
			filtrarModalidades();
		});
	}
	const inputCheckIVA = document.getElementById("check-iva");
	if (inputCheckIVA) {
		inputCheckIVA.addEventListener("change", () => {
			// Skeleton effect al cambiar el check igual que en duración
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

	const btnEjemplo = document.getElementById("rellenar_ejemplo");
	if (btnEjemplo) {
		btnEjemplo.addEventListener("click", () => {
			setTimeout(() => {
				dynamicFields.forEach((id) => {
					const el = document.getElementById(id);
					if (el) {
						el.dispatchEvent(new Event("change", { bubbles: true }));
						if (onInputFields.includes(id)) {
							el.dispatchEvent(new Event("input", { bubbles: true }));
						}
					}
				});
				filtrarModalidades();
			}, 30);
		});
	}

	filtrarModalidades();
}

export default initCalculations;

/*Opción para mostrar u ocultar precio anterior tachado*/
/*Check doble motor*/
/*Explicar porcentaje en descuentos acumulativas */

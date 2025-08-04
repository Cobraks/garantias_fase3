"use strict";

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-calculations]", ...args);
}

// ------- Tipos y comparadores de suplementos -------
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

// ----------- Recargos (suplementos) -----------
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

	// Agrupa por grupo_acumulabilidad y calcula total por grupo
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
		recargoTotal, // decimal (ej: 0.40 = 40%)
		topePorGrupo,
		maximoAcumulableTotal,
	};
}

// ------------ Genera HTML para los recargos de cada plan (solo admin) -----------
function renderRecargosHTML({ precioBase, precioFinal, breakdown }) {
	// Si no hay recargos ni suplementos, devuelvo vacío.
	if (!breakdown || !breakdown.suplementosAplicados.length) return "";

	// 1. Mostrar precio base | recargos % | precio final
	const totalRecargo = breakdown.recargoTotal * 100;
	let html = `
		<div class="form__plan-recargos">
			<p class="form__plan-recargos-precios">
				Precio base: ${precioBase.toLocaleString("es-ES", {
					minimumFractionDigits: 2,
				})}€
				| Recargos: ${totalRecargo.toFixed(1)}%
				| Precio final: ${precioFinal.toLocaleString("es-ES", {
					minimumFractionDigits: 2,
				})}€
			</p>
			<ul class="form__plan-recargos-list">
	`;

	// 2. Mostrar <li> de recargos, agrupando según reglas:
	const grupos = breakdown.grupos;
	for (const grupo in grupos) {
		const arr = grupos[grupo];
		const esAcumulable = arr.some((s) => s.acumulable);

		if (esAcumulable || arr.length === 1) {
			// Un <li> por recargo
			for (const sup of arr) {
				html += `<li class="form__plan-recargos-item"><span class="form__plan-porcentaje-recargo">Recargo ${(
					sup.recargo * 100
				).toFixed(1)}%:</span> ${sup.descripcion || ""}</li>`;
			}
		} else {
			// NO acumulable: solo el mayor, pero concateno descripciones
			const maxRecargo = Math.max(...arr.map((s) => s.recargo));
			const maxSups = arr.filter((s) => s.recargo === maxRecargo);
			const descripciones = arr.map((s) => s.descripcion).filter(Boolean);

			let descripcionesTxt = "";
			if (descripciones.length === 2) {
				descripcionesTxt = descripciones.join(" y ");
			} else if (descripciones.length > 2) {
				descripcionesTxt =
					descripciones.slice(0, -1).join(", ") +
					" y " +
					descripciones[descripciones.length - 1];
			} else {
				descripcionesTxt = descripciones[0] || "";
			}

			html += `<li class="form__plan-recargos-item"><span class="form__plan-porcentaje-recargo">Recargo ${(
				maxRecargo * 100
			).toFixed(1)}%:</span> ${descripcionesTxt}</li>`;
		}
	}

	html += "</ul>";

	// 3. Mostrar límite máximo si hay y se ha alcanzado
	const hasGrupoLimit = Object.entries(breakdown.topePorGrupo).some(
		([grupo, tope]) => breakdown.recargosPorGrupo[grupo] === tope
	);
	const hasTotalLimit =
		breakdown.maximoAcumulableTotal &&
		breakdown.recargoTotal * 100 >= breakdown.maximoAcumulableTotal;

	if (hasGrupoLimit) {
		// Por cada grupo donde aplique el tope, lo mostramos
		for (const [grupo, tope] of Object.entries(breakdown.topePorGrupo)) {
			if (breakdown.recargosPorGrupo[grupo] === tope) {
				html += `<p class="form__plan-recargos-precios">Límite máximo grupo ${grupo}: <span class="form__plan-porcentaje-recargo">${(
					tope * 100
				).toFixed(1)}%</span></p>`;
			}
		}
	}
	if (hasTotalLimit) {
		html += `<p class="form__plan-recargos-precios">Límite máximo total: <span class="form__plan-porcentaje-recargo">${breakdown.maximoAcumulableTotal}%</span></p>`;
	}
	html += "</div>";
	return html;
}

// --------- Otras utilidades (sin cambios) --------------
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

// ----------- Modalidades y lógica de filtrado ---------------
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

// ---------- Renderizado de planes (ahora con recargos HTML para admin) -----------
function renderPlans(modalidades, valoresForm) {
	const plansContainer = document.getElementById("formPlans");
	if (!plansContainer) return;

	plansContainer.classList.remove("form__plans--featured");

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

			// ---- SUPLEMENTOS / RECARGOS ----
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
			const precioFinal =
				precioBase !== null ? precioBase * (1 + breakdown.recargoTotal) : null;

			// ---- LOG LIMPIO Y ÚTIL ----
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
					`[${title}] Base: ${precioBase ?? "--"} | Recargos: ${(
						breakdown.recargoTotal * 100
					).toFixed(1)}% | Final: ${
						precioFinal?.toFixed(2) ?? "--"
					}\n  ↳ Suplementos aplicados:`,
					suplementoLog,
					descripcionesSuplementos.length
						? `\n  ↳ Descripciones: ${descripcionesSuplementos.join("; ")}`
						: ""
				);
			}

			const priceValue =
				precioFinal !== null
					? precioFinal.toLocaleString("es-ES", { minimumFractionDigits: 2 })
					: "--";

			// ---- Recargos HTML solo para admin ----
			let recargosHTML = "";
			if (
				typeof window.userRole !== "undefined" &&
				window.userRole === "admin" &&
				precioBase !== null &&
				precioFinal !== null
			) {
				recargosHTML = renderRecargosHTML({
					precioBase,
					precioFinal,
					breakdown,
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
                        <span class="form__plan-price-text">
							<span class="plan-price-value">${priceValue}</span>
							<span class="form__plan-price-euro">€</span>
						</span>
						<span class="plan-price-skeleton"></span>
                    </div>
                    <div class="form__plan-iva">IVA incluido</div>
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

// ----------- FILTRAR MODALIDADES (ÁMBITO GLOBAL) ----------- //
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

	if (
		tipoVehiculoSeleccionado === "camion" &&
		(!valoresForm.mma || !valoresForm.traccion_camion)
	) {
		updateDuracionSelect([]);
		renderPlans([], valoresForm);
		window.limitesDinamicos = {
			cilindrada: { min: 0, max: 9000 },
			potencia: { min: 0, max: 3000 },
		};
		return;
	}

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
//Antes de integrar descuentos
//Hacer copia de seguridad

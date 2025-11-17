// assets/js/modules/form-calculations.js
"use strict";

import {
        eurosString,
        parseNumericFormValue,
        getAntiguedadFromDate,
        IVA_PORCENTAJE,
        debounce,
} from "./form-utils.js";
import {
        fetchOfertas,
        ofertaAplicaAmodalidad,
        refreshOfertasDisplay,
        showOfertasLoading,
        shouldSkipLoaderForSelf,
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
        getSpecialFixedOffers,
        subscribeSpecialFixedOffers,
} from "./form-state.js";
import { setupPlanSelection } from "./plan-selection.js";

const ENABLE_LOGS = false;
function log(...args) {
        if (ENABLE_LOGS) console.log("[form-calculations]", ...args);
}

function normalizeToArray(value) {
        if (Array.isArray(value)) return value;
        if (value === null || typeof value === "undefined") return [];
        return [value];
}

function resolveTermSlug(item) {
        if (typeof item === "string") return item;
        if (item && typeof item === "object") {
                if (typeof item.slug === "string") return item.slug;
                if (typeof item.value === "string") return item.value;
        }
        return "";
}

function resolveTermId(item) {
        if (item && typeof item === "object") {
                if (typeof item.id !== "undefined") return item.id;
                if (typeof item.term_id !== "undefined") return item.term_id;
                if (typeof item.value !== "undefined") return item.value;
        }
        return item;
}

function toLowerSlug(value) {
        return typeof value === "string" ? value.toLowerCase() : "";
}

function getSlugSet(field) {
        const set = new Set();
        normalizeToArray(field)
                .map(resolveTermSlug)
                .map(toLowerSlug)
                .filter(Boolean)
                .forEach((slug) => set.add(slug));
        return set;
}

function toPositiveInt(value) {
        if (value === null || typeof value === "undefined" || value === "") return null;
        const num = Number(value);
        if (!Number.isFinite(num)) return null;
        const intVal = Math.trunc(num);
        return intVal > 0 ? intVal : null;
}

function toFloat(value) {
        if (value === null || typeof value === "undefined" || value === "") return null;
        if (typeof value === "number") {
                return Number.isFinite(value) ? value : null;
        }
        if (typeof value === "string") {
                const parsed = parseNumericFormValue(value);
                return Number.isNaN(parsed) ? null : parsed;
        }
        const num = Number(value);
        return Number.isFinite(num) ? num : null;
}

function getIdSet(field) {
        const set = new Set();
        normalizeToArray(field)
                .map(resolveTermId)
                .map(toPositiveInt)
                .filter((id) => id !== null)
                .forEach((id) => set.add(id));
        return set;
}

function getSpecialFixedConfig(modalidad) {
        const specials = getSpecialFixedOffers();
        if (!Array.isArray(specials) || specials.length === 0) return null;

        const tipoSlugs = getSlugSet(modalidad?.tipo_garantia);
        const tipoIds = getIdSet(modalidad?.tipo_garantia_ids);
        const nivelSlugs = getSlugSet(modalidad?.nivel_garantia);
        const nivelIds = getIdSet(modalidad?.nivel_garantia_ids);

        for (const special of specials) {
                if (!special || typeof special !== "object") continue;

                const tipoSlug = toLowerSlug(special.tipo_garantia_slug);
                const tipoId = toPositiveInt(special.tipo_garantia_id);
                const nivelSlug = toLowerSlug(special.nivel_garantia_slug);
                const nivelId = toPositiveInt(special.nivel_garantia_id);

                const matchesTipo =
                        (tipoSlug && tipoSlugs.has(tipoSlug)) ||
                        (tipoId !== null && tipoIds.has(tipoId));
                if (!matchesTipo) continue;

                const matchesNivel =
                        (nivelSlug && nivelSlugs.has(nivelSlug)) ||
                        (nivelId !== null && nivelIds.has(nivelId));
                if (!matchesNivel) continue;

                const precio = toFloat(special.precio_fijo);
                const duracion = toPositiveInt(special.duracion_meses);
                const duracionLabel =
                        typeof special.duracion_label === "string"
                                ? special.duracion_label
                                : "";

                return {
                        ...special,
                        tipo_garantia_slug: tipoSlug,
                        tipo_garantia_id: tipoId,
                        nivel_garantia_slug: nivelSlug,
                        nivel_garantia_id: nivelId,
                        precio,
                        duracion,
                        duracion_label: duracionLabel,
                };
        }

        return null;
}

function buildSpecialRestrictionMap() {
        const specials = getSpecialFixedOffers();
        if (!Array.isArray(specials) || specials.length === 0) return null;

        const byTipoSlug = new Map();
        const byTipoId = new Map();

        specials.forEach((special) => {
                if (!special || !special.excluir_resto_niveles) return;

                const tipoSlug = toLowerSlug(special.tipo_garantia_slug);
                const tipoId = toPositiveInt(special.tipo_garantia_id);
                const nivelSlug = toLowerSlug(special.nivel_garantia_slug);
                const nivelId = toPositiveInt(special.nivel_garantia_id);

                if (tipoSlug) {
                        if (!byTipoSlug.has(tipoSlug)) {
                                byTipoSlug.set(tipoSlug, { slugs: new Set(), ids: new Set() });
                        }
                        const entry = byTipoSlug.get(tipoSlug);
                        if (nivelSlug) entry.slugs.add(nivelSlug);
                        if (nivelId !== null) entry.ids.add(nivelId);
                }

                if (tipoId !== null) {
                        if (!byTipoId.has(tipoId)) {
                                byTipoId.set(tipoId, { slugs: new Set(), ids: new Set() });
                        }
                        const entry = byTipoId.get(tipoId);
                        if (nivelSlug) entry.slugs.add(nivelSlug);
                        if (nivelId !== null) entry.ids.add(nivelId);
                }
        });

        if (!byTipoSlug.size && !byTipoId.size) return null;
        return { byTipoSlug, byTipoId };
}

function tieneNivelPermitido(allowed, nivelSlugs, nivelIds) {
        if (!allowed) return true;
        if (allowed.slugs.size === 0 && allowed.ids.size === 0) {
            return false;
        }
        for (const slug of allowed.slugs) {
                if (nivelSlugs.has(slug)) return true;
        }
        for (const id of allowed.ids) {
                if (nivelIds.has(id)) return true;
        }
        return false;
}

function modalidadRespetaRestriccionesEspeciales(modalidad, restrictions) {
        if (!restrictions) return true;
        const tipoSlugs = getSlugSet(modalidad?.tipo_garantia);
        const tipoIds = getIdSet(modalidad?.tipo_garantia_ids);
        const nivelSlugs = getSlugSet(modalidad?.nivel_garantia);
        const nivelIds = getIdSet(modalidad?.nivel_garantia_ids);

        for (const slug of tipoSlugs) {
                const allowed = restrictions.byTipoSlug.get(slug);
                if (allowed && !tieneNivelPermitido(allowed, nivelSlugs, nivelIds)) {
                        return false;
                }
        }

        for (const id of tipoIds) {
                const allowed = restrictions.byTipoId.get(id);
                if (allowed && !tieneNivelPermitido(allowed, nivelSlugs, nivelIds)) {
                        return false;
                }
        }

        return true;
}

function createEmptyBreakdown() {
        return {
                recargoTotal: 0,
                maximoAcumulableTotal: null,
                detalles: [],
                limiteTotalAlcanzado: false,
                recargosPorGrupo: {},
                topePorGrupo: {},
        };
}

function renderRecargosEspecial({ precioBase, precioIVA, duracionLabel }) {
        let html = `<div class="form__plan-recargos form__plan-recargos--especial">`;
        html += `<p class="form__plan-recargos-precios"><b>Precio base:</b> ${eurosString(
                precioBase
        )}€ | <b>Precio final + IVA:</b> ${eurosString(precioIVA)}€</p>`;
        html += `<p class="form__plan-recargos-note">Tarifa especial con precio fijo. No se aplican suplementos ni descuentos.</p>`;
        if (duracionLabel) {
                html += `<p class="form__plan-recargos-note">Duración disponible: ${escapeHtml(
                        duracionLabel
                )}</p>`;
        }
        html += `</div>`;
        return html;
}

const MODALIDAD_CACHE_TTL = 5 * 60 * 1000; // 5 minutos de cache
let filtroToken = 0;

const CHANNEL_NORMALIZATION = {
        profesional: "profesional",
        go_profesional: "profesional",
        particular: "particular",
        go_particular: "particular",
        individual: "particular",
        go_individual: "particular",
        gestoria: "gestoria",
        go_gestoria: "gestoria",
};

let vehicleTypesByChannelCache = new Map();

function normalizeChannel(value) {
        if (!value) return "";
        const key = String(value).toLowerCase();
        return CHANNEL_NORMALIZATION[key] || "";
}

function getDefaultChannelForRole() {
        const role = getEffectiveUserRole();
        switch (role) {
                case "go_particular":
                case "particular":
                case "go_individual":
                case "individual":
                        return "particular";
                case "go_gestoria":
                case "gestoria":
                        return "gestoria";
                default:
                        return "profesional";
        }
}

function getModalidadChannels(modalidad) {
        const canalField =
                modalidad?.acf?.condiciones_generales_y_tarifas?.condiciones_modalidad
                        ?.canal_venta;

        let canales = [];
        if (Array.isArray(canalField)) {
                canales = canalField;
        } else if (typeof canalField === "string" && canalField) {
                canales = [canalField];
        } else if (canalField && typeof canalField === "object") {
                if (Array.isArray(canalField.value)) {
                        canales = canalField.value;
                } else if (canalField.value) {
                        canales = [canalField.value];
                }
        }

        const normalizados = canales
                .map((item) => {
                        if (typeof item === "object" && item !== null) {
                                if (typeof item.value !== "undefined") {
                                        return item.value;
                                }
                                if (typeof item.label !== "undefined") {
                                        return item.label;
                                }
                        }
                        return item;
                })
                .map((item) => normalizeChannel(item))
                .filter(Boolean);

        if (!normalizados.length) {
                return ["profesional"];
        }
        return Array.from(new Set(normalizados));
}

function buildVehicleTypesCache(modalidades) {
        const map = new Map();
        modalidades.forEach((modalidad) => {
                const tipos = Array.isArray(modalidad.tipo_vehiculo)
                        ? modalidad.tipo_vehiculo
                        : [];
                const canales = getModalidadChannels(modalidad);
                canales.forEach((canal) => {
                        if (!map.has(canal)) {
                                map.set(canal, new Set());
                        }
                        const current = map.get(canal);
                        tipos.forEach((tipo) => {
                                if (typeof tipo === "string" && tipo) {
                                        current.add(tipo);
                                }
                        });
                });
        });
        return map;
}

function getVehicleTypesForChannel(canal) {
        if (!vehicleTypesByChannelCache || vehicleTypesByChannelCache.size === 0) {
                return new Set();
        }
        if (!canal) return new Set();
        return new Set(vehicleTypesByChannelCache.get(canal) || []);
}

function getActiveChannelSlug(canalesDisponibles = []) {
	const select = document.getElementById("canal-venta");
	const canalesSet = new Set(canalesDisponibles.map((canal) => normalizeChannel(canal)));
	const selectValue = select ? normalizeChannel(select.value) : "";

	if (selectValue) {
		return selectValue;
	}

	const fallback = getDefaultChannelForRole();
	if (!canalesSet.size) {
		return fallback;
	}
	if (canalesSet.has(fallback)) {
		return fallback;
	}
	const first = canalesSet.values().next();
	return !first.done ? first.value : fallback;
}

function normalizeSelectValue(field) {
        if (!field) return "";
        if (typeof field === "string") return field;
        if (Array.isArray(field)) {
                if (!field.length) return "";
                const first = field[0];
                if (typeof first === "object" && first !== null && first.value) {
                        return first.value;
                }
                return String(first || "");
        }
        if (typeof field === "object") {
                if (typeof field.value !== "undefined") {
                        return String(field.value || "");
                }
                if (typeof field.label !== "undefined") {
                        return String(field.label || "");
                }
        }
        return "";
}

function evaluarFiltrosParticulares(tarifa, valoresForm) {
        const tipoTarifa = normalizeSelectValue(tarifa.tipo_de_vehiculo);
        const traccionTarifa = normalizeSelectValue(tarifa.traccion);
        const desdeAnosRaw = tarifa.desde_anos;
        const hastaAnosRaw = tarifa.hasta_anos;

        const antiguedad =
                valoresForm.antiguedad !== null && valoresForm.antiguedad !== undefined
                        ? Number(valoresForm.antiguedad)
                        : null;

        const tieneRangoAntiguedad =
                (desdeAnosRaw !== undefined && desdeAnosRaw !== null && desdeAnosRaw !== "") ||
                (hastaAnosRaw !== undefined && hastaAnosRaw !== null && hastaAnosRaw !== "");

        if (tieneRangoAntiguedad && (antiguedad === null || Number.isNaN(antiguedad))) {
                return { coincide: false, especificidad: 0 };
        }

        if (antiguedad !== null && !Number.isNaN(antiguedad)) {
                if (desdeAnosRaw !== undefined && desdeAnosRaw !== null && desdeAnosRaw !== "") {
                        const desdeNum = Number(desdeAnosRaw);
                        if (!Number.isNaN(desdeNum) && antiguedad < desdeNum) {
                                return { coincide: false, especificidad: 0 };
                        }
                }
                if (hastaAnosRaw !== undefined && hastaAnosRaw !== null && hastaAnosRaw !== "") {
                        const hastaNum = Number(hastaAnosRaw);
                        if (!Number.isNaN(hastaNum) && antiguedad > hastaNum) {
                                return { coincide: false, especificidad: 0 };
                        }
                }
        }

        const tipoSeleccionado = String(valoresForm.tipo_vehiculo || "").toLowerCase();
        const traccionSeleccionada = String(valoresForm.traccion || "").toLowerCase();

        const tieneTipo = tipoTarifa && tipoTarifa !== "ninguno";
        const tieneTraccion = traccionTarifa && traccionTarifa !== "ninguna";

        const tipoCoincide = tieneTipo
                ? tipoSeleccionado === String(tipoTarifa).toLowerCase()
                : true;
        const traccionCoincide = tieneTraccion
                ? traccionSeleccionada === String(traccionTarifa).toLowerCase()
                : true;

        let coincide;
        if (tieneTipo && tieneTraccion) {
                coincide = tipoCoincide || traccionCoincide;
        } else {
                coincide = tipoCoincide && traccionCoincide;
        }

        let especificidad = 0;
        if (coincide) {
                if (tieneTipo || tieneTraccion) {
                        especificidad += 1; // indica que es una tarifa con filtros especiales
                }
                if (tieneTipo && tipoCoincide) {
                        especificidad += 1;
                }
                if (tieneTraccion && traccionCoincide) {
                        especificidad += 1;
                }
        }

        return { coincide, especificidad };
}

function syncCanalVentaSelect(canalesDisponibles, canalActivo) {
	const select = document.getElementById("canal-venta");
	if (!select) return;

	const opciones = Array.from(select.options).filter((opt) => opt.value !== "");
	const disponiblesSet = new Set(canalesDisponibles.map((canal) => normalizeChannel(canal)));
	const valorActual = normalizeChannel(select.value);

	opciones.forEach((opt) => {
		const canalOpt = normalizeChannel(opt.value);
		const habilitar =
			disponiblesSet.size === 0 || disponiblesSet.has(canalOpt) || canalOpt === valorActual;
		opt.disabled = !habilitar;
		opt.hidden = !habilitar;
	});

	const container = select.closest(".form__input-container");
	if (!valorActual) {
		let targetCanal = canalActivo && canalActivo !== valorActual ? canalActivo : null;
		if (!targetCanal) {
			targetCanal = getActiveChannelSlug(Array.from(disponiblesSet));
		}
		const opcionObjetivo = opciones.find(
			(opt) => normalizeChannel(opt.value) === targetCanal
		);
		if (opcionObjetivo) {
			opcionObjetivo.selected = true;
			select.value = opcionObjetivo.value;
			select.dispatchEvent(new Event("change", { bubbles: true }));
			if (container) container.classList.add("has-value");
		} else if (container) {
			container.classList.remove("has-value");
		}
	} else if (container) {
		container.classList.add("has-value");
	}
}

function syncTipoVehiculoOptions(canalActivo) {
        const select = document.getElementById("tipo_vehiculo");
        if (!select) return;

        const opciones = Array.from(select.options).filter((opt) => opt.value !== "");
        if (!opciones.length) return;

        const restringir = canalActivo === "particular" || getDefaultChannelForRole() === "particular";
        const permitidos = restringir ? getVehicleTypesForChannel("particular") : null;

        let primerPermitido = null;
        opciones.forEach((opt) => {
                if (!restringir) {
                        opt.disabled = false;
                        opt.hidden = false;
                        return;
                }
                const habilitar = permitidos.has(opt.value);
                opt.disabled = !habilitar;
                opt.hidden = !habilitar;
                if (habilitar && primerPermitido === null) {
                        primerPermitido = opt.value;
                }
        });

        if (restringir) {
                if (!permitidos.has(select.value)) {
                        const nuevoValor = primerPermitido || "";
                        if (select.value !== nuevoValor) {
                                select.value = nuevoValor;
                                select.dispatchEvent(new Event("change", { bubbles: true }));
                        }
                }
        }

        const container = select.closest(".form__input-container");
        if (container) {
                if (select.value) container.classList.add("has-value");
                else container.classList.remove("has-value");
        }
}

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

function escapeHtml(value) {
        if (value === null || value === undefined) return "";
        return String(value)
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#39;");
}

function isTruthy(value) {
        return value === true || value === 1 || value === "1" || value === "true";
}

function getOptionValue(option) {
        if (typeof option === "string") return option;
        if (typeof option === "number") return String(option);
        if (option && typeof option === "object") {
                if (typeof option.value === "string" || typeof option.value === "number") {
                        return String(option.value);
                }
        }
        return "";
}

function getOptionLabel(option) {
        if (option && typeof option === "object" && option.label) {
                return String(option.label);
        }
        return "";
}

function formatNumber(value) {
        const num = typeof value === "number" ? value : parseFloat(value);
        if (!Number.isFinite(num)) return "";
        return new Intl.NumberFormat("es-ES", {
                maximumFractionDigits: 0,
        }).format(num);
}

function formatEuroValue(value) {
        if (value === null || value === undefined || value === "") return "";
        const num = typeof value === "number" ? value : parseFloat(value);
        if (!Number.isFinite(num)) return "";
        const formatted = eurosString(num);
        return `${formatted.replace(/,00$/, "")}€`;
}

function parseConditionNumber(value) {
        if (value === null || value === undefined || value === "") return null;
        const num = Number(value);
        return Number.isFinite(num) ? num : null;
}

function renderVentajasList(descInfo) {
        const listado = Array.isArray(descInfo?.listado_ventajas)
                ? descInfo.listado_ventajas
                : [];
        if (!listado.length) return "";

        const items = listado
                .map((item) => {
                        const texto = escapeHtml(item?.ventaja || "");
                        if (!texto) return "";
                        const iconType = getOptionValue(item?.icono);
                        const iconKey = iconType === "negativo" ? "disadvantage" : "advantage";
                        const iconHtml = getIcon(iconKey) || (iconType === "negativo" ? "×" : "✓");
                        const iconClass =
                                iconType === "negativo"
                                        ? "form__plan-description-icon form__plan-description-icon--negative"
                                        : "form__plan-description-icon form__plan-description-icon--positive";
                        return `
                <li class="form__plan-description-item">
                        <span class="${iconClass}" aria-hidden="true">${iconHtml}</span>
                        <span class="form__plan-description-text">${texto}</span>
                </li>
            `;
                })
                .filter(Boolean)
                .join("");

        if (!items) return "";

        return `
        <ul class="form__plan-description-list" role="list">
                ${items}
        </ul>
    `;
}

function getLimitItemLabel(limite) {
        const tipo = getOptionValue(limite?.limite_por);
        if (tipo === "averia") return "Límite por avería";
        if (tipo === "contrato") return "Límite por contrato";
        const personalizado = typeof limite?.nombre_del_limite === "string" ? limite.nombre_del_limite.trim() : "";
        return personalizado || "";
}

function getSpecialLimitType(limite) {
        return getOptionValue(limite?.limite_por_condicion_especial);
}

function getSpecialLimitLabel(limite) {
        const tipo = getSpecialLimitType(limite);
        if (tipo === "averia") return "Límite por avería";
        if (tipo === "contrato") return "Límite por contrato";
        const personalizado = typeof limite?.nombre_del_limite_condicion_especial === "string"
                ? limite.nombre_del_limite_condicion_especial.trim()
                : "";
        return personalizado || "";
}

function getSpecialLimitEstilo(limite) {
        const estilo =
                getOptionValue(limite?.estilo_limite_condicion_especial) ||
                getOptionValue(limite?.estilo_limite);
        return estilo;
}

function getLimitItemClassNames(limite) {
        const classes = ["form__plan-conditions-item"];
        const estilo = getOptionValue(limite?.estilo_limite);
        if (estilo && estilo !== "normal") {
                classes.push(`form__plan-conditions-item--${estilo}`);
                if (estilo === "negrita") {
                        classes.push("form__plan-conditions-item--accent");
                }
        }
        return classes.join(" ");
}

function renderStandardLimits(descInfo, subtitleHtml = null) {
        const limites = Array.isArray(descInfo?.limites_y_valores) ? descInfo.limites_y_valores : [];
        const subtitle = typeof subtitleHtml === "string" ? subtitleHtml : getDescriptionSubtitleHtml(descInfo);

        if (!limites.length && !subtitle) return "";

        const htmlItems = limites
                .map((limite) => {
                        const estilo = getOptionValue(limite?.estilo_limite);
                        const label = getLimitItemLabel(limite);
                        const aclaracion = typeof limite?.aclaracion === "string" ? limite.aclaracion.trim() : "";
                        const rawValue =
                                limite?.valor_del_limite === null || limite?.valor_del_limite === undefined
                                        ? null
                                        : Number(limite.valor_del_limite);
                        const hasValor = Number.isFinite(rawValue) && rawValue > 0;
                        const valorTexto = hasValor ? formatEuroValue(rawValue) : "";
                        const useAclaracionAsValue = !hasValor && !!aclaracion;

                        if (!label && !valorTexto && !aclaracion) {
                                return "";
                        }

                        const classes = getLimitItemClassNames(limite);
                        let labelHtml = "";
                        if (label) {
                                labelHtml = escapeHtml(label);
                                if (hasValor || useAclaracionAsValue || aclaracion) {
                                        labelHtml += ":";
                                        if (hasValor || useAclaracionAsValue) {
                                                labelHtml += " ";
                                        }
                                }
                                if (aclaracion && !useAclaracionAsValue) {
                                        labelHtml += ` ${escapeHtml(aclaracion)}`;
                                }
                        } else if (aclaracion) {
                                if (!useAclaracionAsValue) {
                                        labelHtml = escapeHtml(aclaracion);
                                }
                        }

                        const labelSpan = labelHtml
                                ? `<span class="form__plan-conditions-item-label">${labelHtml}</span>`
                                : "";
                        const isAccent = estilo === "negrita";
                        const valueClass = isAccent
                                ? "form__plan-conditions-item-value form__plan-conditions-item-value--accent"
                                : "form__plan-conditions-item-value";
                        const valueContent = hasValor ? valorTexto : useAclaracionAsValue ? aclaracion : "";
                        const valueSpan = valueContent
                                ? `<span class="${valueClass}">${escapeHtml(valueContent)}</span>`
                                : "";

                        return `
                <li class="${classes}">${labelSpan}${valueSpan}</li>
        `;
                })
                .filter(Boolean)
                .join("");

        const listHtml = htmlItems
                ? `
                <ul class="form__plan-conditions-list" role="list">
                        ${htmlItems}
                </ul>
        `
                : "";

        if (!listHtml) {
                return subtitle
                        ? `
        <div class="form__plan-conditions">
                ${subtitle}
        </div>
    `
                        : "";
        }

        return `
        <div class="form__plan-conditions">
                ${subtitle}
                ${listHtml}
        </div>
    `;
}

function getDescriptionSubtitleHtml(descInfo) {
        const text = descInfo?.titulo_descripcion;
        if (!text) return "";
        return `<div class="form__plan-conditions-subtitle">${escapeHtml(text)}</div>`;
}

function buildRangeTextFromNumbers(desde, hasta, unidad = "") {
        const tieneDesde = Number.isFinite(desde);
        const tieneHasta = Number.isFinite(hasta);
        const desdeTexto = tieneDesde ? formatNumber(desde) : "";
        const hastaTexto = tieneHasta ? formatNumber(hasta) : "";

        if (tieneDesde && tieneHasta) {
                if (desde <= 0) {
                        return hastaTexto ? `hasta ${hastaTexto}${unidad}` : "";
                }
                if (desdeTexto && hastaTexto) {
                        return `de ${desdeTexto} hasta ${hastaTexto}${unidad}`;
                }
        }
        if (tieneHasta && hastaTexto) {
                return `hasta ${hastaTexto}${unidad}`;
        }
        if (tieneDesde && desdeTexto) {
                return `desde ${desdeTexto}${unidad}`;
        }
        return "";
}

function normalizeRange(range) {
        if (!range) return null;
        const desde = parseConditionNumber(range.desde);
        const hasta = parseConditionNumber(range.hasta);
        const tieneDesde = Number.isFinite(desde);
        const tieneHasta = Number.isFinite(hasta);
        if (!tieneDesde && !tieneHasta) return null;
        return { desde: tieneDesde ? desde : null, hasta: tieneHasta ? hasta : null };
}

function extractRangeFromEntry(entry) {
        const desde = parseConditionNumber(entry?.desde_condicion);
        const hasta = parseConditionNumber(entry?.hasta_condicion);
        const tieneDesde = Number.isFinite(desde);
        const tieneHasta = Number.isFinite(hasta);
        if (!tieneDesde && !tieneHasta) return null;
        return { desde: tieneDesde ? desde : null, hasta: tieneHasta ? hasta : null };
}

function getConditionHeadline(descInfo, condicion) {
        const tipo = getOptionValue(descInfo?.tipo_de_condicion);
        const tituloCondicion = typeof descInfo?.titulo_condicion === "string"
                ? descInfo.titulo_condicion.trim()
                : "";
        const labelBase = tituloCondicion || getOptionLabel(descInfo?.tipo_de_condicion) || "";

        if (tipo === "antiguedad_kilometros") {
                const partes = [];
                if (labelBase) partes.push(labelBase);

                const rangePieces = [];
                const kmRange = normalizeRange(condicion?.kilometrosRange || condicion?.kilometros);
                if (kmRange) {
                        const texto = buildRangeTextFromNumbers(kmRange.desde, kmRange.hasta, " km");
                        if (texto) rangePieces.push(texto);
                }
                const antiguedadRange = normalizeRange(
                        condicion?.antiguedadRange || condicion?.antiguedad
                );
                if (antiguedadRange) {
                        const texto = buildRangeTextFromNumbers(
                                antiguedadRange.desde,
                                antiguedadRange.hasta,
                                " años"
                        );
                        if (texto) rangePieces.push(texto);
                }

                if (!rangePieces.length && condicion) {
                        const fallbackRange = extractRangeFromEntry(condicion);
                        if (fallbackRange) {
                                const texto = buildRangeTextFromNumbers(
                                        fallbackRange.desde,
                                        fallbackRange.hasta,
                                        ""
                                );
                                if (texto) rangePieces.push(texto);
                        }
                }

                if (rangePieces.length) {
                        const combined = rangePieces.filter(Boolean).join(" y ");
                        if (combined) {
                                if (partes.length) {
                                        const lastIndex = partes.length - 1;
                                        partes[lastIndex] = `${partes[lastIndex]} ${combined}`;
                                } else {
                                        partes.push(combined);
                                }
                        }
                }

                return partes.map((fragment) => escapeHtml(fragment)).join(" ").trim();
        }

        if (tipo === "ninguno") {
                return labelBase ? escapeHtml(labelBase) : "";
        }

        const rangoRef = condicion?.cilindradaRange || extractRangeFromEntry(condicion) || null;
        const unidad = tipo === "cilindrada_cc" ? " cc" : "";
        const rangoTexto = rangoRef
                ? buildRangeTextFromNumbers(rangoRef.desde, rangoRef.hasta, unidad)
                : "";

        const partes = [];
        if (labelBase) partes.push(labelBase);
        if (rangoTexto) partes.push(rangoTexto);

        return partes.map((fragment) => escapeHtml(fragment)).join(" ").trim();
}

function renderSpecialLimits(descInfo, valoresForm, subtitleHtml = "") {
        const condiciones = Array.isArray(descInfo?.condiciones_limites)
                ? descInfo.condiciones_limites.slice()
                : [];
        if (!condiciones.length) return "";

        const tipoCondicion = getOptionValue(descInfo?.tipo_de_condicion) || "ninguno";
        const normalizeFormNumber = (raw) => {
                        if (raw === null || raw === undefined) return null;
                        if (raw === 0) return null;
                        if (typeof raw === "string" && raw.trim() === "") return null;
                        const parsed = parseNumericFormValue(raw);
                        return Number.isFinite(parsed) ? parsed : null;
                };
        const cilindradaValor = normalizeFormNumber(valoresForm?.cilindrada);
        const kilometrosValor = normalizeFormNumber(valoresForm?.kilometros);
        const antiguedadValor =
                typeof valoresForm?.antiguedad === "number" && !Number.isNaN(valoresForm.antiguedad)
                        ? valoresForm.antiguedad
                        : null;

        const grupos = [];
        condiciones.forEach((cond) => {
                const label = getSpecialLimitLabel(cond);
                const tipo = getSpecialLimitType(cond) || "";
                if (!label && !tipo) return;
                const key = `${tipo}|${label}`;
                let grupo = grupos.find((g) => g.key === key);
                if (!grupo) {
                        grupo = { key, label, tipo, entries: [] };
                        grupos.push(grupo);
                }
                grupo.entries.push(cond);
        });

        if (!grupos.length) return "";

        const sortEntries = (entries) =>
                entries
                        .map((entry) => ({ entry, range: extractRangeFromEntry(entry) }))
                        .sort((a, b) => {
                                const getSortValue = (item) => {
                                        if (item.range && Number.isFinite(item.range.desde)) {
                                                return item.range.desde;
                                        }
                                        if (item.range && Number.isFinite(item.range.hasta)) {
                                                return item.range.hasta;
                                        }
                                        return Infinity;
                                };
                                return getSortValue(a) - getSortValue(b);
                        });

        const matchesValue = (value, range) => {
                if (!range) return true;
                if (range.desde !== null && range.desde !== undefined && Number.isFinite(range.desde)) {
                        if (value < range.desde) return false;
                }
                if (range.hasta !== null && range.hasta !== undefined && Number.isFinite(range.hasta)) {
                        if (value > range.hasta) return false;
                }
                return true;
        };

        const findBestMatch = (entries, value) => {
                if (!entries.length) return null;
                const ordered = sortEntries(entries);
                if (value === null || value === undefined || Number.isNaN(value)) {
                        return null;
                }
                const exact = ordered.find((item) => matchesValue(value, item.range));
                if (exact) return exact;
                let fallback = ordered[0];
                for (const item of ordered) {
                        const desde = item.range?.desde;
                        if (Number.isFinite(desde) && value >= desde) {
                                fallback = item;
                        }
                }
                return fallback || ordered[ordered.length - 1];
        };

        const getSafestItem = (entries) => {
                const ordered = sortEntries(entries);
                let best = null;
                let bestValor = null;
                for (const item of ordered) {
                        const valorParsed = parseConditionNumber(
                                item.entry?.valor_limite_condicion_especial
                        );
                        const valorNum =
                                typeof valorParsed === "number" && Number.isFinite(valorParsed) && valorParsed > 0
                                        ? valorParsed
                                        : null;
                        if (valorNum !== null) {
                                if (best === null || valorNum < bestValor) {
                                        best = item;
                                        bestValor = valorNum;
                                }
                        }
                }
                return best || ordered[0] || null;
        };

        const createCandidate = (item, dimension) => {
                if (!item) return null;
                const valorParsed = parseConditionNumber(item.entry?.valor_limite_condicion_especial);
                const valorNum =
                        typeof valorParsed === "number" && Number.isFinite(valorParsed) && valorParsed > 0
                                ? valorParsed
                                : null;
                const aclaracion = typeof item.entry?.aclaracion_condicion_especial === "string"
                        ? item.entry.aclaracion_condicion_especial.trim()
                        : "";
                return {
                        entry: item.entry,
                        range: item.range || extractRangeFromEntry(item.entry),
                        dimension,
                        valorNum,
                        aclaracion,
                        estilo: getSpecialLimitEstilo(item.entry) || "",
                        tipo: getSpecialLimitType(item.entry) || "",
                };
        };

        const headlineRanges = { cilindradaRange: null, kilometrosRange: null, antiguedadRange: null };
        let headlineEntry = null;
        const itemsHtml = grupos
                .map((grupo) => {
                        const entries = grupo.entries || [];
                        const candidates = [];

                        if (!entries.length) return "";

                        if (tipoCondicion === "cilindrada_cc") {
                                const match = findBestMatch(entries, cilindradaValor);
                                const fallback = match || (entries.length
                                        ? { entry: entries[0], range: extractRangeFromEntry(entries[0]) }
                                        : null);
                                const candidate = createCandidate(fallback, "cilindrada");
                                if (candidate) {
                                        candidates.push(candidate);
                                        if (!headlineRanges.cilindradaRange && candidate.range) {
                                                headlineRanges.cilindradaRange = candidate.range;
                                        }
                                        if (!headlineEntry) headlineEntry = candidate.entry;
                                }
                        } else if (tipoCondicion === "antiguedad_kilometros") {
                                const kmEntries = entries.filter(
                                        (entry) => getOptionValue(entry?.km_o_antiguedad) === "kilometros"
                                );
                                const antigEntries = entries.filter(
                                        (entry) => getOptionValue(entry?.km_o_antiguedad) === "antiguedad"
                                );
                                const neutralEntries = entries.filter((entry) => {
                                        const tipoKm = getOptionValue(entry?.km_o_antiguedad);
                                        return !tipoKm || tipoKm === "ninguna";
                                });

                                const kmMatchRaw = kmEntries.length
                                        ? findBestMatch(kmEntries, kilometrosValor)
                                        : null;
                                const kmItem = kmMatchRaw || (kilometrosValor == null ? getSafestItem(kmEntries) : null);
                                if (kmItem) {
                                        const candidate = createCandidate(kmItem, "kilometros");
                                        if (candidate) {
                                                candidates.push(candidate);
                                                if (!headlineRanges.kilometrosRange && candidate.range) {
                                                        headlineRanges.kilometrosRange = candidate.range;
                                                }
                                                if (!headlineEntry) headlineEntry = candidate.entry;
                                        }
                                }

                                const antigMatchRaw = antigEntries.length
                                        ? findBestMatch(antigEntries, antiguedadValor)
                                        : null;
                                const antigItem = antigMatchRaw || (antiguedadValor == null
                                        ? getSafestItem(antigEntries)
                                        : null);
                                if (antigItem) {
                                        const candidate = createCandidate(antigItem, "antiguedad");
                                        if (candidate) {
                                                candidates.push(candidate);
                                                if (!headlineRanges.antiguedadRange && candidate.range) {
                                                        headlineRanges.antiguedadRange = candidate.range;
                                                }
                                                if (!headlineEntry) headlineEntry = candidate.entry;
                                        }
                                }

                                if (!candidates.length && neutralEntries.length) {
                                        const neutralMatch = sortEntries(neutralEntries)[0] || null;
                                        const candidate = createCandidate(neutralMatch, "ninguna");
                                        if (candidate) {
                                                candidates.push(candidate);
                                                if (!headlineEntry) headlineEntry = candidate.entry;
                                        }
                                }
                        } else {
                                const safest = getSafestItem(entries);
                                const candidate = createCandidate(safest, "general");
                                if (candidate) {
                                        candidates.push(candidate);
                                        if (!headlineEntry) headlineEntry = candidate.entry;
                                }
                        }

                        if (!candidates.length) return "";

                        const numericCandidates = candidates.filter(
                                (cand) => typeof cand.valorNum === "number" && Number.isFinite(cand.valorNum)
                        );
                        let selected = null;
                        if (numericCandidates.length) {
                                selected = numericCandidates.reduce((best, cand) => {
                                        if (!best) return cand;
                                        return cand.valorNum < best.valorNum ? cand : best;
                                }, null);
                        }
                        if (!selected) {
                                selected = candidates.find((cand) => cand.aclaracion) || candidates[0];
                        }
                        if (!selected) return "";

                        const estilo = selected.estilo;
                        const accent = estilo === "negrita";
                        const labelBase = grupo.label || getSpecialLimitLabel(selected.entry) || "";
                        const hasValor = typeof selected.valorNum === "number" && Number.isFinite(selected.valorNum);
                        const valorTexto = hasValor ? formatEuroValue(selected.valorNum) : "";
                        const aclaracion = selected.aclaracion;
                        const useAclaracionAsValue = !hasValor && !!aclaracion;

                        if (!labelBase && !valorTexto && !aclaracion) {
                                return "";
                        }

                        const labelParts = [];
                        if (labelBase) {
                                let labelHtml = escapeHtml(labelBase);
                                if (hasValor || useAclaracionAsValue || aclaracion) {
                                        labelHtml += ":";
                                        if (hasValor || useAclaracionAsValue) {
                                                labelHtml += " ";
                                        }
                                }
                                if (aclaracion && !useAclaracionAsValue) {
                                        labelHtml += ` ${escapeHtml(aclaracion)}`;
                                }
                                labelParts.push(`<span class="form__plan-conditions-item-label">${labelHtml}</span>`);
                        } else if (aclaracion && !useAclaracionAsValue) {
                                labelParts.push(
                                        `<span class="form__plan-conditions-item-label">${escapeHtml(aclaracion)}</span>`
                                );
                        }

                        const valueContent = hasValor ? valorTexto : useAclaracionAsValue ? aclaracion : "";
                        const valueClass = accent
                                ? "form__plan-conditions-item-value form__plan-conditions-item-value--accent"
                                : "form__plan-conditions-item-value";
                        const valueSpan = valueContent
                                ? `<span class="${valueClass}">${escapeHtml(valueContent)}</span>`
                                : "";

                        const classes = getLimitItemClassNames({ estilo_limite: estilo });

                        return `
                <li class="${classes}">${labelParts.join("")}${valueSpan}</li>
        `;
                })
                .filter(Boolean)
                .join("");

        const headlineData = (() => {
                if (tipoCondicion === "antiguedad_kilometros") {
                        return {
                                kilometrosRange: headlineRanges.kilometrosRange,
                                antiguedadRange: headlineRanges.antiguedadRange,
                        };
                }
                if (tipoCondicion === "cilindrada_cc") {
                        if (headlineRanges.cilindradaRange) {
                                return { cilindradaRange: headlineRanges.cilindradaRange };
                        }
                        return headlineEntry || null;
                }
                return headlineEntry || null;
        })();

        const headline = getConditionHeadline(descInfo, headlineData || headlineEntry || {});

        if (!itemsHtml && !headline && !subtitleHtml) return "";

        const listHtml = itemsHtml
                ? `
                <ul class="form__plan-conditions-list" role="list">
                        ${itemsHtml}
                </ul>
        `
                : "";

        return `
        <div class="form__plan-conditions">
                ${headline ? `<div class="form__plan-conditions-title">${headline}</div>` : ""}
                ${subtitleHtml}
                ${listHtml}
        </div>
    `;
}

function renderPlanDescription(descInfo, valoresForm) {
        const tipo = getOptionValue(descInfo?.tipo_descripcion) || "descripcion";
        const subtitleHtml = getDescriptionSubtitleHtml(descInfo);

        if (tipo === "listado_ventajas") {
                const listadoHtml = renderVentajasList(descInfo);
                if (!listadoHtml) return subtitleHtml;
                return `${subtitleHtml}${listadoHtml}`;
        }

        if (tipo === "limites") {
                if (isTruthy(descInfo?.condiciones_especiales)) {
                        const especiales = renderSpecialLimits(descInfo, valoresForm, subtitleHtml);
                        if (especiales) return especiales;
                }
                return renderStandardLimits(descInfo, subtitleHtml);
        }

        const descripcion = descInfo?.descripcion_garantia || "";
        if (!descripcion && !subtitleHtml) return "";
        return `${subtitleHtml}${descripcion}`;
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

function applyDesgloseVisibility() {
        const check = document.getElementById("check-desglose");
        const show = check && check.checked;
        document.querySelectorAll(".form__plan-recargos").forEach((el) => {
                el.style.display = show ? "block" : "none";
        });
}


// --------- Cálculo de recargos (suplementos) ---------
function calcularRecargos(modalidad, valoresForm) {
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.precio !== null) {
                return createEmptyBreakdown();
        }
        const suplementos = modalidad.acf?.suplementos || [];
        const maximosGrupo = modalidad.acf?.maximo_acumulable_por_grupo || [];
        const maximoAcumulableTotal =
                Number(modalidad.acf?.maximo_acumulable_total) || null;

	const topePorGrupo = {};
	maximosGrupo.forEach((g) => {
		if (g.selec_grupo && g.valor_max_grupo)
			topePorGrupo[g.selec_grupo] = Number(g.valor_max_grupo) / 100;
	});

        const evaluaciones = [];

        suplementos.forEach((sup, index) => {
                const condicion = sup.condicion;
                const tipo = tipoCondicion[condicion];
                const comparador = comparadores[tipo]?.[sup.comparador];
                if (!comparador) return;

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
                const condicionesExactas =
                        sup.condiciones_exactas === true ||
                        sup.condiciones_exactas === "true" ||
                        sup.condiciones_exactas === 1 ||
                        sup.condiciones_exactas === "1";

                evaluaciones.push({
                        condicion: sup.condicion,
                        valorCondicion: inputValor,
                        comparador: sup.comparador,
                        valorComparar: sup.valor,
                        recargo: Number(sup.recargo) / 100,
                        grupo: sup.grupo_acumulabilidad,
                        acumulable: !!sup.acumulable,
                        descripcion: sup.descripcion,
                        condicionesExactas,
                        matches,
                        index,
                });
        });

        const evaluacionesPorGrupo = {};
        for (const evalSup of evaluaciones) {
                const grupo = evalSup.grupo || "X";
                evaluacionesPorGrupo[grupo] = evaluacionesPorGrupo[grupo] || [];
                evaluacionesPorGrupo[grupo].push(evalSup);
        }

        const suplementosAplicados = [];
        for (const grupo of Object.keys(evaluacionesPorGrupo)) {
                const evaluadosGrupo = evaluacionesPorGrupo[grupo];
                const exactos = evaluadosGrupo.filter((e) => e.condicionesExactas);
                const todasCondicionesExactasCumplen =
                        exactos.length === 0 || exactos.every((e) => e.matches);

                evaluadosGrupo.forEach((evaluado) => {
                        if (!evaluado.matches) return;
                        if (evaluado.condicionesExactas && !todasCondicionesExactasCumplen)
                                return;

                        const { index, ...rest } = evaluado;
                        delete rest.condicionesExactas;
                        delete rest.matches;
                        suplementosAplicados.push({ ...rest, index });
                });
        }

        suplementosAplicados.sort((a, b) => a.index - b.index);
        suplementosAplicados.forEach((sup) => {
                delete sup.index;
        });

        const grupos = {};
        for (const sup of suplementosAplicados) {
                const grupo = sup.grupo || "X";
                grupos[grupo] = grupos[grupo] || [];
                grupos[grupo].push(sup);
        }

        const items = [];
        const gruposAgregados = new Set();
        for (const sup of suplementosAplicados) {
                const grupo = sup.grupo || "X";
                const arr = grupos[grupo];
                const esAcumulable = arr.some((s) => s.acumulable);
                if (esAcumulable || arr.length === 1) {
                        items.push({
                                descripcion: sup.descripcion,
                                porcentajeOriginal: sup.recargo,
                                grupo,
                        });
                } else if (!gruposAgregados.has(grupo)) {
                        const maxRecargo = Math.max(...arr.map((s) => s.recargo));
                        const descripciones = arr
                                .map((s) => s.descripcion)
                                .filter(Boolean);
                        let descripcionTxt = "";
                        if (descripciones.length === 2)
                                descripcionTxt = descripciones.join(" y ");
                        else if (descripciones.length > 2)
                                descripcionTxt =
                                        descripciones.slice(0, -1).join(", ") +
                                        " y " +
                                        descripciones[descripciones.length - 1];
                        else descripcionTxt = descripciones[0] || "";
                        items.push({
                                descripcion: descripcionTxt,
                                porcentajeOriginal: maxRecargo,
                                grupo,
                        });
                        gruposAgregados.add(grupo);
                }
        }

        const remainingPorGrupo = {};
        for (const item of items) {
                const grupo = item.grupo || "X";
                if (remainingPorGrupo[grupo] == null)
                        remainingPorGrupo[grupo] =
                                topePorGrupo[grupo] != null ? topePorGrupo[grupo] : Infinity;
                const restante = remainingPorGrupo[grupo];
                const aplicado = Math.min(item.porcentajeOriginal, restante);
                item.porcentajeTrasGrupo = aplicado;
                remainingPorGrupo[grupo] = restante - aplicado;
        }

        const totalLimit =
                maximoAcumulableTotal != null
                        ? maximoAcumulableTotal / 100
                        : Infinity;
        const totalAntesLimite = items.reduce(
                (acc, it) => acc + it.porcentajeTrasGrupo,
                0
        );
        let restanteTotal = totalLimit;
        for (const item of items) {
                const aplicado = Math.min(item.porcentajeTrasGrupo, restanteTotal);
                item.porcentajeAplicado = aplicado;
                restanteTotal -= aplicado;
        }
        const recargoTotal = items.reduce((acc, it) => acc + it.porcentajeAplicado, 0);
        const limiteTotalAlcanzado = totalAntesLimite > totalLimit;

        const recargosPorGrupo = {};
        for (const item of items) {
                const grupo = item.grupo || "X";
                recargosPorGrupo[grupo] =
                        (recargosPorGrupo[grupo] || 0) + item.porcentajeAplicado;
        }

        return {
                recargoTotal,
                maximoAcumulableTotal,
                detalles: items.map((it) => ({
                        descripcion: it.descripcion,
                        porcentajeOriginal: it.porcentajeOriginal,
                        porcentajeAplicado: it.porcentajeAplicado,
                        grupo: it.grupo,
                })),
                limiteTotalAlcanzado,
                recargosPorGrupo,
                topePorGrupo,
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
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.precio !== null) {
                return [];
        }
        await ensureOfertasLoaded();
        if (!Array.isArray(getCurrentOfertas())) return [];

	const now = Date.now() / 1000;
	const modalidadID = modalidad.ID;
	const nivelGarantia = Array.isArray(modalidad.nivel_garantia)
		? modalidad.nivel_garantia[0]
		: modalidad.nivel_garantia;

        const descuentos = [];

        (getCurrentOfertas() || []).forEach((oferta) => {
                const esSinSuplementos = oferta?.tipo_oferta === "sin_suplementos";
                if (esSinSuplementos) return;
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
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.precio !== null) {
                return [];
        }
        const ofertas = getCurrentOfertas();
        if (!Array.isArray(ofertas)) return [];

        const now = Date.now() / 1000;
        const descuentos = [];
        ofertas.forEach((oferta) => {
                const esSinSuplementos = oferta?.tipo_oferta === "sin_suplementos";
                if (esSinSuplementos) return;
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

function isOfertaSinSuplementos(oferta) {
        return oferta?.tipo_oferta === "sin_suplementos";
}

function getOfertaSinSuplementosAplicableSync(
        modalidad,
        { incluirCaducadas = false } = {},
) {
        if (!modalidad) return null;
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.precio !== null) {
                return null;
        }
        const ofertas = getCurrentOfertas();
        if (!Array.isArray(ofertas)) return null;
        const now = Date.now() / 1000;
        for (const oferta of ofertas) {
                if (!isOfertaSinSuplementos(oferta)) continue;
                if (oferta.estado === false) continue;
                const caducada = oferta.timestamp_caducidad && now > oferta.timestamp_caducidad;
                if (!incluirCaducadas && caducada) continue;
                if (!ofertaAplicaAmodalidad(oferta, modalidad)) continue;
                return oferta;
        }
        return null;
}

async function getOfertaSinSuplementosAplicable(
        modalidad,
        { incluirCaducadas = false } = {},
) {
        if (!modalidad) return null;
        await ensureOfertasLoaded();
        return getOfertaSinSuplementosAplicableSync(modalidad, { incluirCaducadas });
}

async function hasOfertaSinSuplementos(modalidad) {
        const oferta = await getOfertaSinSuplementosAplicable(modalidad);
        return !!oferta;
}

function hasOfertaSinSuplementosSync(modalidad) {
        return !!getOfertaSinSuplementosAplicableSync(modalidad);
}

// --------- RENDERIZADO DE RECARGOS Y DESCUENTOS ---------
function renderRecargosHTML({
        precioBase,
        breakdown,
        precioFinal,
        precioIVA,
        descuentoTotal,
        modalidad,
        sinSuplementos = false,
        ofertaSinSuplementos = null,
}) {
        const clases = ["form__plan-recargos"];
        if (sinSuplementos) clases.push("form__plan-recargos--sin-suplementos");

        let html = `<div class="${clases.join(" ")}"><p class="form__plan-recargos-precios"><b>Precio base:</b> ${eurosString(
                precioBase
        )}€`;

        if (breakdown.recargoTotal > 0) {
                const recargoPct = Math.round(breakdown.recargoTotal * 100);
                if (sinSuplementos) {
                        html += ` | <b>Recargos:</b> <span class="form__plan-recargos-value--tachado">${recargoPct}%</span>`;
                } else {
                        html += ` | <b>Recargos:</b> ${recargoPct}%`;
                }
        }
        if (descuentoTotal > 0)
                html += ` | <b>Descuentos:</b> ${Math.round(descuentoTotal * 100)}%`;

        html += ` | <b>Precio final:</b> ${eurosString(
                precioFinal
        )}€ | <b>Precio final + IVA:</b> ${eurosString(precioIVA)}€</p>`;

        const detalles = breakdown.detalles || [];
        if (detalles.length) {
                html += `<ul class="form__plan-recargos-list">`;
                for (const sup of detalles) {
                        const pct = Math.round(sup.porcentajeAplicado * 100);
                        const itemClass =
                                "form__plan-recargos-item" +
                                (sinSuplementos ? " form__plan-recargos-item--sin-aplicar" : "");
                        html += `<li class="${itemClass}">
                  <span class="form__plan-porcentaje-recargo">Recargo ${pct}%:</span>
                  ${sup.descripcion || ""}
                </li>`;
                }
                html += `</ul>`;
        }

// <<<<<<< i1d36g-codex/refactor-import-in-nueva_garantia.js
        const descuentosAplicadosSync = getDescuentosAplicablesSync(modalidad);
// =======
        // descuentos aplicados (sin await, se asume que ya están precargados cuando se renderiza admin)
    // const descuentosAplicadosSync = getCurrentOfertas()
	// 	? [] // se deja vacío aquí porque la lógica de admin usa renderRecargosHTML solo si todo ya está calculado
	// 	: [];
// >>>>>>> main

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

        if (sinSuplementos && detalles.length) {
                const etiqueta = ofertaSinSuplementos?.nombre || ofertaSinSuplementos?.etiqueta || "Sin suplementos";
                html += `<p class="form__plan-recargos-note">Suplementos no aplicados por la oferta “${escapeHtml(
                        etiqueta
                )}”.</p>`;
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
async function updateOfertas() {
        if (isProfesional()) {
                await ensureCurrentUserIdReady();
        }

        const usuarioId = getEffectiveProfessionalId();
        if (!usuarioId && !isProfesional()) {
                const ofertasContainer = document.querySelector(".form__ofertas");
                const lista = ofertasContainer?.querySelector("ul.ofertas__list");
                if (lista) {
                        lista.innerHTML = "";
                }
                setCurrentOfertas([]);
                setSpecialFixedOffers([], { enabled: false });
                document.dispatchEvent(new Event("ofertas:actualizadas"));
                return;
        }

        const skipLoader = shouldSkipLoaderForSelf({});
        if (!skipLoader) {
                showOfertasLoading();
        } else {
                const ofertasContainer = document.querySelector(".form__ofertas");
                const lista = ofertasContainer?.querySelector("ul.ofertas__list");
                if (lista) {
                        lista.innerHTML = "";
                }
        }
        await fetchOfertas(usuarioId ?? null, { force: true });
        await filtrarModalidadesBase();
        document.dispatchEvent(new Event("ofertas:actualizadas"));
}

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
                if (!vehicleTypesByChannelCache || vehicleTypesByChannelCache.size === 0) {
                        vehicleTypesByChannelCache = buildVehicleTypesCache(
                                modalidadesCache.data
                        );
                }
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
                vehicleTypesByChannelCache = buildVehicleTypesCache(json);
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
        let kmLimits = null;
        let kmRequiresAntiguedad = false;
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
                const condicionesEspecialesArr = getCondicionesEspeciales(modalidad);
                if (condicionesEspecialesArr.includes("kilometraje")) {
                        const cm =
                                modalidad.acf?.condiciones_generales_y_tarifas
                                        ?.condiciones_modalidad || {};
                        const grupoKm = cm.condicion_por_kilometros || {};
                        const desde = parseNumericFormValue(grupoKm.desde || 0);
                        const hastaRaw = grupoKm.hasta;
                        const hasta =
                                hastaRaw === "" || hastaRaw == null
                                        ? Infinity
                                        : parseNumericFormValue(hastaRaw);
                        if (!kmLimits) kmLimits = { min: desde, max: hasta };
                        else {
                                if (desde < kmLimits.min) kmLimits.min = desde;
                                if (hasta > kmLimits.max) kmLimits.max = hasta;
                        }
                        if (condicionesEspecialesArr.includes("antiguedad")) {
                                kmRequiresAntiguedad = true;
                        }
                }
        }

        if (!found) {
                cilLimits = { min: 0, max: 9000 };
                potLimits = { min: 0, max: 3000 };
        }
        const limits = getLimitesDinamicos();
        limits.cilindrada = cilLimits || { min: 0, max: 9000 };
        limits.potencia = potLimits || { min: 0, max: 3000 };
        limits.kilometros = kmRequiresAntiguedad
                ? { min: 0, max: Infinity }
                : kmLimits || { min: 0, max: Infinity };
        setLimitesDinamicos(limits);
}

function modalidadAdmiteValor(modalidad, valoresForm) {
        const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
        const tarifas = cg?.tarifas || [];
        const esCamion = valoresForm.tipo_vehiculo === "camion";
        const ejes = esCamion ? valoresForm.traccion_camion : null;

        const { tipo, valor } = determineValorComparar(modalidad, valoresForm);

        return tarifas.some((tarifa) => {
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
                const { coincide } = evaluarFiltrosParticulares(tarifa, valoresForm);
                return checkValor && checkEjes && coincide;
        });
}

function getMesesDisponiblesPorModalidad(modalidad, valoresForm) {
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.duracion) {
                return [specialConfig.duracion];
        }
        const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
        const tarifas = cg?.tarifas || [];
        const esCamion = valoresForm.tipo_vehiculo === "camion";
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
                        const { coincide } = evaluarFiltrosParticulares(tarifa, valoresForm);
                        return checkValor && checkEjes && coincide;
                })
                .map((tarifa) => Number(tarifa.duracion_meses));

        return [...new Set(mesesPorGarantia)].sort((a, b) => a - b);
}

function calcularPrecioBase(modalidad, valoresForm) {
        const specialConfig = getSpecialFixedConfig(modalidad);
        if (specialConfig && specialConfig.precio !== null) {
                return redondearEuros(specialConfig.precio);
        }
        const cg = modalidad.acf?.condiciones_generales_y_tarifas || {};
        const cm = cg?.condiciones_modalidad || {};
        const tarifas = cg?.tarifas || [];
        const meses = Number(valoresForm.duracion);
        const esCamion = valoresForm.tipo_vehiculo === "camion";
        let ejes = null;
        if (esCamion) ejes = valoresForm.traccion_camion;

	const { tipo, valor } = determineValorComparar(modalidad, valoresForm);

        let mejorTarifa = null;
        let mejorEspecificidad = -1;

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
                        const { coincide, especificidad } = evaluarFiltrosParticulares(
                                tarifa,
                                valoresForm
                        );
                        if (!coincide) continue;

                        if (especificidad > mejorEspecificidad) {
                                mejorTarifa = tarifa;
                                mejorEspecificidad = especificidad;
                        }
                }
        }

        if (!mejorTarifa) {
                return null;
        }

        const precioBaseRaw = mejorTarifa.precio_base;
        if (precioBaseRaw === null || precioBaseRaw === "") {
                return null;
        }
        const precio = parseNumericFormValue(precioBaseRaw);
        return Number.isNaN(precio) ? null : precio;
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
                select.appendChild(opt);
                select.disabled = true;
                select.removeAttribute("required");
                return;
        }
        select.disabled = false;
        select.setAttribute("required", "required");
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
function renderPlans(modalidades, valoresForm, opciones = {}) {
        const plansContainer = document.getElementById("formPlans");
        if (!plansContainer) return;

        const {
                mostrarMensajeAntiguedad = false,
                mostrarMensajeAntiguedadMinima = false,
                mostrarMensajeKilometrosMaximos = false,
                mostrarMensajeKilometrosMinimos = false,
                mostrarMensajePotenciaMaxima = false,
                mostrarMensajePotenciaMinima = false,
                mostrarMensajeGenerico = false,
        } = opciones;

        plansContainer.classList.remove("form__plans--featured");

        const preciosConIVA = document.getElementById("check-iva")?.checked !== false;

        if (!modalidades || !modalidades.length) {
                const mensajeAntiguedadMinima =
                        "El vehículo no supera el límite mínimo de antigüedad permitido para esta cobertura. Ponte en contacto con el Departamento Comercial de 360VO";
                const mensajeAntiguedad =
                        "El vehículo supera el límite de antigüedad permitido para esta cobertura. Ponte en contacto con el Departamento Comercial de 360VO";
                const mensajeKilometros =
                        "El vehículo supera el límite de kilómetros permitido para esta cobertura. Ponte en contacto con el Departamento Comercial de 360VO";
                const mensajeGenerico =
                        "No hay garantías disponibles para esta cobertura con los datos proporcionados. Ponte en contacto con el Departamento Comercial de 360VO";
                const mensajePotenciaMaxima =
                        "El vehículo supera la potencia máxima permitida en las condiciones de la garantía. Ponte en contacto con el Departamento Técnico de 360VO.";
                const mensajePotenciaMinima =
                        "El vehículo no supera la potencia mínima permitida en las condiciones de la garantía. Ponte en contacto con el Departamento Comercial de 360VO.";

                let texto = mensajeGenerico;
                let variant = "warning";
                if (mostrarMensajeAntiguedadMinima) {
                        texto = mensajeAntiguedadMinima;
                        variant = "warning";
                } else if (mostrarMensajeAntiguedad) {
                        texto = mensajeAntiguedad;
                        variant = "warning";
                } else if (mostrarMensajeKilometrosMaximos || mostrarMensajeKilometrosMinimos) {
                        texto = mensajeKilometros;
                        variant = "warning";
                } else if (mostrarMensajePotenciaMaxima) {
                        texto = mensajePotenciaMaxima;
                        variant = "warning";
                } else if (mostrarMensajePotenciaMinima) {
                        texto = mensajePotenciaMinima;
                        variant = "warning";
                } else if (!mostrarMensajeGenerico) {
                        texto = "No hay garantías disponibles para estos filtros.";
                        variant = "empty";
                }

                const iconHtml = getIcon("warning") || "";

                plansContainer.innerHTML = `
    <div class="form__plans-message form__plans-message--${variant}" role="alert" aria-live="polite">
      <span class="form__plans-message-icon" aria-hidden="true">${iconHtml}</span>
      <span class="form__plans-message-text">${texto}</span>
    </div>
  `;
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
        const anySelected =
                persistedModalidadId != null &&
                sorted.some((m) => String(m.ID) === String(persistedModalidadId));

	let alreadySelected = false;
	let anyFeatured = false;

	plansContainer.innerHTML = sorted
		.map((m, idx) => {
			const detalles = m.acf?.detalles_modalidad || {};
			const cg = m.acf?.condiciones_generales_y_tarifas || {};
			const descInfo = cg?.descripcion_informacion || {};
			const estilos = descInfo?.estilos || {};

			const title = detalles.nombre_mostrar || m.title || "";
                        const description = renderPlanDescription(descInfo, valoresForm);
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
                        } else if (anySelected) {
                                planClasses.push("form__plan--no-selected");
                        }

                        const specialConfig = getSpecialFixedConfig(m);
                        const isSpecial = !!(specialConfig && specialConfig.precio !== null);

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

                        const ofertaSinSuplementos = getOfertaSinSuplementosAplicableSync(m);
                        const aplicaSinSuplementos =
                                !!ofertaSinSuplementos && breakdown.recargoTotal > 0;

                        const factorRecargos = 1 + breakdown.recargoTotal;
                        const factorAplicado = aplicaSinSuplementos ? 1 : factorRecargos;
                        const tieneRecargos = breakdown.recargoTotal > 0;

                        const precioConRecargos =
                                precioBase !== null
                                        ? redondearEuros(precioBase * factorRecargos)
                                        : null;
                        const precioConRecargosIVA =
                                precioConRecargos !== null
                                        ? redondearEuros(
                                                  precioConRecargos * (1 + IVA_PORCENTAJE / 100)
                                          )
                                        : null;

                        const precioAntesDescuento =
                                precioBase !== null
                                        ? redondearEuros(precioBase * factorAplicado)
                                        : null;

                        const precioFinal =
                                precioAntesDescuento !== null
                                        ? redondearEuros(
                                                  precioAntesDescuento * (1 - descuentoTotalSync)
                                          )
                                        : null;
                        const precioIVA =
                                precioFinal !== null
                                        ? redondearEuros(precioFinal * (1 + IVA_PORCENTAJE / 100))
                                        : null;
                        const ivaSolo =
                                precioFinal !== null ? redondearEuros(precioIVA - precioFinal) : null;

                        const puedeRevertirDescuento =
                                descuentoTotalSync > 0 &&
                                descuentoTotalSync < 1 &&
                                precioFinal !== null;
                        const precioAntesDescuentosReal = puedeRevertirDescuento
                                ? redondearEuros(precioFinal / (1 - descuentoTotalSync))
                                : precioAntesDescuento;
                        const precioAntesDescuentosRealIVA =
                                precioAntesDescuentosReal !== null
                                        ? redondearEuros(
                                                  precioAntesDescuentosReal * (1 + IVA_PORCENTAJE / 100)
                                          )
                                        : null;

                        const precioAnteriorInfo = (() => {
                                if (aplicaSinSuplementos && tieneRecargos && precioConRecargos !== null) {
                                        return {
                                                sinIVA: precioConRecargos,
                                                conIVA: precioConRecargosIVA,
                                        };
                                }
                                if (descuentoTotalSync > 0 && precioAntesDescuentosReal !== null) {
                                        return {
                                                sinIVA: precioAntesDescuentosReal,
                                                conIVA: precioAntesDescuentosRealIVA,
                                        };
                                }
                                return null;
                        })();

                        let recargosHTML = "";
                        if (
                                getEffectiveUserRole() === "admin" &&
                                precioBase !== null &&
                                precioFinal !== null
                        ) {
                                if (isSpecial) {
                                        const duracionLabel =
                                                specialConfig?.duracion_label ||
                                                (specialConfig?.duracion
                                                        ? `${specialConfig.duracion} meses`
                                                        : "");
                                        recargosHTML = renderRecargosEspecial({
                                                precioBase,
                                                precioIVA,
                                                duracionLabel,
                                        });
                                } else {
                                        recargosHTML = renderRecargosHTML({
                                                precioBase,
                                                breakdown,
                                                precioFinal,
                                                precioIVA,
                                                descuentoTotal: descuentoTotalSync,
                                                modalidad: m,
                                                sinSuplementos: aplicaSinSuplementos,
                                                ofertaSinSuplementos,
                                        });
                                }
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

                        const isConsultar = precioFinal === null;
                        const priceConIva =
                                !isConsultar && precioIVA !== null
                                        ? eurosString(precioIVA)
                                        : "Consultar";
                        const priceSinIva =
                                !isConsultar && precioFinal !== null
                                        ? eurosString(precioFinal)
                                        : "Consultar";
                        const priceIvaOnly =
                                !isConsultar && ivaSolo !== null
                                        ? `+ ${eurosString(ivaSolo)}€ IVA`
                                        : "";
                        const euroDisplay = !isConsultar ? "inline" : "none";

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
                                                        if (precioAnteriorInfo) {
                                                                const valorAnterior = preciosConIVA
                                                                        ? precioAnteriorInfo.conIVA
                                                                        : precioAnteriorInfo.sinIVA;
                                                                if (valorAnterior != null) {
                                                                        return `<span class="form__plan-price-anterior">${eurosString(
                                                                                valorAnterior
                                                                        )}€</span>`;
                                                                }
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
                            <span class="form__plan-price-euro" style="display:${
                                                                                                                        euroDisplay
                                                                                                                }">€</span>
                        </span>
                    </div>
                    <div class="form__plan-iva" style="display:${
                                                                                        preciosConIVA && !isConsultar
                                                                                                ? "block"
                                                                                                : "none"
                                                                                }">IVA incluido</div>
                    <div class="form__plan-iva-no" style="display:${
                                                                                        !preciosConIVA && !isConsultar
                                                                                                ? "block"
                                                                                                : "none"
                                                                                }">${priceIvaOnly}</div>
                    <div class="form__plan-description">${description}</div>
                    ${recargosHTML}
                    <div class="form__plan-actions">
                        ${
                                                                                                pdf
                                                                                                        ? `
                        <a class="form__plan-link" href="${pdf}" target="_blank" rel="noopener">
                            <span class="form__plan-link-icon">${getIcon("pdf")}</span>
                            Cobertura
                        </a>
                        `
                                                                                                       : ""
                                                                                       }
                        <button class="form__plan-button" type="button">
                            <span class="form__plan-button-text">${buttonInner}</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
		})
		.join("");

        if (anyFeatured) plansContainer.classList.add("form__plans--featured");
    setupPlanSelection();
    applyDesgloseVisibility();
}

// --------- FILTRADO PRINCIPAL ---------
async function filtrarModalidadesBase() {
        const token = ++filtroToken;
        const tipoVehiculoSeleccionado = getValorInput("tipo_vehiculo") || "";
        const fechaPrimeraMatriculacion = getValorInput(
                "fecha_primera_matriculacion"
        );
        const antiguedad = getAntiguedadFromDate(fechaPrimeraMatriculacion);

        const valoresForm = {
                tipo_vehiculo: tipoVehiculoSeleccionado,
                cilindrada: getValorInput("cilindrada") || 0,
                potencia: getValorInput("potencia") || 0,
                kilometros: getValorInput("kilometros") || 0,
                duracion: Number(getValorInput("duracion")) || 12,
                traccion_camion: getValorInput("traccion_camion") || null,
                combustible: getValorInput("combustible") || null,
                traccion: getValorInput("traccion") || "",
                antiguedad,
        };

        const modalidades = await fetchModalidades();
        if (token !== filtroToken) return;
        let candidatas = modalidades.filter(
                (m) => m.tipo_vehiculo && m.tipo_vehiculo.includes(tipoVehiculoSeleccionado)
        );

        const specialRestrictions = buildSpecialRestrictionMap();
        if (specialRestrictions) {
                candidatas = candidatas.filter((m) =>
                        modalidadRespetaRestriccionesEspeciales(m, specialRestrictions)
                );
        }

        let antiguedadSuperaMaximo = false;
        let maxAntiguedadPermitida = 0;
        let kilometrosSuperaMaximo = false;
        let maxKilometrosPermitidos = 0;
        let kilometrajeCondicionesConsideradas = 0;
        let kilometrajeCondicionesExcluyentes = 0;
        let potenciaSuperaMaximo = false;
        let maxPotenciaPermitida = 0;
        let potenciaCondicionesConsideradas = 0;
        let potenciaCondicionesExcluyentes = 0;
        const motivosDescarte = {
                antiguedadMaxima: false,
                antiguedadMinima: false,
                kilometrosMaximos: false,
                kilometrosMinimos: false,
                potenciaMaxima: false,
                potenciaMinima: false,
                otros: false,
        };

        function cumpleCondiciones(modalidad) {
                const cm =
                        modalidad.acf?.condiciones_generales_y_tarifas?.condiciones_modalidad;
                if (!cm) return true;

                const condicionesEspecialesArr = getCondicionesEspeciales(modalidad);

                const configs = [
                        {
                                key: "combustible",
                                formField: "combustible",
                                modalidadField: "combustible",
                        },
                ];

                for (const config of configs) {
                        if (condicionesEspecialesArr.includes(config.key)) {
                                const valorFormulario = getValorInput(config.formField);
                                const valoresModalidad = getValoresModalidadCampo(
                                        cm[config.modalidadField]
                                );
                                if (!valorFormulario) {
                                        motivosDescarte.otros = true;
                                        return false;
                                }
                                if (!valoresModalidad.includes(valorFormulario)) {
                                        motivosDescarte.otros = true;
                                        return false;
                                }
                        }
                }

                let excedeAntiguedad = false;
                let excedeKilometros = false;

                if (condicionesEspecialesArr.includes("antiguedad")) {
                        const grupoAntiguedad = cm.condicion_por_antiguedad || {};
                        const desde = Number(grupoAntiguedad.desde || 0);
                        const hastaRaw = grupoAntiguedad.hasta;
                        const hasta =
                                hastaRaw !== "" && hastaRaw !== undefined ? Number(hastaRaw) : null;

                        if (hasta === null) {
                                maxAntiguedadPermitida = Infinity;
                        } else if (hasta > maxAntiguedadPermitida) {
                                maxAntiguedadPermitida = hasta;
                        }

                        if (antiguedad === null || isNaN(antiguedad)) {
                                motivosDescarte.otros = true;
                                return false;
                        }
                        if (antiguedad < desde) {
                                motivosDescarte.antiguedadMinima = true;
                                return false;
                        }
                        if (hasta !== null && antiguedad > hasta) {
                                excedeAntiguedad = true;
                        }
                } else {
                        maxAntiguedadPermitida = Infinity;
                }

                if (condicionesEspecialesArr.includes("kilometraje")) {
                        const grupoKm = cm.condicion_por_kilometros || {};
                        const desdeKm = parseNumericFormValue(grupoKm.desde || 0);
                        const hastaKmRaw = grupoKm.hasta;
                        const hastaKm =
                                hastaKmRaw !== "" && hastaKmRaw !== undefined
                                        ? parseNumericFormValue(hastaKmRaw)
                                        : null;

                        if (hastaKm === null) {
                                maxKilometrosPermitidos = Infinity;
                        } else if (maxKilometrosPermitidos !== Infinity && hastaKm > maxKilometrosPermitidos) {
                                maxKilometrosPermitidos = hastaKm;
                        }

                        const kms = parseNumericFormValue(getValorInput("kilometros"));
                        if (isNaN(kms)) {
                                kilometrajeCondicionesConsideradas += 1;
                                kilometrajeCondicionesExcluyentes += 1;
                                motivosDescarte.otros = true;
                                return false;
                        }

                        kilometrajeCondicionesConsideradas += 1;

                        if (kms < desdeKm) {
                                kilometrajeCondicionesExcluyentes += 1;
                                motivosDescarte.kilometrosMinimos = true;
                                return false;
                        }
                        if (hastaKm !== null && kms > hastaKm) {
                                excedeKilometros = true;
                                kilometrosSuperaMaximo = true;
                                kilometrajeCondicionesExcluyentes += 1;
                                motivosDescarte.kilometrosMaximos = true;
                        }
                } else {
                        maxKilometrosPermitidos = Infinity;
                }

                if (condicionesEspecialesArr.includes("potencia")) {
                        const grupoPotencia = cm.condicion_por_potencia || {};
                        const desdePot = parseNumericFormValue(grupoPotencia.desde || 0);
                        const hastaPotRaw = grupoPotencia.hasta;
                        const hastaPot =
                                hastaPotRaw !== "" && hastaPotRaw !== undefined
                                        ? parseNumericFormValue(hastaPotRaw)
                                        : null;

                        if (hastaPot === null) {
                                maxPotenciaPermitida = Infinity;
                        } else if (
                                maxPotenciaPermitida !== Infinity &&
                                (maxPotenciaPermitida === 0 || hastaPot > maxPotenciaPermitida)
                        ) {
                                maxPotenciaPermitida = hastaPot;
                        }

                        const potenciaValor = parseNumericFormValue(getValorInput("potencia"));
                        if (isNaN(potenciaValor)) {
                                potenciaCondicionesConsideradas += 1;
                                potenciaCondicionesExcluyentes += 1;
                                motivosDescarte.otros = true;
                                return false;
                        }

                        potenciaCondicionesConsideradas += 1;

                        if (potenciaValor < desdePot) {
                                potenciaCondicionesExcluyentes += 1;
                                motivosDescarte.potenciaMinima = true;
                                return false;
                        }
                        if (hastaPot !== null && potenciaValor > hastaPot) {
                                potenciaSuperaMaximo = true;
                                potenciaCondicionesExcluyentes += 1;
                                motivosDescarte.potenciaMaxima = true;
                                return false;
                        }
                } else {
                        maxPotenciaPermitida = Infinity;
                }

                if (excedeAntiguedad && excedeKilometros) {
                        // FLAG: Revisar condición para camiones si supera 12 años y 800.000km.
                        antiguedadSuperaMaximo = true;
                        kilometrosSuperaMaximo = true;
                        motivosDescarte.antiguedadMaxima = true;
                        motivosDescarte.kilometrosMaximos = true;
                        return false;
                }

                if (excedeAntiguedad) {
                        antiguedadSuperaMaximo = true;
                        motivosDescarte.antiguedadMaxima = true;
                        return false;
                }
                if (excedeKilometros) {
                        kilometrosSuperaMaximo = true;
                        motivosDescarte.kilometrosMaximos = true;
                        return false;
                }
                return true;
        }

        candidatas = candidatas.filter((m) => cumpleCondiciones(m));

        const canalesDisponibles = new Set();
        candidatas.forEach((m) => {
                const canales = getModalidadChannels(m);
                canales.forEach((canal) => canalesDisponibles.add(canal));
        });

        const canalActivo = getActiveChannelSlug(Array.from(canalesDisponibles));
        valoresForm.canal = canalActivo;

        syncCanalVentaSelect(canalesDisponibles, canalActivo);
        syncTipoVehiculoOptions(canalActivo);

        if (canalActivo) {
                candidatas = candidatas.filter((m) =>
                        getModalidadChannels(m).includes(canalActivo)
                );
        }

        let disponibles = candidatas.filter((m) =>
                modalidadAdmiteValor(m, valoresForm)
        );

        setVisibleModalidades(disponibles);
        if (token !== filtroToken) return;

        await refreshOfertasDisplay();
        if (token !== filtroToken) return;

        setDynamicLimits(candidatas, valoresForm);

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

        if (!disponibles.length) {
                const kmsVal = parseNumericFormValue(valoresForm.kilometros);
                if (
                        !kilometrosSuperaMaximo &&
                        maxAntiguedadPermitida !== Infinity &&
                        antiguedad != null &&
                        antiguedad > maxAntiguedadPermitida
                ) {
                        antiguedadSuperaMaximo = true;
                        motivosDescarte.antiguedadMaxima = true;
                }

                if (
                        !antiguedadSuperaMaximo &&
                        kilometrajeCondicionesConsideradas > 0 &&
                        maxKilometrosPermitidos !== Infinity &&
                        kmsVal > maxKilometrosPermitidos
                ) {
                        kilometrosSuperaMaximo = true;
                        if (kilometrajeCondicionesExcluyentes < kilometrajeCondicionesConsideradas) {
                                kilometrajeCondicionesExcluyentes = kilometrajeCondicionesConsideradas;
                        }
                        motivosDescarte.kilometrosMaximos = true;
                }

                const potenciaVal = parseNumericFormValue(valoresForm.potencia);
                if (
                        !potenciaSuperaMaximo &&
                        potenciaCondicionesConsideradas > 0 &&
                        maxPotenciaPermitida !== Infinity &&
                        potenciaVal > maxPotenciaPermitida
                ) {
                        potenciaSuperaMaximo = true;
                        if (potenciaCondicionesExcluyentes < potenciaCondicionesConsideradas) {
                                potenciaCondicionesExcluyentes = potenciaCondicionesConsideradas;
                        }
                        motivosDescarte.potenciaMaxima = true;
                }

                updateDuracionSelect([]);
                const mostrarAntiguedadMinima = motivosDescarte.antiguedadMinima;
                const mostrarAntiguedadMaxima = motivosDescarte.antiguedadMaxima;
                const mostrarKilometrosMaximos = motivosDescarte.kilometrosMaximos;
                const mostrarKilometrosMinimos = motivosDescarte.kilometrosMinimos;
                const mostrarPotenciaMaxima = motivosDescarte.potenciaMaxima;
                const mostrarPotenciaMinima = motivosDescarte.potenciaMinima;
                const algunMotivoEspecifico =
                        mostrarAntiguedadMinima ||
                        mostrarAntiguedadMaxima ||
                        mostrarKilometrosMaximos ||
                        mostrarKilometrosMinimos ||
                        mostrarPotenciaMaxima ||
                        mostrarPotenciaMinima;

                if (!algunMotivoEspecifico) {
                        motivosDescarte.otros = true;
                }

                renderPlans([], valoresForm, {
                        mostrarMensajeAntiguedad: mostrarAntiguedadMaxima,
                        mostrarMensajeAntiguedadMinima: mostrarAntiguedadMinima,
                        mostrarMensajeKilometrosMaximos: mostrarKilometrosMaximos,
                        mostrarMensajeKilometrosMinimos: mostrarKilometrosMinimos,
                        mostrarMensajePotenciaMaxima: mostrarPotenciaMaxima,
                        mostrarMensajePotenciaMinima: mostrarPotenciaMinima,
                        mostrarMensajeGenerico: !algunMotivoEspecifico,
                });
        } else {
                updateDuracionSelect(mesesDisponibles);
                valoresForm.duracion = Number(getValorInput("duracion")) || 12;
                renderPlans(disponibles, valoresForm);
        }

        return disponibles;
}

let lastSpecialSignature = null;
subscribeSpecialFixedOffers(({ ofertas, meta }) => {
        const enabled = Boolean(
                meta?.enabled ?? meta?.tiene_oferta_especial_precio_fijo ?? meta?.tieneOfertaEspecial
        );
        const itemsSignature = Array.isArray(ofertas)
                ? ofertas
                          .map((item) => {
                                  if (!item || typeof item !== "object") return "";
                                  const parts = [
                                          item.tipo_garantia_id ?? "",
                                          (item.tipo_garantia_slug || "").toLowerCase(),
                                          item.nivel_garantia_id ?? "",
                                          (item.nivel_garantia_slug || "").toLowerCase(),
                                          item.precio_fijo ?? "",
                                          item.excluir_resto_niveles ? "1" : "0",
                                          item.duracion_meses ?? "",
                                  ];
                                  return parts.join("|");
                          })
                          .sort()
                          .join("||")
                : "";
        const signature = `${enabled ? 1 : 0}::${itemsSignature}`;
        if (signature === lastSpecialSignature) return;
        lastSpecialSignature = signature;
        setTimeout(() => {
                filtrarModalidadesBase();
        }, 0);
});

const filtrarModalidades = debounce(() => {
        filtrarModalidadesBase();
}, 120);
// --------- INIT ---------
async function initCalculations() {
        const dynamicFields = [
                "tipo_vehiculo",
                "combustible",
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
                        const handler = () => {
                                showPlanPriceSkeleton();
                                showOfertasLoading();
                                filtrarModalidades();
                        };
                        input.addEventListener("input", handler);
                        input.addEventListener("change", handler);
                }
        });
	const inputTipoVehiculo = document.getElementById("tipo_vehiculo");
	if (inputTipoVehiculo) {
		inputTipoVehiculo.addEventListener("change", (e) => {
			const nuevoTipo = e.target.value;
                        if (nuevoTipo !== "camion") {
                                const campoEjes = document.getElementById("traccion_camion");
                                if (campoEjes) campoEjes.value = "";
                        }
                        [
                                "potencia",
                                "cilindrada",
                                "combustible",
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
        // Helper para indicar visualmente que los precios se están recalculando
        function showPlanPriceSkeleton() {
                document
                        .querySelectorAll(".form__plan-price-text")
                        .forEach((el) => {
                                el.style.display = "none";
                        });
                document.querySelectorAll(".plan-price-skeleton").forEach((el) => {
                        el.style.display = "inline-block";
                        el.style.opacity = "1";
                });
        }

        const inputVendedor = document.getElementById("usuario-rol");
        if (inputVendedor) {
                inputVendedor.addEventListener("change", async () => {
                        showPlanPriceSkeleton();
                        await updateOfertas();
                        setTimeout(filtrarModalidades, 50);
                });
        }

        const inputCheckIVA = document.getElementById("check-iva");
        if (inputCheckIVA) {
                inputCheckIVA.addEventListener("change", () => {
                        showPlanPriceSkeleton();
                        setTimeout(filtrarModalidades, 50);
                });
        }

        const inputCheckDesglose = document.getElementById("check-desglose");
        if (inputCheckDesglose) {
                inputCheckDesglose.addEventListener("change", applyDesgloseVisibility);
                applyDesgloseVisibility();
        }

        const inputCanalVenta = document.getElementById("canal-venta");
        if (inputCanalVenta) {
                inputCanalVenta.addEventListener("change", () => {
                        showPlanPriceSkeleton();
                        setTimeout(filtrarModalidades, 50);
                });
        }

        const inputDuracion = document.getElementById("duracion");
        if (inputDuracion) {
                inputDuracion.addEventListener("change", () => {
                        showPlanPriceSkeleton();
                        setTimeout(filtrarModalidades, 50);
                });
        }

        const esProfesional = isProfesional();
        if (esProfesional) {
                await ensureCurrentUserIdReady();
        }
        const initialUserId = getEffectiveProfessionalId();
        if (esProfesional) {
                await fetchOfertas(initialUserId ?? null, { force: true });
                document.dispatchEvent(new Event("ofertas:actualizadas"));
        } else if (initialUserId) {
                await fetchOfertas(initialUserId ?? null);
        }
        await filtrarModalidadesBase();
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
        getOfertaSinSuplementosAplicable,
        getOfertaSinSuplementosAplicableSync,
        hasOfertaSinSuplementos,
        hasOfertaSinSuplementosSync,
};
export default initCalculations;

/*renderPlans hace uso parcial de descuentos sin esperar el await de su cálculo real; considera convertir parte de esa lógica en async/await para que el precio refleje correctamente los descuentos si es necesario.*/

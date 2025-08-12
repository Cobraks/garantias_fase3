// assets/js/modules/form-ofertas.js
"use strict";

import {
	getEffectiveUserRole,
	getEffectiveProfessionalId,
	ensureCurrentUserIdReady,
} from "./form-role-utils.js";
import { getRestRoot, getRestNonce } from "./config.js";
import {
	setCurrentOfertas,
	getCurrentOfertas,
	getVisibleModalidades,
} from "./form-state.js";

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-ofertas]", ...args);
}

// Cache simple por userId con posibilidad de invalidar
const ofertasCache = new Map(); // userId -> { ofertas, fetchedAt }

/**
 * Resuelve el userId efectivo (profesional/admin seleccionando)
 * @param {number|string|null} userId
 * @returns {number|null}
 */
function resolveUserId(userId) {
	if (userId) return Number(userId);
	const effectiveProf = getEffectiveProfessionalId();
	if (effectiveProf !== null && !isNaN(Number(effectiveProf))) {
		return Number(effectiveProf);
	}
	return null;
}

/**
 * Normaliza campo aplicacion de la oferta
 */
function normalizeAplicacion(oferta) {
	let aplic = oferta.aplicacion;
	if (Array.isArray(aplic)) {
		return aplic
			.map((a) => (typeof a === "object" ? a.value || "" : String(a)))
			.filter(Boolean);
	}
	if (typeof aplic === "object" && aplic !== null) {
		return [String(aplic.value || "")];
	}
	if (typeof aplic === "string") {
		return [aplic];
	}
	return [];
}

/**
 * Comprueba si una oferta aplica a una modalidad concreta.
 */
export function ofertaAplicaAmodalidad(oferta, modalidad) {
	if (!oferta || !modalidad) return false;

	const modalidadesAplicacion = normalizeAplicacion(oferta);
	const modalidadID = modalidad.ID;
	const nivelGarantia = Array.isArray(modalidad.nivel_garantia)
		? modalidad.nivel_garantia[0]
		: modalidad.nivel_garantia;

	if (modalidadesAplicacion.includes("todas")) return true;

	if (
		nivelGarantia &&
		modalidadesAplicacion.some(
			(a) => String(a).toLowerCase() === String(nivelGarantia).toLowerCase()
		)
	) {
		return true;
	}

	if (modalidadesAplicacion.includes("seleccion")) {
		if (
			Array.isArray(oferta.seleccion_modalidad) &&
			oferta.seleccion_modalidad.includes(modalidadID)
		) {
			return true;
		}
	}

	return false;
}

/**
 * Filtrado de ofertas por modalidades visibles y estado.
 */
export function filterOfertasPorModalidades(
	ofertas = [],
	modalidades = [],
	{ incluirCaducadas = false } = {}
) {
	const now = Date.now() / 1000;
	return (ofertas || []).filter((oferta) => {
		if (oferta.estado === false) return false;
		if (!oferta.porcentaje_descuento && oferta.porcentaje_descuento !== 0)
			return false;

		const caducada =
			oferta.timestamp_caducidad && now > oferta.timestamp_caducidad;
		if (!incluirCaducadas && caducada) return false;

		if (!modalidades || !modalidades.length) return true;

		return modalidades.some((m) => ofertaAplicaAmodalidad(oferta, m));
	});
}

/**
 * Fetch de ofertas con cache y fallback profesional.
 */
export async function fetchOfertas(userId, { force = false } = {}) {
	// Si dependemos de profesional y no se pasó userId, asegurar que esté listo
	if (!userId) {
		await ensureCurrentUserIdReady();
	}

	const effectiveUserId = resolveUserId(userId);
	if (!effectiveUserId) return [];

	if (!force && ofertasCache.has(effectiveUserId)) {
		const cached = ofertasCache.get(effectiveUserId);
		if (cached && Array.isArray(cached.ofertas)) {
			if (ENABLE_LOGS)
				log(`Usando cache de ofertas para usuario ${effectiveUserId}`);
			setCurrentOfertas(cached.ofertas);
			return cached.ofertas;
		}
	}

	const restRoot = getRestRoot();
	const restNonce = getRestNonce();
	const url = `${restRoot}go/v1/ofertas-usuario?id=${encodeURIComponent(
		effectiveUserId
	)}`;

	try {
		const res = await fetch(url, {
			method: "GET",
			credentials: "include",
			headers: {
				"X-WP-Nonce": restNonce,
				Accept: "application/json",
			},
			cache: "no-store",
		});
		if (!res.ok) {
			throw new Error(`Error al obtener ofertas: ${res.status}`);
		}
		const ofertas = await res.json();
		ofertasCache.set(effectiveUserId, { ofertas, fetchedAt: Date.now() });
		setCurrentOfertas(ofertas);
		return ofertas;
	} catch (e) {
		log("Error al obtener ofertas para usuario", effectiveUserId, e);
		return [];
	}
}

/**
 * Actualiza la lista visible de ofertas en DOM filtrando por modalidades actuales.
 */
export async function updateOfertasList(
	userId,
	modalidadesVisibles = [],
	container = null,
	{ force = false } = {}
) {
	if (!userId) {
		await ensureCurrentUserIdReady();
	}
	const effectiveUserId = resolveUserId(userId);
	if (!effectiveUserId) return;

	const parent = document.querySelector(".form__ofertas");
	if (!parent) return;

	let ul = container;
	if (!ul) {
		ul = parent.querySelector("ul.ofertas__list");
		if (!ul) {
			ul = document.createElement("ul");
			ul.className = "ofertas__list";
			parent.insertBefore(ul, parent.querySelector(".ofertas__iva"));
		}
	}

        // Loader visual mientras se obtienen las ofertas
        ul.innerHTML = "";
        const loadingItem = document.createElement("li");
        loadingItem.className = "ofertas__item ofertas__item--loading";
        loadingItem.textContent = "Cargando ofertas...";
        ul.appendChild(loadingItem);

	const ofertas = await fetchOfertas(effectiveUserId, { force });
	const now = Date.now() / 1000;

	const visibles = filterOfertasPorModalidades(ofertas, modalidadesVisibles, {
		incluirCaducadas: false,
	});
	const caducadas = filterOfertasPorModalidades(ofertas, modalidadesVisibles, {
		incluirCaducadas: true,
	}).filter(
		(o) =>
			o.timestamp_caducidad &&
			now > o.timestamp_caducidad &&
			o.estado !== false &&
			o.porcentaje_descuento > 0
	);

	// Render
	ul.innerHTML = "";
	if (!visibles.length && !caducadas.length) {
		const li = document.createElement("li");
		li.className = "ofertas__item";
		li.textContent = "Sin ofertas activas";
		ul.appendChild(li);
		return;
	}

	visibles.forEach((oferta) => {
		const li = document.createElement("li");
		li.className = "ofertas__item";
		li.innerHTML = `${oferta.nombre} <span class="ofertas__percent">-${oferta.porcentaje_descuento}%</span>`;
		ul.appendChild(li);
	});
	caducadas.forEach((oferta) => {
		const li = document.createElement("li");
		li.className = "ofertas__item ofertas__item--caducada";
		const span = document.createElement("span");
		span.className = "tachada";
		span.textContent = `${oferta.nombre}: -${oferta.porcentaje_descuento}%`;
		const vencida = document.createElement("span");
		vencida.className = "vencida";
		vencida.textContent = "VENCIDA";
		li.appendChild(span);
		li.appendChild(document.createTextNode(" "));
		li.appendChild(vencida);
		ul.appendChild(li);
	});
}

/**
 * Invalida cache de ofertas para un userId dado.
 */
export function invalidateOfertasCache(userId) {
	const effectiveUserId = resolveUserId(userId);
	if (effectiveUserId === null) return;
	if (
		!isNaN(Number(effectiveUserId)) &&
		ofertasCache.has(Number(effectiveUserId))
	) {
		ofertasCache.delete(Number(effectiveUserId));
	}
}

/**
 * Utility público para refrescar ofertas basándose en el profesional y modalidades actuales.
 */
let _refreshOfertasPending = null;
export async function refreshOfertasDisplay(attempt = 0) {
	const effectiveUserId = resolveUserId();
	if (!effectiveUserId) {
		if (attempt < 5) {
			setTimeout(() => refreshOfertasDisplay(attempt + 1), 200);
		}
		return;
	}

	if (_refreshOfertasPending) clearTimeout(_refreshOfertasPending);
	_refreshOfertasPending = setTimeout(async () => {
		if (isNaProfesionalFallback()) {
			await ensureCurrentUserIdReady();
		}
		const ofertas =
			getCurrentOfertas() || (await fetchOfertas(effectiveUserId));
		setCurrentOfertas(ofertas);
		const modalidadesVisibles = getVisibleModalidades();
		await updateOfertasList(effectiveUserId, modalidadesVisibles);
		if (ENABLE_LOGS) {
			log("Refresco ofertas tras cambio de filtros:", {
				user: effectiveUserId,
				modalidadesVisibles,
			});
		}
	}, 50);
}

// pequeño helper para comprobar si estamos en rol profesional (por compatibilidad)
function isNaProfesionalFallback() {
	const role = getEffectiveUserRole();
	return role === "go_profesional" || role === "profesional";
}

// Exponer para compatibilidad legacy mínima
export { refreshOfertasDisplay as updateOfertas };

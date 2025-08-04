// assets/js/modules/form-ofertas.js
"use strict";

import {
	getEffectiveProfessionalId,
	isProfesional,
	ensureCurrentUserIdReady,
} from "./form-role-utils.js";

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-ofertas]", ...args);
}

// Cache simple por userId con posibilidad de invalidar
const ofertasCache = new Map(); // userId -> { ofertas, fetchedAt }

/**
 * Intenta resolver el userId efectivo, incluyendo fallback para profesionales logueados
 * Si se pasa userId explícito se usa, si no se delega a form-role-utils.
 */
async function resolveUserId(userId) {
	if (userId) return userId;

	// Si es profesional, esperar a que currentUserId esté listo
	if (isProfesional()) {
		await ensureCurrentUserIdReady();
	}

	return getEffectiveProfessionalId();
}

/**
 * Normaliza el campo de aplicación de una oferta a un array de valores/cadenas
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
 * Filtra una lista de ofertas dejando solo las que aplican a al menos
 * una de las modalidades visibles (si se pasan), y opcionalmente descarta caducadas/invalidas.
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
 * Obtiene ofertas de un usuario desde el endpoint REST.
 * @param {number|string|null} userId
 * @param {Object} options
 * @param {boolean} options.force Si es true, ignora cache y fuerza refetch.
 * @returns {Promise<Array>} Lista de ofertas (vacía en error)
 */
export async function fetchOfertas(userId, { force = false } = {}) {
	const effectiveUserId = await resolveUserId(userId);
	if (!effectiveUserId) return [];

	if (!force && ofertasCache.has(effectiveUserId)) {
		const cached = ofertasCache.get(effectiveUserId);
		if (cached && Array.isArray(cached.ofertas)) {
			if (ENABLE_LOGS)
				log(`Usando cache de ofertas para usuario ${effectiveUserId}`);
			return cached.ofertas;
		}
	}

	const restRoot = window.GO_REST?.root ?? "/wp-json/";
	const restNonce = window.GO_REST?.nonce ?? "";
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
		return ofertas;
	} catch (e) {
		log("Error al obtener ofertas para usuario", effectiveUserId, e);
		return [];
	}
}

/**
 * Renderiza la lista de ofertas en un contenedor dado. Si no se pasa contenedor,
 * busca `.ofertas__list` en el DOM. Aplica filtrado por modalidades visibles.
 * @param {number|string|null} userId
 * @param {Array} modalidadesVisibles
 * @param {HTMLElement|null} [container]
 * @param {Object} options
 * @param {boolean} options.force Refrescar aunque haya en cache
 */
export async function updateOfertasList(
	userId,
	modalidadesVisibles = [],
	container = null,
	{ force = false } = {}
) {
	const effectiveUserId = await resolveUserId(userId);
	const parent = document.querySelector(".form__ofertas");
	if (!parent) return;

	// Asegura que hay UL
	let ul = container;
	if (!ul) {
		ul = parent.querySelector("ul.ofertas__list");
		if (!ul) {
			ul = document.createElement("ul");
			ul.className = "ofertas__list";
			parent.insertBefore(ul, parent.querySelector(".ofertas__iva"));
		}
	}

	// Loader
	ul.innerHTML = "";
	const loadingItem = document.createElement("li");
	loadingItem.className = "ofertas__item";
	loadingItem.textContent = "Cargando ofertas...";
	ul.appendChild(loadingItem);

	const ofertas = await fetchOfertas(effectiveUserId, { force });

	// Filtrar visibles y caducadas según modalidades
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
 * Invalida la cache de un usuario (por ejemplo al forzar cambio externo)
 * @param {number|string} userId
 */
export async function invalidateOfertasCache(userId) {
	const effectiveUserId = await resolveUserId(userId);
	if (effectiveUserId && ofertasCache.has(effectiveUserId)) {
		ofertasCache.delete(effectiveUserId);
	}
}

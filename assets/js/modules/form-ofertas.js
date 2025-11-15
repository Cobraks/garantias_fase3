// assets/js/modules/form-ofertas.js
"use strict";

import {
        getEffectiveUserRole,
        getEffectiveProfessionalId,
        ensureCurrentUserIdReady,
        isAdmin,
        isProfesional,
        isParticular,
} from "./form-role-utils.js";
import { getRestRoot, getRestNonce } from "./config.js";
import {
        setCurrentOfertas,
        getCurrentOfertas,
        getVisibleModalidades,
        setSpecialFixedOffers,
} from "./form-state.js";

const ENABLE_LOGS = true;
function log(...args) {
        if (ENABLE_LOGS) console.log("[form-ofertas]", ...args);
}

const selfOffersPrefetchState = {
        done: false,
        empty: false,
};
let selfOffersPrefetchPromise = null;

function shouldSkipLoaderForSelf({ ofertas = null } = {}) {
        if (!(isProfesional() || isParticular())) {
                return false;
        }
        if (!selfOffersPrefetchState.done) {
                return false;
        }
        const hasOfertas = Array.isArray(ofertas) && ofertas.length > 0;
        if (hasOfertas) {
                return false;
        }
        return selfOffersPrefetchState.empty;
}

// Cache simple por userId con posibilidad de invalidar
const ofertasCache = new Map(); // cacheKey -> { ofertas, especiales, meta, fetchedAt, version }
const CACHE_TTL = 24 * 60 * 60 * 1000; // 24h
const CACHE_VERSION = 3;

function isValidCacheEntry(entry) {
        if (!entry || typeof entry !== "object") return false;
        if (entry.version !== CACHE_VERSION) return false;
        if (!Array.isArray(entry.ofertas)) return false;
        if (!Array.isArray(entry.especiales)) return false;
        if (typeof entry.fetchedAt !== "number") return false;
        if (Date.now() - entry.fetchedAt >= CACHE_TTL) return false;
        return true;
}

function storageKey(cacheKey) {
        return `ofertas_${cacheKey}`;
}

function toCacheKey(userId, { fallbackToSelf = false } = {}) {
        if (userId !== null && typeof userId !== "undefined") {
                return String(userId);
        }
        return fallbackToSelf ? "self" : null;
}

function normalizeOfertasResponse(payload) {
        if (Array.isArray(payload)) {
                return {
                        ofertas: payload,
                        especiales: [],
                        meta: {},
                        tiene_oferta_especial_precio_fijo: false,
                };
        }

        if (!payload || typeof payload !== "object") {
                return {
                        ofertas: [],
                        especiales: [],
                        meta: {},
                        tiene_oferta_especial_precio_fijo: false,
                };
        }

        const ofertas = Array.isArray(payload.ofertas) ? payload.ofertas : [];
        const especiales = Array.isArray(payload.ofertas_precio_fijo)
                ? payload.ofertas_precio_fijo
                : [];
        const meta = payload.meta && typeof payload.meta === "object" ? payload.meta : {};
        const tieneEspecial = Boolean(
                payload.tiene_oferta_especial_precio_fijo || meta.tiene_oferta_especial_precio_fijo
        );

        return {
                ofertas,
                especiales,
                meta,
                tiene_oferta_especial_precio_fijo: tieneEspecial,
        };
}

function applyOfertasState(entry) {
        if (!entry || typeof entry !== "object") {
                setCurrentOfertas([]);
                setSpecialFixedOffers([], { enabled: false });
                return;
        }

        const ofertas = Array.isArray(entry.ofertas) ? entry.ofertas : [];
        const especiales = Array.isArray(entry.especiales) ? entry.especiales : [];
        const meta = entry.meta && typeof entry.meta === "object" ? entry.meta : {};
        const enabledFlag = Boolean(
                meta.enabled ??
                        meta.tieneOfertaEspecial ??
                        meta.tiene_oferta_especial_precio_fijo ??
                        entry.tiene_oferta_especial_precio_fijo ??
                        (especiales.length > 0)
        );

        setCurrentOfertas(ofertas);
        setSpecialFixedOffers(especiales, {
                ...meta,
                enabled: enabledFlag,
        });
}

function getCachedOfertas(cacheKey) {
        if (!cacheKey) return null;

        if (ofertasCache.has(cacheKey)) {
                const cached = ofertasCache.get(cacheKey);
                if (isValidCacheEntry(cached)) {
                        return cached;
                }
                ofertasCache.delete(cacheKey);
        }

        const raw = sessionStorage.getItem(storageKey(cacheKey));
        if (raw) {
                try {
                        const parsed = JSON.parse(raw);
                        if (isValidCacheEntry(parsed)) {
                                ofertasCache.set(cacheKey, parsed);
                                return parsed;
                        }
                        sessionStorage.removeItem(storageKey(cacheKey));
                } catch (e) {
                        // ignore parse errors
                        sessionStorage.removeItem(storageKey(cacheKey));
                }
        }

        return null;
}

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
        const esSinSuplementos = oferta?.tipo_oferta === "sin_suplementos";
        if (!esSinSuplementos && !oferta.porcentaje_descuento && oferta.porcentaje_descuento !== 0)
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
        const useSelfFallback = !effectiveUserId && isProfesional();
        const cacheKey = toCacheKey(effectiveUserId, { fallbackToSelf: useSelfFallback });

        if (!effectiveUserId && !useSelfFallback) {
                return [];
        }

        if (!force) {
                const cached = getCachedOfertas(cacheKey);
                if (cached) {
                        if (ENABLE_LOGS)
                                log(
                                        `Usando cache de ofertas para usuario ${
                                                effectiveUserId ?? "self"
                                        }`
                                );
                        applyOfertasState(cached);
                        return Array.isArray(cached.ofertas) ? cached.ofertas : [];
                }
        }

        const restRoot = getRestRoot();
        const restNonce = getRestNonce();
        const url = effectiveUserId
                ? `${restRoot}go/v1/ofertas-usuario?id=${encodeURIComponent(effectiveUserId)}`
                : `${restRoot}go/v1/ofertas-usuario`;

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
                const payload = await res.json();
                const normalized = normalizeOfertasResponse(payload);
                const entry = {
                        ...normalized,
                        fetchedAt: Date.now(),
                        version: CACHE_VERSION,
                };
                if (cacheKey) {
                        ofertasCache.set(cacheKey, entry);
                        try {
                                sessionStorage.setItem(storageKey(cacheKey), JSON.stringify(entry));
                        } catch (e) {
                                // storage might be full/disabled
                        }
                }
                applyOfertasState(entry);
                return entry.ofertas;
        } catch (e) {
                log("Error al obtener ofertas para usuario", effectiveUserId ?? "self", e);
                applyOfertasState(null);
                return [];
        }
}

function showOfertasLoading(ul = null) {
        const parent = document.querySelector(".form__ofertas");
        if (!parent) return null;
        if (!ul) {
                ul = parent.querySelector("ul.ofertas__list");
                if (!ul) {
                        ul = document.createElement("ul");
                        ul.className = "ofertas__list";
                        parent.insertBefore(ul, parent.querySelector(".ofertas__iva"));
                }
        }
        ul.innerHTML = "";
        if (isParticular()) {
                return ul;
        }
        const li = document.createElement("li");
        li.className = "ofertas__item ofertas__item--loading";
        li.textContent = "Cargando ofertas...";
        ul.appendChild(li);
        return ul;
}

/**
 * Actualiza la lista visible de ofertas en DOM filtrando por modalidades actuales.
 */
export async function updateOfertasList(
        userId,
        modalidadesVisibles = [],
        container = null,
        { force = false, ofertas = null, showLoading = true } = {},
) {
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

        if (!userId) {
                await ensureCurrentUserIdReady();
        }

        await ensureSelfOffersPrefetched();

        const effectiveUserId = resolveUserId(userId);
        if (!effectiveUserId) {
                ul.innerHTML = "";
                applyOfertasState(null);
                return;
        }

        if (showLoading) {
                if (!shouldSkipLoaderForSelf({ ofertas })) {
                        showOfertasLoading(ul);
                }
        }

        const ofertasData =
                ofertas || (await fetchOfertas(effectiveUserId, { force }));
        const now = Date.now() / 1000;

        const visibles = filterOfertasPorModalidades(ofertasData, modalidadesVisibles, {
                incluirCaducadas: false,
        });
        const caducadas = filterOfertasPorModalidades(ofertasData, modalidadesVisibles, {
                incluirCaducadas: true,
        }).filter((o) => {
                if (!o.timestamp_caducidad || o.estado === false) return false;
                const caducada = now > o.timestamp_caducidad;
                if (!caducada) return false;
                if (o?.tipo_oferta === "sin_suplementos") return true;
                return o.porcentaje_descuento > 0;
        });

	// Render
	ul.innerHTML = "";
        if (!visibles.length && !caducadas.length) {
                if (isAdmin()) {
                        const li = document.createElement("li");
                        li.className = "ofertas__item ofertas__item--empty";
                        li.textContent = "Sin ofertas activas";
                        ul.appendChild(li);
                }
                return;
        }

        visibles.forEach((oferta) => {
                const esSinSuplementos = oferta?.tipo_oferta === "sin_suplementos";
                const li = document.createElement("li");
                li.className = "ofertas__item" + (esSinSuplementos ? " ofertas__item--sin-suplementos" : "");
                if (esSinSuplementos) {
                        li.textContent = oferta.nombre || "Sin suplementos";
                } else {
                        li.innerHTML = `${oferta.nombre} <span class="ofertas__percent">-${oferta.porcentaje_descuento}%</span>`;
                }
                ul.appendChild(li);
        });
        caducadas.forEach((oferta) => {
                const li = document.createElement("li");
                const esSinSuplementos = oferta?.tipo_oferta === "sin_suplementos";
                li.className =
                        "ofertas__item ofertas__item--caducada" +
                        (esSinSuplementos ? " ofertas__item--sin-suplementos" : "");
                const span = document.createElement("span");
                span.className = "tachada";
                span.textContent = esSinSuplementos
                        ? oferta.nombre || "Sin suplementos"
                        : `${oferta.nombre}: -${oferta.porcentaje_descuento}%`;
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
export function refreshOfertasDisplay() {
        return new Promise((resolve) => {
                const effectiveUserId = resolveUserId();
                if (!effectiveUserId) {
                        resolve();
                        return;
                }

                const hasCache = !!getCachedOfertas(effectiveUserId);

                if (_refreshOfertasPending) clearTimeout(_refreshOfertasPending);
                _refreshOfertasPending = setTimeout(async () => {
                        if (!hasCache && !shouldSkipLoaderForSelf({})) {
                                showOfertasLoading();
                        }
                        if (isNaProfesionalFallback()) {
                                await ensureCurrentUserIdReady();
                        }
                        const modalidadesVisibles = getVisibleModalidades();
                        await updateOfertasList(
                                effectiveUserId,
                                modalidadesVisibles,
                                null,
                                {
                                        force: !hasCache,
                                        showLoading: false,
                                }
                        );
                        if (ENABLE_LOGS) {
                                log("Refresco ofertas tras cambio de filtros:", {
                                        user: effectiveUserId,
                                        modalidadesVisibles,
                                });
                        }
                        resolve();
                }, 0);
        });
}

// pequeño helper para comprobar si estamos en rol profesional (por compatibilidad)
function isNaProfesionalFallback() {
        const role = getEffectiveUserRole();
        return role === "go_profesional" || role === "profesional";
}

// Exponer para compatibilidad legacy mínima
export { refreshOfertasDisplay as updateOfertas, showOfertasLoading, shouldSkipLoaderForSelf };

function prefetchAllVendorOffers() {
        const select = document.getElementById("usuario-rol");
        if (!select || !select.options) return;
        const ids = Array.from(select.options)
                .map((o) => Number(o.value))
                .filter((id) => !isNaN(id));
        ids.forEach((id) => {
                // ignorar errores individuales
                fetchOfertas(id).catch(() => {});
        });
}

async function prefetchCurrentUserOffers() {
        if (selfOffersPrefetchState.done) return;

        if (isProfesional()) {
                await ensureCurrentUserIdReady();
                const effectiveId = getEffectiveProfessionalId();
                if (!effectiveId) {
                        selfOffersPrefetchState.done = true;
                        selfOffersPrefetchState.empty = true;
                        return;
                }
                try {
                        const ofertas = await fetchOfertas(effectiveId);
                        selfOffersPrefetchState.done = true;
                        selfOffersPrefetchState.empty = !(
                                Array.isArray(ofertas) && ofertas.length > 0
                        );
                } catch (error) {
                        if (ENABLE_LOGS) log("prefetchCurrentUserOffers error", error);
                        selfOffersPrefetchState.done = true;
                        selfOffersPrefetchState.empty = true;
                }
                return;
        }

        if (isParticular()) {
                selfOffersPrefetchState.done = true;
                selfOffersPrefetchState.empty = true;
        }
}

function ensureSelfOffersPrefetched() {
        if (selfOffersPrefetchState.done) {
                return Promise.resolve();
        }
        if (!selfOffersPrefetchPromise) {
                selfOffersPrefetchPromise = prefetchCurrentUserOffers().catch((error) => {
                        if (ENABLE_LOGS) log("ensureSelfOffersPrefetched error", error);
                        selfOffersPrefetchState.done = true;
                        selfOffersPrefetchState.empty = true;
                });
        }
        return selfOffersPrefetchPromise || Promise.resolve();
}

document.addEventListener("DOMContentLoaded", () => {
        prefetchAllVendorOffers();
        ensureSelfOffersPrefetched();
});

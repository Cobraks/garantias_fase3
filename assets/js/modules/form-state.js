// assets/js/modules/form-state.js
"use strict";

/**
 * Estado compartido entre cálculos / ofertas / UI sin contaminar globales.
 * Sólo se expone mediante APIs: get/set/subscribe.
 */

let currentOfertas = null;
let currentOfertasMeta = {};
let visibleModalidades = [];
const formCache = {
        selectedModalidadId: null,
};
let limitesDinamicos = {
        cilindrada: { min: 0, max: 9000 },
        potencia: { min: 0, max: 3000 },
        kilometros: { min: 0, max: Infinity },
};
let specialFixedOffers = [];
let specialFixedOffersMeta = {
        enabled: false,
        raw: null,
};

const listeners = {
        ofertas: [],
        ofertasMeta: [],
        modalidades: [],
        limites: [],
        selectedModalidad: [],
        specialFixedOffers: [],
};

function notify(topic, payload) {
	(listeners[topic] || []).forEach((cb) => cb(payload));
}

// Ofertas
export function setCurrentOfertas(ofertas) {
        currentOfertas = ofertas;
        notify("ofertas", ofertas);
}
export function getCurrentOfertas() {
        return currentOfertas;
}
export function setCurrentOfertasMeta(meta) {
        currentOfertasMeta = meta || {};
        notify("ofertasMeta", currentOfertasMeta);
}
export function getCurrentOfertasMeta() {
        return currentOfertasMeta;
}
export function subscribeOfertasMeta(cb) {
        listeners.ofertasMeta.push(cb);
}
export function subscribeOfertas(cb) {
        listeners.ofertas.push(cb);
}

// Modalidades visibles
export function setVisibleModalidades(mods) {
	visibleModalidades = mods;
	notify("modalidades", mods);
}
export function getVisibleModalidades() {
	return visibleModalidades;
}
export function subscribeModalidades(cb) {
	listeners.modalidades.push(cb);
}

// Modalidad seleccionada persistida
export function setSelectedModalidadId(id) {
	formCache.selectedModalidadId = id;
	notify("selectedModalidad", id);
}
export function getSelectedModalidadId() {
	return formCache.selectedModalidadId;
}
export function subscribeSelectedModalidad(cb) {
        listeners.selectedModalidad.push(cb);
}

// Límites dinámicos
export function setLimitesDinamicos(limits) {
        limitesDinamicos = limits;
        notify("limites", limits);
}
export function getLimitesDinamicos() {
        return limitesDinamicos;
}
export function subscribeLimites(cb) {
        listeners.limites.push(cb);
}

// Ofertas especiales de precio fijo
export function setSpecialFixedOffers(ofertas, meta = {}) {
        specialFixedOffers = Array.isArray(ofertas) ? ofertas : [];
        const enabledFlag = Boolean(
                meta.enabled ??
                        meta.tieneOfertaEspecial ??
                        meta.tiene_oferta_especial_precio_fijo ??
                        (specialFixedOffers.length > 0)
        );
        specialFixedOffersMeta = {
                enabled: enabledFlag,
                raw: meta,
        };
        notify("specialFixedOffers", {
                ofertas: specialFixedOffers,
                meta: specialFixedOffersMeta,
        });
}

export function getSpecialFixedOffers() {
        return specialFixedOffers;
}

export function getSpecialFixedOffersMeta() {
        return specialFixedOffersMeta;
}

export function hasSpecialFixedOffers() {
        return specialFixedOffersMeta.enabled && specialFixedOffers.length > 0;
}

export function subscribeSpecialFixedOffers(cb) {
        listeners.specialFixedOffers.push(cb);
}

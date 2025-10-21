// assets/js/modules/form-state.js
"use strict";

/**
 * Estado compartido entre cálculos / ofertas / UI sin contaminar globales.
 * Sólo se expone mediante APIs: get/set/subscribe.
 */

let currentOfertas = null;
let visibleModalidades = [];
const formCache = {
        selectedModalidadId: null,
};
let limitesDinamicos = {
        cilindrada: { min: 0, max: 9000 },
        potencia: { min: 0, max: 3000 },
        kilometros: { min: 0, max: Infinity },
};
let fixedPriceConfig = { habilitado: false, items: [] };

const listeners = {
        ofertas: [],
        modalidades: [],
        limites: [],
        selectedModalidad: [],
        fixedPrice: [],
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

// Configuración de precios fijos
export function setFixedPriceConfig(config) {
        if (!config || typeof config !== "object") {
                fixedPriceConfig = { habilitado: false, items: [] };
        } else {
                const enabled = !!(config.habilitado ?? config.enabled ?? false);
                const items = Array.isArray(config.items) ? config.items : [];
                fixedPriceConfig = {
                        habilitado: enabled && items.length > 0,
                        items,
                };
        }
        notify("fixedPrice", fixedPriceConfig);
}

export function getFixedPriceConfig() {
        return fixedPriceConfig;
}

export function subscribeFixedPrice(cb) {
        listeners.fixedPrice.push(cb);
}

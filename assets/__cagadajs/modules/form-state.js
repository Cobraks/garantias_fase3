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
};

const listeners = {
	ofertas: [],
	modalidades: [],
	limites: [],
	selectedModalidadId: [],
};

function notify(topic, payload) {
	(listeners[topic] || []).forEach((cb) => cb(payload));
}

// -------------------- Ofertas --------------------
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

// ---------------- Modalidades visibles ----------------
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

// --------- Modalidad seleccionada persistida ----------
export function setSelectedModalidadId(id) {
	formCache.selectedModalidadId = id;
	notify("selectedModalidadId", id);
}
export function getSelectedModalidadId() {
	return formCache.selectedModalidadId;
}
export function subscribeSelectedModalidadId(cb) {
	listeners.selectedModalidadId.push(cb);
}

// ------------- Límites dinámicos --------------------
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

// ------------- Utilities / reset (opcional) ----------
export function clearSelectedModalidad() {
	setSelectedModalidadId(null);
}
export function clearVisibleModalidades() {
	setVisibleModalidades([]);
}
export function clearOfertas() {
	setCurrentOfertas(null);
}
export function resetLimitesDinamicos() {
	setLimitesDinamicos({
		cilindrada: { min: 0, max: 9000 },
		potencia: { min: 0, max: 3000 },
	});
}

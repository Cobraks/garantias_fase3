// assets/js/modules/form-role-utils.js
"use strict";

/**
 * Utilidades para resolver rol de usuario y userId efectivo
 * - Admin puede elegir profesional/vendedor via #usuario-rol
 * - Profesional usa su propio currentUserId
 * - Comercial se trata aparte si hace falta (puede comportarse como admin para ciertos flujos)
 */

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-role-utils]", ...args);
}

/**
 * Normaliza y devuelve el rol efectivo del usuario en frontend.
 * Puede venir de window.userRole o de dataset en body.
 * Siempre en minúsculas.
 */
export function getEffectiveUserRole() {
	const raw =
		(window.userRole && String(window.userRole)) ||
		(document.body?.dataset?.userRole &&
			String(document.body.dataset.userRole)) ||
		"";
	return raw.toLowerCase();
}

export function isAdmin() {
	return getEffectiveUserRole() === "admin";
}

export function isComercial() {
	return getEffectiveUserRole() === "comercial";
}

/**
 * Acepta variantes: 'go_profesional' y 'profesional' por compatibilidad histórica.
 */
export function isProfesional() {
	const role = getEffectiveUserRole();
	return role === "go_profesional" || role === "profesional";
}

/**
 * Helper para leer el select de usuario cuando el rol es admin/comercial.
 * Devuelve el ID numérico o null.
 */
export function getSelectedUserIdForAdmin() {
	const input = document.getElementById("usuario-rol");
	if (input && input.value) {
		const val = parseInt(input.value, 10);
		if (!isNaN(val)) return val;
	}
	return null;
}

/**
 * Devuelve el userId que debe usarse para lógica de ofertas / profesional efectivo:
 * - Si es admin, lo toma del select #usuario-rol (puede ser un profesional).
 * - Si es comercial, se comporta igual que admin (puedes adaptarlo si quieres diferenciar).
 * - Si es profesional, usa window.currentUserId.
 * - En otros casos devuelve null.
 */
export function getEffectiveProfessionalId() {
	if (isAdmin() || isComercial()) {
		return getSelectedUserIdForAdmin();
	}
	if (isProfesional()) {
		if (
			typeof window.currentUserId !== "undefined" &&
			!isNaN(Number(window.currentUserId))
		) {
			return Number(window.currentUserId);
		}
	}
	return null;
}

/**
 * Espera hasta que window.currentUserId sea válido (útil para profesionales).
 * Timeout a los 3 segundos.
 */
export async function ensureCurrentUserIdReady(timeoutMs = 3000) {
	const role = getEffectiveUserRole();
	if (!(role === "go_profesional" || role === "profesional")) return;

	const start = Date.now();
	while (
		(typeof window.currentUserId === "undefined" ||
			isNaN(Number(window.currentUserId))) &&
		Date.now() - start < timeoutMs
	) {
		if (ENABLE_LOGS)
			log(
				"[ensureCurrentUserIdReady] esperando currentUserId, llevamos",
				Date.now() - start,
				"ms"
			);
		await new Promise((r) => setTimeout(r, 100));
	}
	if (ENABLE_LOGS)
		log(
			"[ensureCurrentUserIdReady] resultado currentUserId:",
			window.currentUserId
		);
}

/**
 * Conveniencia: devuelve true si hay un profesional efectivo (admin seleccionó uno o profesional logueado).
 */
export function hasEffectiveProfessionalId() {
	return !!getEffectiveProfessionalId();
}


/*Hacer diff en form-calculations.js, form-ofertas.js y otros*/

/* Importar */
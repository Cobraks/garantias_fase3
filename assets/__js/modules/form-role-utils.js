"use strict";

import { getEl } from "./form-utils.js";

// ==== Constantes de rol ====
export const ROLE_ADMIN = "admin";
export const ROLE_PROFESIONAL = "go_profesional";
export const ROLE_COMERCIAL = "comercial";

// Alias y normalizaciones futuras
const ROLE_ALIASES = {
	[ROLE_PROFESIONAL]: new Set([ROLE_PROFESIONAL]),
	[ROLE_ADMIN]: new Set([ROLE_ADMIN]),
	[ROLE_COMERCIAL]: new Set([ROLE_COMERCIAL]),
};

/**
 * Devuelve el rol efectivo normalizado.
 * @returns {string}
 */
export function getEffectiveUserRole() {
	let role = "";
	if (window.userRole) role = String(window.userRole).toLowerCase();
	else if (document.body?.dataset?.userRole)
		role = String(document.body.dataset.userRole).toLowerCase();
	else role = "";

	for (const [canonical, aliases] of Object.entries(ROLE_ALIASES)) {
		if (aliases.has(role)) return canonical;
	}
	return role;
}

export function isAdmin() {
	return getEffectiveUserRole() === ROLE_ADMIN;
}

export function isProfesional() {
	return getEffectiveUserRole() === ROLE_PROFESIONAL;
}

export function isComercial() {
	return getEffectiveUserRole() === ROLE_COMERCIAL;
}

/**
 * Devuelve el userId efectivo para obtener ofertas.
 * @param {string} selector - id del input admin (por defecto "usuario-rol")
 * @returns {number|null}
 */
export function getEffectiveProfessionalId(selector = "usuario-rol") {
	if (isAdmin()) {
		const input = getEl(selector);
		if (input && input.value) {
			const val = parseInt(input.value, 10);
			if (!isNaN(val)) return val;
		}
		return null;
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
 * Si el rol es profesional, espera hasta que currentUserId esté listo (o expira).
 * @param {number} timeout
 */
export async function ensureCurrentUserIdReady(timeout = 3000) {
	if (!isProfesional()) return;
	const start = Date.now();
	while (
		(typeof window.currentUserId === "undefined" ||
			isNaN(Number(window.currentUserId))) &&
		Date.now() - start < timeout
	) {
		await new Promise((r) => setTimeout(r, 100));
	}
}

// Export por defecto para compatibilidad
export default {
	getEffectiveUserRole,
	isAdmin,
	isProfesional,
	isComercial,
	getEffectiveProfessionalId,
	ensureCurrentUserIdReady,
};

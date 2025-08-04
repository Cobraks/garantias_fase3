// assets/js/modules/form-role-utils.js
"use strict";

import { getUserRole, getCurrentUserId } from "./config.js";

/**
 * Utilidades para resolver rol de usuario y userId efectivo.
 * - Admin/comercial puede seleccionar profesional/vendedor via #usuario-rol.
 * - Profesional usa su propio currentUserId (vía config.js).
 */

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-role-utils]", ...args);
}

/**
 * Devuelve el rol efectivo del usuario en frontend (minúsculas).
 */
export function getEffectiveUserRole() {
	return String(getUserRole() || "").toLowerCase();
}

export function isAdmin() {
	return getEffectiveUserRole() === "admin";
}

export function isComercial() {
	return getEffectiveUserRole() === "comercial";
}

/**
 * Acepta variantes históricas para profesional.
 */
export function isProfesional() {
	const role = getEffectiveUserRole();
	return role === "go_profesional" || role === "profesional";
}

/**
 * Lee el select #usuario-rol si el rol es admin/comercial y devuelve el ID numérico o null.
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
 * Devuelve el userId que se debe usar como profesional efectivo:
 * - Admin/comercial: del select.
 * - Profesional: currentUserId de config.
 * - Otros: null.
 */
export function getEffectiveProfessionalId() {
	if (isAdmin() || isComercial()) {
		return getSelectedUserIdForAdmin();
	}
	if (isProfesional()) {
		const id = getCurrentUserId();
		if (id !== null && !isNaN(Number(id))) {
			return Number(id);
		}
	}
	return null;
}

/**
 * Espera hasta que currentUserId válido esté disponible (para profesionales).
 * Timeout por defecto 3s.
 */
export async function ensureCurrentUserIdReady(timeoutMs = 3000) {
	if (!isProfesional()) return;

	const start = Date.now();
	while (
		(getCurrentUserId() === null || isNaN(Number(getCurrentUserId()))) &&
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
			getCurrentUserId()
		);
}

/**
 * Conveniencia: true si hay un profesional efectivo resuelto.
 */
export function hasEffectiveProfessionalId() {
	return !!getEffectiveProfessionalId();
}

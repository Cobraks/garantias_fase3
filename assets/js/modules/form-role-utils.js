// assets/js/modules/form-role-utils.js
"use strict";

import { getUserRole, getCurrentUserId } from "./config.js";

/**
 * Utilidades para resolver rol de usuario y userId efectivo
 * - Admin puede elegir profesional/vendedor via #usuario-rol
 * - Profesional usa su propio currentUserId (vía config.js)
 * - Comercial se trata igual que admin en esta lógica
 */

const ENABLE_LOGS = true;
function log(...args) {
	if (ENABLE_LOGS) console.log("[form-role-utils]", ...args);
}

/**
 * Normaliza y devuelve el rol efectivo del usuario en frontend.
 * Siempre en minúsculas.
 */
export function getEffectiveUserRole() {
        const rawRole = String(getUserRole() || "").toLowerCase();
        switch (rawRole) {
                case "go_comercial":
                        return "comercial";
                case "go_director_comercial":
                case "go_garantias":
                        return "admin";
                case "profesional":
                        return "go_profesional";
                case "go_particular":
                case "particular":
                case "go_individual":
                case "individual":
                        return "go_particular";
                default:
                        return rawRole;
        }
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

export function isParticular() {
        const role = getEffectiveUserRole();
        return (
                role === "go_particular" ||
                role === "particular" ||
                role === "go_individual" ||
                role === "individual"
        );
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
 * - Si es admin o comercial, lo toma del select #usuario-rol.
 * - Si es profesional, usa currentUserId (vía config.js).
 * - En otros casos devuelve null.
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
 * Espera hasta que currentUserId válido esté disponible (útil para profesionales).
 * Timeout configurable (por defecto 3 segundos).
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
 * Conveniencia: devuelve true si hay un profesional efectivo (admin seleccionó uno o profesional logueado).
 */
export function hasEffectiveProfessionalId() {
	return !!getEffectiveProfessionalId();
}

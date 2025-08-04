// assets/js/modules/config.js
"use strict";

/**
 * Wrapper central para leer la configuración expuesta desde el servidor.
 * Preferimos window.__GO_CONFIG__ y solo si no existe caemos a legacy.
 */

function getRawConfig() {
	return (typeof window !== "undefined" && window.__GO_CONFIG__) || {};
}

// REST
export function getRestRoot() {
	return (
		(getRawConfig().rest && getRawConfig().rest.root) ||
		(typeof window !== "undefined" && window.GO_REST && window.GO_REST.root) ||
		"/wp-json/"
	);
}

export function getRestNonce() {
	return (
		(getRawConfig().rest && getRawConfig().rest.nonce) ||
		(typeof window !== "undefined" && window.GO_REST && window.GO_REST.nonce) ||
		""
	);
}

// Usuario / rol
export function getUserRole() {
	const roleFromConfig = getRawConfig().user?.role;
	if (roleFromConfig) return String(roleFromConfig).toLowerCase();
	if (typeof window !== "undefined" && typeof window.userRole !== "undefined")
		return String(window.userRole).toLowerCase();
	return "";
}

export function getCurrentUserId() {
	const idFromConfig = getRawConfig().user?.currentUserId;
	if (typeof idFromConfig !== "undefined" && idFromConfig !== null) {
		const n = Number(idFromConfig);
		if (!isNaN(n)) return n;
	}
	if (
		typeof window !== "undefined" &&
		typeof window.currentUserId !== "undefined"
	) {
		const n = Number(window.currentUserId);
		if (!isNaN(n)) return n;
	}
	return null;
}

// Íconos
export function getIcon(name) {
	if (
		getRawConfig().icons &&
		typeof getRawConfig().icons[name] !== "undefined"
	) {
		return getRawConfig().icons[name];
	}
	if (
		typeof window !== "undefined" &&
		window.GO_ICONS &&
		typeof window.GO_ICONS[name] !== "undefined"
	) {
		return window.GO_ICONS[name];
	}
	return "";
}

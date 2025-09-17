// assets/js/modules/config.js
"use strict";

/**
 * Wrapper central para leer la configuración expuesta desde el servidor.
 */

function getRawConfig() {
        return window.__GO_CONFIG__ || {};
}

// REST
export function getRestRoot() {
        return getRawConfig().rest?.root || "/wp-json/";
}

export function getRestNonce() {
        return getRawConfig().rest?.nonce || "";
}

// Usuario / rol
export function getUserRole() {
        const roleFromConfig = getRawConfig().user?.role;
        if (roleFromConfig) return String(roleFromConfig).toLowerCase();
        return "";
}

export function getCurrentUserId() {
        const idFromConfig = getRawConfig().user?.currentUserId;
        if (typeof idFromConfig !== "undefined" && idFromConfig !== null) {
                const n = Number(idFromConfig);
                if (!isNaN(n)) return n;
        }
        return null;
}

// Íconos
export function getIcon(name) {
        const icons = getRawConfig().icons || {};
        if (typeof icons[name] !== "undefined") {
                return icons[name];
        }
        return "";
}

export function getPageUrl(key) {
        if (!key) return "";
        const pages = getRawConfig().pages || {};
        const value = pages[key];
        return typeof value === "string" ? value : "";
}

export function getMisGarantiasUrl() {
        return getPageUrl("misGarantias");
}

export function getNuevaGarantiaUrl() {
        return getPageUrl("nuevaGarantia");
}

export function getDocumentUrl(key) {
        if (!key) return "";
        const docs = getRawConfig().documents || {};
        const value = docs[key];
        return typeof value === "string" ? value : "";
}

// assets/js/modules/form-user-select.js
"use strict";

import {
        getRestRoot,
        getRestNonce,
        getUserRole,
        getCurrentUserId,
} from "./config.js";
import { updateNextButtonState } from "./form-navigation.js";
import UserPicker, { escapeHtml } from "./user-picker.js";

const ADMIN_EQUIVALENT_ROLES = new Set([
        "admin",
        "go_garantias",
        "go_director_comercial",
]);
const COMERCIAL_EQUIVALENT_ROLES = new Set(["comercial", "go_comercial"]);
const PROFESIONAL_EQUIVALENT_ROLES = new Set(["profesional", "go_profesional"]);

function normalizeRole(value) {
        return String(value || "").toLowerCase();
}

function isAdminLike(role) {
        return ADMIN_EQUIVALENT_ROLES.has(normalizeRole(role));
}

function isComercial(role) {
        return COMERCIAL_EQUIVALENT_ROLES.has(normalizeRole(role));
}

function isProfesional(role) {
        return PROFESIONAL_EQUIVALENT_ROLES.has(normalizeRole(role));
}

function getChannelSlug(value) {
        const normalized = normalizeRole(value);
        switch (normalized) {
                case "go_profesional":
                case "profesional":
                        return "profesional";
                case "go_particular":
                case "particular":
                        return "particular";
                case "go_gestoria":
                case "gestoria":
                        return "gestoria";
                default:
                        return "";
        }
}

function getRoleValueForFetch(channelSlug) {
        switch (channelSlug) {
                case "profesional":
                        return "go_profesional";
                case "particular":
                        return "go_particular";
                case "gestoria":
                        return "go_gestoria";
                default:
                        return "";
        }
}

function channelRequiresAssignment(channelSlug) {
        return channelSlug === "profesional" || channelSlug === "particular";
}

function computeInitials(text = "") {
        const value = String(text).trim();
        if (!value) return "?";
        const parts = value.split(/\s+/).filter(Boolean);
        if (!parts.length) return value.charAt(0).toUpperCase();
        const first = parts[0].charAt(0);
        const last = parts.length > 1 ? parts[parts.length - 1].charAt(0) : "";
        return `${first}${last}`.toUpperCase() || first.toUpperCase();
}

function buildOption(user, channelSlug) {
        if (!user) return null;
        const personalName = (user.personal_name || user.display_name || "").trim();
        const companyName = (
                user.company_name || (user.company && user.company.name) || ""
        ).trim();
        const email = (user.email || "").trim();

        const fallbackLabel = `Usuario #${user.id}`;
        let label = fallbackLabel;
        if (channelSlug === "profesional") {
                        label = companyName || personalName || email || fallbackLabel;
        } else if (channelSlug === "particular") {
                        label = personalName || email || companyName || fallbackLabel;
        } else {
                        label = personalName || companyName || email || fallbackLabel;
        }

        const secondaryParts = [];
        if (channelSlug === "profesional") {
                if (personalName && personalName !== label) secondaryParts.push(personalName);
                if (companyName && companyName !== label) secondaryParts.push(companyName);
        } else if (channelSlug === "particular") {
                if (companyName && companyName !== label) secondaryParts.push(companyName);
        }
        if (email && !secondaryParts.includes(email)) secondaryParts.push(email);

        return {
                id: String(user.id),
                label,
                secondary: secondaryParts.join(" · "),
                avatar: user.avatar || "",
                initials: computeInitials(personalName || companyName || email),
                email,
                personalName,
                companyName,
                displayValue: label,
        };
}

function renderOption(option) {
        const avatar = option.avatar
                ? `<img src="${escapeHtml(option.avatar)}" alt="" class="user-picker__avatar" loading="lazy" decoding="async" />`
                : `<span class="user-picker__avatar user-picker__avatar--placeholder">${escapeHtml(
                                option.initials || "?",
                        )}</span>`;

        const lines = [`<span class="user-picker__primary">${escapeHtml(option.label)}</span>`];

        if (option.personalName && option.personalName !== option.label) {
                lines.push(
                        `<span class="user-picker__meta">${escapeHtml(option.personalName)}</span>`,
                );
        }
        if (
                option.companyName &&
                option.companyName !== option.label &&
                option.companyName !== option.personalName
        ) {
                lines.push(
                        `<span class="user-picker__meta">${escapeHtml(option.companyName)}</span>`,
                );
        }
        if (option.email) {
                lines.push(
                        `<span class="user-picker__meta user-picker__meta--muted">${escapeHtml(
                                option.email,
                        )}</span>`,
                );
        } else if (option.secondary && lines.length === 1) {
                lines.push(
                        `<span class="user-picker__meta">${escapeHtml(option.secondary)}</span>`,
                );
        }

        return `${avatar}<span class="user-picker__info">${lines.join("")}</span>`;
}

let pickerInstance = null;
let currentChannelSlug = "";
let currentFetchRole = "";
let searchAbortController = null;
let resolveAbortController = null;
let baseUserRole = "";

async function fetchUsuariosList(role, searchTerm = "") {
        if (!role) return [];
        if (searchAbortController) searchAbortController.abort();
        const controller = new AbortController();
        searchAbortController = controller;
        const params = new URLSearchParams({ role });
        if (searchTerm) params.append("search", searchTerm);
        try {
                const res = await fetch(`${getRestRoot()}go/v1/usuarios?${params.toString()}`, {
                        method: "GET",
                        credentials: "include",
                        headers: {
                                "X-WP-Nonce": getRestNonce(),
                                Accept: "application/json",
                        },
                        signal: controller.signal,
                });
                if (!res.ok) throw new Error(`REST ${res.status}`);
                const data = await res.json();
                return Array.isArray(data) ? data : [];
        } catch (error) {
                if (error.name === "AbortError") return [];
                console.warn("[form-user-select] fetchUsuariosList error", error);
                return [];
        }
}

async function fetchUsuarioById(role, id) {
        if (!id) return null;
        if (resolveAbortController) resolveAbortController.abort();
        const controller = new AbortController();
        resolveAbortController = controller;
        const params = new URLSearchParams({ id });
        if (role) params.append("role", role);
        try {
                const res = await fetch(`${getRestRoot()}go/v1/usuarios?${params.toString()}`, {
                        method: "GET",
                        credentials: "include",
                        headers: {
                                "X-WP-Nonce": getRestNonce(),
                                Accept: "application/json",
                        },
                        signal: controller.signal,
                });
                if (!res.ok) throw new Error(`REST ${res.status}`);
                const data = await res.json();
                if (Array.isArray(data) && data.length) return data[0];
                return null;
        } catch (error) {
                if (error.name === "AbortError") return null;
                console.warn("[form-user-select] fetchUsuarioById error", error);
                return null;
        }
}

function ensurePicker() {
        if (pickerInstance) return pickerInstance;

        const container = document.querySelector("[data-user-picker-container]");
        if (!container) return null;
        const input = container.querySelector("[data-user-picker-input]");
        const valueInput = container.querySelector("[data-user-picker-target]");
        const results = container.querySelector("[data-user-picker-results]");
        const clearBtn = container.querySelector("[data-user-picker-clear]");
        const labelEl = container.querySelector("[data-user-picker-label]");

        if (!input || !valueInput || !results) return null;

        pickerInstance = new UserPicker({
                container,
                input,
                valueInput,
                resultsContainer: results,
                clearButton: clearBtn,
                labelElement: labelEl,
                renderItem: renderOption,
                messages: {
                        empty: "Sin usuarios disponibles",
                        noResults: "No se han encontrado coincidencias",
                        loading: "Buscando usuarios...",
                        error: "No ha sido posible cargar los usuarios.",
                },
                onSearch: async () => [],
                resolveById: async (id) => {
                        if (!id) return null;
                        const user = await fetchUsuarioById(currentFetchRole, id);
                        const slug =
                                currentChannelSlug ||
                                getChannelSlug(document.getElementById("canal-venta")?.value);
                        return user ? buildOption(user, slug || "profesional") : null;
                },
                onChange: (option) => {
                        const selectedId = option ? option.id : null;
                        refreshMetodoPagoPorUsuario(selectedId);
                        updateNextButtonState();
                },
        });

        return pickerInstance;
}

function configurePickerForChannel(channelSlug, { keepValue = false } = {}) {
        const picker = ensurePicker();
        if (!picker) return;

        const container = picker.container;
        const input = picker.input;

        currentChannelSlug = channelSlug;
        currentFetchRole = getRoleValueForFetch(channelSlug);

        const requiresAssignment = channelRequiresAssignment(channelSlug);
        if (!requiresAssignment) {
                if (container) {
                        container.style.display = "none";
                        container.classList.remove("has-value");
                }
                if (input) {
                        input.required = false;
                        input.value = "";
                }
                picker.clear({ preserveText: false, silent: false });
                return;
        }

        if (container) {
                container.style.display = "";
        }
        if (input) {
                input.required = true;
        }

        const isComercialRole =
                baseUserRole === "comercial" || baseUserRole === "go_comercial";
        const labelText =
                channelSlug === "particular"
                        ? "Cliente particular"
                        : isComercialRole
                        ? "Profesional asignado"
                        : "Vendedor";
        const placeholderText =
                channelSlug === "particular"
                        ? "Buscar por nombre, apellidos o correo..."
                        : "Buscar profesional por empresa, responsable o correo...";
        const messages =
                channelSlug === "particular"
                        ? {
                                  empty: "Sin particulares disponibles",
                                  noResults: "No se encontraron particulares",
                                  loading: "Buscando particulares...",
                                  error: "No ha sido posible cargar los particulares.",
                          }
                        : {
                                  empty: "Sin profesionales disponibles",
                                  noResults: "No se encontraron profesionales",
                                  loading: "Buscando profesionales...",
                                  error: "No ha sido posible cargar los profesionales.",
                          };

        picker.setLabel(labelText);
        picker.setPlaceholder(placeholderText);
        picker.setMessages(messages);

        picker.setSearchProvider(async (term) => {
                const users = await fetchUsuariosList(currentFetchRole, term);
                return users
                        .map((user) => buildOption(user, currentChannelSlug))
                        .filter(Boolean);
        });

        picker.setResolveHandler(async (id) => {
                const user = await fetchUsuarioById(currentFetchRole, id);
                return user ? buildOption(user, currentChannelSlug) : null;
        });

        if (!keepValue) {
                picker.clear({ preserveText: false, silent: false });
        }

        const selectedId = picker.valueInput ? picker.valueInput.value : null;
        refreshMetodoPagoPorUsuario(selectedId || null);
        updateNextButtonState();
}

async function fetchEstadoSepa(targetUserId = null) {
        const restRoot = getRestRoot();
        const restNonce = getRestNonce();
        let url = `${restRoot}go/v1/estado-sepa`;

        if (isAdminLike(getUserRole()) && targetUserId) {
                url += `?id=${encodeURIComponent(targetUserId)}`;
        }

        try {
                const res = await fetch(url, {
                        method: "GET",
                        credentials: "include",
                        headers: {
                                "X-WP-Nonce": restNonce,
                                Accept: "application/json",
                        },
                        cache: "no-cache",
                });
                if (!res.ok) throw new Error("Error al obtener estado SEPA");
                const json = await res.json();
                return !!json.estado_sepa;
        } catch (e) {
                console.warn("[form-user-select] fallo al obtener estado SEPA:", e);
                return false;
        }
}

async function refreshMetodoPagoPorUsuario(targetUserId) {
        const select = document.getElementById("metodo_pago");
        const mensaje = document.querySelector(".mensaje_falta_sepa");
        if (!select) return;

        const canalSelect = document.getElementById("canal-venta");
        const canalSlug = canalSelect ? getChannelSlug(canalSelect.value) : currentChannelSlug;

        select.innerHTML = "";
        const optTransfer = document.createElement("option");
        optTransfer.value = "transferencia";
        optTransfer.textContent = "Transferencia bancaria";
        select.appendChild(optTransfer);

        if (canalSlug === "particular") {
                optTransfer.selected = true;
                if (mensaje) mensaje.style.display = "none";
                const container = select.closest(".form__input-container");
                if (container) {
                        container.classList.toggle("has-value", !!select.value);
                }
                updateNextButtonState();
                return;
        }

        const tieneSepa = await fetchEstadoSepa(targetUserId);
        if (tieneSepa) {
                const optDomic = document.createElement("option");
                optDomic.value = "domiciliacion";
                optDomic.textContent = "Domiciliación bancaria";
                optDomic.selected = true;
                select.appendChild(optDomic);
                optTransfer.selected = false;
                if (mensaje) mensaje.style.display = "none";
        } else {
                optTransfer.selected = true;
                if (mensaje) mensaje.style.display = "flex";
        }

        const container = select.closest(".form__input-container");
        if (container) {
                container.classList.toggle("has-value", !!select.value);
        }
        updateNextButtonState();
}

function setupSepaWatcher() {
        const canalSelect = document.getElementById("canal-venta");
        const usuarioHidden = document.getElementById("usuario-rol");

        if (usuarioHidden) {
                usuarioHidden.addEventListener("change", () => {
                        const target = usuarioHidden.value || getCurrentUserId() || null;
                        refreshMetodoPagoPorUsuario(target);
                });
        }

        if (canalSelect) {
                canalSelect.addEventListener("change", () => {
                        const role = getUserRole();
                        let target = null;
                        if (isProfesional(role)) {
                                target = getCurrentUserId();
                        } else if (isAdminLike(role) || isComercial(role)) {
                                const v = document.getElementById("usuario-rol");
                                target = (v && v.value) || getCurrentUserId();
                        } else {
                                target = getCurrentUserId();
                        }
                        refreshMetodoPagoPorUsuario(target);
                });
        }

        let initialTarget = null;
        const role = getUserRole();
        if (isProfesional(role)) {
                initialTarget = getCurrentUserId();
        } else if (isAdminLike(role) || isComercial(role)) {
                const v = document.getElementById("usuario-rol");
                initialTarget = (v && v.value) || getCurrentUserId();
        } else {
                initialTarget = getCurrentUserId();
        }
        refreshMetodoPagoPorUsuario(initialTarget);
}

function initUserSelect() {
        const canalSelect = document.getElementById("canal-venta");
        const rawRole = getUserRole() || document.body.dataset.userRole || "";
        const normalizedRole = normalizeRole(rawRole);
        baseUserRole = normalizedRole;

        let initialChannel = "";
        if (isAdminLike(normalizedRole)) {
                if (canalSelect && !canalSelect.value) {
                        const defaultOption =
                                canalSelect.querySelector('option[value="go_profesional"]') ||
                                canalSelect.querySelector('option[value="profesional"]');
                        if (defaultOption) {
                                defaultOption.selected = true;
                                canalSelect.value = defaultOption.value;
                        }
                }
                initialChannel = getChannelSlug(canalSelect?.value) || "profesional";
        } else if (isComercial(normalizedRole)) {
                if (canalSelect) {
                        canalSelect.value = "go_profesional";
                }
                initialChannel = "profesional";
        } else if (normalizedRole === "go_particular" || normalizedRole === "particular") {
                initialChannel = "particular";
        } else if (normalizedRole === "go_gestoria" || normalizedRole === "gestoria") {
                initialChannel = "gestoria";
        }

        currentChannelSlug = initialChannel;
        currentFetchRole = getRoleValueForFetch(initialChannel);

        const picker = ensurePicker();

        if (initialChannel) {
                configurePickerForChannel(initialChannel, { keepValue: true });
        } else if (picker && picker.container) {
                picker.container.style.display = "none";
        }

        if (isAdminLike(normalizedRole) && canalSelect) {
                canalSelect.addEventListener("change", (event) => {
                        configurePickerForChannel(getChannelSlug(event.target.value));
                });
        } else if (isComercial(normalizedRole) && canalSelect) {
                canalSelect.addEventListener("change", (event) => {
                        configurePickerForChannel(getChannelSlug(event.target.value));
                });
        }

        setupSepaWatcher();
}

export default initUserSelect;

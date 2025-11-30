// assets/js/modules/form-user-select.js
"use strict";

import {
        getRestRoot,
        getRestNonce,
        getUserRole,
        getCurrentUserId,
} from "./config.js";
import { updateNextButtonState } from "./form-navigation.js";

const ADMIN_EQUIVALENT_ROLES = new Set([
        "admin",
        "go_garantias",
        "go_director_comercial",
]);
const COMERCIAL_EQUIVALENT_ROLES = new Set([
        "comercial",
        "go_comercial",
]);
const PROFESIONAL_EQUIVALENT_ROLES = new Set([
        "profesional",
        "go_profesional",
]);

const CHANNEL_NORMALIZATION = {
        profesional: "profesional",
        go_profesional: "profesional",
        particular: "particular",
        go_particular: "particular",
        individual: "particular",
        go_individual: "particular",
        gestoria: "gestoria",
        go_gestoria: "gestoria",
};

const CHANNEL_ROLE_MAP = {
        profesional: "go_profesional",
        particular: "go_particular",
        gestoria: "go_gestoria",
};

const CHANNEL_TEXTS = {
        profesional: {
                placeholder: "Selecciona profesional",
                empty: "Sin profesionales disponibles",
                loading: "Cargando profesionales...",
                error: "Error cargando profesionales",
                labelAdmin: "Vendedor",
                labelComercial: "Profesional asignado",
        },
        particular: {
                placeholder: "Selecciona cliente",
                empty: "Sin particulares disponibles",
                loading: "Cargando particulares...",
                error: "Error cargando particulares",
                labelAdmin: "Cliente particular",
                labelComercial: "Cliente particular",
        },
        gestoria: {
                placeholder: "Selecciona gestoría",
                empty: "Sin gestorías disponibles",
                loading: "Cargando gestorías...",
                error: "Error cargando gestorías",
                labelAdmin: "Gestoría",
                labelComercial: "Gestoría",
        },
};

const PAYMENT_LABELS = {
        transferencia: "Transferencia bancaria",
        domiciliacion: "Domiciliación bancaria",
};

const PAYMENT_OPTIONS = [
        { value: "transferencia", label: PAYMENT_LABELS.transferencia },
        { value: "domiciliacion", label: PAYMENT_LABELS.domiciliacion },
];

const ADMIN_SEPA_MESSAGES = {
        sin_rellenar_sepa: (company) =>
                `${company} no ha rellenado todavía el SEPA`,
        pendiente_firma: (company) =>
                `${company} no ha firmado todavía el SEPA`,
        pendiente_validacion: (company) =>
                `${company} tiene el SEPA firmado pendiente de validación`,
        deshabilitado: (company) =>
                `${company} tiene la domiciliación bancaria deshabilitada`,
};

let baseUserRole = "";
let currentChannelSlug = "";
let usuariosRequestId = 0;
let defaultSepaWarningText = "";

function normalizeRole(value) {
        return String(value || "").toLowerCase();
}

function getChannelSlug(value) {
        if (!value) return "";
        const key = String(value).toLowerCase();
        return CHANNEL_NORMALIZATION[key] || "";
}

function getRoleValueForFetch(channelSlug) {
        return CHANNEL_ROLE_MAP[channelSlug] || "";
}

function getChannelTexts(channelSlug) {
        return CHANNEL_TEXTS[channelSlug] || CHANNEL_TEXTS.profesional;
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

function channelRequiresAssignment(channelSlug) {
        return channelSlug === "profesional" || channelSlug === "particular";
}

function buildUserLabel(user, channelSlug) {
        if (!user) return "";
        const personalName = (user.personal_name || user.display_name || "").trim();
        const companyName = (user.company_name || user.company?.name || "").trim();
        const email = (user.email || "").trim();
        const fallback = `Usuario #${user.id}`;

        if (channelSlug === "particular") {
                return personalName || email || fallback;
        }

        if (channelSlug === "profesional") {
                return companyName || personalName || email || fallback;
        }

        return personalName || companyName || email || fallback;
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
                if (!json || typeof json !== "object") {
                        return null;
                }
                return {
                        estado: json.estado_sepa || "",
                        status: json.status || null,
                        activar: Boolean(json.activar),
                        solicitado: Boolean(json.solicitado),
                        metodo: json.metodo || null,
                };
        } catch (e) {
                console.warn("[form-user-select] fallo al obtener estado SEPA:", e);
                return null;
        }
}

function updateSelectDataset(select) {
        if (!select) return;
        const selectedOption = select.options[select.selectedIndex];
        const label =
                selectedOption &&
                !selectedOption.disabled &&
                selectedOption.value
                        ? selectedOption.textContent
                        : "";
        select.dataset.displayLabel = label ? label.trim() : "";
}

async function refreshMetodoPagoPorUsuario(targetUserId) {
        const select = document.getElementById("metodo_pago");
        const mensaje = document.querySelector(".mensaje_falta_sepa");
        if (!select) return;

        if (mensaje && !defaultSepaWarningText) {
                defaultSepaWarningText = mensaje.textContent.trim();
        }

        const canalSelect = document.getElementById("canal-venta");
        const canalSlug = canalSelect
                ? getChannelSlug(canalSelect.value)
                : currentChannelSlug;

        select.innerHTML = "";

        const canalEsParticular = canalSlug === "particular";

        const role = baseUserRole || normalizeRole(getUserRole());
        const esAdmin = isAdminLike(role);

        const appendOptions = (options, selectedValue) => {
                options.forEach((option) => {
                        const opt = document.createElement("option");
                        opt.value = option.value;
                        opt.textContent = option.label;
                        if (option.value === selectedValue) {
                                opt.selected = true;
                        }
                        select.appendChild(opt);
                });
        };

        const ensureDefaultSelected = () => {
                if (!select.value && select.options.length) {
                        select.options[0].selected = true;
                }
        };

        const usuarioSelect = document.getElementById("usuario-rol");
        const selectedUsuarioOption = usuarioSelect
                ? usuarioSelect.options[usuarioSelect.selectedIndex]
                : null;
        const selectedCompanyName = selectedUsuarioOption
                ? (selectedUsuarioOption.dataset.companyName || selectedUsuarioOption.textContent || "")
                : "";

        const companyForMessages = selectedCompanyName.trim() || "Este profesional";

        let metodoValue = "transferencia";
        let sepaInfo = null;

        if (!canalEsParticular && targetUserId) {
                sepaInfo = await fetchEstadoSepa(targetUserId);
        }

        const metodoDesdeApi = sepaInfo ? sepaInfo.metodo : null;

        if (metodoDesdeApi && typeof metodoDesdeApi === "object") {
                const value = metodoDesdeApi.value || "";
                if (value) {
                        metodoValue = String(value).toLowerCase();
                }
        } else if (typeof metodoDesdeApi === "string") {
                const value = metodoDesdeApi.toLowerCase();
                metodoValue = value === "domiciliación" ? "domiciliacion" : value;
        }

        if (canalEsParticular) {
                appendOptions([PAYMENT_OPTIONS[0]], "transferencia");
                select.disabled = true;
        } else if (!targetUserId) {
                if (esAdmin) {
                        appendOptions(PAYMENT_OPTIONS, "transferencia");
                        select.disabled = false;
                } else {
                        appendOptions([PAYMENT_OPTIONS[0]], "transferencia");
                        select.disabled = true;
                }
        } else if (esAdmin) {
                if (metodoValue === "domiciliacion") {
                        appendOptions(PAYMENT_OPTIONS, "domiciliacion");
                        select.disabled = false;
                } else {
                        appendOptions([PAYMENT_OPTIONS[0]], "transferencia");
                        select.disabled = true;
                }
        } else {
                const label =
                        PAYMENT_LABELS[metodoValue] || PAYMENT_LABELS.transferencia;
                appendOptions([
                        { value: metodoValue, label },
                ], metodoValue);
                select.disabled = true;
        }

        ensureDefaultSelected();

        if (mensaje) {
                if (esAdmin && sepaInfo && sepaInfo.estado) {
                        const formatter = ADMIN_SEPA_MESSAGES[sepaInfo.estado] || null;
                        if (formatter) {
                                mensaje.textContent = formatter(companyForMessages);
                                mensaje.style.display = "flex";
                        } else {
                                mensaje.style.display = "none";
                        }
                } else if (!canalEsParticular && metodoValue === "transferencia") {
                        mensaje.textContent = defaultSepaWarningText;
                        mensaje.style.display = "flex";
                } else {
                        mensaje.style.display = "none";
                }
        }

        const container = select.closest(".form__input-container");
        if (container) {
                container.classList.toggle("has-value", !!select.value);
        }
        updateSelectDataset(select);
        select.dispatchEvent(new Event("change", { bubbles: true }));
        updateNextButtonState();
}

async function loadUsuariosPorCanal(channelSlug, { keepValue = false } = {}) {
        const select = document.getElementById("usuario-rol");
        if (!select) return;

        const role = getRoleValueForFetch(channelSlug);
        const texts = getChannelTexts(channelSlug);
        const previousValue = keepValue ? select.value : "";

        const requestId = ++usuariosRequestId;

        if (!role) {
                select.innerHTML = "";
                select.value = "";
                updateSelectDataset(select);
                select.dispatchEvent(new Event("change", { bubbles: true }));
                return;
        }

        select.innerHTML = `<option value="">${texts.loading}</option>`;

        try {
                const res = await fetch(
                        `${getRestRoot()}go/v1/usuarios?role=${encodeURIComponent(role)}`,
                        {
                                method: "GET",
                                credentials: "include",
                                headers: {
                                        "X-WP-Nonce": getRestNonce(),
                                        Accept: "application/json",
                                },
                        },
                );
                if (!res.ok) throw new Error(`REST ${res.status}`);
                const data = await res.json();

                if (requestId !== usuariosRequestId) {
                        return;
                }

                select.innerHTML = "";
                const defaultOption = document.createElement("option");
                defaultOption.value = "";
                defaultOption.textContent = texts.placeholder;
                defaultOption.disabled = true;
                defaultOption.selected = true;
                select.appendChild(defaultOption);

                if (Array.isArray(data) && data.length) {
                        data.forEach((user) => {
                                const option = document.createElement("option");
                                option.value = user.id;
                                option.textContent = buildUserLabel(user, channelSlug);
                                option.dataset.companyName =
                                        user.company_name || user.company?.name || "";
                                option.dataset.personalName =
                                        user.personal_name || user.display_name || "";
                                select.appendChild(option);
                        });

                        let targetValue = "";
                        if (keepValue && previousValue) {
                                targetValue = String(previousValue);
                        }
                        if (!targetValue) {
                                const pending = select.dataset.pendingValue || "";
                                if (pending) {
                                        targetValue = String(pending);
                                }
                        }
                        if (targetValue) {
                                const existing = Array.from(select.options).find(
                                        (opt) => opt.value === targetValue,
                                );
                                if (existing) {
                                        existing.selected = true;
                                        defaultOption.selected = false;
                                        select.value = targetValue;
                                        if (select.dataset.pendingValue === targetValue) {
                                                delete select.dataset.pendingValue;
                                        }
                                }
                        }
                } else {
                        const emptyOption = document.createElement("option");
                        emptyOption.value = "";
                        emptyOption.textContent = texts.empty;
                        emptyOption.disabled = true;
                        select.appendChild(emptyOption);
                }
        } catch (error) {
                if (requestId !== usuariosRequestId) {
                        return;
                }
                select.innerHTML = "";
                const errorOption = document.createElement("option");
                errorOption.value = "";
                errorOption.textContent = texts.error;
                errorOption.disabled = true;
                select.appendChild(errorOption);
                console.warn("[form-user-select] loadUsuariosPorCanal error:", error);
        }

        if (requestId !== usuariosRequestId) {
                return;
        }

        updateSelectDataset(select);
        select.dispatchEvent(new Event("change", { bubbles: true }));
        updateNextButtonState();
}

function configureUsuarioSelect(channelValue, { keepValue = false } = {}) {
        const channelSlug = getChannelSlug(channelValue);
        currentChannelSlug = channelSlug;

        const wrapUsuario = document.getElementById("wrap-select-usuario");
        const usuarioSelect = document.getElementById("usuario-rol");
        if (!wrapUsuario || !usuarioSelect) {
                if (!channelRequiresAssignment(channelSlug)) {
                        refreshMetodoPagoPorUsuario(getCurrentUserId() || null);
                } else if (channelSlug === "particular") {
                        refreshMetodoPagoPorUsuario(null);
                }
                return;
        }

        if (!channelRequiresAssignment(channelSlug)) {
                wrapUsuario.style.display = "none";
                usuarioSelect.removeAttribute("required");
                usuarioSelect.innerHTML = "";
                usuarioSelect.value = "";
                updateSelectDataset(usuarioSelect);
                usuarioSelect.dispatchEvent(
                        new Event("change", { bubbles: true }),
                );
                refreshMetodoPagoPorUsuario(getCurrentUserId() || null);
                updateNextButtonState();
                return;
        }

        wrapUsuario.style.display = "";
        usuarioSelect.setAttribute("required", "");

        const label = wrapUsuario.querySelector('label[for="usuario-rol"]');
        if (label) {
                const texts = getChannelTexts(channelSlug);
                const role = normalizeRole(baseUserRole);
                const labelText = isComercial(role)
                        ? texts.labelComercial
                        : texts.labelAdmin;
                label.textContent = labelText;
        }

        loadUsuariosPorCanal(channelSlug, { keepValue });
}

function setupSepaWatcher() {
        const canalSelect = document.getElementById("canal-venta");
        const usuarioSelect = document.getElementById("usuario-rol");

        if (usuarioSelect) {
                usuarioSelect.addEventListener("change", () => {
                        updateSelectDataset(usuarioSelect);
                        const role = getUserRole();
                        let target = null;
                        if (isProfesional(role)) {
                                target = usuarioSelect.value || getCurrentUserId() || null;
                        } else if (isAdminLike(role) || isComercial(role)) {
                                target = usuarioSelect.value || null;
                        } else {
                                target = usuarioSelect.value || getCurrentUserId() || null;
                        }
                        refreshMetodoPagoPorUsuario(target);
                });
                updateSelectDataset(usuarioSelect);
        }

        if (canalSelect) {
                canalSelect.addEventListener("change", () => {
                        const role = getUserRole();
                        let target = null;
                        if (isProfesional(role)) {
                                target = getCurrentUserId();
                        } else if (isAdminLike(role) || isComercial(role)) {
                                const v = document.getElementById("usuario-rol");
                                target = (v && v.value) || null;
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
                initialTarget = (v && v.value) || null;
        } else {
                initialTarget = getCurrentUserId();
        }
        refreshMetodoPagoPorUsuario(initialTarget);
}

function initUserSelect() {
        const canalSelect = document.getElementById("canal-venta");
        const usuarioSelect = document.getElementById("usuario-rol");
        const wrapUsuario = document.getElementById("wrap-select-usuario");
        const rawRole = getUserRole() || document.body.dataset.userRole || "";
        baseUserRole = normalizeRole(rawRole);

        let initialChannelValue = canalSelect ? canalSelect.value : "";

        const searchParams = (typeof window !== "undefined" && window.location)
                ? new URLSearchParams(window.location.search)
                : null;
        const hasUuidParam = searchParams ? searchParams.has("uuid") : false;
        const continuingDraft = Boolean(hasUuidParam);

        if (isAdminLike(baseUserRole) && canalSelect && usuarioSelect && wrapUsuario) {
                if (!initialChannelValue && !continuingDraft) {
                        const profesionalOption =
                                canalSelect.querySelector('option[value="go_profesional"]') ||
                                canalSelect.querySelector('option[value="profesional"]');
                        if (profesionalOption) {
                                profesionalOption.selected = true;
                                canalSelect.value = profesionalOption.value;
                                initialChannelValue = profesionalOption.value;
                        }
                }
        } else if (isComercial(baseUserRole) && canalSelect) {
                if (!continuingDraft) {
                        canalSelect.value = "go_profesional";
                        initialChannelValue = canalSelect.value;
                }
        } else if (
                baseUserRole === "go_particular" ||
                baseUserRole === "particular" ||
                baseUserRole === "go_individual" ||
                baseUserRole === "individual"
        ) {
                initialChannelValue = "go_particular";
        } else if (baseUserRole === "go_gestoria" || baseUserRole === "gestoria") {
                initialChannelValue = "go_gestoria";
        }

        configureUsuarioSelect(initialChannelValue, { keepValue: true });

        if (canalSelect && (isAdminLike(baseUserRole) || isComercial(baseUserRole))) {
                canalSelect.addEventListener("change", (event) => {
                        configureUsuarioSelect(event.target.value);
                });
        }

        setupSepaWatcher();
}

export default initUserSelect;

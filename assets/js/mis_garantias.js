import { AVAILABLE_DOCS } from "./modules/docs-config.js";

const ADD_DOC_KEY = "add-document";

(() => {
        "use strict";
        document.addEventListener("DOMContentLoaded", () => {

                const tbody = document.querySelector("tbody[data-current-page]");
                const table = tbody.closest("table");
                const docsConfigMap = new Map(AVAILABLE_DOCS.map((doc) => [doc.key, doc]));
                const RECEIPT_ALLOWED_MIMES = ["application/pdf", "image/jpeg", "image/png"];
                const RECEIPT_ALLOWED_EXTENSIONS = ["pdf", "jpg", "jpeg", "png"];
                const RECEIPT_MAX_BYTES = 10 * 1024 * 1024;
                const listContainer = document.querySelector(".guarantees-list");
                const tableScrollContainer = document.querySelector(
                        ".guarantees-table__scroll"
                );
                const mobileCardsRoot = document.querySelector("[data-mobile-cards]");
                const mobileCardsList = mobileCardsRoot
                        ? mobileCardsRoot.querySelector("[data-mobile-cards-list]")
                        : null;
                const mobileCardsEmpty = mobileCardsRoot
                        ? mobileCardsRoot.querySelector("[data-mobile-cards-empty]")
                        : null;
                const mobileCardsMap = new Map();
                const scrollEnd = listContainer
                        ? listContainer.querySelector("#scroll-end")
                        : null;
                const spinner = scrollEnd ? scrollEnd.querySelector(".spinner") : null;
                const panelHistory =
                        typeof window !== "undefined" && window.go360PanelHistory
                                ? window.go360PanelHistory
                                : null;

                let spinnerObserver = null;
                let spinnerRaf = null;
                let spinnerFallbackTimeout = null;
                let spinnerTokenCounter = 0;
                let activeSpinnerToken = 0;

                const clearSpinnerWatchers = () => {
                        if (spinnerObserver) {
                                spinnerObserver.disconnect();
                                spinnerObserver = null;
                        }
                        if (spinnerRaf !== null && typeof cancelAnimationFrame === "function") {
                                cancelAnimationFrame(spinnerRaf);
                        }
                        spinnerRaf = null;
                        if (spinnerFallbackTimeout !== null) {
                                clearTimeout(spinnerFallbackTimeout);
                                spinnerFallbackTimeout = null;
                        }
                };

                const syncSpinnerCompensation = () => {
                        if (!scrollEnd || !tableScrollContainer) {
                                return;
                        }
                        if (desktopMediaQuery && desktopMediaQuery.matches) {
                                scrollEnd.style.removeProperty("--spinner-compensation");
                                return;
                        }
                        const offset = tableScrollContainer.scrollLeft || 0;
                        scrollEnd.style.setProperty(
                                "--spinner-compensation",
                                `${offset}px`
                        );
                };

                const setSpinnerVisible = (visible, token = null) => {
                        const show = Boolean(visible);
                        let resolvedToken =
                                typeof token === "number" && Number.isFinite(token)
                                        ? token
                                        : null;

                        if (show) {
                                if (resolvedToken === null) {
                                        resolvedToken = ++spinnerTokenCounter;
                                } else {
                                        spinnerTokenCounter = Math.max(
                                                spinnerTokenCounter,
                                                resolvedToken
                                        );
                                }
                                activeSpinnerToken = resolvedToken;
                                clearSpinnerWatchers();
                                if (scrollEnd) {
                                        scrollEnd.classList.add("is-loading");
                                        scrollEnd.hidden = false;
                                        syncSpinnerCompensation();
                                        scrollEnd.setAttribute(
                                                "aria-hidden",
                                                hasMore || show ? "false" : "true"
                                        );
                                }
                                if (spinner) {
                                        spinner.hidden = false;
                                        spinner.setAttribute("aria-hidden", "false");
                                }
                                return resolvedToken;
                        }

                        resolvedToken =
                                resolvedToken !== null ? resolvedToken : activeSpinnerToken;

                        if (resolvedToken !== activeSpinnerToken) {
                                return resolvedToken;
                        }

                        clearSpinnerWatchers();

                        if (scrollEnd) {
                                scrollEnd.classList.remove("is-loading");
                                scrollEnd.style.removeProperty("--spinner-compensation");
                                if (!hasMore) {
                                        scrollEnd.hidden = true;
                                }
                                scrollEnd.setAttribute(
                                        "aria-hidden",
                                        hasMore ? "false" : "true"
                                );
                        }
                        if (spinner) {
                                spinner.hidden = true;
                                spinner.setAttribute("aria-hidden", "true");
                        }
                        activeSpinnerToken = 0;
                        return resolvedToken;
                };

                const finalizeSpinnerVisibility = (
                        previousCount,
                        appendedRows,
                        token = null
                ) => {
                        if (!scrollEnd) {
                                return;
                        }
                        const resolvedToken =
                                typeof token === "number" && Number.isFinite(token)
                                        ? token
                                        : activeSpinnerToken;

                        if (resolvedToken !== activeSpinnerToken) {
                                return;
                        }

                        clearSpinnerWatchers();
                        const targetCount = Math.max(0, previousCount) + Math.max(0, appendedRows);
                        const checkContentReady = () => {
                                if (!tbody) {
                                        return true;
                                }
                                if (appendedRows <= 0) {
                                        return true;
                                }
                                const currentRows = tbody.querySelectorAll(".guarantees-table__row").length;
                                const hasEmptyRow = Boolean(
                                        tbody.querySelector(".guarantees-table__empty-row")
                                );
                                const hasMessage = Boolean(
                                        resultMessage &&
                                        typeof resultMessage.textContent === "string" &&
                                        resultMessage.textContent.trim().length > 0
                                );

                                if (appendedRows > 0) {
                                        return currentRows >= targetCount;
                                }

                                if (currentRows > 0 || hasEmptyRow || hasMessage) {
                                        return true;
                                }

                                return false;
                        };

                        let completed = false;
                        const complete = () => {
                                if (completed) {
                                        return;
                                }
                                completed = true;
                                clearSpinnerWatchers();
                                setSpinnerVisible(false, resolvedToken);
                                if (scrollEnd) {
                                        scrollEnd.hidden = !hasMore;
                                        scrollEnd.setAttribute(
                                                "aria-hidden",
                                                hasMore ? "false" : "true"
                                        );
                                }
                        };

                        if (checkContentReady()) {
                                complete();
                                return;
                        }

                        if (tbody) {
                                spinnerObserver = new MutationObserver(() => {
                                        if (checkContentReady()) {
                                                complete();
                                        }
                                });
                                spinnerObserver.observe(tbody, { childList: true, subtree: true });
                        }

                        if (typeof requestAnimationFrame === "function") {
                                const rafCheck = () => {
                                        if (checkContentReady()) {
                                                complete();
                                                return;
                                        }
                                        spinnerRaf = requestAnimationFrame(rafCheck);
                                };
                                spinnerRaf = requestAnimationFrame(rafCheck);
                        }

                        spinnerFallbackTimeout = window.setTimeout(() => {
                                complete();
                        }, 12000);
                };

                initResizableColumns(table);

                function resolveCurrentUserNameFromConfig(config = {}) {
                        const user = (config && config.user) || {};
                        const candidates = [
                                user.name,
                                user.display_name,
                                user.displayName,
                                user.full_name,
                                user.fullName,
                                user.username,
                                user.user_login,
                        ];

                        for (const candidate of candidates) {
                                if (typeof candidate === "string") {
                                        const trimmed = candidate.trim();
                                        if (trimmed) {
                                                return trimmed;
                                        }
                                }
                        }

                        return "";
                }

                const goConfig = window.__GO_CONFIG__ || {};
                const restRoot =
                        (goConfig.rest && goConfig.rest.root) ||
                        (window.GO_REST && window.GO_REST.root) ||
                        "/wp-json/";
                const restNonce =
                        (goConfig.rest && goConfig.rest.nonce) ||
                        (window.GO_REST && window.GO_REST.nonce) ||
                        "";
                const currentUserName = resolveCurrentUserNameFromConfig(goConfig);
                const userRole =
                        (goConfig.user && goConfig.user.role) ||
                        "user";
                const normalizedRole = String(userRole ?? "")
                        .trim()
                        .toLowerCase();
                const isAdmin =
                        [
                                "administrator",
                                "admin",
                                "go_garantias",
                                "go_comercial",
                                "go_director_comercial",
                        ].includes(normalizedRole);
                const canAccessManagementHub =
                        [
                                "administrator",
                                "admin",
                                "go_garantias",
                                "go_comercial",
                                "go_director_comercial",
                        ].includes(normalizedRole);
                const canUploadDocuments =
                        ["administrator", "admin", "go_garantias"].includes(normalizedRole);
                const isCoreAdmin =
                        normalizedRole === "administrator" || normalizedRole === "admin";
                const isComercial =
                        normalizedRole === "go_comercial" || normalizedRole === "comercial";
                const isDirector = normalizedRole === "go_director_comercial";
                const isProfesional =
                        normalizedRole === "go_profesional" || normalizedRole === "profesional";
                const isParticular =
                        normalizedRole === "go_particular" ||
                        normalizedRole === "particular" ||
                        normalizedRole === "go_individual" ||
                        normalizedRole === "individual";
                const rawListCacheVersion =
                        goConfig.cache && typeof goConfig.cache.guaranteesListVersion !== "undefined"
                                ? Number(goConfig.cache.guaranteesListVersion)
                                : Number.NaN;
                const listCacheVersion =
                        Number.isFinite(rawListCacheVersion) && rawListCacheVersion > 0
                                ? Math.floor(rawListCacheVersion)
                                : 1;
                let listCacheStorageVersion = listCacheVersion;
                const canSeeVerifyCollectStates =
                        [
                                "administrator",
                                "admin",
                                "go_director_comercial",
                                "go_garantias",
                        ].includes(normalizedRole);
                const shouldRestrictEmptyStateOptions = !canSeeVerifyCollectStates;
                const canManageDetailActions =
                        ["administrator", "admin", "go_garantias"].includes(normalizedRole);
                const canContinueGuarantee =
                        canManageDetailActions || isProfesional || isDirector || isParticular;
                const canViewAdminSummary = isCoreAdmin || isDirector || isProfesional;
                const canDeleteGuarantee =
                        [
                                "administrator",
                                "admin",
                                "go_garantias",
                                "go_director_comercial",
                        ].includes(normalizedRole);
                const REALTIME_POLL_INTERVAL = 6000;
                const REALTIME_POLL_HIDDEN_INTERVAL = 15000;
                const ADMIN_SUMMARY_ERROR_MESSAGE =
                        "No hemos podido cargar los datos. Vuelve a intentarlo en unos segundos.";
                const ADMIN_SUMMARY_DEFAULT_CONTEXT = "month";
                const ADMIN_SUMMARY_STATE_VALUES = [
                        "activada",
                        "pendiente_pago",
                        "pendiente_revision",
                        "sin_finalizar",
                ];
                const ADMIN_SUMMARY_STATE_ORDER = [...ADMIN_SUMMARY_STATE_VALUES];
                const ADMIN_SUMMARY_STATE_COLORS = {
                        activada: {
                                color: "var(--admin-summary-state-activada)",
                                muted: "var(--admin-summary-state-activada-muted)",
                        },
                        pendiente_pago: {
                                color: "var(--admin-summary-state-pendiente-pago)",
                                muted: "var(--admin-summary-state-pendiente-pago-muted)",
                        },
                        pendiente_revision: {
                                color: "var(--admin-summary-state-pendiente-revision)",
                                muted: "var(--admin-summary-state-pendiente-revision-muted)",
                        },
                        sin_finalizar: {
                                color: "var(--admin-summary-state-sin-finalizar)",
                                muted: "var(--admin-summary-state-sin-finalizar-muted)",
                        },
                };
                const ADMIN_SUMMARY_ACTION_ARROW_ICON =
                        '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>';
                const continueIcon =
                        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>';
                const deleteIcon =
                        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M280-120q-33 0-56.5-23.5T200-200v-520h-40v-80h200v-40h240v40h200v80h-40v520q0 33-23.5 56.5T680-120H280Zm400-600H280v520h400v-520ZM360-280h80v-360h-80v360Zm160 0h80v-360h-80v360ZM280-720v520-520Z"/></svg>';
                const managementShieldIcon =
                        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="m438-338 226-226-57-57-169 169-84-84-57 57 141 141Zm42 258q-139-35-229.5-159.5T160-516v-244l320-120 320 120v244q0 152-90.5 276.5T480-80Zm0-84q104-33 172-132t68-220v-189l-240-90-240 90v189q0 121 68 220t172 132Zm0-316Z"/></svg>';
                const managementInlineIcon =
                        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-600v-80h360v80H360Zm0 120v-80h360v80H360Zm120 320H200h280Zm0 80H240q-50 0-85-35t-35-85v-120h120v-560h600v361q-20-2-40.5 1.5T760-505v-295H320v480h240l-80 80H200v40q0 17 11.5 28.5T240-160h240v80Zm80 0v-123l221-220q9-9 20-13t22-4q12 0 23 4.5t20 13.5l37 37q8 9 12.5 20t4.5 22q0 11-4 22.5T903-300L683-80H560Zm300-263-37-37 37 37ZM620-140h38l121-122-18-19-19-18-122 121v38Zm141-141-19-18 37 37-18-19Z"/></svg>';
                const ADMIN_SUMMARY_ACTIONS = [
                        {
                                key: "draft",
                                label: "Sin finalizar",
                                description: "Revisa las garantías pendientes de completar",
                                filterValue: "sin_finalizar",
                                icon: continueIcon,
                                accent: "var(--admin-summary-state-sin-finalizar)",
                                showAmount: false,
                        },
                        {
                                key: "payment",
                                label: "Pendientes de pago",
                                description: "Deben completarse los cobros pendientes de pago",
                                filterValue: "pendiente_pago",
                                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -960 960 960" fill="currentColor"><path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/></svg>',
                                accent: "var(--admin-summary-action-payment)",
                        },
                        {
                                key: "validation",
                                label: "Pendientes de verificar transferencia",
                                description: "Verifica las transferencias recibidas",
                                filterValue: "validacion_pendiente",
                                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -960 960 960" fill="currentColor"><path d="M200-800v241-1 400-640 200-200Zm80 400h140q9-23 22-43t30-37H280v80Zm0 160h127q-5-20-6.5-40t.5-40H280v80ZM200-80q-33 0-56.5-23.5T120-160v-640q0-33 23.5-56.5T200-880h320l240 240v100q-19-8-39-12.5t-41-6.5v-41H480v-200H200v640h241q16 24 36 44.5T521-80H200Zm460-120q42 0 71-29t29-71q0-42-29-71t-71-29q-42 0-71 29t-29 71q0 42 29 71t71 29ZM864-40 756-148q-21 14-45.5 21t-50.5 7q-75 0-127.5-52.5T480-300q0-75 52.5-127.5T660-480q75 0 127.5 52.5T840-300q0 26-7 50.5T812-204L920-96l-56 56Z"/></svg>',
                                accent: "var(--admin-summary-action-validation)",
                                requiresFullAccess: true,
                        },
                        {
                                key: "collect",
                                label: "Pendientes de cobrar domiciliación",
                                description: "Revisa las domiciliaciones en curso",
                                filterValue: "pendiente_cobro",
                                icon: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 -960 960 960" fill="currentColor"><path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H360q0 33-23.5 56.5T280-640v160q33 0 56.5 23.5T360-400Zm440 240H120q-33 0-56.5-23.5T40-240v-440h80v440h680v80ZM280-400v-320 320Z"/></svg>',
                                accent: "var(--admin-summary-action-collect)",
                                requiresFullAccess: true,
                        },
                ];
                const integerFormatter = new Intl.NumberFormat("de-DE", {
                        useGrouping: true,
                        maximumFractionDigits: 0,
                });
                const currencyFormatter = new Intl.NumberFormat("de-DE", {
                        style: "currency",
                        currency: "EUR",
                        useGrouping: true,
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                });
                let adminSummaryCache = null;
                let adminSummaryPromise = null;
                const numberAnimations = new WeakMap();
                const copyIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z"/></svg>';
                const phoneIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M798-120q-125 0-247-54.5T329-329Q229-429 174.5-551T120-798q0-18 12-30t30-12h162q14 0 25 9.5t13 22.5l26 140q2 16-1 27t-11 19l-97 98q20 37 47.5 71.5T387-386q31 31 65 57.5t72 48.5l94-94q9-9 23.5-13.5T670-390l138 28q14 4 23 14.5t9 23.5v162q0 18-12 30t-30 12ZM241-600l66-66-17-94h-89q5 41 14 81t26 79Zm358 358q39 17 79.5 27t81.5 13v-88l-94-19-67 67ZM241-600Zm358 358Z"/></svg>';
                const emailIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm320-280L160-640v400h640v-400L480-440Zm0-80 320-200H160l320 200ZM160-640v-80 480-400Z"/></svg>';
                const userIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M234-276q51-39 114-61.5T480-360q69 0 132 22.5T726-276q35-41 54.5-93T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 59 19.5 111t54.5 93Zm246-164q-59 0-99.5-40.5T340-580q0-59 40.5-99.5T480-720q59 0 99.5 40.5T620-580q0 59-40.5 99.5T480-440Zm0 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q53 0 100-15.5t86-44.5q-39-29-86-44.5T480-280q-53 0-100 15.5T294-220q39 29 86 44.5T480-160Zm0-360q26 0 43-17t17-43q0-26-17-43t-43-17q-26 0-43 17t-17 43q0 26 17 43t43 17Zm0-60Zm0 360Z"/></svg>';
                const warningIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/></svg>';
                const heartIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m480-120-58-52q-101-91-167-157T150-447.5Q111-500 95.5-544T80-634q0-94 63-157t157-63q52 0 99 22t81 62q34-40 81-62t99-22q94 0 157 63t63 157q0 46-15.5 90T810-447.5Q771-395 705-329T538-172l-58 52Zm0-108q96-86 158-147.5t98-107q36-45.5 50-81t14-70.5q0-60-40-100t-100-40q-47 0-87 26.5T518-680h-76q-15-41-55-67.5T300-774q-60 0-100 40t-40 100q0 35 14 70.5t50 81q36 45.5 98 107T480-228Zm0-273Z"/></svg>';
                const shareIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M680-80q-50 0-85-35t-35-85q0-6 3-28L282-392q-16 15-37 23.5t-45 8.5q-50 0-85-35t-35-85q0-50 35-85t85-35q24 0 45 8.5t37 23.5l281-164q-2-7-2.5-13.5T560-760q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35q-24 0-45-8.5T598-672L317-508q2 7 2.5 13.5t.5 14.5q0 8-.5 14.5T317-452l281 164q16-15 37-23.5t45-8.5q50 0 85 35t35 85q0 50-35 85t-85 35Zm0-80q17 0 28.5-11.5T720-200q0-17-11.5-28.5T680-240q-17 0-28.5 11.5T640-200q0 17 11.5 28.5T680-160ZM200-440q17 0 28.5-11.5T240-480q0-17-11.5-28.5T200-520q-17 0-28.5 11.5T160-480q0 17 11.5 28.5T200-440Zm480-280q17 0 28.5-11.5T720-760q0-17-11.5-28.5T680-800q-17 0-28.5 11.5T640-760q0 17 11.5 28.5T680-720Zm0 520ZM200-480Zm480-280Z"/></svg>';
                const personAddIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M720-400v-120H600v-80h120v-120h80v120h120v80H800v120h-80Zm-360-80q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM40-160v-112q0-34 17.5-62.5T104-378q62-31 126-46.5T360-440q66 0 130 15.5T616-378q29 15 46.5 43.5T680-272v112H40Zm80-80h480v-32q0-11-5.5-20T580-306q-54-27-109-40.5T360-360q-56 0-111 13.5T140-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T440-640q0-33-23.5-56.5T360-720q-33 0-56.5 23.5T280-640q0 33 23.5 56.5T360-560Zm0-80Zm0 400Z"/></svg>';
                const personIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M480-480q-66 0-113-47t-47-113q0-66 47-113t113-47q66 0 113 47t47 113q0 66-47 113t-113 47ZM160-160v-112q0-34 17.5-62.5T224-378q62-31 126-46.5T480-440q66 0 130 15.5T736-378q29 15 46.5 43.5T800-272v112H160Zm80-80h480v-32q0-11-5.5-20T700-306q-54-27-109-40.5T480-360q-56 0-111 13.5T260-306q-9 5-14.5 14t-5.5 20v32Zm240-320q33 0 56.5-23.5T560-640q0-33-23.5-56.5T480-720q-33 0-56.5 23.5T400-640q0 33 23.5 56.5T480-560Zm0-80Zm0 400Z"/></svg>';
                const paymentIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H360q0 33-23.5 56.5T280-640v160q33 0 56.5 23.5T360-400Zm440 240H120q-33 0-56.5-23.5T40-240v-440h80v440h680v80ZM280-400v-320 320Z"/></svg>';
                const pdfIcon = (goConfig.icons && goConfig.icons.pdf) || "";
               const downloadIcon = (goConfig.icons && goConfig.icons.download) || "";
               const plusIcon = (goConfig.icons && goConfig.icons.plus) || "";
                const arrowDownIcon =
                        (goConfig.icons && goConfig.icons.arrowDropDown) || "";
                const arrowUpIcon =
                        (goConfig.icons && goConfig.icons.arrowDropUp) || "";
                const arrowLeftIcon =
                        (goConfig.icons && goConfig.icons.arrowLeft) ||
                        '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M400-240 160-480l240-240 56 58-142 142h486v80H314l142 142-56 58Z"/></svg>';
                const ADMIN_SUMMARY_TREND_ICON_UP =
                        '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 15a.5.5 0 0 0 .5-.5V2.707l3.146 3.147a.5.5 0 0 0 .708-.708l-4-4a.5.5 0 0 0-.708 0l-4 4a.5.5 0 1 0 .708.708L7.5 2.707V14.5a.5.5 0 0 0 .5.5z"/></svg>';
                const ADMIN_SUMMARY_TREND_ICON_DOWN =
                        '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M8 1a.5.5 0 0 1 .5.5v11.793l3.146-3.147a.5.5 0 0 1 .708.708l-4 4a.5.5 0 0 1-.708 0l-4-4a.5.5 0 0 1 .708-.708L7.5 13.293V1.5A.5.5 0 0 1 8 1z"/></svg>';

                let pendingConfirmContext = null;
                const confirmModalController = setupConfirmModal(
                        document.querySelector(".confirm-modal")
                );
                const managementModal = document.querySelector("[data-management-modal]");
                const managementDialog = managementModal
                        ? managementModal.querySelector("[data-management-dialog]")
                        : null;
                const managementPlateLabel = managementModal
                        ? managementModal.querySelector("[data-management-plate]")
                        : null;
                const managementStatusBadge = managementModal
                        ? managementModal.querySelector("[data-management-status]")
                        : null;
                const managementStatusText = managementModal
                        ? managementModal.querySelector("[data-management-status-text]")
                        : null;
                const managementNotesList = managementModal
                        ? managementModal.querySelector("[data-management-notes]")
                        : null;
                const managementNotesEmpty = managementModal
                        ? managementModal.querySelector("[data-management-notes-empty]")
                        : null;
                const managementNotesComposer = managementModal
                        ? managementModal.querySelector(".management-notes__composer")
                        : null;
                const managementNoteInput = managementModal
                        ? managementModal.querySelector("[data-management-note-input]")
                        : null;
                const managementNoteSaveBtn = managementModal
                        ? managementModal.querySelector("[data-management-save-note]")
                        : null;
                const managementNoteSaveLabel = managementNoteSaveBtn
                        ? managementNoteSaveBtn.querySelector("[data-management-save-label]")
                        : null;
                const defaultSaveLabel = managementNoteSaveLabel
                        ? managementNoteSaveLabel.textContent.trim()
                        : "";
                const defaultSaveIconHtml = managementNoteSaveBtn?.querySelector(
                        ".guarantee-management__btn-icon"
                )?.innerHTML;
                const managementVehicleLabel = managementModal
                        ? managementModal.querySelector("[data-management-vehicle]")
                        : null;
                const managementVendorLabel = managementModal
                        ? managementModal.querySelector("[data-management-vendor]")
                        : null;
                const managementValidUntilLabel = managementModal
                        ? managementModal.querySelector("[data-management-valid-until]")
                        : null;
                const managementCoverageLabel = managementModal
                        ? managementModal.querySelector("[data-management-coverage]")
                        : null;
                const managementActionsList = managementModal
                        ? managementModal.querySelector(".management-actions__list")
                        : null;
                const managementActionsTitle = managementModal
                        ? managementModal.querySelector(".management-actions__title")
                        : null;
                const managementActionsDanger = managementModal
                        ? managementModal.querySelector(".management-actions__danger")
                        : null;
                const managementActionAnchors = new Map();
                const MANAGEMENT_MODAL_TRANSITION = 260;
                let managementModalCloseTimer = null;
                let managementModalTrigger = null;
                let managementNotesState = { notes: [], currentUserId: null };
                let managementNotesLoadedId = null;
                let managementNoteSavingTimer = null;

                const PDF_CACHE_LIMIT = 12;
                const pdfBlobCache = new Map();
                const pdfBlobOrder = [];
                const pdfBlobPromises = new Map();

                function normalizeDocUrl(url) {
                        return typeof url === "string" ? url.trim() : "";
                }

                function touchPdfCache(key) {
                        const index = pdfBlobOrder.indexOf(key);
                        if (index !== -1) {
                                pdfBlobOrder.splice(index, 1);
                        }
                        pdfBlobOrder.push(key);
                }

                function evictPdfCache() {
                        while (pdfBlobOrder.length > PDF_CACHE_LIMIT) {
                                const oldest = pdfBlobOrder.shift();
                                if (!oldest) {
                                        break;
                                }
                                const cached = pdfBlobCache.get(oldest);
                                if (cached && cached.objectUrl) {
                                        URL.revokeObjectURL(cached.objectUrl);
                                }
                                pdfBlobCache.delete(oldest);
                        }
                }

                function storePdfBlob(key, blob) {
                        const objectUrl = URL.createObjectURL(blob);
                        pdfBlobCache.set(key, { objectUrl, timestamp: Date.now() });
                        touchPdfCache(key);
                        evictPdfCache();
                        return objectUrl;
                }

                function getCachedDocumentObjectUrl(url) {
                        const normalized = normalizeDocUrl(url);
                        if (!normalized) {
                                return "";
                        }
                        const cached = pdfBlobCache.get(normalized);
                        if (!cached) {
                                return "";
                        }
                        touchPdfCache(normalized);
                        return cached.objectUrl;
                }

                function ensureDocumentPreloaded(url) {
                        const normalized = normalizeDocUrl(url);
                        if (!normalized) {
                                return Promise.resolve("");
                        }
                        if (pdfBlobCache.has(normalized)) {
                                touchPdfCache(normalized);
                                return Promise.resolve(
                                        pdfBlobCache.get(normalized).objectUrl
                                );
                        }
                        if (pdfBlobPromises.has(normalized)) {
                                return pdfBlobPromises.get(normalized);
                        }
                        const fetchPromise = fetch(normalized, {
                                credentials: "same-origin",
                        })
                                .then((res) => {
                                        if (!res.ok) {
                                                throw new Error(
                                                        `Error HTTP ${res.status}`
                                                );
                                        }
                                        return res.blob();
                                })
                                .then((blob) => {
                                        if (!blob || blob.size === 0) {
                                                throw new Error("Documento vacío");
                                        }
                                        const type = (blob.type || "").toLowerCase();
                                        if (type) {
                                                const isKnownMime =
                                                        RECEIPT_ALLOWED_MIMES.includes(
                                                                type
                                                        ) || type.includes("pdf");
                                                if (!isKnownMime) {
                                                        console.warn(
                                                                "Contenido no es PDF, se intentará mostrar igualmente",
                                                                type
                                                        );
                                                }
                                        }
                                        pdfBlobPromises.delete(normalized);
                                        return storePdfBlob(normalized, blob);
                                })
                                .catch((error) => {
                                        pdfBlobPromises.delete(normalized);
                                        throw error;
                                });
                        pdfBlobPromises.set(normalized, fetchPromise);
                        return fetchPromise;
                }

                function scheduleDocumentPreload(url) {
                        const normalized = normalizeDocUrl(url);
                        if (!normalized) {
                                return;
                        }
                        ensureDocumentPreloaded(normalized).catch((error) => {
                                console.warn(
                                        "No se pudo precargar el documento",
                                        error
                                );
                        });
                }
                const misGarantiasBase =
                        (goConfig.pages && goConfig.pages.misGarantias) ||
                        "/garantias-online/mis-garantias/";
                const newGuaranteeUrl =
                        (goConfig.pages && goConfig.pages.nuevaGarantia) ||
                        "/garantias-online/nueva-garantia/";
                const SHARE_UNAVAILABLE_MESSAGE =
                        "La función de compartir no está disponible en este navegador.";
                const saveStatus = document.createElement("div");
                saveStatus.className = "autosave-status autosave-status--hidden";
                saveStatus.innerHTML =
                        '<span class="autosave-status__spinner"></span>' +
                        '<span class="autosave-status__icon" style="display:none"></span>' +
                        '<span class="autosave-status__text">Guardando</span>';
                document.body.appendChild(saveStatus);
                const saveStatusText = saveStatus.querySelector(
                        ".autosave-status__text"
                );
                const DEFAULT_PER = 12;
                let perPage = DEFAULT_PER;
		let currentPage = 1;
		let totalPages = 1;
		let totalPosts = 0;
		let isLoading = false;
		let hasMore = true;
		let searchQuery = "";
		let lastValidQuery = "";
		let lastValidResults = [];
                const detail = document.querySelector(".guarantee-detail");
                let panel1 = document.getElementById("detail-panel-1");
                let panel2 = document.getElementById("detail-panel-2");
                let lastEmptyPanel = panel1;
                const emptyTemplatesWrapper = document.querySelector(
                        ".guarantee-detail__empty-templates"
                );
                const emptyTemplateMap = {};
                if (emptyTemplatesWrapper) {
                        emptyTemplatesWrapper
                                .querySelectorAll("[data-empty-template]")
                                .forEach((node) => {
                                        const key = node.getAttribute("data-empty-template");
                                        if (key) {
                                                emptyTemplateMap[key] = node.innerHTML.trim();
                                        }
                                });
                }
                if (canViewAdminSummary && panel1 && panel1.classList.contains("active")) {
                        initializeAdminSummary(panel1);
                }
                let currentEmptyMode = "awaiting";
                let prevSelectedRow = null;
                let prevIdx = null;

                const filtersRoot = document.querySelector(
                        ".guarantees-list__filters"
                );
                const estadoSelect = document.querySelector(
                        '[data-filter="estado"]'
                );
                const planSelect = document.querySelector(
                        '[data-filter="plan"]'
                );
                const canalSelect = document.querySelector(
                        '[data-filter="canal"]'
                );
                const concesionarioSelect = document.querySelector(
                        '[data-filter="cliente"]'
                );
                const paymentSelect = document.querySelector(
                        '[data-filter="payment"]'
                );
                const commercialSelect = document.querySelector(
                        '[data-filter="commercial"]'
                );
                const yearSelect = document.querySelector('[data-filter="year"]');
                const monthFromSelect = document.querySelector('[data-filter="month-from"]');
                const monthToSelect = document.querySelector('[data-filter="month-to"]');
                const hasPeriodFilters = Boolean(
                        yearSelect &&
                        monthFromSelect &&
                        monthToSelect
                );
                const monthFromField = monthFromSelect
                        ? monthFromSelect.closest('.guarantees-list__filter-field')
                        : null;
                const monthToField = monthToSelect
                        ? monthToSelect.closest('.guarantees-list__filter-field')
                        : null;
                const clientsWrapper = document.querySelector(
                        "[data-clients-wrapper]"
                );
                const moreFiltersToggle = document.querySelector(
                        "[data-more-filters]"
                );
                const advancedPanel = document.querySelector(
                        "[data-advanced-panel]"
                );
                const resetFiltersBtn = document.querySelector(
                        "[data-reset-filters]"
                );
                const mobileFiltersToggle = document.querySelector(
                        "[data-mobile-filters-toggle]"
                );
                const mobileFiltersPanel = document.querySelector(
                        "[data-mobile-filters-panel]"
                );
                const mobileFiltersOverlay = document.querySelector(
                        "[data-mobile-filters-overlay]"
                );
                const mobileFiltersDismissEls = document.querySelectorAll(
                        "[data-mobile-filters-dismiss]"
                );
                const mobileSearchSlot = document.querySelector(
                        "[data-mobile-search-slot]"
                );
                const desktopSearchSlot = document.querySelector(
                        "[data-desktop-search-slot]"
                );
                const searchField = document.querySelector("[data-search-field]");
                const mobileSearchPanel = document.querySelector(
                        "[data-mobile-search-panel]"
                );
                const searchInput = searchField
                        ? searchField.querySelector("input")
                        : null;
                const bottomBar = document.querySelector("[data-mobile-bottom-bar]");
                const bottomNavButtons = bottomBar
                        ? Array.from(
                                  bottomBar.querySelectorAll(
                                          "[data-mobile-nav-action]"
                                  )
                          )
                        : [];
                const mobileSearchToggle = bottomBar
                        ? bottomBar.querySelector("[data-mobile-search-toggle]")
                        : null;
                const summaryNavButton = bottomBar
                        ? bottomBar.querySelector(
                                  "[data-mobile-nav-action=\"summary\"]"
                          )
                        : null;
                const listNavButton = bottomBar
                        ? bottomBar.querySelector(
                                  "[data-mobile-nav-action=\"guarantees\"]"
                          )
                        : null;
                const mobileQuickAdd = document.querySelector(
                        "[data-mobile-quick-add]"
                );
                const orderRoot = document.querySelector("[data-order-root]");
                const orderToggle = orderRoot
                        ? orderRoot.querySelector("[data-order-toggle]")
                        : null;
                const orderMenu = orderRoot
                        ? orderRoot.querySelector("[data-order-menu]")
                        : null;
                const orderLabelNode = orderRoot
                        ? orderRoot.querySelector("[data-order-label]")
                        : null;
                let orderMenuPortalParent = null;
                let orderMenuPlaceholder = null;
                let detachOrderMenuPortal = null;
                let selectedEstado = "";
                let selectedPlan = "";
                let selectedCanal = "";
                let selectedConcesionario = "";
                let selectedVendorType = "";
                let selectedPaymentMethod = "";
                let selectedCommercial = "";
                let selectedYear = "";
                let selectedMonthFrom = "";
                let selectedMonthTo = "";
                const periodDefaults = {
                        year: "",
                        monthFrom: "",
                        monthTo: "",
                };
                const rootElement = document.documentElement;
                const rootStyle = rootElement ? rootElement.style : null;
                const mobileViewportOffsetVar = "--mobile-viewport-bottom-offset";
                const mobileHeaderCompensationVar = "--mobile-header-compensation";
                const htmlLang =
                        (rootElement &&
                                (rootElement.lang ||
                                        rootElement.getAttribute("xml:lang"))) ||
                        "es-ES";
                const monthsByYear = new Map();
                let availableYears = [];
                let currentPeriodYear = new Date().getFullYear();
                let currentPeriodMonth = new Date().getMonth() + 1;
                let monthOptions = [];
                if (hasPeriodFilters) {
                        let monthFormatter = null;
                        try {
                                monthFormatter = new Intl.DateTimeFormat(htmlLang, {
                                        month: "long",
                                });
                        } catch (e) {
                                monthFormatter = null;
                        }
                        monthOptions = Array.from({ length: 12 }, (_, index) => {
                                const monthIndex = index + 1;
                                let formatted = "";
                                if (monthFormatter) {
                                        try {
                                                formatted = monthFormatter.format(
                                                        new Date(Date.UTC(2020, index, 1))
                                                );
                                        } catch (error) {
                                                formatted = "";
                                        }
                                }
                                if (typeof formatted !== "string" || formatted.length === 0) {
                                        formatted = String(monthIndex);
                                }
                                const label =
                                        formatted.charAt(0).toUpperCase() + formatted.slice(1);
                                return {
                                        value: String(monthIndex),
                                        label,
                                };
                        });
                        periodDefaults.year = "";
                        periodDefaults.monthFrom = "";
                        periodDefaults.monthTo = "";
                }

                function updateMonthFieldVisibility() {
                        if (!hasPeriodFilters) {
                                return;
                        }
                        const shouldShow = Boolean(selectedYear);
                        [monthFromField, monthToField].forEach((field) => {
                                if (!field) {
                                        return;
                                }
                                if (shouldShow) {
                                        field.removeAttribute("hidden");
                                } else {
                                        field.setAttribute("hidden", "");
                                }
                        });
                }

                updateMonthFieldVisibility();
                const defaultOrderKey = orderToggle
                        ? orderToggle.getAttribute("data-default-sort") || "created_desc"
                        : "created_desc";
                let selectedOrderKey = defaultOrderKey;
                let selectedOrderBy = "created";
                let selectedOrderDirection = defaultOrderKey.endsWith("_asc")
                        ? "asc"
                        : "desc";
                let currentListAbort = null;
                let rawClients = [];
                let rawCommercials = [];
                const orderOptions = new Map();
                let setAdvancedOpen = () => {};
                let baseFiltersHeight = filtersRoot ? filtersRoot.offsetHeight || 0 : 0;
                const desktopMediaQuery =
                        typeof window !== "undefined" &&
                        typeof window.matchMedia === "function"
                                ? window.matchMedia("(min-width: 1280px)")
                                : null;
                const isDesktopView = () =>
                        desktopMediaQuery ? desktopMediaQuery.matches : true;
                let mobileFiltersOpen = Boolean(
                        filtersRoot &&
                                filtersRoot.getAttribute("data-mobile-open") === "true"
                );
                let lastMobileOpenState = mobileFiltersOpen;
                const bodyElement = document.body;
                const notificationsRoot = document.querySelector('[data-admin-notifications]');
                const deleteNotificationIcon = (() => {
                        if (
                                !notificationsRoot ||
                                typeof window === 'undefined' ||
                                typeof window.atob !== 'function'
                        ) {
                                return '';
                        }
                        const encoded = notificationsRoot.dataset.iconDelete || '';
                        if (!encoded) {
                                return '';
                        }
                        try {
                                return window
                                        .atob(encoded)
                                        .replace(
                                                /notifications-panel__action-icon/g,
                                                'notifications-panel__icon-svg'
                                        );
                        } catch (error) {
                                return '';
                        }
                })();
                const detailDialog = detail
                        ? detail.querySelector("[data-detail-dialog]")
                        : null;
                const detailDismissTriggers = detail
                        ? detail.querySelectorAll("[data-mobile-detail-dismiss]")
                        : [];
                let mobileDetailOpen = false;
                let mobileDetailHistoryAttached = false;
                let mobileSummaryOpen = false;
                let mobileSearchOpen = false;
                let lastMobileSearchVisible = false;
                let notificationsPanelOpen = false;
                let notificationsModalOpen = false;
                let currentMobileNavState = "guarantees";
                let lastDetailTrigger = null;
                let infiniteScrollObserver = null;
                let mobileHideEmpty = false;
                const syncMobileEmptyHidden = () => {
                        if (!detail) {
                                return;
                        }
                        if (!desktopMediaQuery || desktopMediaQuery.matches) {
                                detail.classList.remove("guarantee-detail--hide-empty");
                                return;
                        }
                        detail.classList.toggle(
                                "guarantee-detail--hide-empty",
                                mobileHideEmpty
                        );
                };
                const setMobileEmptyHidden = (hidden) => {
                        mobileHideEmpty = Boolean(hidden);
                        syncMobileEmptyHidden();
                        return mobileHideEmpty;
                };
                const setMobileSummaryOpen = (open, options = {}) => {
                        const previous = mobileSummaryOpen;
                        mobileSummaryOpen = Boolean(open);
                        if (mobileSummaryOpen) {
                                setMobileSearchOpen(false, { silent: true });
                        }
                        if (mobileSummaryOpen) {
                                setMobileEmptyHidden(false);
                        }
                        if (bodyElement) {
                                bodyElement.classList.toggle(
                                        "has-mobile-summary-open",
                                        mobileSummaryOpen
                                );
                        }

                        const historyAvailable =
                                panelHistory &&
                                typeof panelHistory.push === "function" &&
                                (!desktopMediaQuery || !desktopMediaQuery.matches);
                        if (historyAvailable && !options.silent) {
                                if (mobileSummaryOpen && !previous) {
                                        panelHistory.push("mobile-summary", () => {
                                                setMobileSummaryOpen(false, { silent: true });
                                                closeMobileDetail({
                                                        focus: false,
                                                        restoreFocus: false,
                                                        silentHistory: true,
                                                });
                                        });
                                } else if (!mobileSummaryOpen && previous) {
                                        panelHistory.close("mobile-summary");
                                }
                        }

                        syncMobileNavState();
                        return mobileSummaryOpen;
                };
                setMobileSummaryOpen(false);
                syncMobileEmptyHidden();
                const updateBaseFiltersHeight = () => {
                        if (!filtersRoot) {
                                baseFiltersHeight = 0;
                                return;
                        }
                        baseFiltersHeight = filtersRoot.offsetHeight || 0;
                };
                const updateAdvancedHeight = () => {
                        if (!filtersRoot) {
                                rootElement.style.setProperty(
                                        "--go-advanced-filters-height",
                                        "0px"
                                );
                                baseFiltersHeight = 0;
                                return;
                        }

                        const isAdvancedVisible =
                                advancedPanel && !advancedPanel.hasAttribute("hidden");

                        if (!isAdvancedVisible) {
                                updateBaseFiltersHeight();
                                rootElement.style.setProperty(
                                        "--go-advanced-filters-height",
                                        "0px"
                                );
                                return;
                        }

                        const currentHeight = filtersRoot.offsetHeight || 0;
                        const baseline = baseFiltersHeight || 0;
                        const extra = Math.max(0, Math.round(currentHeight - baseline));

                        rootElement.style.setProperty(
                                "--go-advanced-filters-height",
                                `${extra}px`
                        );
                };
                updateBaseFiltersHeight();

                function syncMobileHeaderCompensation() {
                        if (!rootStyle) {
                                return;
                        }
                        if (isDesktopView()) {
                                rootStyle.setProperty(mobileHeaderCompensationVar, "0px");
                                return;
                        }
                        let viewportOffset = 0;
                        if (
                                typeof window !== "undefined" &&
                                window.visualViewport &&
                                typeof window.visualViewport.offsetTop === "number"
                        ) {
                                viewportOffset = window.visualViewport.offsetTop;
                        }
                        const compensation = Math.max(
                                0,
                                Math.round(viewportOffset)
                        );
                        rootStyle.setProperty(
                                mobileHeaderCompensationVar,
                                `${compensation}px`
                        );
                }

                function syncMobileViewportOffset() {
                        if (!rootStyle) {
                                return;
                        }
                        if (isDesktopView()) {
                                rootStyle.setProperty(mobileViewportOffsetVar, "0px");
                                syncMobileHeaderCompensation();
                                return;
                        }
                        if (
                                typeof window === "undefined" ||
                                !window.visualViewport ||
                                typeof window.visualViewport.height !== "number"
                        ) {
                                rootStyle.setProperty(mobileViewportOffsetVar, "0px");
                                syncMobileHeaderCompensation();
                                return;
                        }
                        const viewport = window.visualViewport;
                        const layoutViewportHeight =
                                typeof window.innerHeight === "number"
                                        ? window.innerHeight
                                        : viewport.height;
                        const visualHeight = viewport.height;
                        const offsetTop =
                                typeof viewport.offsetTop === "number"
                                        ? viewport.offsetTop
                                        : 0;
                        const availableHeight = visualHeight + offsetTop;
                        const delta = layoutViewportHeight - availableHeight;
                        const offset = Number.isFinite(delta) && delta > 0 ? delta : 0;
                        rootStyle.setProperty(
                                mobileViewportOffsetVar,
                                `${Math.round(offset)}px`
                        );
                        syncMobileHeaderCompensation();
                }

                function syncBottomBarState({ measure = false } = {}) {
                        if (!rootElement) {
                                return;
                        }
                        if (!bottomBar) {
                                rootElement.style.setProperty(
                                        "--guarantees-bottom-bar-height",
                                        "0px"
                                );
                                if (rootStyle) {
                                        rootStyle.setProperty(
                                                mobileViewportOffsetVar,
                                                "0px"
                                        );
                                }
                                syncMobileHeaderCompensation();
                                return;
                        }
                        const desktop = isDesktopView();
                        if (desktop) {
                                bottomBar.hidden = true;
                                bottomBar.style.position = "";
                                bottomBar.style.left = "";
                                bottomBar.style.right = "";
                                bottomBar.style.bottom = "";
                                rootElement.style.setProperty(
                                        "--guarantees-bottom-bar-height",
                                        "0px"
                                );
                                syncMobileViewportOffset();
                                return;
                        }
                        bottomBar.hidden = false;
                        bottomBar.style.position = "fixed";
                        bottomBar.style.left = "0";
                        bottomBar.style.right = "0";
                        bottomBar.style.bottom = "";
                        const updateHeight = () => {
                                let height = 0;
                                if (bottomBar) {
                                        const rect = bottomBar.getBoundingClientRect();
                                        if (
                                                rect &&
                                                Number.isFinite(rect.height) &&
                                                rect.height > 0
                                        ) {
                                                height = Math.round(rect.height);
                                        } else if (
                                                Number.isFinite(bottomBar.scrollHeight) &&
                                                bottomBar.scrollHeight > 0
                                        ) {
                                                height = Math.round(bottomBar.scrollHeight);
                                        }
                                }
                                rootElement.style.setProperty(
                                        "--guarantees-bottom-bar-height",
                                        `${height}px`
                                );
                        };
                        if (measure && typeof requestAnimationFrame === "function") {
                                requestAnimationFrame(updateHeight);
                        } else {
                                updateHeight();
                        }
                        syncMobileViewportOffset();
                }

                function setMobileNavState(state) {
                        if (typeof state !== "string" || state.length === 0) {
                                return currentMobileNavState;
                        }
                        currentMobileNavState = state;
                        if (bottomNavButtons.length === 0) {
                                return currentMobileNavState;
                        }
                        bottomNavButtons.forEach((button) => {
                                const action = button.getAttribute("data-mobile-nav-action");
                                const isActive = action === state;
                                button.classList.toggle("is-active", isActive);
                                button.setAttribute("aria-pressed", isActive ? "true" : "false");
                                if (action === "filters" && button.hasAttribute("aria-expanded")) {
                                        button.setAttribute(
                                                "aria-expanded",
                                                isActive ? "true" : "false"
                                        );
                                }
                        });
                        return currentMobileNavState;
                }

                function computeMobileNavState() {
                        if (!bottomBar || isDesktopView()) {
                                return "guarantees";
                        }
                        if (notificationsModalOpen || notificationsPanelOpen) {
                                return "notifications";
                        }
                        if (mobileFiltersOpen) {
                                return "filters";
                        }
                        if (mobileSearchOpen) {
                                return "search";
                        }
                        if (mobileSummaryOpen) {
                                return "summary";
                        }
                        if (mobileDetailOpen) {
                                return "detail";
                        }
                        return "guarantees";
                }

                function syncMobileNavState(explicitState = null) {
                        const target =
                                explicitState && typeof explicitState === "string"
                                        ? explicitState
                                        : computeMobileNavState();
                        const state = setMobileNavState(target);
                        syncBottomBarState({ measure: true });
                        syncMobileQuickAddVisibility(state);
                        return state;
                }

                function syncMobileQuickAddVisibility(state = computeMobileNavState()) {
                        if (!mobileQuickAdd) {
                                return;
                        }
                        const desktop = isDesktopView();
                        const shouldShow =
                                !desktop && Boolean(bottomBar) && state === "guarantees";
                        mobileQuickAdd.classList.toggle("is-hidden", !shouldShow);
                        mobileQuickAdd.setAttribute(
                                "aria-hidden",
                                shouldShow ? "false" : "true"
                        );
                        if (shouldShow) {
                                mobileQuickAdd.removeAttribute("tabindex");
                        } else {
                                mobileQuickAdd.setAttribute("tabindex", "-1");
                        }
                }

                if (typeof window !== "undefined") {
                        window.addEventListener("go360:notifications:opened", () => {
                                notificationsModalOpen = false;
                                notificationsPanelOpen = true;
                                syncMobileNavState();
                        });
                        window.addEventListener("go360:notifications:closed", () => {
                                notificationsPanelOpen = false;
                                notificationsModalOpen = false;
                                syncMobileNavState();
                        });
                        window.addEventListener("go360:notifications:modal-opened", () => {
                                notificationsPanelOpen = false;
                                notificationsModalOpen = true;
                                syncMobileNavState();
                        });
                        window.addEventListener("go360:notifications:modal-closed", () => {
                                notificationsModalOpen = false;
                                syncMobileNavState();
                        });
                }

                function syncMobileDetailVisibility({ focus = false, restoreFocus = false } = {}) {
                        if (!detail) {
                                return;
                        }
                        const desktop = isDesktopView();
                        syncMobileEmptyHidden();
                        const shouldBeOpen = desktop || mobileDetailOpen;
                        detail.setAttribute(
                                "data-mobile-open",
                                shouldBeOpen ? "true" : "false"
                        );

                        if (desktop) {
                                detail.removeAttribute("aria-hidden");
                                if (detailDialog) {
                                        detailDialog.setAttribute("role", "region");
                                        detailDialog.removeAttribute("aria-modal");
                                        detailDialog.removeAttribute("tabindex");
                                }
                                if (bodyElement) {
                                        bodyElement.classList.remove("has-mobile-detail-open");
                                }
                                return;
                        }

                        if (detailDialog) {
                                detailDialog.setAttribute("role", "dialog");
                                detailDialog.setAttribute("aria-modal", "true");
                                detailDialog.setAttribute("tabindex", "-1");
                        }

                        if (shouldBeOpen) {
                                detail.removeAttribute("aria-hidden");
                                if (bodyElement) {
                                        bodyElement.classList.add("has-mobile-detail-open");
                                }
                                if (focus && detailDialog && typeof detailDialog.focus === "function") {
                                        requestAnimationFrame(() => {
                                                try {
                                                        detailDialog.focus({ preventScroll: true });
                                                } catch (error) {
                                                        detailDialog.focus();
                                                }
                                        });
                                }
                        } else {
                                detail.setAttribute("aria-hidden", "true");
                                if (bodyElement) {
                                        bodyElement.classList.remove("has-mobile-detail-open");
                                }
                                if (
                                        restoreFocus &&
                                        lastDetailTrigger &&
                                        typeof lastDetailTrigger.focus === "function"
                                ) {
                                        requestAnimationFrame(() => {
                                                try {
                                                        lastDetailTrigger.focus({ preventScroll: true });
                                                } catch (error) {
                                                        lastDetailTrigger.focus();
                                                }
                                        });
                                }
                        }

                        syncMobileNavState();
                }

                function setMobileDetailOpen(
                        open,
                        {
                                focus = true,
                                restoreFocus = true,
                                preserveSummary = false,
                                silentHistory = false,
                        } = {}
                ) {
                        if (!detail) {
                                return;
                        }
                        if (isDesktopView()) {
                                mobileDetailOpen = true;
                                mobileDetailHistoryAttached = false;
                                syncMobileDetailVisibility();
                                return;
                        }
                        const shouldOpen = Boolean(open);
                        const previousState = mobileDetailOpen;
                        if (previousState === shouldOpen) {
                                syncMobileDetailVisibility({
                                        focus: shouldOpen && focus,
                                        restoreFocus: !shouldOpen && restoreFocus,
                                });
                                return;
                        }
                        if (shouldOpen) {
                                if (mobileFiltersOpen) {
                                        mobileFiltersOpen = false;
                                        syncMobileFiltersVisibility();
                                }
                                setMobileSearchOpen(false, { silent: true });
                                if (!preserveSummary) {
                                        setMobileSummaryOpen(false);
                                }
                        }

                        mobileDetailOpen = shouldOpen;
                        if (!shouldOpen) {
                                setMobileSummaryOpen(false);
                                setMobileEmptyHidden(false);
                        }
                        syncMobileDetailVisibility({
                                focus: shouldOpen && focus,
                                restoreFocus: !shouldOpen && restoreFocus,
                        });

                        const historyAvailable =
                                panelHistory &&
                                typeof panelHistory.push === "function" &&
                                (!desktopMediaQuery || !desktopMediaQuery.matches);
                        const skipHistory = preserveSummary || silentHistory;

                        if (historyAvailable) {
                                if (
                                        shouldOpen &&
                                        !previousState &&
                                        !skipHistory &&
                                        !mobileDetailHistoryAttached
                                ) {
                                        mobileDetailHistoryAttached = true;
                                        panelHistory.push("mobile-detail", () => {
                                                setMobileDetailOpen(false, {
                                                        focus: false,
                                                        restoreFocus: false,
                                                        silentHistory: true,
                                                });
                                        });
                                } else if (!shouldOpen && previousState) {
                                        if (mobileDetailHistoryAttached) {
                                                if (
                                                        !silentHistory &&
                                                        typeof panelHistory.close === "function"
                                                ) {
                                                        panelHistory.close("mobile-detail");
                                                }
                                                mobileDetailHistoryAttached = false;
                                        }
                                }
                        } else {
                                mobileDetailHistoryAttached = false;
                        }

                        if (shouldOpen && skipHistory) {
                                mobileDetailHistoryAttached = false;
                        }
                }

                function openMobileDetail(options = {}) {
                        setMobileDetailOpen(true, options);
                }

                function closeMobileDetail(options = {}) {
                        setMobileDetailOpen(false, options);
                }

                function getScrollRoot() {
                        if (
                                tableScrollContainer &&
                                tableScrollContainer.scrollHeight >
                                        tableScrollContainer.clientHeight
                        ) {
                                return tableScrollContainer;
                        }
                        if (
                                listContainer &&
                                listContainer.scrollHeight > listContainer.clientHeight
                        ) {
                                return listContainer;
                        }
                        return null;
                }

                function observeScrollEnd() {
                        if (!scrollEnd) {
                                return;
                        }
                        if (infiniteScrollObserver) {
                                infiniteScrollObserver.disconnect();
                        }
                        const scrollRoot = getScrollRoot();
                        const observerOptions = {
                                root: scrollRoot,
                                threshold: 0.1,
                                rootMargin: scrollRoot ? "200px 0px" : "400px 0px",
                        };
                        infiniteScrollObserver = new IntersectionObserver((entries) => {
                                if (
                                        entries &&
                                        entries[0] &&
                                        entries[0].isIntersecting &&
                                        hasMore &&
                                        !isLoading
                                ) {
                                        loadPage(currentPage + 1);
                                }
                        }, observerOptions);
                        infiniteScrollObserver.observe(scrollEnd);
                }

                const moveSearchFieldTo = (target) => {
                        if (!target || !searchField) {
                                return;
                        }
                        if (target.contains(searchField)) {
                                return;
                        }
                        target.appendChild(searchField);
                };

                const syncSearchPlacement = () => {
                        if (!searchField) {
                                return;
                        }
                        const desktop = isDesktopView();
                        const target = desktop ? desktopSearchSlot : mobileSearchSlot;
                        if (!target) {
                                return;
                        }
                        moveSearchFieldTo(target);
                };

                const syncMobileSearchVisibility = ({ focus = false } = {}) => {
                        if (!filtersRoot || !mobileSearchPanel) {
                                return;
                        }
                        const desktop = isDesktopView();
                        const shouldBeOpen = desktop || mobileSearchOpen;

                        filtersRoot.setAttribute(
                                "data-mobile-search-open",
                                shouldBeOpen ? "true" : "false"
                        );

                        if (desktop) {
                                mobileSearchPanel.classList.remove("is-visible");
                                mobileSearchPanel.removeAttribute("aria-hidden");
                        } else {
                                mobileSearchPanel.classList.toggle(
                                        "is-visible",
                                        shouldBeOpen
                                );
                                mobileSearchPanel.setAttribute(
                                        "aria-hidden",
                                        shouldBeOpen ? "false" : "true"
                                );
                        }

                        if (!desktop && shouldBeOpen && focus && searchInput) {
                                requestAnimationFrame(() => {
                                        try {
                                                searchInput.focus({ preventScroll: true });
                                        } catch (error) {
                                                searchInput.focus();
                                        }
                                        if (typeof searchInput.select === "function") {
                                                searchInput.select();
                                        }
                                });
                        } else if (
                                !desktop &&
                                !shouldBeOpen &&
                                lastMobileSearchVisible &&
                                searchInput &&
                                typeof searchInput.blur === "function"
                        ) {
                                searchInput.blur();
                        }

                        lastMobileSearchVisible = shouldBeOpen;
                };

                const setMobileSearchOpen = (open, options = {}) => {
                        if (!filtersRoot || !mobileSearchPanel) {
                                return mobileSearchOpen;
                        }
                        const desktop = isDesktopView();
                        if (desktop) {
                                mobileSearchOpen = false;
                                syncMobileSearchVisibility();
                                return mobileSearchOpen;
                        }
                        const shouldOpen = Boolean(open);
                        const previous = mobileSearchOpen;
                        if (previous === shouldOpen) {
                                if (shouldOpen && options.focus === true) {
                                        syncMobileSearchVisibility({ focus: true });
                                }
                                return mobileSearchOpen;
                        }
                        mobileSearchOpen = shouldOpen;
                        syncMobileSearchVisibility({ focus: shouldOpen });

                        const historyAvailable =
                                panelHistory &&
                                typeof panelHistory.push === "function" &&
                                (!desktopMediaQuery || !desktopMediaQuery.matches);

                        if (historyAvailable && !options.silent) {
                                if (shouldOpen && !previous) {
                                        panelHistory.push("mobile-search", () => {
                                                setMobileSearchOpen(false, { silent: true });
                                        });
                                } else if (!shouldOpen && previous) {
                                        panelHistory.close("mobile-search");
                                }
                        }

                        syncMobileNavState();
                        return mobileSearchOpen;
                };

                const syncMobileFiltersVisibility = () => {
                        if (!filtersRoot || !mobileFiltersPanel) {
                                return;
                        }
                        const desktop = isDesktopView();
                        const shouldBeOpen = desktop || mobileFiltersOpen;

                        syncSearchPlacement();
                        syncMobileSearchVisibility();

                        filtersRoot.setAttribute(
                                "data-mobile-open",
                                shouldBeOpen ? "true" : "false"
                        );

                        if (mobileFiltersToggle) {
                                mobileFiltersToggle.setAttribute(
                                        "aria-expanded",
                                        shouldBeOpen ? "true" : "false"
                                );
                        }

                        if (desktop) {
                                mobileFiltersPanel.setAttribute("role", "region");
                                mobileFiltersPanel.removeAttribute("aria-modal");
                                mobileFiltersPanel.removeAttribute("aria-hidden");
                        } else {
                                mobileFiltersPanel.setAttribute("role", "dialog");
                                mobileFiltersPanel.setAttribute("aria-modal", "true");
                                if (shouldBeOpen) {
                                        mobileFiltersPanel.removeAttribute("aria-hidden");
                                } else {
                                        mobileFiltersPanel.setAttribute(
                                                "aria-hidden",
                                                "true"
                                        );
                                }
                        }

                        if (mobileFiltersOverlay) {
                                if (desktop) {
                                        mobileFiltersOverlay.removeAttribute("aria-hidden");
                                } else if (shouldBeOpen) {
                                        mobileFiltersOverlay.removeAttribute(
                                                "aria-hidden"
                                        );
                                } else {
                                        mobileFiltersOverlay.setAttribute(
                                                "aria-hidden",
                                                "true"
                                        );
                                }
                        }

                        if (bodyElement) {
                                bodyElement.classList.toggle(
                                        "has-mobile-filters-open",
                                        shouldBeOpen && !desktop
                                );
                        }

                        if (!desktop && !shouldBeOpen) {
                                setAdvancedOpen(false);
                                closeOrderMenu();
                        }

                        updateBaseFiltersHeight();
                        requestAnimationFrame(updateAdvancedHeight);

                        if (!desktop && shouldBeOpen && !lastMobileOpenState) {
                                requestAnimationFrame(() => {
                                        const focusTarget = mobileFiltersPanel.querySelector(
                                                "select, input, button, [href], [tabindex]:not([tabindex='-1'])"
                                        );
                                        if (focusTarget && typeof focusTarget.focus === "function") {
                                                try {
                                                        focusTarget.focus({
                                                                preventScroll: true,
                                                        });
                                                } catch (error) {
                                                        focusTarget.focus();
                                                }
                                        } else if (
                                                typeof mobileFiltersPanel.focus === "function"
                                        ) {
                                                try {
                                                        mobileFiltersPanel.focus({
                                                                preventScroll: true,
                                                        });
                                                } catch (error) {
                                                        mobileFiltersPanel.focus();
                                                }
                                        }
                                });
                        } else if (
                                !desktop &&
                                !shouldBeOpen &&
                                lastMobileOpenState &&
                                mobileFiltersToggle &&
                                typeof mobileFiltersToggle.focus === "function"
                        ) {
                                requestAnimationFrame(() => {
                                        try {
                                                mobileFiltersToggle.focus({
                                                        preventScroll: true,
                                                });
                                        } catch (error) {
                                                mobileFiltersToggle.focus();
                                        }
                                });
                        }

                        syncMobileNavState();
                        lastMobileOpenState = shouldBeOpen;
                };

                const setMobileFiltersOpen = (open, options = {}) => {
                        if (!filtersRoot || !mobileFiltersPanel) {
                                return;
                        }
                        if (isDesktopView()) {
                                mobileFiltersOpen = true;
                                syncMobileFiltersVisibility();
                                if (
                                        panelHistory &&
                                        typeof panelHistory.close === "function" &&
                                        !options.silent
                                ) {
                                        panelHistory.close("mobile-filters");
                                }
                                return;
                        }
                        const previous = mobileFiltersOpen;
                        mobileFiltersOpen = Boolean(open);
                        if (mobileFiltersOpen) {
                                setMobileSummaryOpen(false);
                                setMobileSearchOpen(false, { silent: true });
                        }
                        syncMobileFiltersVisibility();

                        const historyAvailable =
                                panelHistory &&
                                typeof panelHistory.push === "function" &&
                                (!desktopMediaQuery || !desktopMediaQuery.matches);
                        if (historyAvailable && !options.silent) {
                                if (mobileFiltersOpen && !previous) {
                                        panelHistory.push("mobile-filters", () => {
                                                setMobileFiltersOpen(false, { silent: true });
                                        });
                                } else if (!mobileFiltersOpen && previous) {
                                        panelHistory.close("mobile-filters");
                                }
                        }
                };

                if (mobileFiltersToggle && mobileFiltersPanel && filtersRoot) {
                        mobileFiltersToggle.addEventListener("click", () => {
                                if (isDesktopView()) {
                                        return;
                                }
                                if (!mobileFiltersOpen) {
                                        closeMobileDetail({ focus: false, restoreFocus: false });
                                }
                                setMobileFiltersOpen(!mobileFiltersOpen);
                        });
                }

                if (mobileFiltersDismissEls && mobileFiltersDismissEls.length > 0) {
                        mobileFiltersDismissEls.forEach((trigger) => {
                                trigger.addEventListener("click", (event) => {
                                        if (isDesktopView()) {
                                                return;
                                        }
                                        event.preventDefault();
                                        setMobileFiltersOpen(false);
                                });
                        });
                }

                if (summaryNavButton) {
                        summaryNavButton.addEventListener("click", () => {
                                if (isDesktopView()) {
                                        return;
                                }
                                const shouldOpen = !mobileSummaryOpen;
                                setMobileFiltersOpen(false);
                                setMobileSearchOpen(false, { silent: true });
                                if (shouldOpen) {
                                        clearSelectionAndDetail({
                                                preserveQuery: false,
                                                restoreFocus: false,
                                        });
                                        setMobileSummaryOpen(true);
                                        openMobileDetail({
                                                focus: false,
                                                restoreFocus: false,
                                                preserveSummary: true,
                                        });
                                        syncMobileNavState("summary");
                                        return;
                                }
                                setMobileSummaryOpen(false);
                                closeMobileDetail({
                                        focus: false,
                                        restoreFocus: false,
                                });
                                syncMobileNavState("guarantees");
                        });
                }

                if (mobileSearchToggle) {
                        mobileSearchToggle.addEventListener("click", () => {
                                if (isDesktopView()) {
                                        return;
                                }
                                const shouldOpen = !mobileSearchOpen;
                                setMobileFiltersOpen(false);
                                setMobileSummaryOpen(false);
                                if (shouldOpen) {
                                        closeMobileDetail({
                                                focus: false,
                                                restoreFocus: false,
                                        });
                                }
                                setMobileSearchOpen(shouldOpen);
                        });
                }

                if (listNavButton) {
                        listNavButton.addEventListener("click", () => {
                                if (isDesktopView()) {
                                        return;
                                }
                                setMobileFiltersOpen(false);
                                setMobileSummaryOpen(false);
                                clearSelectionAndDetail({
                                        preserveQuery: false,
                                        restoreFocus: false,
                                });
                                syncMobileNavState("guarantees");
                        });
                }

                if (detailDismissTriggers && detailDismissTriggers.length > 0) {
                        detailDismissTriggers.forEach((trigger) => {
                                trigger.addEventListener("click", (event) => {
                                        if (isDesktopView()) {
                                                return;
                                        }
                                        event.preventDefault();
                                        if (prevSelectedRow && prevSelectedRow.isConnected) {
                                                lastDetailTrigger = prevSelectedRow;
                                        } else {
                                                lastDetailTrigger = trigger;
                                        }
                                        clearSelectionAndDetail({
                                                preserveQuery: isDesktopView(),
                                        });
                                });
                        });
                }

                if (desktopMediaQuery) {
                        const handleDesktopChange = (event) => {
                                if (event.matches) {
                                        mobileFiltersOpen = true;
                                        mobileDetailOpen = true;
                                        mobileSearchOpen = false;
                                } else {
                                        mobileFiltersOpen = false;
                                        mobileDetailOpen = prevSelectedRow ? true : false;
                                        mobileSearchOpen = false;
                                }
                                syncMobileFiltersVisibility();
                                syncMobileDetailVisibility();
                                observeScrollEnd();
                                syncSearchPlacement();
                                syncSpinnerCompensation();
                                syncBottomBarState({ measure: true });
                                syncMobileViewportOffset();
                        };
                        if (typeof desktopMediaQuery.addEventListener === "function") {
                                desktopMediaQuery.addEventListener(
                                        "change",
                                        handleDesktopChange
                                );
                        } else if (
                                typeof desktopMediaQuery.addListener === "function"
                        ) {
                                desktopMediaQuery.addListener(handleDesktopChange);
                        }
                }

                document.addEventListener("keydown", (event) => {
                        if (event.key !== "Escape") {
                                return;
                        }
                        if (isDesktopView()) {
                                return;
                        }
                        let handled = false;
                        if (mobileDetailOpen) {
                                if (prevSelectedRow && prevSelectedRow.isConnected) {
                                        lastDetailTrigger = prevSelectedRow;
                                }
                                clearSelectionAndDetail({
                                        preserveQuery: isDesktopView(),
                                });
                                handled = true;
                        }
                        if (mobileFiltersOpen) {
                                setMobileFiltersOpen(false);
                                handled = true;
                        }
                        if (mobileSearchOpen) {
                                setMobileSearchOpen(false);
                                handled = true;
                        }
                        if (handled) {
                                event.preventDefault();
                        }
                });

                syncMobileFiltersVisibility();
                syncSearchPlacement();
                mobileDetailOpen = isDesktopView();
                syncMobileDetailVisibility();
                syncMobileNavState();
                observeScrollEnd();

                if (tableScrollContainer) {
                        tableScrollContainer.addEventListener(
                                "scroll",
                                () => {
                                        if (desktopMediaQuery && desktopMediaQuery.matches) {
                                                return;
                                        }
                                        syncSpinnerCompensation();
                                },
                                { passive: true }
                        );
                }

                if (typeof window !== "undefined") {
                        window.addEventListener("resize", syncSpinnerCompensation, {
                                passive: true,
                        });
                        const handleResize = () => {
                                syncBottomBarState({ measure: true });
                                syncMobileViewportOffset();
                        };
                        window.addEventListener("resize", handleResize, {
                                passive: true,
                        });
                        window.addEventListener(
                                "scroll",
                                () => {
                                        syncMobileHeaderCompensation();
                                },
                                { passive: true }
                        );
                        if (window.visualViewport) {
                                window.visualViewport.addEventListener(
                                        "resize",
                                        syncMobileViewportOffset
                                );
                                window.visualViewport.addEventListener(
                                        "scroll",
                                        syncMobileViewportOffset
                                );
                        }
                }

                syncSpinnerCompensation();
                syncBottomBarState({ measure: true });
                syncMobileViewportOffset();

                function populateMonthSelect(select) {
                        if (!select || monthOptions.length === 0) {
                                return;
                        }
                        select.innerHTML = "";
                        monthOptions.forEach(({ value, label }) => {
                                const option = document.createElement("option");
                                option.value = value;
                                option.textContent = label;
                                select.appendChild(option);
                        });
                }

                function populateYearOptions(yearValues, selectedValue) {
                        if (!yearSelect) {
                                return;
                        }
                        const allLabel =
                                yearSelect.getAttribute("data-all-label") ||
                                "Todos los años";
                        yearSelect.innerHTML = "";
                        const allOption = document.createElement("option");
                        allOption.value = "";
                        allOption.textContent = allLabel;
                        yearSelect.appendChild(allOption);
                        yearValues.forEach((year) => {
                                const yearString = String(year);
                                const option = document.createElement("option");
                                option.value = yearString;
                                option.textContent = yearString;
                                yearSelect.appendChild(option);
                        });
                        yearSelect.value = selectedValue || "";
                }

                function getMonthsForYear(yearValue) {
                        if (!yearValue) {
                                return [];
                        }
                        const stored = monthsByYear.get(String(yearValue));
                        if (!stored) {
                                return [];
                        }
                        return stored.slice();
                }

                function getEarliestMonthForYear(yearValue) {
                        const months = getMonthsForYear(yearValue);
                        if (months.length === 0) {
                                return 1;
                        }
                        const first = Number.parseInt(months[0], 10);
                        return Number.isFinite(first) && first >= 1 ? first : 1;
                }

                function getLatestMonthForYear(yearValue) {
                        const months = getMonthsForYear(yearValue);
                        if (months.length === 0) {
                                return 12;
                        }
                        const last = Number.parseInt(months[months.length - 1], 10);
                        return Number.isFinite(last) && last >= 1 ? last : 12;
                }

                function normalizeMonthValue(value) {
                        const intValue = Number.parseInt(value, 10);
                        if (!Number.isFinite(intValue) || intValue < 1) {
                                return "";
                        }
                        if (intValue > 12) {
                                return "12";
                        }
                        return String(intValue);
                }

                function getPeriodCacheKeyParts(overrides = {}) {
                        if (!hasPeriodFilters) {
                                return ["", "", ""];
                        }
                        const yearValue =
                                typeof overrides.year !== "undefined"
                                        ? String(overrides.year || "")
                                        : selectedYear || "";
                        if (!yearValue) {
                                return ["", "", ""];
                        }
                        let monthFromValue =
                                typeof overrides.monthFrom !== "undefined"
                                        ? normalizeMonthValue(overrides.monthFrom)
                                        : normalizeMonthValue(selectedMonthFrom);
                        let monthToValue =
                                typeof overrides.monthTo !== "undefined"
                                        ? normalizeMonthValue(overrides.monthTo)
                                        : normalizeMonthValue(selectedMonthTo);
                        monthFromValue = monthFromValue || "";
                        monthToValue = monthToValue || "";
                        if (
                                monthFromValue &&
                                monthToValue &&
                                Number(monthFromValue) > Number(monthToValue)
                        ) {
                                monthToValue = monthFromValue;
                        }
                        return [yearValue, monthFromValue, monthToValue];
                }

                function initializePeriodFilters() {
                        if (!hasPeriodFilters) {
                                return;
                        }
                        if (monthOptions.length > 0) {
                                populateMonthSelect(monthFromSelect);
                                populateMonthSelect(monthToSelect);
                        }
                        const initialYear = selectedYear || "";
                        availableYears = initialYear ? [initialYear] : [];
                        populateYearOptions(availableYears, initialYear);
                        if (yearSelect) {
                                yearSelect.value = initialYear;
                        }
                        if (monthFromSelect) {
                                monthFromSelect.value = selectedMonthFrom || "";
                        }
                        if (monthToSelect) {
                                monthToSelect.value = selectedMonthTo || "";
                        }
                        updateMonthFieldVisibility();
                }

                function syncPeriodFilters(periods = {}) {
                        if (!hasPeriodFilters) {
                                return;
                        }
                        const previousYear = selectedYear;
                        const previousFrom = selectedMonthFrom;
                        const previousTo = selectedMonthTo;

                        const normalizedYears = Array.isArray(periods.years)
                                ? periods.years
                                          .map((year) => Number.parseInt(year, 10))
                                          .filter((year) =>
                                                  Number.isFinite(year) && year > 0
                                          )
                                : [];

                        const responseYear = Number.parseInt(
                                periods.current_year,
                                10
                        );
                        if (Number.isFinite(responseYear) && responseYear > 0) {
                                currentPeriodYear = responseYear;
                        }

                        const responseMonth = Number.parseInt(
                                periods.current_month,
                                10
                        );
                        if (
                                Number.isFinite(responseMonth) &&
                                responseMonth >= 1 &&
                                responseMonth <= 12
                        ) {
                                currentPeriodMonth = responseMonth;
                        }

                        if (!normalizedYears.includes(currentPeriodYear)) {
                                normalizedYears.push(currentPeriodYear);
                        }

                        normalizedYears.sort((a, b) => b - a);
                        availableYears = normalizedYears.map((year) => String(year));

                        monthsByYear.clear();
                        if (
                                periods.year_months &&
                                typeof periods.year_months === "object"
                        ) {
                                Object.entries(periods.year_months).forEach(
                                        ([yearKey, monthsList]) => {
                                                const yearInt = Number.parseInt(yearKey, 10);
                                                if (!Number.isFinite(yearInt) || yearInt <= 0) {
                                                        return;
                                                }
                                                const normalizedMonths = Array.isArray(monthsList)
                                                        ? monthsList
                                                                  .map((value) =>
                                                                          Number.parseInt(
                                                                                  value,
                                                                                  10
                                                                          )
                                                                  )
                                                                  .filter((value) =>
                                                                          Number.isFinite(
                                                                                  value
                                                                          ) &&
                                                                          value >= 1 &&
                                                                          value <= 12
                                                                  )
                                                        : [];
                                                normalizedMonths.sort((a, b) => a - b);
                                                monthsByYear.set(
                                                        String(yearInt),
                                                        normalizedMonths.map((value) =>
                                                                String(value)
                                                        )
                                                );
                                        }
                                );
                        }

                        const currentYearKey = String(currentPeriodYear);
                        if (!monthsByYear.has(currentYearKey)) {
                                monthsByYear.set(currentYearKey, []);
                        }

                        const normalizedSelectedYear =
                                typeof selectedYear === "string"
                                        ? selectedYear
                                        : selectedYear != null
                                        ? String(selectedYear)
                                        : "";
                        const selectionStillValid =
                                normalizedSelectedYear !== "" &&
                                availableYears.includes(normalizedSelectedYear);
                        const nextYearValue = selectionStillValid
                                ? normalizedSelectedYear
                                : "";

                        populateYearOptions(availableYears, nextYearValue);
                        if (monthOptions.length > 0) {
                                populateMonthSelect(monthFromSelect);
                                populateMonthSelect(monthToSelect);
                        }

                        let nextFromValue = "";
                        let nextToValue = "";
                        if (selectionStillValid) {
                                const fallbackFrom = String(
                                        getEarliestMonthForYear(nextYearValue)
                                );
                                const fallbackTo =
                                        nextYearValue === currentYearKey
                                                ? String(currentPeriodMonth)
                                                : String(
                                                          getLatestMonthForYear(
                                                                  nextYearValue
                                                          )
                                                  );
                                nextFromValue =
                                        normalizeMonthValue(selectedMonthFrom) ||
                                        fallbackFrom;
                                nextToValue =
                                        normalizeMonthValue(selectedMonthTo) ||
                                        fallbackTo;
                                if (
                                        Number(nextFromValue) >
                                        Number(nextToValue)
                                ) {
                                        nextToValue = nextFromValue;
                                }
                        }

                        selectedYear = nextYearValue;
                        selectedMonthFrom = nextFromValue;
                        selectedMonthTo = nextToValue;

                        periodDefaults.year = "";
                        periodDefaults.monthFrom = "";
                        periodDefaults.monthTo = "";

                        if (yearSelect) {
                                yearSelect.value = nextYearValue || "";
                        }
                        if (monthFromSelect) {
                                monthFromSelect.value = nextFromValue || "";
                        }
                        if (monthToSelect) {
                                monthToSelect.value = nextToValue || "";
                        }

                        updateMonthFieldVisibility();

                        const periodChanged =
                                previousYear !== selectedYear ||
                                previousFrom !== selectedMonthFrom ||
                                previousTo !== selectedMonthTo;

                        if (periodChanged) {
                                applyFilters();
                        } else {
                                updateResetVisibility();
                        }
                }

                const hasActiveFilters = () => {
                        const hasSearch = Boolean((searchQuery || "").trim());
                        if (hasSearch) {
                                return true;
                        }
                        if (hasPeriodFilters) {
                                const defaultYear = periodDefaults.year || "";
                                const defaultFrom = periodDefaults.monthFrom || "";
                                const defaultTo = periodDefaults.monthTo || "";
                                if (selectedYear !== defaultYear) {
                                        return true;
                                }
                                if (
                                        selectedYear &&
                                        (selectedMonthFrom !== defaultFrom ||
                                                selectedMonthTo !== defaultTo)
                                ) {
                                        return true;
                                }
                        }
                        if (
                                selectedEstado ||
                                selectedPlan ||
                                selectedCanal ||
                                selectedConcesionario ||
                                selectedVendorType ||
                                selectedPaymentMethod ||
                                selectedCommercial
                        ) {
                                return true;
                        }
                        if (orderToggle && selectedOrderKey !== defaultOrderKey) {
                                return true;
                        }
                        return false;
                };

                const updateResetVisibility = () => {
                        if (!resetFiltersBtn) {
                                return;
                        }
                        resetFiltersBtn.hidden = !hasActiveFilters();
                };

                initializePeriodFilters();
                updateResetVisibility();

                if (typeof ResizeObserver !== "undefined" && advancedPanel) {
                        const resizeObserver = new ResizeObserver(() => {
                                if (!advancedPanel.hasAttribute("hidden")) {
                                        updateAdvancedHeight();
                                }
                        });
                        resizeObserver.observe(advancedPanel);
                }

                window.addEventListener("resize", () => {
                        if (advancedPanel && !advancedPanel.hasAttribute("hidden")) {
                                updateAdvancedHeight();
                        } else {
                                updateBaseFiltersHeight();
                        }
                });

                let resultMessage =
                        document.querySelector("[data-search-result-message]") ||
                        document.querySelector(".guarantees-list__result-message");
                if (resultMessage) {
                        resultMessage.classList.add("guarantees-list__result-message");
                        if (!resultMessage.hasAttribute("aria-live")) {
                                resultMessage.setAttribute("aria-live", "polite");
                        }
                        resultMessage.setAttribute("aria-hidden", "true");
                        resultMessage.hidden = true;
                } else if (table) {
                        resultMessage = document.createElement("div");
                        resultMessage.className = "guarantees-list__result-message";
                        resultMessage.setAttribute("aria-live", "polite");
                        resultMessage.setAttribute("aria-hidden", "true");
                        resultMessage.hidden = true;
                        const target = listContainer || table.parentElement;
                        if (target && table.parentElement === target) {
                                table.insertAdjacentElement("afterend", resultMessage);
                        } else if (target && typeof target.appendChild === "function") {
                                target.appendChild(resultMessage);
                        } else {
                                table.insertAdjacentElement("afterend", resultMessage);
                        }
                }

                if (!panel1 || !panel2) {
                        detail.innerHTML =
                                '<div class="guarantee-detail__panel active" id="detail-panel-1">' +
                                detail.innerHTML +
                                '</div><div class="guarantee-detail__panel" id="detail-panel-2"></div>';
                        panel1 = document.getElementById("detail-panel-1");
                        panel2 = document.getElementById("detail-panel-2");
                }

                let activePanel = panel1;
                let inactivePanel = panel2;
                activePanel.classList.add("active");
                inactivePanel.classList.remove("active");
                const urlMat = new URLSearchParams(window.location.search).get("matricula");
                let pendingMatSelection = Boolean(urlMat);
                let initialMatQuery = typeof urlMat === "string" ? urlMat.trim() : "";
                const detailCache = new Map();
                const detailPromises = new Map();
                const loadedIds = new Set();
                const listCache = new Map();
                const detailPreloadQueue = new Set();
                let detailPreloadScheduled = false;
                let detailPreloadProcessing = false;
                const LIST_CACHE_TTL_MS = 5 * 60 * 1000;
                const LIST_CACHE_MAX_ENTRIES = 6;
                const getListCacheStorageKey = () =>
                        `go:guarantees:list-cache:v${listCacheStorageVersion}`;
                const realtimeHighlightEntries = new Set();
                const REALTIME_BADGE_LABEL = "Nuevo";
                let realtimePollTimer = null;
                let realtimePendingGeneration = 0;
                let realtimeRefreshInFlight = false;
                let realtimeFetchController = null;
                let realtimeKnownGeneration = listCacheVersion;
                let realtimeStarted = false;

                function loadPersistentListCache() {
                        if (typeof window === "undefined" || !window.sessionStorage) {
                                return [];
                        }
                        try {
                                const storageKey = getListCacheStorageKey();
                                const raw = window.sessionStorage.getItem(storageKey);
                                if (!raw) {
                                        return [];
                                }
                                const parsed = JSON.parse(raw);
                                if (!Array.isArray(parsed)) {
                                        return [];
                                }
                                const now = Date.now();
                                const entries = [];
                                for (const entry of parsed) {
                                        if (!Array.isArray(entry) || entry.length < 2) {
                                                continue;
                                        }
                                        const [key, value] = entry;
                                        if (typeof key !== "string" || !value || typeof value !== "object") {
                                                continue;
                                        }
                                        if (!Array.isArray(value.data)) {
                                                continue;
                                        }
                                        const fetchedAt = typeof value.fetchedAt === "number" ? value.fetchedAt : 0;
                                        if (fetchedAt && now - fetchedAt > LIST_CACHE_TTL_MS) {
                                                continue;
                                        }
                                        const totalPagesNumber = Number(value.totalPages);
                                        const totalPostsNumber = Number(value.totalPosts);
                                        entries.push([
                                                key,
                                                {
                                                        data: value.data,
                                                        totalPages:
                                                                Number.isFinite(totalPagesNumber) && totalPagesNumber > 0
                                                                        ? Math.floor(totalPagesNumber)
                                                                        : 1,
                                                        totalPosts:
                                                                Number.isFinite(totalPostsNumber) && totalPostsNumber >= 0
                                                                        ? Math.floor(totalPostsNumber)
                                                                        : 0,
                                                        fetchedAt,
                                                },
                                        ]);
                                }
                                if (entries.length === 0 && parsed.length > 0) {
                                        try {
                                                window.sessionStorage.removeItem(storageKey);
                                        } catch (storageError) {
                                                console.warn(
                                                        "No se pudo limpiar la caché de garantías caducada:",
                                                        storageError
                                                );
                                        }
                                }
                                return entries;
                        } catch (error) {
                                console.warn("No se pudo recuperar la caché de garantías:", error);
                                return [];
                        }
                }

                function persistListCacheSnapshot(sourceMap) {
                        if (typeof window === "undefined" || !window.sessionStorage) {
                                return;
                        }
                        try {
                                const entries = [];
                                const now = Date.now();
                                sourceMap.forEach((value, key) => {
                                        if (!value || typeof value !== "object" || !Array.isArray(value.data)) {
                                                return;
                                        }
                                        const totalPagesNumber = Number(value.totalPages);
                                        const totalPostsNumber = Number(value.totalPosts);
                                        const fetchedAt =
                                                typeof value.fetchedAt === "number" && value.fetchedAt > 0
                                                        ? value.fetchedAt
                                                        : now;
                                        entries.push([
                                                key,
                                                {
                                                        data: value.data,
                                                        totalPages:
                                                                Number.isFinite(totalPagesNumber) && totalPagesNumber > 0
                                                                        ? Math.floor(totalPagesNumber)
                                                                        : 1,
                                                        totalPosts:
                                                                Number.isFinite(totalPostsNumber) && totalPostsNumber >= 0
                                                                        ? Math.floor(totalPostsNumber)
                                                                        : 0,
                                                        fetchedAt,
                                                },
                                        ]);
                                });
                                const storageKey = getListCacheStorageKey();
                                if (entries.length === 0) {
                                        window.sessionStorage.removeItem(storageKey);
                                        return;
                                }
                                entries.sort((a, b) => (b[1].fetchedAt || 0) - (a[1].fetchedAt || 0));
                                const limited = entries.slice(0, LIST_CACHE_MAX_ENTRIES);
                                window.sessionStorage.setItem(storageKey, JSON.stringify(limited));
                        } catch (error) {
                                console.warn("No se pudo guardar la caché de garantías:", error);
                        }
                }

                function resetListCacheStorageVersion(nextVersion) {
                        if (!Number.isFinite(nextVersion) || nextVersion <= 0) {
                                return;
                        }
                        if (listCacheStorageVersion === nextVersion) {
                                return;
                        }
                        const previousKey = getListCacheStorageKey();
                        listCacheStorageVersion = Math.floor(nextVersion);
                        if (typeof window !== "undefined" && window.sessionStorage) {
                                try {
                                        window.sessionStorage.removeItem(previousKey);
                                } catch (error) {
                                        console.warn(
                                                "No se pudo limpiar la caché antigua de garantías:",
                                                error
                                        );
                                }
                        }
                        listCache.clear();
                        persistListCacheSnapshot(listCache);
                }

                function isCacheEntryUsable(entry) {
                        return Boolean(
                                entry &&
                                        typeof entry === "object" &&
                                        Array.isArray(entry.data)
                        );
                }

                function isCacheEntryFresh(entry) {
                        if (!entry || typeof entry !== "object") {
                                return false;
                        }
                        const fetchedAt = typeof entry.fetchedAt === "number" ? entry.fetchedAt : 0;
                        if (!fetchedAt) {
                                return false;
                        }
                        return Date.now() - fetchedAt <= LIST_CACHE_TTL_MS;
                }

                const persistedListEntries = loadPersistentListCache();
                if (persistedListEntries.length > 0) {
                        for (const [cacheKey, value] of persistedListEntries) {
                                listCache.set(cacheKey, value);
                        }
                        persistListCacheSnapshot(listCache);
                }

                function buildListCacheKey(
                        search = "",
                        estado = "",
                        plan = "",
                        canal = "",
                        concesionario = "",
                        vendorType = "",
                        payment = "",
                        orderBy = "",
                        orderDirection = "",
                        commercial = "",
                        year = "",
                        monthFrom = "",
                        monthTo = ""
                ) {
                        return [
                                search,
                                estado,
                                plan,
                                canal,
                                concesionario,
                                vendorType,
                                payment,
                                orderBy,
                                orderDirection,
                                commercial,
                                year,
                                monthFrom,
                                monthTo,
                        ].join("|");
                }

                function scheduleDetailPreload() {
                        if (detailPreloadScheduled) {
                                return;
                        }
                        detailPreloadScheduled = true;
                        const scheduler =
                                typeof window !== "undefined" &&
                                typeof window.requestIdleCallback === "function"
                                        ? window.requestIdleCallback
                                        : (callback) => setTimeout(callback, 100);
                        scheduler(() => {
                                detailPreloadScheduled = false;
                                processDetailPreloadQueue();
                        });
                }

                async function processDetailPreloadQueue() {
                        if (detailPreloadProcessing) {
                                return;
                        }
                        detailPreloadProcessing = true;
                        try {
                                while (detailPreloadQueue.size > 0) {
                                        const iterator = detailPreloadQueue.values();
                                        const id = iterator.next().value;
                                        detailPreloadQueue.delete(id);
                                        try {
                                                await fetchDetail(id);
                                        } catch (error) {
                                                console.error(
                                                        "❌ Error precargando detalle:",
                                                        error
                                                );
                                        }
                                }
                        } finally {
                                detailPreloadProcessing = false;
                                if (detailPreloadQueue.size > 0) {
                                        scheduleDetailPreload();
                                }
                        }
                }

                function queueDetailPreload(id) {
                        if (id == null) {
                                return;
                        }
                        const normalizedId =
                                typeof id === "number" || typeof id === "string"
                                        ? String(id)
                                        : "";
                        if (!normalizedId) {
                                return;
                        }
                        if (
                                detailCache.has(normalizedId) ||
                                detailPromises.has(normalizedId) ||
                                detailPreloadQueue.has(normalizedId)
                        ) {
                                return;
                        }
                        detailPreloadQueue.add(normalizedId);
                        scheduleDetailPreload();
                }

                function ensureDetailPreloaded(item) {
                        if (!item || typeof item !== "object") {
                                return;
                        }
                        const hasId = Object.prototype.hasOwnProperty.call(item, "id");
                        if (!hasId) {
                                return;
                        }
                        const normalizedId =
                                typeof item.id === "number" || typeof item.id === "string"
                                        ? String(item.id)
                                        : "";
                        if (!normalizedId) {
                                return;
                        }
                        if (item.detail) {
                                detailCache.set(
                                        normalizedId,
                                        normalizeDetailData(item.detail)
                                );
                                return;
                        }
                        queueDetailPreload(normalizedId);
                }

                function renderFromCache(cache, spinnerToken = null) {
                        if (!cache || !tbody || !Array.isArray(cache.data)) {
                                finalizeSpinnerVisibility(0, 0, spinnerToken);
                                return;
                        }
                        tbody.innerHTML = "";
                        resetMobileCards();
                        let appended = 0;
                        for (const item of cache.data) {
                                tbody.appendChild(renderRow(item));
                                ensureDetailPreloaded(item);
                                if (item && Object.prototype.hasOwnProperty.call(item, "id")) {
                                        loadedIds.add(item.id);
                                }
                                appended += 1;
                        }
                        const totalPagesNumber = Number(cache.totalPages);
                        const totalPostsNumber = Number(cache.totalPosts);
                        totalPages = Number.isFinite(totalPagesNumber) && totalPagesNumber > 0
                                ? Math.floor(totalPagesNumber)
                                : 1;
                        totalPosts = Number.isFinite(totalPostsNumber) && totalPostsNumber >= 0
                                ? Math.floor(totalPostsNumber)
                                : 0;
                        const per = Math.max(perPage || DEFAULT_PER, 1);
                        const computedPageCount = appended > 0
                                ? Math.ceil(appended / per)
                                : totalPosts > 0
                                ? Math.ceil(totalPosts / per)
                                : 0;
                        currentPage = computedPageCount > 0
                                ? Math.min(totalPages, computedPageCount)
                                : totalPosts > 0
                                ? Math.min(totalPages, 1)
                                : 0;
                        hasMore = currentPage > 0 && currentPage < totalPages;
                        finalizeSpinnerVisibility(0, appended, spinnerToken);
                        if (!hasMore && scrollEnd) {
                                scrollEnd.hidden = true;
                                scrollEnd.setAttribute("aria-hidden", "true");
                        }
                        if (!hasActiveFilters() && totalPosts === 0) {
                                setEmptyDetailPanel("forward", "no-results");
                        }
                        trySelectInitialMatricula().catch((error) => {
                                console.error(
                                        "❌ Error al seleccionar la garantía inicial desde la caché:",
                                        error
                                );
                        });
                }

                async function trySelectInitialMatricula() {
                        if (!pendingMatSelection || !initialMatQuery) {
                                return;
                        }
                        const normalizedPlate = initialMatQuery
                                .toString()
                                .replace(/\s+/g, "")
                                .toUpperCase();
                        const rows = Array.from(
                                document.querySelectorAll(".guarantees-table__row")
                        );
                        const match = rows.find((row) =>
                                (row.dataset.matricula || "")
                                        .toString()
                                        .replace(/\s+/g, "")
                                        .toUpperCase() === normalizedPlate
                        );
                        if (!match) {
                                return;
                        }
                        try {
                                await activateRow(match);
                        } catch (error) {
                                console.error(
                                        "❌ Error al seleccionar la garantía inicial:",
                                        error
                                );
                        }
                        pendingMatSelection = false;
                        initialMatQuery = "";
                }

                function getSelectedChannelData() {
                        if (!canalSelect) {
                                return { channel: "", vendorType: "" };
                        }
                        const option = canalSelect.options[canalSelect.selectedIndex];
                        if (!option) {
                                return { channel: "", vendorType: "" };
                        }
                        const dataset = option.dataset || {};
                        const channel = dataset.channel || option.value || "";
                        const vendorType = dataset.vendorType || "";

                        return { channel, vendorType };
                }

                function populateClientsSelect(filterType = "") {
                        if (!concesionarioSelect) {
                                return;
                        }
                        const previous = concesionarioSelect.value;
                        concesionarioSelect
                                .querySelectorAll("option:not(:first-child)")
                                .forEach((opt) => opt.remove());

                        if (!Array.isArray(rawClients) || rawClients.length === 0) {
                                selectedConcesionario = "";
                                return;
                        }

                        const filtered = rawClients.filter((client) => {
                                if (!filterType) {
                                        return true;
                                }
                                return (client.type || "") === filterType;
                        });

                        filtered.forEach((client) => {
                                const opt = document.createElement("option");
                                opt.value = String(client.id);
                                opt.textContent = client.name;
                                if (client.type) {
                                        opt.dataset.type = client.type;
                                }
                                concesionarioSelect.appendChild(opt);
                        });

                        if (
                                previous &&
                                filtered.some((client) => String(client.id) === previous)
                        ) {
                                concesionarioSelect.value = previous;
                                selectedConcesionario = previous;
                        } else {
                                concesionarioSelect.value = "";
                                selectedConcesionario = "";
                        }

                        if (!advancedPanel || advancedPanel.hasAttribute("hidden")) {
                                updateBaseFiltersHeight();
                        }

                        updateResetVisibility();
                }

                function refreshClientsVisibility() {
                        const { channel, vendorType } = getSelectedChannelData();
                        selectedCanal = channel;

                        if (!clientsWrapper) {
                                selectedVendorType = "";
                                return;
                        }

                        selectedVendorType = vendorType;
                        const shouldShow =
                                channel === "profesional" || channel === "gestoria";

                        if (shouldShow) {
                                clientsWrapper.hidden = false;
                        } else {
                                clientsWrapper.hidden = true;
                        }

                        if (!shouldShow) {
                                selectedConcesionario = "";
                                if (concesionarioSelect) {
                                        concesionarioSelect.value = "";
                                }
                        }

                        populateClientsSelect(shouldShow ? vendorType : "");
                        if (!advancedPanel || advancedPanel.hasAttribute("hidden")) {
                                updateBaseFiltersHeight();
                        }
                        updateResetVisibility();
                }

                function populateCommercialSelect() {
                        if (!commercialSelect) {
                                return;
                        }
                        const previous = commercialSelect.value || "";
                        commercialSelect
                                .querySelectorAll("option:not(:first-child)")
                                .forEach((opt) => opt.remove());

                        if (!Array.isArray(rawCommercials) || rawCommercials.length === 0) {
                                selectedCommercial = "";
                                commercialSelect.value = "";
                                return;
                        }

                        let hasPrevious = false;
                        rawCommercials.forEach((item) => {
                                const id = item && typeof item.id !== "undefined" ? item.id : null;
                                const name =
                                        item && typeof item.name === "string"
                                                ? item.name
                                                : "";
                                const numericId = Number.parseInt(id, 10);
                                if (!Number.isFinite(numericId) || numericId <= 0 || !name) {
                                        return;
                                }
                                const option = document.createElement("option");
                                option.value = String(numericId);
                                option.textContent = name;
                                commercialSelect.appendChild(option);
                                if (!hasPrevious && previous && String(numericId) === previous) {
                                        hasPrevious = true;
                                }
                        });

                        if (previous && hasPrevious) {
                                commercialSelect.value = previous;
                                selectedCommercial = previous;
                        } else if (previous && !hasPrevious) {
                                commercialSelect.value = "";
                                if (selectedCommercial) {
                                        selectedCommercial = "";
                                        applyFilters();
                                }
                        } else {
                                selectedCommercial = commercialSelect.value || "";
                        }
                        updateResetVisibility();
                }

                function fetchDetail(id) {
                        if (detailCache.has(id)) {
                                return Promise.resolve(detailCache.get(id));
                        }
                        if (detailPromises.has(id)) {
                                return detailPromises.get(id);
                        }
                        const p = fetch(`${restRoot}go/v1/guarantees/${id}`, {
                                headers: { "X-WP-Nonce": restNonce },
                        })
                                .then((res) => {
                                        if (!res.ok) throw res.status;
                                        return res.json();
                                })
                                .then((json) => {
                                        const data = normalizeDetailData(json);
                                        detailCache.set(id, data);
                                        detailPromises.delete(id);
                                        return data;
                                })
                                .catch((err) => {
                                        detailPromises.delete(id);
                                        throw err;
                                });
                        detailPromises.set(id, p);
                        return p;
                }

                document.addEventListener("click", (e) => {
                        const btn = e.target.closest(
                                ".guarantee-detail__btn--continue"
                        );
                        if (!btn) return;
                        const panel = btn.closest(".guarantee-detail__panel");
                        const id = panel?.dataset.loadedId;
                        if (id) {
                                localStorage.setItem("go_draft_id", id);
                                const data = detailCache.get(id);
                                if (data && data.uuid) {
                                        localStorage.setItem(
                                                "go_draft_uuid",
                                                data.uuid
                                        );
                                }
                        }
                        const data = id ? detailCache.get(id) : null;
                        const uuid = data && data.uuid ? data.uuid : "";
                        window.location.href =
                                "/garantias-online/nueva-garantia?uuid=" +
                                encodeURIComponent(uuid);
                });

                function formatAmountForMessage(raw) {
                        if (raw === undefined || raw === null) return "";
                        const formatted = formatPrice(raw);
                        if (formatted && formatted !== "-" && formatted.trim() !== "") {
                                return formatted.includes("€") ? formatted : `${formatted} €`;
                        }
                        const str = String(raw).trim();
                        if (!str) return "";
                        return str.includes("€") ? str : `${str} €`;
                }

                function escapeHtml(value) {
                        if (value === undefined || value === null) {
                                return "";
                        }
                        return String(value)
                                .replace(/&/g, "&amp;")
                                .replace(/</g, "&lt;")
                                .replace(/>/g, "&gt;")
                                .replace(/"/g, "&quot;")
                                .replace(/'/g, "&#039;");
                }

                function formatNotificationMetaText(value) {
                        if (value === undefined || value === null) {
                                return "";
                        }
                        return String(value).trim();
                }

                function dispatchNotificationEvent(detail) {
                        if (!notificationsRoot || !detail) {
                                return;
                        }
                        if (typeof window === "undefined" || typeof window.dispatchEvent !== "function") {
                                return;
                        }
                        try {
                                window.dispatchEvent(
                                        new CustomEvent("go360:notifications:inject", { detail })
                                );
                        } catch (error) {
                                if (typeof document !== "undefined" && typeof document.createEvent === "function") {
                                        const fallback = document.createEvent("CustomEvent");
                                        fallback.initCustomEvent(
                                                "go360:notifications:inject",
                                                true,
                                                true,
                                                detail
                                        );
                                        window.dispatchEvent(fallback);
                                }
                        }
                }

                function notifyGuaranteeTrashed(context = {}) {
                        if (!notificationsRoot) {
                                return;
                        }
                        const matricula = formatNotificationMetaText(context.matricula);
                        const plan = formatNotificationMetaText(context.plan);
                        const guaranteeId = formatNotificationMetaText(context.id);
                        const meta = [];
                        if (matricula) {
                                meta.push({ label: "Matrícula", text: matricula });
                        }
                        if (plan) {
                                meta.push({ label: "Plan", text: plan });
                        }
                        if (guaranteeId) {
                                meta.push({ label: "ID", text: `#${guaranteeId}` });
                        }
                        const safePlate = matricula ? escapeHtml(matricula) : "";
                        const body = safePlate
                                ? `La garantía <strong>${safePlate}</strong> se envió a la papelera.`
                                : "La garantía se envió a la papelera.";
                        const detail = {
                                id: Date.now(),
                                title: "Garantía eliminada",
                                body,
                                icon_slug: "delete",
                                icon_svg: deleteNotificationIcon,
                                badge: "Garantías",
                                tone: "danger",
                                meta,
                                created_at: new Date().toISOString(),
                        };
                        dispatchNotificationEvent(detail);
                }

                function parseDisplayDate(value) {
                        if (typeof value !== "string") {
                                return null;
                        }
                        const trimmed = value.trim();
                        if (!trimmed) {
                                return null;
                        }
                        const parts = trimmed.split(/[\/]/);
                        if (parts.length < 3) {
                                return null;
                        }
                        const day = parseInt(parts[0], 10);
                        const month = parseInt(parts[1], 10);
                        let year = parseInt(parts[2], 10);
                        if (Number.isNaN(day) || Number.isNaN(month) || Number.isNaN(year)) {
                                return null;
                        }
                        if (year < 100) {
                                year += 2000;
                        }
                        const date = new Date(year, month - 1, day);
                        if (Number.isNaN(date.getTime())) {
                                return null;
                        }
                        return date;
                }

                function handleConfirmClick(e) {
                        const btn = e.target.closest(
                                ".guarantee-detail__btn--confirm"
                        );
                        if (!btn) return;
                        const panel = btn.closest(".guarantee-detail__panel");
                        if (!panel) return;
                        const id = panel.dataset.loadedId;
                        if (!id) return;
                        const cacheData = detailCache.get(id);
                        const uuid = cacheData && cacheData.uuid ? cacheData.uuid : "";
                        if (!uuid) return;
                        const textSpan = btn.querySelector(
                                ".guarantee-detail__btn-text"
                        );
                        const currentLabel = textSpan
                                ? textSpan.textContent.trim()
                                : "";
                        const row = tbody.querySelector(
                                `.guarantees-table__row[data-id="${id}"]`
                        );
                        const metodoRaw =
                                (cacheData && cacheData.metodo_pago) ||
                                (row && row.dataset.metodoPago) ||
                                "";
                        const metodo =
                                typeof metodoRaw === "string"
                                        ? metodoRaw.toLowerCase().trim()
                                        : "";
                        const isDomiciliacion = metodo.startsWith("domiciliacion");
                        const vendorName =
                                (cacheData && cacheData.concesionario) ||
                                (row && row.dataset.vendedor_name) ||
                                "";
                        const priceRaw =
        (cacheData && cacheData.precio) ||
        (row && row.dataset.precio) ||
        "";
    const formattedAmount = formatAmountForMessage(priceRaw);
                        const accountCandidate =
                                (cacheData &&
                                        (cacheData.transfer_iban || cacheData.iban_vendedor)) ||
                                (row && (row.dataset.transferIban || row.dataset.ibanVendedor)) ||
                                "";
                        const accountTextRaw =
                                typeof accountCandidate === "string"
                                        ? accountCandidate.trim()
                                        : "";
                        const matricula =
                                panel.dataset.matricula ||
                                (row && row.dataset.matricula) ||
                                (cacheData && cacheData.matricula) ||
                                "";
                        const title = isDomiciliacion
                                ? "Confirmar cobro por domiciliación"
                                : "Confirmar transferencia";
                        const safeSubtitleId = escapeHtml(matricula || id);
                        const subtitle = safeSubtitleId
                                ? `Garantía <strong>${safeSubtitleId}</strong>`
                                : "";
                        const cleanedVendorName =
                                typeof vendorName === "string" ? vendorName.trim() : "";
                        const companyText =
                                cleanedVendorName !== "" && cleanedVendorName !== "-"
                                        ? cleanedVendorName
                                        : "el cliente";
                        const normalizedAmount = formattedAmount.trim();
                        const hasAmount =
                                normalizedAmount !== "" &&
                                normalizedAmount !== "-" &&
                                normalizedAmount !== "- €";
                        const amountText = hasAmount
                                ? normalizedAmount
                                : "el importe acordado";
                        const hasAccount =
                                accountTextRaw !== "" && accountTextRaw !== "-";
                        const accountText = hasAccount ? accountTextRaw : "";
                        const periodStartDisplay =
                                (cacheData && cacheData.desde_fmt) ||
                                (row && row.dataset.desdeFmt) ||
                                "";
                        const safeCompany = escapeHtml(companyText);
                        const safeAmount = escapeHtml(amountText);
                        const safeAccount = escapeHtml(accountText);

                        let message;
                        if (isDomiciliacion) {
                                message = `Confirmo que 360VO ha gestionado el cobro por domiciliación por valor de <strong>${safeAmount}</strong> a <strong>${safeCompany}</strong>.`;
                        } else if (safeAccount) {
                                message = `Confirmo que <strong>${safeCompany}</strong> ha realizado la transferencia por valor de <strong>${safeAmount}</strong> a la cuenta ${safeAccount}.`;
                        } else {
                                message = `Confirmo que <strong>${safeCompany}</strong> ha realizado la transferencia por valor de <strong>${safeAmount}</strong>.`;
                        }

                        const startIso =
                                (row && row.dataset.desde) ||
                                (cacheData && cacheData.desde) ||
                                "";
                        let paymentWindowText = "";
                        if (!isDomiciliacion) {
                                let startDate = null;
                                if (startIso) {
                                        const parsedIso = new Date(startIso);
                                        if (!Number.isNaN(parsedIso.getTime())) {
                                                startDate = parsedIso;
                                        }
                                }
                                if (!startDate && periodStartDisplay) {
                                        startDate = parseDisplayDate(periodStartDisplay);
                                }
                                if (startDate) {
                                        const formatter = new Intl.DateTimeFormat("es-ES", {
                                                day: "2-digit",
                                                month: "2-digit",
                                                year: "2-digit",
                                        });
                                        const deadline = new Date(
                                                startDate.getTime() +
                                                        48 * 60 * 60 * 1000
                                        );
                                        const startDisplay = formatter.format(startDate);
                                        const deadlineDisplay = formatter.format(deadline);
                                        paymentWindowText = `Plazo de pago: del ${startDisplay} al ${deadlineDisplay}`;
                                }
                        }

                        if (paymentWindowText) {
                                const escapedWindow = escapeHtml(paymentWindowText);
                                message += ` <span class="confirm-modal__payment-window">${escapedWindow}</span>`;
                        }

                        const note = !isDomiciliacion ? "La garantía se activará." : "";
                        const context = {
                                intent: "activate",
                                btn,
                                panel,
                                id,
                                uuid,
                                metodo,
                                cacheData,
                                row,
                                resetLabel:
                                        currentLabel ||
                                        (isDomiciliacion
                                                ? "Confirmar domiciliación"
                                                : "Confirmar pago"),
                        };
                        if (confirmModalController) {
                                pendingConfirmContext = context;
                                confirmModalController.open({
                                        title,
                                        subtitle,
                                        message,
                                        note,
                                        confirmLabel: "Confirmar",
                                        requireAcknowledgement: true,
                                });
                                return;
                        }
                        runConfirmRequest(context);
                }

                function handleTransferReportClick(event) {
                        const btn = event.target.closest(
                                ".guarantee-detail__btn--transfer-report"
                        );
                        if (!btn) {
                                return;
                        }
                        const panel = btn.closest(".guarantee-detail__panel");
                        if (!panel) {
                                return;
                        }
                        const id = panel.dataset.loadedId;
                        if (!id) {
                                return;
                        }
                        const cacheData = detailCache.get(id);
                        if (!cacheData) {
                                return;
                        }
                        const row = tbody.querySelector(
                                `.guarantees-table__row[data-id="${id}"]`
                        );
                        const matricula =
                                cacheData.matricula ||
                                (row && row.dataset.matricula) ||
                                panel.dataset.matricula ||
                                "";
                        const conceptRaw = `Garantía ${matricula || id}`;
                        const amountRaw = formatAmountForMessage(
                                cacheData.precio || (row && row.dataset.precio) || ""
                        );
                        const normalizedAmount = amountRaw.trim();
                        const hasAmount =
                                normalizedAmount !== "" &&
                                normalizedAmount !== "-" &&
                                normalizedAmount !== "- €";
                        const transferAccount = (() => {
                                const candidate =
                                        cacheData.transfer_iban ||
                                        cacheData.iban_vendedor ||
                                        (row && (row.dataset.transferIban || row.dataset.ibanVendedor)) ||
                                        "";
                                return typeof candidate === "string"
                                        ? candidate.trim()
                                        : "";
                        })();
                        const safeConcept = escapeHtml(conceptRaw);
                        const safeAmount = hasAmount ? escapeHtml(normalizedAmount) : "";
                        const hasAccount = transferAccount !== "" && transferAccount !== "-";
                        const safeAccount = hasAccount ? escapeHtml(transferAccount) : "";
                        const safeSubtitleId = escapeHtml(matricula || id);
                        let message = "Adjunta el comprobante de la transferencia";
                        if (safeAmount) {
                                message += ` por <strong>${safeAmount}</strong>`;
                        }
                        if (safeAccount) {
                                message += ` realizada a la cuenta <strong>${safeAccount}</strong>`;
                        }
                        message += `, utilizando el concepto <strong>${safeConcept}</strong>.`;
                        const note =
                                "Validaremos la operación y recibirás un correo cuando la garantía esté activa.";
                        const subtitle = safeSubtitleId
                                ? `Garantía <strong>${safeSubtitleId}</strong>`
                                : "";
                        const context = {
                                intent: "transfer-report",
                                btn,
                                panel,
                                id,
                                row,
                                confirmPayload: {
                                        concept: conceptRaw,
                                        amount: hasAmount ? normalizedAmount : "",
                                        account: hasAccount ? transferAccount : "",
                                },
                                resetLabel:
                                        btn.querySelector(".guarantee-detail__btn-text")?.textContent.trim() ||
                                        "Ya he realizado la transferencia",
                        };
                        if (confirmModalController) {
                                pendingConfirmContext = context;
                                confirmModalController.open({
                                        title: "Confirmación de Pago",
                                        subtitle,
                                        message,
                                        note,
                                        confirmLabel: "Enviar comprobante",
                                        requireFile: true,
                                });
                                return;
                        }
                        runConfirmRequest(context);
                }

                function runGenerateInvoice(context, controls = {}) {
                        if (!context) {
                                return;
                        }
                        const { confirmBtn, cancelBtn, closeModal, statusEl } = controls;
                        if (!confirmBtn || typeof closeModal !== "function") {
                                return;
                        }
                        const resetLabel = context.resetLabel || confirmBtn.textContent || "Generar factura";
                        confirmBtn.classList.add("is-loading");
                        confirmBtn.textContent = "Generando factura";
                        confirmBtn.disabled = true;
                        if (cancelBtn) {
                                cancelBtn.disabled = true;
                        }
                        if (statusEl) {
                                statusEl.textContent = "Generando factura...";
                                statusEl.hidden = false;
                        }
                        window.setTimeout(() => {
                                confirmBtn.classList.remove("is-loading");
                                confirmBtn.textContent = resetLabel;
                                if (cancelBtn) {
                                        cancelBtn.disabled = false;
                                }
                                closeModal();
                        }, 1800);
                }

                function runConfirmRequest(context) {
                        if (!context) return;
                        if (context.intent === "trash") {
                                runTrashRequest(context);
                                return;
                        }
                        if (context.intent === "transfer-report") {
                                runTransferReportRequest(context);
                                return;
                        }
                        const { btn, panel, id, uuid } = context;
                        if (!btn || !panel || !id || !uuid) return;
                        const textSpan = btn.querySelector(
                                ".guarantee-detail__btn-text"
                        );
                        const metodo =
                                typeof context.metodo === "string"
                                        ? context.metodo.toLowerCase()
                                        : "";
                        const resetText =
                                context.resetLabel ||
                                (textSpan
                                        ? textSpan.textContent.trim()
                                        : metodo.startsWith("domiciliacion")
                                        ? "Confirmar domiciliación"
                                        : "Confirmar pago");
                        let spinner = btn.querySelector(
                                ".guarantee-detail__btn-spinner"
                        );
                        if (!spinner) {
                                spinner = document.createElement("span");
                                spinner.className = "guarantee-detail__btn-spinner";
                                if (textSpan) {
                                        btn.insertBefore(spinner, textSpan);
                                } else {
                                        btn.appendChild(spinner);
                                }
                        }
                        if (textSpan) {
                                textSpan.textContent = "Activando garantía";
                        }
                        btn.disabled = true;
                        saveStatus.classList.remove("autosave-status--hidden");
                        let row = context.row;
                        if (!row || !row.isConnected) {
                                row = tbody.querySelector(
                                        `.guarantees-table__row[data-id="${id}"]`
                                );
                        }
                        const body = {
                                id,
                                uuid,
                                data: {
                                        estado_garantia: {
                                                estado_contratacion: "activada",
                                        },
                                },
                        };
                        if (metodo.startsWith("domiciliacion")) {
                                body.data.garantia_contratada = {
                                        estado_cobro: { cobro_realizado: true },
                                };
                        }
                        fetch(`${restRoot}go/v1/guarantees/autosave`, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": restNonce,
                                },
                                body: JSON.stringify(body),
                        })
                                .then((res) => {
                                        if (!res.ok) throw res.status;
                                        detailCache.delete(id);
                                        if (!row || !row.isConnected) {
                                                row = tbody.querySelector(
                                                        `.guarantees-table__row[data-id="${id}"]`
                                                );
                                        }
                                        if (row) {
                                                row.dataset.estadoclase = "activada";
                                                row.dataset.estado = "Activada";
                                                row.dataset.cobroRealizado = "1";
                                                const badge = row.querySelector(
                                                        ".guarantees-list__badge"
                                                );
                                                if (badge) {
                                                        badge.textContent = "Activada";
                                                        badge.className =
                                                                "guarantees-list__badge guarantees-list__badge--activada";
                                                }
                                                const cobroBadge = row.querySelector(
                                                        ".guarantees-list__badge--pend-cobro"
                                                );
                                                if (cobroBadge) {
                                                        cobroBadge.remove();
                                                }
                                        }
                                        return fetch(`${restRoot}go/v1/guarantees/${id}`, {
                                                headers: { "X-WP-Nonce": restNonce },
                                        });
                                })
                                .then((detailRes) => {
                                        if (!detailRes.ok) throw detailRes.status;
                                        const rowData = row ? buildRowData(row) : {};
                                        return detailRes.json().then((json) => {
                                                const data = normalizeDetailData(json);
                                                detailCache.set(id, data);
                                                panel.innerHTML = renderFullDetail(data, rowData);
                                                setupTransferCountdown(panel);
                                                panel.dataset.matricula =
                                                        data.matricula || rowData.matricula || "";
                                                panel.dataset.plan = data.plan || rowData.plan || "";
                                                hydrateManagementDataset(panel, data, rowData);
                                                syncManagementDetailFields(panel);
                                                syncPdfModalDocs(panel);
                                        });
                                })
                                .catch((err) => {
                                        console.error("Error confirm payment:", err);
                                })
                                .finally(() => {
                                        btn.disabled = false;
                                        saveStatus.classList.add(
                                                "autosave-status--hidden"
                                        );
                                        if (textSpan) {
                                                const fallbackLabel = metodo.startsWith(
                                                        "domiciliacion"
                                                )
                                                        ? "Confirmar domiciliación"
                                                        : "Confirmar pago";
                                                textSpan.textContent = resetText || fallbackLabel;
                                        }
                                        if (spinner && spinner.parentNode) {
                                                spinner.remove();
                                        }
                                });
                }

                function runTransferReportRequest(context) {
                        const { btn, panel, id } = context;
                        if (!btn || !panel || !id) return;
                        const textSpan = btn.querySelector(
                                ".guarantee-detail__btn-text"
                        );
                        const resetText =
                                context.resetLabel ||
                                (textSpan
                                        ? textSpan.textContent.trim()
                                        : "Ya he realizado la transferencia");
                        let spinner = btn.querySelector(
                                ".guarantee-detail__btn-spinner"
                        );
                        if (!spinner) {
                                spinner = document.createElement("span");
                                spinner.className = "guarantee-detail__btn-spinner";
                                if (textSpan) {
                                        btn.insertBefore(spinner, textSpan);
                                } else {
                                        btn.appendChild(spinner);
                                }
                        }
                        if (textSpan) {
                                textSpan.textContent = "Enviando aviso";
                        }
                        btn.disabled = true;
                        if (saveStatusText) {
                                saveStatusText.textContent = "Enviando aviso";
                        }
                        saveStatus.classList.remove("autosave-status--hidden");
                        const payload = context.confirmPayload || {};
                        const formData = new FormData();
                        formData.append("concept", payload.concept || "");
                        formData.append("amount", payload.amount || "");
                        formData.append("account", payload.account || "");
                        if (context.receiptFile instanceof File) {
                                formData.append(
                                        "receipt",
                                        context.receiptFile,
                                        context.receiptFile.name || "justificante"
                                );
                        }
                        context.receiptFile = null;
                        let row = context.row;
                        if (!row || !row.isConnected) {
                                row = tbody.querySelector(
                                        `.guarantees-table__row[data-id="${id}"]`
                                );
                        }
                        fetch(`${restRoot}go/v1/guarantees/${id}/confirm-transfer`, {
                                method: "POST",
                                headers: {
                                        "X-WP-Nonce": restNonce,
                                },
                                body: formData,
                        })
                                .then(async (res) => {
                                        if (!res.ok) {
                                                let message = "No se pudo enviar la confirmación. Inténtalo de nuevo.";
                                                try {
                                                        const data = await res.json();
                                                        if (data && typeof data.message === "string" && data.message.trim() !== "") {
                                                                message = data.message;
                                                        }
                                                } catch (jsonError) {
                                                        try {
                                                                const text = await res.text();
                                                                if (typeof text === "string" && text.trim() !== "") {
                                                                        message = text;
                                                                }
                                                        } catch (textError) {
                                                                // ignore
                                                        }
                                                }
                                                throw new Error(message);
                                        }
                                        return res.json();
                                })
                                .then((json) => {
                                        const detailResponse = json?.detail || json;
                                        if (!detailResponse) {
                                                return;
                                        }
                                        const data = normalizeDetailData(detailResponse);
                                        detailCache.set(id, data);
                                        if (!row || !row.isConnected) {
                                                row = tbody.querySelector(
                                                        `.guarantees-table__row[data-id="${id}"]`
                                                );
                                        }
                                        const newEstadoValue =
                                                (data.estado && data.estado.value) ||
                                                "validacion_pendiente";
                                        const newEstadoLabel =
                                                (data.estado && data.estado.label) ||
                                                "Validación pendiente";
                                        const newEstadoClase = normalizeEstadoClase(
                                                newEstadoValue
                                        );
                                        if (row) {
                                                row.dataset.estadoclase = newEstadoClase;
                                                row.dataset.estado = newEstadoLabel;
                                                const badge = row.querySelector(
                                                        ".guarantees-list__badge"
                                                );
                                                if (badge) {
                                                        badge.className =
                                                                "guarantees-list__badge guarantees-list__badge--" +
                                                                newEstadoClase;
                                                        badge.textContent = newEstadoLabel;
                                                }
                                        }
                                        const rowData = row ? buildRowData(row) : {};
                                        panel.innerHTML = renderFullDetail(
                                                data,
                                                rowData
                                        );
                                        panel.dataset.matricula =
                                                data.matricula || rowData.matricula || "";
                                        panel.dataset.plan = data.plan || rowData.plan || "";
                                        panel.dataset.estado = newEstadoLabel;
                                        panel.dataset.estadoclase = newEstadoClase;
                                        panel.dataset.loadedId = id;
                                        hydrateManagementDataset(panel, data, rowData);
                                        syncManagementDetailFields(panel);
                                        setupTransferCountdown(panel);
                                        syncPdfModalDocs(panel);
                                        showDetailToast(
                                                panel,
                                                "Hemos avisado a 360VO. Revisarán la transferencia en las próximas horas."
                                        );
                                })
                                .catch((err) => {
                                        console.error("Error reporting transfer:", err);
                                        const message = err instanceof Error && err.message
                                                ? err.message
                                                : "No se pudo enviar la confirmación. Inténtalo de nuevo.";
                                        showDetailToast(panel, message);
                                })
                                .finally(() => {
                                        btn.disabled = false;
                                        if (textSpan) {
                                                textSpan.textContent = resetText;
                                        }
                                        if (spinner && spinner.parentNode) {
                                                spinner.remove();
                                        }
                                        if (saveStatusText) {
                                                saveStatusText.textContent = "Guardando";
                                        }
                                        saveStatus.classList.add(
                                                "autosave-status--hidden"
                                        );
                                });
                }

                function runTrashRequest(context) {
                        if (!context) return;
                        const { btn, panel, id } = context;
                        if (!panel || !id) {
                                return;
                        }
                        const normalizedId = String(id);
                        const detailMatricula = panel.dataset.matricula || "";
                        const detailPlan = panel.dataset.plan || "";
                        const textSpan = btn?.querySelector(".guarantee-detail__btn-text") || null;
                        const resetText =
                                context.resetLabel ||
                                (textSpan ? textSpan.textContent.trim() : "Eliminar garantía");
                        let spinner = btn?.querySelector(".guarantee-detail__btn-spinner") || null;
                        if (btn) {
                                if (!spinner) {
                                        spinner = document.createElement("span");
                                        spinner.className = "guarantee-detail__btn-spinner";
                                        if (textSpan) {
                                                btn.insertBefore(spinner, textSpan);
                                        } else {
                                                btn.appendChild(spinner);
                                        }
                                }
                                btn.disabled = true;
                        }
                        if (textSpan) {
                                textSpan.textContent = "Enviando…";
                        }

                        fetch(`${restRoot}go/v1/guarantees/${normalizedId}/trash`, {
                                method: "DELETE",
                                headers: {
                                        "X-WP-Nonce": restNonce,
                                },
                        })
                                .then(async (res) => {
                                        if (res.ok) {
                                                try {
                                                        return await res.json();
                                                } catch (jsonError) {
                                                        return {};
                                                }
                                        }
                                        let message = "No se ha podido enviar la garantía a la papelera.";
                                        try {
                                                const data = await res.json();
                                                if (data && typeof data.message === "string" && data.message.trim() !== "") {
                                                        message = data.message;
                                                }
                                        } catch (jsonError) {
                                                try {
                                                        const text = await res.text();
                                                        if (typeof text === "string" && text.trim() !== "") {
                                                                message = text;
                                                        }
                                                } catch (textError) {
                                                        // ignore
                                                }
                                        }
                                        throw new Error(message);
                                })
                                .then(() => {
                                        detailCache.delete(normalizedId);
                                        const row =
                                                context.row && context.row.isConnected
                                                        ? context.row
                                                        : findRowById(normalizedId);
                                        const wasSelected = Boolean(row && row.classList.contains("selected"));
                                        if (row && row.parentNode) {
                                                row.parentNode.removeChild(row);
                                        }
                                        if (row && prevSelectedRow === row) {
                                                prevSelectedRow = null;
                                                prevIdx = null;
                                        }
                                        const card = mobileCardsMap.get(normalizedId);
                                        if (card && card.parentNode) {
                                                card.parentNode.removeChild(card);
                                        }
                                        mobileCardsMap.delete(normalizedId);
                                        syncMobileCardsEmptyState();
                                        refreshSelectedRowIndex();
                                        lastDetailTrigger = null;
                                        setEmptyDetailPanel("forward", "awaiting");
                                        notifyGuaranteeTrashed({
                                                id: normalizedId,
                                                matricula: detailMatricula,
                                                plan: detailPlan,
                                        });
                                        showDetailToast(panel, "La garantía se envió a la papelera.");
                                })
                                .catch((error) => {
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se ha podido enviar la garantía a la papelera.";
                                        showDetailToast(panel, message);
                                })
                                .finally(() => {
                                        if (textSpan) {
                                                textSpan.textContent = resetText || "Eliminar garantía";
                                        }
                                        if (spinner && spinner.parentNode) {
                                                spinner.remove();
                                        }
                                        if (btn) {
                                                btn.disabled = false;
                                        }
                                });
                }

                function runCancelRequest(context, ui = {}) {
                        const { confirmBtn = null, closeModal = () => {} } = ui;
                        const panel = context.panel || document.querySelector(".guarantee-detail__panel.active");
                        const id = context.id || panel?.dataset?.loadedId || "";
                        if (!id) {
                                closeModal();
                                return;
                        }

                        let row = context.row && context.row.isConnected ? context.row : findRowById(id);
                        const detail = detailCache.get(id) || {};
                        const uuid = context.uuid || detail.uuid || "";
                        const originalLabel = confirmBtn?.textContent?.trim() || "Cancelar garantía";

                        const modal = confirmBtn?.closest(".confirm-modal") || null;
                        const statusMessage = modal?.querySelector(".confirm-modal__status") || null;
                        let confirmSpinner = null;

                        const setStatusMessage = (text) => {
                                if (!statusMessage) return;
                                if (text) {
                                        statusMessage.textContent = text;
                                        statusMessage.hidden = false;
                                } else {
                                        statusMessage.textContent = "";
                                        statusMessage.hidden = true;
                                }
                        };

                        const setConfirmLabel = (text, disabled, showSpinner = false) => {
                                if (!confirmBtn) return;
                                confirmBtn.textContent = text;
                                confirmBtn.disabled = Boolean(disabled);

                                if (showSpinner) {
                                        if (!confirmSpinner) {
                                                confirmSpinner = document.createElement("span");
                                                confirmSpinner.className = "guarantee-detail__btn-spinner";
                                                confirmSpinner.setAttribute("aria-hidden", "true");
                                        }
                                        if (confirmSpinner.parentNode !== confirmBtn) {
                                                confirmBtn.insertBefore(confirmSpinner, confirmBtn.firstChild);
                                        }
                                } else if (confirmSpinner && confirmSpinner.parentNode === confirmBtn) {
                                        confirmSpinner.remove();
                                }
                        };

                        setStatusMessage("Espera por favor, esto puede tardar unos segundos.");
                        setConfirmLabel("Cancelando garantía", true, true);

                        const todayIso = formatDateIsoLocal(new Date());
                        const cancelReasons = resolveCancelReasons(context);
                                const body = {
                                        id,
                                        uuid,
                                        data: {
                                                estado_garantia: {
                                                        estado_contratacion: "cancelada",
                                                        fecha_cancelacion: todayIso,
                                                        motivo_cancelacion: cancelReasons.reason || "",
                                                        otra_causa: cancelReasons.other || "",
                                                },
                                        },
                                        notify_customer: Boolean(context.notifyCustomer),
                                };

                        fetch(`${restRoot}go/v1/guarantees/autosave`, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": restNonce,
                                },
                                body: JSON.stringify(body),
                        })
                                .then(async (res) => {
                                        if (!res.ok) {
                                                let message = "No se ha podido cancelar la garantía.";
                                                try {
                                                        const data = await res.json();
                                                        if (data && typeof data.message === "string" && data.message.trim() !== "") {
                                                                message = data.message;
                                                        }
                                                } catch (jsonError) {
                                                        try {
                                                                const text = await res.text();
                                                                if (typeof text === "string" && text.trim() !== "") {
                                                                        message = text;
                                                                }
                                                        } catch (textError) {
                                                                // ignore
                                                        }
                                                }
                                                throw new Error(message);
                                        }
                                        return res.json();
                                })
                                .then((json) => {
                                        const detailResponse = json?.detail || json;
                                        if (!detailResponse) {
                                                return;
                                        }
                                        const data = normalizeDetailData(detailResponse);
                                        detailCache.set(id, data);

                                        if (!row || !row.isConnected) {
                                                row = findRowById(id);
                                        }

                                        const newEstadoValue = (data.estado && data.estado.value) || "cancelada";
                                        const newEstadoLabel = (data.estado && data.estado.label) || "Cancelada";
                                        const newEstadoClase = normalizeEstadoClase(newEstadoValue);

                                        if (row) {
                                                row.dataset.estadoclase = newEstadoClase;
                                                row.dataset.estado = newEstadoLabel;
                                                const badge = row.querySelector(".guarantees-list__badge");
                                                if (badge) {
                                                        badge.className =
                                                                "guarantees-list__badge guarantees-list__badge--" + newEstadoClase;
                                                        badge.textContent = newEstadoLabel;
                                                }
                                        }

                                        const rowData = row ? buildRowData(row) : {};
                                        const targetPanel = panel || document.querySelector(".guarantee-detail__panel.active");
                                        if (targetPanel) {
                                                targetPanel.innerHTML = renderFullDetail(data, rowData);
                                                targetPanel.dataset.matricula =
                                                        data.matricula || rowData.matricula || "";
                                                targetPanel.dataset.plan = data.plan || rowData.plan || "";
                                                targetPanel.dataset.estado = newEstadoLabel;
                                                targetPanel.dataset.estadoclase = newEstadoClase;
                                                targetPanel.dataset.loadedId = id;
                                                hydrateManagementDataset(targetPanel, data, rowData);
                                                syncManagementDetailFields(targetPanel);
                                                setupTransferCountdown(targetPanel);
                                                syncPdfModalDocs(targetPanel);
                                                updateManagementHeaderFromDetail(targetPanel);
                                                syncManagementActionsAvailability(targetPanel);
                                showDetailToast(targetPanel, "Garantía cancelada.");
                        }

                                        if (
                                                typeof window !== "undefined" &&
                                                typeof window.dispatchEvent === "function"
                                        ) {
                                                try {
                                                        console.log(
                                                                "[GO360][cancel] Notificación de cancelación registrada, refrescando panel",
                                                                {
                                                                        id,
                                                                        plate:
                                                                                data.matricula ||
                                                                                rowData.matricula ||
                                                                                "",
                                                                        reason: cancelReasons.reason || "",
                                                                }
                                                        );
                                                } catch (logError) {
                                                        // noop
                                                }
                                                window.dispatchEvent(
                                                        new CustomEvent("go360:notifications:refresh")
                                                );
                                        }

                                        setConfirmLabel("Garantía cancelada", true);
                                        setStatusMessage("");
                                        pendingConfirmContext = null;
                                        window.setTimeout(() => {
                                                closeModal();
                                        }, 1200);
                                })
                                .catch((error) => {
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se ha podido cancelar la garantía.";
                                        const targetPanel = panel || document.querySelector(".guarantee-detail__panel.active");
                                        if (targetPanel) {
                                                showDetailToast(targetPanel, message);
                                        }
                                        setConfirmLabel(originalLabel, false);
                                        setStatusMessage("");
                                        pendingConfirmContext = null;
                                        closeModal();
                                });
                }

                document.addEventListener("click", handleConfirmClick);
                document.addEventListener("click", handleTransferReportClick);
                document.addEventListener("click", handleShareClick);
                document.addEventListener("click", handleTrashClick);
                document.addEventListener("click", handleManagementClicks);
                document.addEventListener("click", handleManagementNotesClick);
                document.addEventListener("keydown", handleManagementKeydown);

                function setupConfirmModal(modal) {
                        if (!modal) return null;
                        const dialog = modal.querySelector(".confirm-modal__dialog");
                        if (!dialog) return null;
                        const titleEl = modal.querySelector(".confirm-modal__title");
                        const subtitleEl = modal.querySelector(".confirm-modal__subtitle");
                        const messageEl = modal.querySelector(".confirm-modal__message");
                        const invoiceFields = modal.querySelector(
                                "[data-confirm-invoice-fields]"
                        );
                        const invoiceReferenceInput = modal.querySelector(
                                "[data-confirm-invoice-reference]"
                        );
                        const invoiceSendEmailInput = modal.querySelector(
                                "[data-confirm-invoice-send-email]"
                        );
                        const invoiceShowDocsInput = modal.querySelector(
                                "[data-confirm-invoice-show-documentation]"
                        );
                        const noteEl = modal.querySelector(".confirm-modal__note");
                        const notifyNoteEl = modal.querySelector(
                                ".confirm-modal__note--notify"
                        );
                        const statusEl = modal.querySelector(".confirm-modal__status");
                        const confirmBtn = modal.querySelector(
                                ".confirm-modal__btn--confirm"
                        );
                        const cancelBtn = modal.querySelector(
                                ".confirm-modal__btn--cancel"
                        );
                        const closeBtn = modal.querySelector(".confirm-modal__close");
                        const uploadBlock = modal.querySelector(".confirm-modal__upload");
                        const fileInput = modal.querySelector(".confirm-modal__file-input");
                        const fileNameEl = modal.querySelector(".confirm-modal__file-name");
                        const fileErrorEl = modal.querySelector(".confirm-modal__file-error");
                        const checkboxWrapper = modal.querySelector(
                                ".confirm-modal__checkbox"
                        );
                        const checkboxInput = modal.querySelector(
                                ".confirm-modal__checkbox-input"
                        );
                        const checkboxLabel = modal.querySelector(
                                ".confirm-modal__checkbox-label"
                        );
                        const notifyCheckboxWrapper = modal.querySelector(
                                ".confirm-modal__checkbox--notify"
                        );
                        const notifyCheckboxInput = modal.querySelector(
                                ".confirm-modal__checkbox-input--notify"
                        );
                        const notifyCheckboxLabel = modal.querySelector(
                                ".confirm-modal__checkbox-label--notify"
                        );
                        const cancelFields = modal.querySelector(
                                ".confirm-modal__cancel-fields"
                        );
                        const cancelSelect = modal.querySelector(
                                "[data-confirm-cancel-reason]"
                        );
                        const cancelOtherField = modal.querySelector(
                                ".confirm-modal__other-reason"
                        );
                        const cancelOtherInput = modal.querySelector(
                                "[data-confirm-cancel-other]"
                        );
                        const fileEmptyText = fileNameEl ? fileNameEl.dataset.empty || "" : "";
                        const defaultCheckboxLabel = checkboxLabel ? checkboxLabel.textContent : "";
                        const defaultNotifyLabel = notifyCheckboxLabel
                                ? notifyCheckboxLabel.textContent
                                : "";
                        let requiresFile = false;
                        let requiresAcknowledgement = false;
                        let requiresReason = false;
                        let requiresInvoiceReference = false;
                        let isInvoiceMode = false;
                        let notifyNoteText = "";
                        let selectedFile = null;
                        let fileErrorMessage = "";
                        let isModalOpen = false;
                        let activeVariant = "";

                        function applyCheckboxLabel(labelText) {
                                if (!checkboxLabel) {
                                        return;
                                }
                                if (labelText) {
                                        checkboxLabel.textContent = labelText;
                                } else if (defaultCheckboxLabel) {
                                        checkboxLabel.textContent = defaultCheckboxLabel;
                                }
                        }

                        function applyNotifyLabel(labelText) {
                                if (!notifyCheckboxLabel) {
                                        return;
                                }
                                if (labelText) {
                                        notifyCheckboxLabel.textContent = labelText;
                                } else if (defaultNotifyLabel) {
                                        notifyCheckboxLabel.textContent = defaultNotifyLabel;
                                }
                        }

                        function updateNotifyNote() {
                                if (!notifyNoteEl) return;
                                const shouldShow =
                                        Boolean(notifyNoteText) &&
                                        notifyCheckboxInput &&
                                        notifyCheckboxInput.checked;
                                notifyNoteEl.textContent = shouldShow ? notifyNoteText : "";
                                notifyNoteEl.hidden = !shouldShow;
                        }

                        function resetInvoiceFields() {
                                if (invoiceFields) {
                                        invoiceFields.hidden = true;
                                }
                                if (invoiceReferenceInput) {
                                        invoiceReferenceInput.value = "";
                                }
                                if (invoiceSendEmailInput) {
                                        invoiceSendEmailInput.checked = false;
                                }
                                if (invoiceShowDocsInput) {
                                        invoiceShowDocsInput.checked = false;
                                }
                                requiresInvoiceReference = false;
                                isInvoiceMode = false;
                        }

                        function populateCancelReasons(options, placeholder) {
                                if (!cancelSelect) return;
                                const placeholderOption = document.createElement("option");
                                placeholderOption.value = "";
                                placeholderOption.disabled = true;
                                placeholderOption.selected = true;
                                placeholderOption.textContent = placeholder || "Selecciona un motivo";

                                cancelSelect.innerHTML = "";
                                cancelSelect.appendChild(placeholderOption);

                                if (Array.isArray(options)) {
                                        options.forEach((entry) => {
                                                const option = document.createElement("option");
                                                option.value = `${entry}`;
                                                option.textContent = `${entry}`;
                                                cancelSelect.appendChild(option);
                                        });
                                }
                        }

                        function setModalVariant(name) {
                                const variant = typeof name === "string" ? name.trim() : "";
                                if (activeVariant) {
                                        modal.classList.remove(`confirm-modal--${activeVariant}`);
                                }
                                if (variant) {
                                        modal.classList.add(`confirm-modal--${variant}`);
                                }
                                activeVariant = variant;
                        }

                        function setFileError(message) {
                                fileErrorMessage = message;
                                if (fileErrorEl) {
                                        fileErrorEl.textContent = message;
                                        fileErrorEl.hidden = message === "";
                                }
                                updateConfirmState();
                        }

                        function resetFileState() {
                                selectedFile = null;
                                if (fileInput) {
                                        fileInput.value = "";
                                }
                                if (fileNameEl) {
                                        fileNameEl.textContent = fileEmptyText;
                                }
                                setFileError("");
                        }

                        function updateConfirmState() {
                                if (!confirmBtn) return;
                                const fileOk = !requiresFile || (selectedFile instanceof File && fileErrorMessage === "");
                                const ackOk =
                                        !requiresAcknowledgement ||
                                        (checkboxInput ? checkboxInput.checked : false);
                                const requiresOther =
                                        requiresReason &&
                                        cancelSelect &&
                                        cancelSelect.value === "Otra causa";
                                const otherReasonValue = cancelOtherInput
                                        ? cancelOtherInput.value.trim()
                                        : "";
                                const reasonOk =
                                        !requiresReason ||
                                        (cancelSelect && cancelSelect.value !== "" && cancelSelect.value !== "undefined");
                                const otherOk = !requiresOther || otherReasonValue !== "";
                                const invoiceReferenceOk =
                                        !isInvoiceMode ||
                                        !requiresInvoiceReference ||
                                        (invoiceReferenceInput
                                                ? invoiceReferenceInput.value.trim() !== ""
                                                : true);
                                confirmBtn.disabled = !(
                                        fileOk && ackOk && reasonOk && otherOk && invoiceReferenceOk
                                );
                        }

                        function syncCancelOtherReason() {
                                const isOther = cancelSelect && cancelSelect.value === "Otra causa";
                                if (cancelOtherField) {
                                        cancelOtherField.hidden = !isOther;
                                }
                                if (!isOther && cancelOtherInput) {
                                        cancelOtherInput.value = "";
                                }
                        }

                        function closeModal(options = {}) {
                                const wasOpen = isModalOpen;
                                modal.classList.remove("is-open");
                                modal.setAttribute("aria-hidden", "true");
                                if (subtitleEl) {
                                        subtitleEl.innerHTML = "";
                                        subtitleEl.hidden = true;
                                }
                                if (messageEl) {
                                        messageEl.innerHTML = "";
                                }
                                if (noteEl) {
                                        noteEl.textContent = "";
                                        noteEl.hidden = true;
                                }
                                if (statusEl) {
                                        statusEl.textContent = "";
                                        statusEl.hidden = true;
                                }
                                if (notifyNoteEl) {
                                        notifyNoteEl.textContent = "";
                                        notifyNoteEl.hidden = true;
                                }
                                if (notifyCheckboxWrapper) {
                                        notifyCheckboxWrapper.classList.remove(
                                                "confirm-modal__checkbox--disabled"
                                        );
                                }
                                if (notifyCheckboxInput) {
                                        notifyCheckboxInput.disabled = false;
                                }
                                if (cancelFields) {
                                        cancelFields.hidden = true;
                                }
                                modal.classList.remove("confirm-modal--requires-reason");
                                if (cancelOtherField) {
                                        cancelOtherField.hidden = true;
                                }
                                if (cancelOtherInput) {
                                        cancelOtherInput.value = "";
                                }
                                if (cancelSelect) {
                                        cancelSelect.value = "";
                                }
                                if (uploadBlock) {
                                        uploadBlock.hidden = true;
                                }
                                requiresFile = false;
                                requiresAcknowledgement = false;
                                requiresReason = false;
                                resetInvoiceFields();
                                notifyNoteText = "";
                                resetFileState();
                                if (checkboxInput) {
                                        checkboxInput.checked = false;
                                }
                                if (checkboxWrapper) {
                                        checkboxWrapper.hidden = true;
                                }
                                if (notifyCheckboxWrapper) {
                                        notifyCheckboxWrapper.hidden = true;
                                }
                                if (notifyCheckboxInput) {
                                        notifyCheckboxInput.checked = false;
                                }
                                applyCheckboxLabel("");
                                applyNotifyLabel("");
                                if (confirmBtn) {
                                        confirmBtn.disabled = true;
                                }
                                setModalVariant("");
                                document.removeEventListener("keydown", onKeydown);
                                pendingConfirmContext = null;
                                isModalOpen = false;

                                const historyAvailable =
                                        wasOpen &&
                                        panelHistory &&
                                        typeof panelHistory.close === "function" &&
                                        (!desktopMediaQuery || !desktopMediaQuery.matches);
                                if (historyAvailable && !options.silent) {
                                        panelHistory.close("confirm-modal");
                                }
                        }

                        function openModal(content) {
                                if (!confirmBtn || !titleEl || !messageEl) return;
                                const cfg = content || {};
                                titleEl.textContent = cfg.title || "";
                                if (subtitleEl) {
                                        const hasSubtitle = Boolean(cfg.subtitle);
                                        subtitleEl.innerHTML = hasSubtitle
                                                ? cfg.subtitle
                                                : "";
                                        subtitleEl.hidden = !hasSubtitle;
                                }
                                messageEl.innerHTML = cfg.message || "";
                                if (noteEl) {
                                        const hasNote = Boolean(cfg.note);
                                        noteEl.textContent = cfg.note || "";
                                        noteEl.hidden = !hasNote;
                                }
                                if (statusEl) {
                                        statusEl.textContent = "";
                                        statusEl.hidden = true;
                                }
                                const reasonOptions = Array.isArray(cfg.reasonOptions)
                                        ? cfg.reasonOptions
                                        : [];
                                requiresReason = Boolean(cfg.requireReason) && reasonOptions.length > 0;
                                if (cancelFields) {
                                        cancelFields.hidden = !requiresReason;
                                }
                                modal.classList.toggle("confirm-modal--requires-reason", requiresReason);
                                isInvoiceMode = Boolean(cfg.invoiceMode);
                                if (invoiceFields) {
                                        invoiceFields.hidden = !isInvoiceMode;
                                }
                                requiresInvoiceReference = isInvoiceMode && Boolean(cfg.requireInvoiceReference);
                                if (invoiceReferenceInput) {
                                        invoiceReferenceInput.value = cfg.invoiceReference || "";
                                        invoiceReferenceInput.placeholder =
                                                cfg.invoiceReferencePlaceholder ||
                                                invoiceReferenceInput.placeholder ||
                                                "";
                                }
                                if (invoiceSendEmailInput) {
                                        invoiceSendEmailInput.checked = Boolean(cfg.invoiceSendEmailChecked);
                                }
                                if (invoiceShowDocsInput) {
                                        invoiceShowDocsInput.checked = Boolean(
                                                cfg.invoiceShowDocumentationChecked
                                        );
                                }
                                if (cancelOtherField) {
                                        cancelOtherField.hidden = true;
                                }
                                if (cancelOtherInput) {
                                        cancelOtherInput.value = "";
                                }
                                if (cancelSelect) {
                                        populateCancelReasons(
                                                reasonOptions,
                                                cfg.reasonPlaceholder || "Selecciona un motivo"
                                        );
                                        cancelSelect.value = "";
                                        syncCancelOtherReason();
                                }
                                requiresFile = Boolean(cfg.requireFile);
                                requiresAcknowledgement = Boolean(
                                        cfg.requireAcknowledgement
                                );
                                if (uploadBlock) {
                                        uploadBlock.hidden = !requiresFile;
                                }
                                if (checkboxWrapper) {
                                        checkboxWrapper.hidden = !requiresAcknowledgement;
                                }
                                applyCheckboxLabel(cfg.checkboxLabel || "");
                                if (checkboxInput) {
                                        checkboxInput.checked = false;
                                }
                                const showNotify = Boolean(cfg.enableNotify);
                                const notifyDisabled = Boolean(cfg.notifyDisabled);
                                notifyNoteText = typeof cfg.notifyNote === "string" ? cfg.notifyNote : "";
                                if (notifyCheckboxWrapper) {
                                        notifyCheckboxWrapper.hidden = !showNotify;
                                        notifyCheckboxWrapper.classList.toggle(
                                                "confirm-modal__checkbox--disabled",
                                                showNotify && notifyDisabled
                                        );
                                }
                                if (notifyCheckboxInput) {
                                        notifyCheckboxInput.disabled = notifyDisabled;
                                        notifyCheckboxInput.checked = notifyDisabled
                                                ? false
                                                : Boolean(cfg.notifyChecked);
                                }
                                applyNotifyLabel(cfg.notifyLabel || "");
                                updateNotifyNote();
                                resetFileState();
                                confirmBtn.textContent = cfg.confirmLabel || "Confirmar";
                                setModalVariant(cfg.variant || "");
                                updateConfirmState();
                                modal.classList.add("is-open");
                                modal.setAttribute("aria-hidden", "false");
                                document.addEventListener("keydown", onKeydown);
                                if (closeBtn && typeof closeBtn.focus === "function") {
                                        closeBtn.focus();
                                }
                                const historyAvailable =
                                        panelHistory &&
                                        typeof panelHistory.push === "function" &&
                                        (!desktopMediaQuery || !desktopMediaQuery.matches);
                                if (!isModalOpen && historyAvailable) {
                                        panelHistory.push("confirm-modal", () => {
                                                closeModal({ silent: true });
                                        });
                                }
                                isModalOpen = true;
                        }

                        function onKeydown(event) {
                                if (event.key === "Escape") {
                                        closeModal();
                                }
                        }

                        if (fileInput) {
                                fileInput.addEventListener("change", () => {
                                        const file = fileInput.files && fileInput.files.length > 0
                                                ? fileInput.files[0]
                                                : null;
                                        if (!file) {
                                                if (fileNameEl) {
                                                        fileNameEl.textContent = fileEmptyText;
                                                }
                                                selectedFile = null;
                                                setFileError(requiresFile ? "Selecciona un archivo." : "");
                                                return;
                                        }
                                        if (file.size > RECEIPT_MAX_BYTES) {
                                                selectedFile = null;
                                                fileInput.value = "";
                                                if (fileNameEl) {
                                                        fileNameEl.textContent = fileEmptyText;
                                                }
                                                setFileError("El archivo supera los 10 MB permitidos.");
                                                return;
                                        }
                                        const type = (file.type || "").toLowerCase();
                                        const extension = (file.name.split(".").pop() || "").toLowerCase();
                                        const allowedType = RECEIPT_ALLOWED_MIMES.includes(type);
                                        const allowedExt = RECEIPT_ALLOWED_EXTENSIONS.includes(extension);
                                        if (!allowedType && !allowedExt) {
                                                selectedFile = null;
                                                fileInput.value = "";
                                                if (fileNameEl) {
                                                        fileNameEl.textContent = fileEmptyText;
                                                }
                                                setFileError("Formato no admitido. Usa PDF, JPG o PNG.");
                                                return;
                                        }
                                        selectedFile = file;
                                        if (fileNameEl) {
                                                fileNameEl.textContent = file.name;
                                        }
                                        setFileError("");
                                });
                        }

                        if (checkboxInput) {
                                checkboxInput.addEventListener("change", () => {
                                        updateConfirmState();
                                });
                        }

                        if (cancelSelect) {
                                cancelSelect.addEventListener("change", () => {
                                        syncCancelOtherReason();
                                        updateConfirmState();
                                });
                        }

                        if (cancelOtherInput) {
                                cancelOtherInput.addEventListener("input", () => {
                                        updateConfirmState();
                                });
                        }

                        if (notifyCheckboxInput) {
                                notifyCheckboxInput.addEventListener("change", () => {
                                        updateNotifyNote();
                                });
                        }

                        if (invoiceReferenceInput) {
                                invoiceReferenceInput.addEventListener("input", () => {
                                        updateConfirmState();
                                });
                        }

                        if (cancelBtn) {
                                cancelBtn.addEventListener("click", () => {
                                        closeModal();
                                });
                        }

                        if (closeBtn) {
                                closeBtn.addEventListener("click", () => {
                                        closeModal();
                                });
                        }

                        modal.addEventListener("click", (event) => {
                                if (event.target === modal) {
                                        closeModal();
                                }
                        });

                        if (confirmBtn) {
                                confirmBtn.addEventListener("click", () => {
                                        if (confirmBtn.disabled) {
                                                return;
                                        }
                                        const context = pendingConfirmContext;
                                        const selectedReason = cancelSelect ? cancelSelect.value : "";
                                        const notifyCustomer = notifyCheckboxInput
                                                ? Boolean(notifyCheckboxInput.checked)
                                                : false;
                                        if (context) {
                                                context.receiptFile = selectedFile || null;
                                                context.cancelReason = selectedReason;
                                                context.cancelOtherReason = cancelOtherInput
                                                        ? cancelOtherInput.value.trim()
                                                        : "";
                                                context.notifyCustomer = notifyCustomer;
                                                if (isInvoiceMode) {
                                                        context.invoiceReference = invoiceReferenceInput
                                                                ? invoiceReferenceInput.value.trim()
                                                                : "";
                                                        context.invoiceSendEmail = invoiceSendEmailInput
                                                                ? Boolean(invoiceSendEmailInput.checked)
                                                                : false;
                                                        context.invoiceShowDocumentation = invoiceShowDocsInput
                                                                ? Boolean(invoiceShowDocsInput.checked)
                                                                : false;
                                                }
                                        }
                                        if (context && context.intent === "cancel-guarantee") {
                                                runCancelRequest(context, { confirmBtn, closeModal });
                                                return;
                                        }
                                        if (context && context.intent === "generate-invoice") {
                                                runGenerateInvoice(context, {
                                                        confirmBtn,
                                                        cancelBtn,
                                                        closeModal,
                                                        statusEl,
                                                });
                                                return;
                                        }
                                        closeModal();
                                        if (context) {
                                                runConfirmRequest(context);
                                        }
                                });
                        }

                        return {
                                open: openModal,
                                close: closeModal,
                        };
                }

                function buildShareUrl(matricula) {
                        if (!matricula) {
                                return "";
                        }
                        const normalized = matricula.replace(/\s+/g, "");
                        const base = misGarantiasBase || "/garantias-online/mis-garantias/";
                        const separator = base.indexOf("?") !== -1 ? "&" : "?";
                        return `${base}${separator}matricula=${encodeURIComponent(normalized)}`;
                }

                function handleShareClick(event) {
                        const btn = event.target.closest(
                                ".guarantee-detail__btn--share"
                        );
                        if (!btn) {
                                return;
                        }
                        let panel = btn.closest(".guarantee-detail__panel");
                        if (!panel) {
                                const context = getActiveManagementContext();
                                panel = context.panel;
                        }
                        if (!panel) {
                                return;
                        }
                        const matricula = panel.dataset.matricula || "";
                        const shareUrl = buildShareUrl(matricula);
                        if (!shareUrl) {
                                return;
                        }
                        const plan = panel.dataset.plan || "";
                        const title = plan
                                ? `Garantía ${matricula} · ${plan}`
                                : `Garantía ${matricula}`;
                        const text = plan
                                ? `Consulta la documentación de la garantía ${matricula} (${plan}).`
                                : `Consulta la documentación de la garantía ${matricula}.`;

                        if (navigator.share) {
                                navigator
                                        .share({ title, text, url: shareUrl })
                                        .catch((err) => {
                                                if (err && err.name === "AbortError") {
                                                        return;
                                                }
                                                showDetailToast(
                                                        panel,
                                                        SHARE_UNAVAILABLE_MESSAGE
                                                );
                                        });
                                return;
                        }

                        showDetailToast(panel, SHARE_UNAVAILABLE_MESSAGE);
                }

                function handleTrashClick(event) {
                        if (!canDeleteGuarantee) {
                                return;
                        }
                        const btn = event.target.closest("[data-trash-trigger]");
                        if (!btn) {
                                return;
                        }
                        const panel = btn.closest(".guarantee-detail__panel");
                        if (!panel) {
                                return;
                        }
                        const id = panel.dataset.loadedId;
                        if (!id) {
                                return;
                        }
                        const row = findRowById(id);
                        const matricula =
                                panel.dataset.matricula ||
                                (row && row.dataset.matricula) ||
                                "";
                        const safeSubtitle = matricula
                                ? `Garantía <strong>${escapeHtml(matricula)}</strong>`
                                : "";
                        const context = {
                                intent: "trash",
                                btn,
                                panel,
                                id,
                                row,
                                resetLabel:
                                        btn.querySelector(".guarantee-detail__btn-text")?.textContent.trim() ||
                                        "Eliminar garantía",
                        };
                        if (confirmModalController) {
                                pendingConfirmContext = context;
                                confirmModalController.open({
                                        title: "Eliminar garantía",
                                        subtitle: safeSubtitle,
                                        message:
                                                "¿Seguro que quieres enviar esta garantía a la papelera? Podrás restaurarla desde el panel de WordPress.",
                                        note: "La garantía dejará de estar disponible para tramitación hasta que la recuperes.",
                                        confirmLabel: "Enviar a la papelera",
                                        requireAcknowledgement: true,
                                        checkboxLabel: "Estoy seguro de que quiero eliminar esta garantía.",
                                        variant: "danger",
                                });
                                return;
                        }
                        runTrashRequest(context);
                }

                function normalizeManagementValue(value) {
                        if (value === undefined || value === null) {
                                return "";
                        }
                        const str = String(value).trim();
                        if (!str || str === "-") {
                                return "";
                        }
                        return str;
                }

                function hydrateManagementDataset(panel, detailData = {}, rowData = {}) {
                        if (!panel) return;
                        const joinParts = (...parts) => {
                                for (const part of parts) {
                                        if (Array.isArray(part)) {
                                                const joined = part.filter(Boolean).join(" ").trim();
                                                const normalized = normalizeManagementValue(joined);
                                                if (normalized) return normalized;
                                        } else {
                                                const normalized = normalizeManagementValue(part);
                                                if (normalized) return normalized;
                                        }
                                }
                                return "";
                        };

                        const vehicle = joinParts(
                                detailData.marca_modelo,
                                detailData.vehicle_name,
                                detailData.detail?.marca_modelo,
                                [detailData.marca, detailData.modelo],
                                rowData.marca_modelo
                        );
                        const vendorRole = joinParts(
                                detailData.detail?.canal_venta_summary,
                                detailData.vendedor,
                                rowData.canal_venta
                        );
                        const vendorName = joinParts(
                                detailData.detail?.concesionario,
                                detailData.detail?.concesionario_personal,
                                rowData.concesionario,
                                rowData.vendedor_name
                        );
                        const vendorCombined = normalizeManagementValue(
                                [vendorRole, vendorName].filter(Boolean).join(" · ")
                        );
                        const vendor = vendorCombined || joinParts(vendorName, vendorRole);
                        const validUntil = joinParts(
                                detailData.hasta_fmt,
                                detailData.hasta_display,
                                detailData.hasta,
                                rowData.hasta_fmt,
                                rowData.hasta
                        );
                        const coverage = joinParts(
                                detailData.plan,
                                detailData.plan_name,
                                detailData.detail?.plan_nombre,
                                rowData.plan
                        );

                        panel.dataset.vehicle = vehicle;
                        panel.dataset.vendor = vendor;
                        panel.dataset.valid_until = validUntil;
                        panel.dataset.coverage = coverage;
                }

                function syncManagementDetailFields(panel) {
                        if (!panel) return;
                        if (managementPlateLabel) {
                                managementPlateLabel.textContent =
                                        panel.dataset?.matricula || managementPlateLabel.textContent || "— — —";
                        }
                        if (managementVehicleLabel) {
                                const vehicle = panel.dataset?.vehicle || "";
                                managementVehicleLabel.textContent = vehicle || "—";
                        }
                        if (managementVendorLabel) {
                                const vendor = panel.dataset?.vendor || "";
                                managementVendorLabel.textContent = vendor || "—";
                        }
                        if (managementValidUntilLabel) {
                                const validUntil = panel.dataset?.valid_until || "";
                                managementValidUntilLabel.textContent = validUntil || "—";
                        }
                        if (managementCoverageLabel) {
                                const coverage = panel.dataset?.coverage || "";
                                managementCoverageLabel.textContent = coverage || "—";
                        }
                }

                function ensureManagementAction(selector) {
                        if (!managementModal || !managementActionsList) {
                                return null;
                        }
                        const cached = managementActionAnchors.get(selector);
                        if (cached) {
                                return cached;
                        }
                        const action = managementModal.querySelector(selector);
                        if (!action || !action.parentNode) {
                                return null;
                        }
                        const placeholder = document.createComment(selector);
                        action.parentNode.insertBefore(placeholder, action);
                        managementActionAnchors.set(selector, {
                                placeholder,
                                template: action.cloneNode(true),
                        });
                        return managementActionAnchors.get(selector);
                }

                function syncManagementActionsAvailability(panel) {
                        if (!managementModal) {
                                return;
                        }

                        if (isComercial) {
                                if (managementActionsTitle?.isConnected) {
                                        managementActionsTitle.remove();
                                }
                                if (managementActionsList?.isConnected) {
                                        managementActionsList.remove();
                                }
                                if (managementActionsDanger?.isConnected) {
                                        managementActionsDanger.remove();
                                }
                                return;
                        }

                        if (!managementActionsList) {
                                return;
                        }
                        const normalizeEstadoClaseValue = (value) =>
                                typeof value === "string" ? value.toLowerCase().trim() : "";
                        const estadoDesdePanel = normalizeEstadoClaseValue(
                                panel?.dataset?.estadoclase || panel?.dataset?.estadoClase || ""
                        );
                        const estadoDesdeBadge = (() => {
                                const badgeElement =
                                        managementStatusBadge?.querySelector(
                                                "[data-management-status-badge]"
                                        ) || managementStatusBadge?.querySelector(
                                                ".guarantee-management__badge"
                                        );

                                if (!badgeElement) {
                                        return "";
                                }

                                const badgeClass = Array.from(badgeElement.classList || []).find(
                                        (cls) => cls.indexOf("guarantee-management__badge--") === 0
                                );

                                if (badgeClass) {
                                        return normalizeEstadoClaseValue(
                                                badgeClass.replace(
                                                        "guarantee-management__badge--",
                                                        ""
                                                )
                                        );
                                }

                                return normalizeEstadoClaseValue(
                                        badgeElement.textContent || badgeElement.innerText || ""
                                );
                        })();
                        const estadoClase = estadoDesdePanel || estadoDesdeBadge;
                        const isCancelled = estadoClase === "cancelada";
                        const toggleAction = (selector) => {
                                const cache = ensureManagementAction(selector);
                                if (!cache || !cache.placeholder || !cache.placeholder.parentNode) {
                                        return;
                                }
                                const existing = managementModal.querySelector(selector);
                                if (isCancelled) {
                                        if (existing) {
                                                existing.remove();
                                        }
                                        return;
                                }

                                if (existing) {
                                        existing.hidden = false;
                                        existing.removeAttribute("aria-hidden");
                                        return;
                                }

                                const clone = cache.template.cloneNode(true);
                                cache.placeholder.parentNode.insertBefore(
                                        clone,
                                        cache.placeholder.nextSibling
                                );
                        };

                        toggleAction('[data-management-action="cancel-for-nonpayment"]');
                        toggleAction('[data-management-action="certificate-error"]');
                        toggleAction('[data-management-action="generate-invoice"]');
                }

                function formatDateIsoLocal(value) {
                        const date = value instanceof Date ? value : new Date(value);
                        if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
                                return "";
                        }
                        const pad = (v) => String(v).padStart(2, "0");
                        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
                }

                function resolveCancelReasons(context = {}) {
                        const selected =
                                typeof context.cancelReason === "string"
                                        ? context.cancelReason.trim()
                                        : "";
                        const other =
                                typeof context.cancelOtherReason === "string"
                                        ? context.cancelOtherReason.trim()
                                        : "";
                        const isOther = selected === "Otra causa";
                        return {
                                reason: isOther && other ? other : selected,
                                other: isOther ? other : "",
                        };
                }

                function resolveCancellationReasonText(detailData = {}, fallbackReason = "", fallbackOther = "") {
                        const estado =
                                (detailData && typeof detailData === "object"
                                        ? detailData.estado_garantia || detailData
                                        : {}) || {};
                        const reason =
                                (typeof estado.motivo_cancelacion === "string"
                                        ? estado.motivo_cancelacion.trim()
                                        : "") ||
                                (typeof detailData.motivo_cancelacion === "string"
                                        ? detailData.motivo_cancelacion.trim()
                                        : "") ||
                                fallbackReason;
                        const other =
                                (typeof estado.otra_causa === "string" ? estado.otra_causa.trim() : "") ||
                                (typeof detailData.otra_causa === "string" ? detailData.otra_causa.trim() : "") ||
                                fallbackOther;

                        if (reason && reason !== "Otra causa") {
                                return reason;
                        }
                        if (reason === "Otra causa" && other) {
                                return other;
                        }
                        return reason || other || "";
                }

                function syncManagementNotesEmptyState() {
                        if (!managementNotesList || !managementNotesEmpty) {
                                return;
                        }

                        const visibleNotes = Array.from(managementNotesList.children || []).filter((child) =>
                                child.classList && child.classList.contains("management-notes__item")
                        );
                        const hasNotes = visibleNotes.length > 0;
                        managementNotesEmpty.toggleAttribute("hidden", hasNotes);
                }

                function clearManagementNotes() {
                        if (!managementNotesList) {
                                return;
                        }
                        const items = managementNotesList.querySelectorAll(
                                ".management-notes__item"
                        );
                        items.forEach((item) => item.remove());
                        syncManagementNotesEmptyState();
                }

                function createNoteElement(note) {
                        const article = document.createElement("article");
                        article.className = "management-notes__item";
                        if (note.is_owner) {
                                article.classList.add("management-notes__item--own");
                        }
                        article.dataset.noteId = `${note.id}`;

                        const meta = document.createElement("div");
                        meta.className = "management-notes__meta";

                        const author = document.createElement("span");
                        author.className = "management-notes__author";
                        author.textContent = note.author_name || "";
                        meta.appendChild(author);

                        const time = document.createElement("time");
                        time.className = "management-notes__time";
                        time.dateTime = note.datetime || "";
                        time.textContent = note.time_label || "";
                        meta.appendChild(time);

                        article.appendChild(meta);

                        const body = document.createElement("p");
                        body.className = "management-notes__body";
                        body.textContent = note.note || "";
                        article.appendChild(body);

                        if (note.is_owner) {
                                const actions = document.createElement("div");
                                actions.className = "management-notes__actions";
                                actions.setAttribute(
                                        "aria-label",
                                        "Acciones de nota"
                                );

                                const editBtn = document.createElement("button");
                                editBtn.type = "button";
                                editBtn.className = "management-notes__action management-notes__action--ghost";
                                editBtn.dataset.noteAction = "edit";
                                editBtn.textContent = "Editar";
                                actions.appendChild(editBtn);

                                const deleteBtn = document.createElement("button");
                                deleteBtn.type = "button";
                                deleteBtn.className = "management-notes__action management-notes__action--danger";
                                deleteBtn.dataset.noteAction = "delete";
                                deleteBtn.textContent = "Eliminar";
                                actions.appendChild(deleteBtn);

                                article.appendChild(actions);
                        }

                        return article;
                }

                function animateNoteEntry(element) {
                        if (!element) return;
                        element.style.opacity = "0";
                        element.style.transform = "translateY(6px)";
                        requestAnimationFrame(() => {
                                element.style.transition = "opacity 160ms ease, transform 160ms ease";
                                element.style.opacity = "1";
                                element.style.transform = "translateY(0)";
                        });
                }

                function renderManagementNotes(notes) {
                        if (!managementNotesList) {
                                return;
                        }
                        clearManagementNotes();
                        notes.forEach((note) => {
                                const entry = createNoteElement(note);
                                managementNotesList.appendChild(entry);
                                animateNoteEntry(entry);
                        });
                        syncManagementNotesEmptyState();
                }

                function appendManagementNote(note) {
                        if (!managementNotesList) {
                                return;
                        }
                        const entry = createNoteElement(note);
                        managementNotesList.insertBefore(entry, managementNotesList.firstChild);
                        animateNoteEntry(entry);
                        syncManagementNotesEmptyState();
                }

                function getManagementNotesEndpoint(id) {
                        if (!restRoot || !id) {
                                return "";
                        }
                        return `${restRoot}go/v1/guarantees/${encodeURIComponent(id)}/notes`;
                }

                function setNoteSaveState(state, labelOverride) {
                        if (!managementNoteSaveBtn) return;
                        const icon = managementNoteSaveBtn.querySelector(
                                ".guarantee-management__btn-icon"
                        );
                        const label = managementNoteSaveLabel;

                        if (managementNoteSavingTimer) {
                                clearTimeout(managementNoteSavingTimer);
                                managementNoteSavingTimer = null;
                        }

                        if (state === "saving") {
                                managementNoteSaveBtn.disabled = true;
                                if (icon) {
                                        icon.innerHTML =
                                                '<span class="guarantee-detail__btn-spinner" aria-hidden="true"></span>';
                                }
                                if (label) {
                                        label.textContent = labelOverride || "Guardando nota";
                                }
                                return;
                        }

                        if (state === "saved") {
                                managementNoteSaveBtn.disabled = false;
                                if (icon && defaultSaveIconHtml) {
                                        icon.innerHTML = defaultSaveIconHtml;
                                }
                                if (label) {
                                        label.textContent = labelOverride || "Nota guardada";
                                }
                                managementNoteSavingTimer = window.setTimeout(() => {
                                        if (label && defaultSaveLabel) {
                                                label.textContent = defaultSaveLabel;
                                        }
                                }, 1400);
                                return;
                        }

                        managementNoteSaveBtn.disabled = false;
                        if (icon && defaultSaveIconHtml) {
                                icon.innerHTML = defaultSaveIconHtml;
                        }
                        if (label && defaultSaveLabel) {
                                label.textContent = defaultSaveLabel;
                        }
                }

                function normalizeNoteEntry(entry) {
                        if (!entry || typeof entry !== "object") {
                                return null;
                        }
                        return {
                                id: entry.id ?? 0,
                                author_name: entry.author_name || "",
                                datetime: entry.datetime || "",
                                time_label: entry.time_label || "",
                                note: entry.note || "",
                                is_owner: Boolean(entry.is_owner),
                        };
                }

                function applyNotesPayload(payload) {
                        if (!payload || typeof payload !== "object") {
                                return;
                        }
                        const notes = Array.isArray(payload.notes)
                                ? payload.notes.map((entry) => normalizeNoteEntry(entry)).filter(Boolean)
                                : [];
                        managementNotesState = {
                                notes,
                                currentUserId: payload.current_user?.id ?? null,
                        };
                        renderManagementNotes(notes);
                }

                function loadManagementNotes(force = false) {
                        const context = getActiveManagementContext();
                        if (!context.id) {
                                return;
                        }
                        if (!force && managementNotesLoadedId === context.id && managementNotesState.notes.length) {
                                return;
                        }
                        const endpoint = getManagementNotesEndpoint(context.id);
                        if (!endpoint) {
                                return;
                        }
                        managementNotesLoadedId = context.id;
                        fetch(endpoint, {
                                headers: {
                                        "X-WP-Nonce": restNonce,
                                },
                        })
                                .then((res) => {
                                        if (!res.ok) {
                                                return res
                                                        .json()
                                                        .catch(() => ({}))
                                                        .then((data) => {
                                                                const message =
                                                                        data && typeof data.message === "string"
                                                                                ? data.message
                                                                                : "No se han podido cargar las notas.";
                                                                throw new Error(message);
                                                        });
                                        }
                                        return res.json();
                                })
                                .then((data) => {
                                        applyNotesPayload(data || {});
                                })
                                .catch((error) => {
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se han podido cargar las notas.";
                                        const contextPanel = context.panel || document.querySelector(".guarantee-detail__panel.active");
                                        if (contextPanel) {
                                                showDetailToast(contextPanel, message);
                                        }
                                });
                }

                function handleNoteSave() {
                        if (!managementNoteInput) {
                                return;
                        }
                        const context = getActiveManagementContext();
                        if (!context.id) {
                                return;
                        }
                        const noteText = managementNoteInput.value.trim();
                        if (!noteText) {
                                return;
                        }
                        const endpoint = getManagementNotesEndpoint(context.id);
                        if (!endpoint) return;

                        setNoteSaveState("saving");
                        fetch(endpoint, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": restNonce,
                                },
                                body: JSON.stringify({ note: noteText }),
                        })
                                .then((res) => {
                                        if (!res.ok) {
                                                return res
                                                        .json()
                                                        .catch(() => ({}))
                                                        .then((data) => {
                                                                const message =
                                                                        data && typeof data.message === "string"
                                                                                ? data.message
                                                                                : "No se ha podido guardar la nota.";
                                                                throw new Error(message);
                                                        });
                                        }
                                        return res.json();
                                })
                                .then((data) => {
                                        applyNotesPayload(data || {});
                                        setNoteSaveState("saved");
                                        managementNoteInput.value = "";
                                })
                                .catch((error) => {
                                        setNoteSaveState("idle");
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se ha podido guardar la nota.";
                                        const panel = context.panel || document.querySelector(".guarantee-detail__panel.active");
                                        if (panel) {
                                                showDetailToast(panel, message);
                                        }
                                });
                }

                function restoreNoteContent(noteEl, noteData) {
                        const body = noteEl.querySelector(".management-notes__body");
                        if (body) {
                                body.remove();
                        }
                        const textarea = noteEl.querySelector("textarea");
                        if (textarea) {
                                textarea.remove();
                        }
                        const actions = noteEl.querySelector(".management-notes__actions");
                        if (actions) {
                                actions.remove();
                        }

                        const rebuilt = createNoteElement(noteData);
                        noteEl.replaceWith(rebuilt);
                        animateNoteEntry(rebuilt);
                }

                function startNoteEdit(noteEl) {
                        if (!noteEl || noteEl.dataset.editing === "true") {
                                return;
                        }
                        const body = noteEl.querySelector(".management-notes__body");
                        const actions = noteEl.querySelector(".management-notes__actions");
                        if (!body || !actions) {
                                return;
                        }
                        const originalText = body.textContent || "";
                        const textarea = document.createElement("textarea");
                        textarea.className = "management-notes__textarea";
                        textarea.value = originalText.trim();
                        noteEl.dataset.editing = "true";
                        body.replaceWith(textarea);

                        const saveBtn = document.createElement("button");
                        saveBtn.type = "button";
                        saveBtn.className = "management-notes__action management-notes__action--ghost";
                        saveBtn.dataset.noteAction = "save-edit";
                        saveBtn.textContent = "Guardar";

                        const cancelBtn = document.createElement("button");
                        cancelBtn.type = "button";
                        cancelBtn.className = "management-notes__action management-notes__action--danger";
                        cancelBtn.dataset.noteAction = "cancel-edit";
                        cancelBtn.textContent = "Cancelar";

                        actions.innerHTML = "";
                        actions.appendChild(saveBtn);
                        actions.appendChild(cancelBtn);
                        textarea.focus();
                }

                function submitNoteEdit(noteEl) {
                        const textarea = noteEl.querySelector("textarea");
                        const noteId = noteEl.dataset.noteId;
                        const context = getActiveManagementContext();
                        if (!textarea || !noteId || !context.id) {
                                return;
                        }
                        const newText = textarea.value.trim();
                        if (!newText) {
                                return;
                        }
                        const endpoint = `${getManagementNotesEndpoint(context.id)}/${encodeURIComponent(noteId)}`;
                        noteEl.dataset.saving = "true";
                        fetch(endpoint, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": restNonce,
                                },
                                body: JSON.stringify({ note: newText }),
                        })
                                .then((res) => {
                                        if (!res.ok) {
                                                return res
                                                        .json()
                                                        .catch(() => ({}))
                                                        .then((data) => {
                                                                const message =
                                                                        data && typeof data.message === "string"
                                                                                ? data.message
                                                                                : "No se ha podido actualizar la nota.";
                                                                throw new Error(message);
                                                        });
                                        }
                                        return res.json();
                                })
                                .then((data) => {
                                        applyNotesPayload(data || {});
                                })
                                .catch((error) => {
                                        const panel = context.panel || document.querySelector(".guarantee-detail__panel.active");
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se ha podido actualizar la nota.";
                                        if (panel) {
                                                showDetailToast(panel, message);
                                        }
                                })
                                .finally(() => {
                                        noteEl.dataset.saving = "false";
                                });
                }

                function requestNoteDelete(noteEl) {
                        const noteId = noteEl?.dataset?.noteId;
                        const context = getActiveManagementContext();
                        if (!noteId || !context.id) {
                                return;
                        }
                        const confirmed = window.confirm("¿Seguro que quieres eliminar esta nota?");
                        if (!confirmed) {
                                return;
                        }
                        const endpoint = `${getManagementNotesEndpoint(context.id)}/${encodeURIComponent(noteId)}`;
                        fetch(endpoint, {
                                method: "DELETE",
                                headers: {
                                        "X-WP-Nonce": restNonce,
                                },
                        })
                                .then((res) => {
                                        if (!res.ok) {
                                                return res
                                                        .json()
                                                        .catch(() => ({}))
                                                        .then((data) => {
                                                                const message =
                                                                        data && typeof data.message === "string"
                                                                                ? data.message
                                                                                : "No se ha podido eliminar la nota.";
                                                                throw new Error(message);
                                                        });
                                        }
                                        return res.json();
                                })
                                .then((data) => {
                                        applyNotesPayload(data || {});
                                })
                                .catch((error) => {
                                        const panel = context.panel || document.querySelector(".guarantee-detail__panel.active");
                                        const message =
                                                error instanceof Error && error.message
                                                        ? error.message
                                                        : "No se ha podido eliminar la nota.";
                                        if (panel) {
                                                showDetailToast(panel, message);
                                        }
                                });
                }

                function updateManagementHeaderFromDetail(panel) {
                        if (!managementModal) {
                                return;
                        }
                        const activePanel = panel || document.querySelector(".guarantee-detail__panel.active");
                        const plateValue = activePanel?.dataset?.matricula || "";
                        const estadoLabel = activePanel?.dataset?.estado || "";
                        const estadoClase =
                                activePanel?.dataset?.estadoclase || activePanel?.dataset?.estadoClase || "";
                        const detailBadge = activePanel?.querySelector(
                                ".guarantee-detail__badge"
                        );

                        if (managementPlateLabel) {
                                managementPlateLabel.textContent = plateValue || "— — —";
                        }

                        if (managementStatusBadge) {
                                const badge = managementStatusBadge.querySelector(
                                        "[data-management-status-badge]"
                                );
                                const badgeLabel = (detailBadge?.textContent || "").trim() || estadoLabel || "";
                                const badgeModifiers = detailBadge
                                        ? Array.from(detailBadge.classList || []).filter((cls) =>
                                                  cls.indexOf("guarantee-detail__badge--") === 0
                                          ).map((cls) =>
                                                  cls.replace(
                                                          "guarantee-detail__badge--",
                                                          "guarantee-management__badge--"
                                                  )
                                          )
                                        : estadoClase
                                          ? [
                                                  `guarantee-management__badge--${estadoClase}`,
                                          ]
                                          : [];
                                const badgeClass = [
                                        "guarantee-management__badge",
                                        ...badgeModifiers,
                                ]
                                        .filter(Boolean)
                                        .join(" ")
                                        .trim();

                                if (managementStatusText && badgeLabel) {
                                        managementStatusText.textContent = badgeLabel;
                                }

                                if (badge) {
                                        badge.className = badgeClass;
                                        const textTarget = badge.querySelector(
                                                "[data-management-status-text]"
                                        );
                                        if (textTarget) {
                                                textTarget.textContent = badgeLabel;
                                        } else {
                                                badge.textContent = badgeLabel;
                                        }
                                        badge.toggleAttribute("hidden", badgeLabel === "");
                                } else if (estadoClase && estadoLabel) {
                                        const newBadge = document.createElement("span");
                                        newBadge.className = badgeClass;
                                        newBadge.setAttribute("data-management-status-badge", "");
                                        const labelSpan = document.createElement("span");
                                        labelSpan.setAttribute("data-management-status-text", "");
                                        labelSpan.textContent = badgeLabel;
                                        newBadge.appendChild(labelSpan);
                                        managementStatusBadge.appendChild(newBadge);
                                }
                        }

                        syncManagementDetailFields(activePanel);
                        syncManagementNotesEmptyState();
                        syncManagementActionsAvailability(activePanel);
                }

                function openManagementModal(trigger) {
                        if (!canAccessManagementHub || !managementModal) {
                                return;
                        }
                        if (managementModal.dataset.state === "open") {
                                return;
                        }
                        updateManagementHeaderFromDetail();
                        loadManagementNotes(true);
                        if (managementModalCloseTimer) {
                                clearTimeout(managementModalCloseTimer);
                                managementModalCloseTimer = null;
                        }
                        managementModalTrigger = trigger || managementModalTrigger;
                        managementModal.dataset.state = "open";
                        managementModal.setAttribute("aria-hidden", "false");
                        document.body.classList.add("has-management-modal-open");
                        if (typeof requestAnimationFrame === "function") {
                                requestAnimationFrame(() => {
                                        if (managementDialog) {
                                                managementDialog.focus();
                                        }
                                });
                        } else if (managementDialog) {
                                managementDialog.focus();
                        }
                }

                function closeManagementModal() {
                        if (!managementModal || managementModal.dataset.state !== "open") {
                                return;
                        }
                        managementModal.dataset.state = "closing";
                        managementModal.setAttribute("aria-hidden", "true");
                        document.body.classList.remove("has-management-modal-open");
                        managementModalCloseTimer = window.setTimeout(() => {
                                if (!managementModal) {
                                        return;
                                }
                                managementModal.dataset.state = "closed";
                        }, MANAGEMENT_MODAL_TRANSITION);
                        if (managementModalTrigger && typeof managementModalTrigger.focus === "function") {
                                managementModalTrigger.focus();
                        }
                        managementModalTrigger = null;
                }

                function getActiveManagementContext() {
                        const panel = document.querySelector(
                                ".guarantee-detail__panel.active"
                        );
                        const id = panel?.dataset?.loadedId || "";
                        const row =
                                id && tbody
                                        ? tbody.querySelector(
                                                  `.guarantees-table__row[data-id="${id}"]`
                                          )
                                        : null;
                        const plate =
                                panel?.dataset?.matricula || row?.dataset?.matricula || "";

                        return { panel, id, row, plate };
                }

                function handleManagementClicks(event) {
                        if (!canAccessManagementHub || !managementModal) {
                                return;
                        }
                        const trigger = event.target.closest("[data-management-open]");
                        if (trigger) {
                                event.preventDefault();
                                openManagementModal(trigger);
                                return;
                        }
                        const dismiss = event.target.closest("[data-management-dismiss]");
                        if (dismiss && managementModal.dataset.state === "open") {
                                event.preventDefault();
                                closeManagementModal();
                                return;
                        }

                        const actionButton = event.target.closest(
                                "[data-management-action]"
                        );
                        if (actionButton && managementModal.dataset.state === "open") {
                                event.preventDefault();
                                const action = actionButton.dataset.managementAction || "";
                                const context = getActiveManagementContext();

                                if (action === "generate-invoice") {
                                        const safePlate = escapeHtml(context.plate || "");
                                        const subtitle = safePlate
                                                ? `Garantía <strong>${safePlate}</strong>`
                                                : "";
                                        pendingConfirmContext = {
                                                intent: "generate-invoice",
                                                btn: actionButton,
                                                panel: context.panel,
                                                id: context.id,
                                                row: context.row,
                                        };
                                        if (confirmModalController) {
                                                confirmModalController.open({
                                                        title: "Generar factura",
                                                        subtitle,
                                                        message:
                                                                "Introduce la referencia de la factura y selecciona cómo compartirla con el cliente.",
                                                        confirmLabel: "Generar factura",
                                                        requireAcknowledgement: true,
                                                        checkboxLabel:
                                                                "Confirmo que quiero generar la factura.",
                                                        invoiceMode: true,
                                                        requireInvoiceReference: true,
                                                        invoiceSendEmailChecked: true,
                                                        invoiceShowDocumentationChecked: true,
                                                });
                                        }
                                        return;
                                }

                                if (action === "delete-guarantee") {
                                        const safePlate = escapeHtml(context.plate || "");
                                        const subtitle = safePlate
                                                ? `Garantía <strong>${safePlate}</strong>`
                                                : "";
                                        const resetLabel =
                                                actionButton.querySelector(
                                                        ".management-actions__copy strong"
                                                )?.textContent.trim() || "Eliminar garantía";
                                        const trashContext = {
                                                intent: "trash",
                                                btn: actionButton,
                                                panel: context.panel,
                                                id: context.id,
                                                row: context.row,
                                                resetLabel,
                                        };

                                        if (confirmModalController) {
                                                pendingConfirmContext = trashContext;
                                                confirmModalController.open({
                                                        title: "Eliminar garantía",
                                                        subtitle,
                                                        message:
                                                                "¿Seguro que quieres enviar esta garantía a la papelera? Podrás restaurarla desde el panel de WordPress.",
                                                        note:
                                                                "La garantía dejará de estar disponible para tramitación hasta que la recuperes.",
                                                        confirmLabel: "Enviar a la papelera",
                                                        requireAcknowledgement: true,
                                                        checkboxLabel:
                                                                "Estoy seguro de que quiero eliminar esta garantía.",
                                                        variant: "danger",
                                                });
                                        } else {
                                                runTrashRequest(trashContext);
                                        }
                                        return;
                                }

                                if (action === "cancel-for-nonpayment") {
                                        const safePlate = escapeHtml(context.plate || "");
                                        const subtitle = safePlate
                                                ? `Garantía <strong>${safePlate}</strong>`
                                                : "";
                                        const vendorName = managementVendorLabel
                                                ? managementVendorLabel.textContent.trim()
                                                : "";
                                        const companyForNote = escapeHtml(
                                                vendorName || "el cliente"
                                        );
                                        const cancelReasons = [
                                                "Pago no recibido",
                                                "Solicitud del cliente",
                                                "Datos incorrectos",
                                                "Vehículo no apto",
                                                "Otra causa",
                                        ];

                                        const cachedDetail = context.id ? detailCache.get(context.id) : null;
                                        const uuid = cachedDetail && cachedDetail.uuid ? cachedDetail.uuid : "";
                                        pendingConfirmContext = {
                                                intent: "cancel-guarantee",
                                                btn: actionButton,
                                                panel: context.panel,
                                                id: context.id,
                                                row: context.row,
                                                uuid,
                                                resetLabel:
                                                        actionButton.querySelector(
                                                                ".management-actions__copy strong"
                                                        )?.textContent.trim() || "Cancelar garantía",
                                        };
                                        if (confirmModalController) {
                                                confirmModalController.open({
                                                        title: "Cancelar garantía",
                                                        subtitle,
                                                        message:
                                                                "¿Seguro que quieres cancelar esta garantía? Podrás activarla de nuevo desde el panel de WordPress.",
                                                        note: "",
                                                        confirmLabel: "Cancelar garantía",
                                                        requireAcknowledgement: true,
                                                        checkboxLabel:
                                                                "Estoy seguro de que quiero cancelar esta garantía.",
                                                        requireReason: true,
                                                        reasonOptions: cancelReasons,
                                                        reasonPlaceholder: "Selecciona un motivo",
                                                        enableNotify: true,
                                                        notifyDisabled: true,
                                                        notifyLabel:
                                                                "Informar al cliente de la cancelación.",
                                                        notifyNote: `Se enviará un correo a ${companyForNote} indicando que la garantía ha sido cancelada.`,
                                                });
                                        }
                                        return;
                                }

                                if (action === "edit-wordpress") {
                                        if (!context.id) {
                                                return;
                                        }
                                        let origin = window.location.origin;
                                        try {
                                                origin = restRoot
                                                        ? new URL(restRoot).origin
                                                        : origin;
                                        } catch (err) {
                                                // ignore
                                        }
                                        const editUrl = `${origin}/wp-admin/post.php?post=${encodeURIComponent(
                                                context.id
                                        )}&action=edit`;
                                        window.open(editUrl, "_blank", "noopener");
                                }
                        }
                }

                function handleManagementNotesClick(event) {
                        if (!canAccessManagementHub || !managementModal) {
                                return;
                        }
                        const saveTrigger = event.target.closest("[data-management-save-note]");
                        if (saveTrigger && managementModal.dataset.state === "open") {
                                event.preventDefault();
                                handleNoteSave();
                                return;
                        }

                        const noteAction = event.target.closest("[data-note-action]");
                        if (!noteAction) {
                                return;
                        }
                        const noteItem = noteAction.closest(".management-notes__item");
                        const action = noteAction.dataset.noteAction;

                        if (action === "edit") {
                                startNoteEdit(noteItem);
                        } else if (action === "save-edit") {
                                submitNoteEdit(noteItem);
                        } else if (action === "cancel-edit") {
                                const noteId = noteItem?.dataset?.noteId;
                                const existing = managementNotesState.notes.find(
                                        (entry) => `${entry.id}` === `${noteId}`
                                );
                                if (noteItem) {
                                        noteItem.dataset.editing = "false";
                                }
                                if (noteItem && existing) {
                                        restoreNoteContent(noteItem, existing);
                                } else if (noteItem) {
                                        noteItem.remove();
                                        syncManagementNotesEmptyState();
                                }
                        } else if (action === "delete") {
                                requestNoteDelete(noteItem);
                        }
                }

                function handleManagementKeydown(event) {
                        if (!canAccessManagementHub || !managementModal) {
                                return;
                        }
                        if (event.key === "Escape" && managementModal.dataset.state === "open") {
                                event.preventDefault();
                                closeManagementModal();
                        }
                }

                function detailCopyText(text) {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                return navigator.clipboard.writeText(text);
                        }
                        const textarea = document.createElement("textarea");
                        textarea.value = text;
                        textarea.setAttribute("readonly", "true");
                        textarea.style.position = "absolute";
                        textarea.style.left = "-9999px";
                        document.body.appendChild(textarea);
                        textarea.select();
                        try {
                                document.execCommand("copy");
                        } catch (err) {
                                console.error("Fallback copy failed", err);
                        }
                        document.body.removeChild(textarea);
                        return Promise.resolve();
                }

                function showDetailToast(panel, message) {
                        const toast = panel.querySelector(".detail__copy-toast");
                        if (!toast) return;
                        toast.textContent = message || "Copiado al portapapeles.";
                        toast.classList.add("show");
                        setTimeout(() => toast.classList.remove("show"), 2000);
                }

                function handleDetailCopy(btn, panel) {
                        if (!btn || !panel) return;
                        const selector = btn.dataset.copy;
                        if (!selector) return;
                        const target = panel.querySelector(selector);
                        if (!target) return;
                        let text = (target.textContent || '').trim();
                        if (target.hasAttribute('data-amount')) {
                                text = text.replace(/[\s\u00A0]*€$/u, '').replace(/^€[\s\u00A0]*/u, '').trim();
                        }
                        if (!text) return;
                        const originalLabel = btn.getAttribute("aria-label") || "";
                        const doneLabel = btn.dataset.done || "Copiado";
                        const toastMessage = btn.dataset.toast || doneLabel;
                        detailCopyText(text).then(() => {
                                showDetailToast(panel, toastMessage);
                                if (doneLabel) {
                                        btn.setAttribute("aria-label", doneLabel);
                                        setTimeout(() => {
                                                if (originalLabel) {
                                                        btn.setAttribute(
                                                                "aria-label",
                                                                originalLabel
                                                        );
                                                }
                                        }, 2000);
                                }
                        });
                }

                document.addEventListener("click", (e) => {
                        const button = e.target.closest(".detail__copy-btn");
                        if (button) {
                                const panel = button.closest(
                                        ".guarantee-detail__panel"
                                );
                                if (panel) {
                                        handleDetailCopy(button, panel);
                                }
                                return;
                        }

                        const row = e.target.closest("tr[data-copy-row]");
                        if (row) {
                                const panel = row.closest(
                                        ".guarantee-detail__panel"
                                );
                                if (!panel) {
                                        return;
                                }
                                const proxyButton = row.querySelector(
                                        ".detail__copy-btn"
                                );
                                if (!proxyButton) {
                                        return;
                                }
                                handleDetailCopy(proxyButton, panel);
                                return;
                        }

                        const cell = e.target.closest("[data-copy-cell]");
                        if (!cell) {
                                return;
                        }
                        const panel = cell.closest(".guarantee-detail__panel");
                        if (!panel) {
                                return;
                        }
                        const proxyButton = cell.querySelector(
                                ".detail__copy-btn"
                        );
                        if (!proxyButton) {
                                return;
                        }
                        handleDetailCopy(proxyButton, panel);
                });

               function normalizeEstadoClase(estado) {
                       if (!estado) return "pendiente-pago";
                       return String(estado)
                               .toLowerCase()
                               .normalize("NFD")
                               .replace(/[\u0300-\u036f]/g, "")
                               .replace(/[^a-z0-9]+/g, "-")
                               .replace(/^-+|-+$/g, "");
               }

   function initResizableColumns(table) {
           if (!table) {
                   return;
           }

           let teardown = null;

           const setup = () => {
                   const wrapper = table.parentElement;
           const headerCells = Array.from(table.querySelectorAll("thead th"));
                   const body = table.tBodies[0];
                   if (!wrapper || headerCells.length === 0 || !body) {
                           return () => {};
                   }

           const computed = window.getComputedStyle(wrapper);
                   const hadInlinePosition =
                           typeof wrapper.style.position === "string" &&
                           wrapper.style.position.length > 0;
                   const shouldRestorePosition = !hadInlinePosition && computed.position === "static";

                   const previousTableLayout = table.style.tableLayout;

                   if (shouldRestorePosition) {
                           wrapper.style.position = "relative";
                   }

                   let colgroup = table.querySelector("colgroup");
                   if (!colgroup) {
                           colgroup = document.createElement("colgroup");
                           headerCells.forEach(() =>
                                   colgroup.appendChild(document.createElement("col"))
                           );
                           table.insertBefore(colgroup, table.firstChild);
                   }
                   const cols = Array.from(colgroup.children);

                   const MIN_WIDTH = 80;
                   const MAX_WIDTH = 480;
                   const clamp = (value, min, max) =>
                           Math.min(Math.max(value, min), max);
                   const pointerSupported =
                           typeof window !== "undefined" && "PointerEvent" in window;

                   let widths = [];
                   const overlay = document.createElement("div");
                   overlay.className = "column-resizers";
                   wrapper.appendChild(overlay);

                   const handles = [];
                   const handleListeners = [];
                   let rafId = null;

                   const cancelScheduled = () => {
                           if (
                                   rafId !== null &&
                                   typeof cancelAnimationFrame === "function"
                           ) {
                                   cancelAnimationFrame(rafId);
                           }
                           rafId = null;
                   };

                   const measureRects = () => {
                           const wrapperRect = wrapper.getBoundingClientRect();
                           const tableRect = table.getBoundingClientRect();
                           return {
                                   wrapperRect,
                                   tableRect,
                           };
                   };

                   const applyOverlayPosition = () => {
                           const { wrapperRect, tableRect } = measureRects();
                           overlay.style.width = `${tableRect.width}px`;
                           overlay.style.height = `${tableRect.height}px`;
                           overlay.style.top = `${
                                   tableRect.top - wrapperRect.top + wrapper.scrollTop
                           }px`;
                           overlay.style.left = `${
                                   tableRect.left - wrapperRect.left + wrapper.scrollLeft
                           }px`;
                          handles.forEach((handle, index) => {
                                  const th = headerCells[index];
                                  if (!th) {
                                          return;
                                  }
                                  const rect = th.getBoundingClientRect();
                                  handle.style.left = `${
                                          rect.right - tableRect.left - handle.offsetWidth / 2
                                  }px`;
                          });
                  };

                   const scheduleOverlayUpdate = () => {
                           if (typeof requestAnimationFrame === "function") {
                                   cancelScheduled();
                                   rafId = requestAnimationFrame(() => {
                                           rafId = null;
                                           applyOverlayPosition();
                                   });
                                   return;
                           }
                           applyOverlayPosition();
                   };

                   const applyWidths = () => {
                           widths.forEach((width, index) => {
                                   if (cols[index]) {
                                           cols[index].style.width = `${width}px`;
                                   }
                           });
                   };

                   const measureWidths = (options = {}) => {
                           const { reset = false } = options;
                           if (reset) {
                                   cols.forEach((col) => {
                                           if (col && col.style) {
                                                   col.style.removeProperty("width");
                                           }
                                   });
                           }
                           table.style.tableLayout = "auto";
                           widths = headerCells.map((th) =>
                                   clamp(th.getBoundingClientRect().width, MIN_WIDTH, MAX_WIDTH)
                           );
                           table.style.tableLayout = "fixed";
                          applyWidths();
                   };

                   const detachHandleListeners = () => {
                           handleListeners.forEach(({ handle, type, listener }) => {
                                   handle.removeEventListener(type, listener);
                           });
                           handleListeners.length = 0;
                   };

                   const createHandles = () => {
                           detachHandleListeners();
                           overlay.innerHTML = "";
                           handles.length = 0;
                           for (let i = 0; i < widths.length - 1; i++) {
                                   const handle = document.createElement("span");
                                   handle.className = "column-resizer";
                                   overlay.appendChild(handle);
                                   handles.push(handle);

                                   const startResize = (event) => {
                                           if (event.button !== undefined && event.button !== 0) {
                                                   return;
                                           }
                                           event.preventDefault();
                                           const isPointer = event.type === "pointerdown";
                                           const pointerId = isPointer ? event.pointerId : null;
                                           const startX = event.clientX ?? event.pageX ?? 0;
                                           const startWidth = widths[i];
                                           const nextWidth = widths[i + 1];
                                           const total = startWidth + nextWidth;

                                           const updateWidths = (clientX) => {
                                                   const delta = clientX - startX;
                                                   let current = clamp(
                                                           startWidth + delta,
                                                           MIN_WIDTH,
                                                           MAX_WIDTH
                                                   );
                                                   let sibling = total - current;
                                                   if (sibling < MIN_WIDTH) {
                                                           sibling = MIN_WIDTH;
                                                           current = total - sibling;
                                                   }
                                                   if (sibling > MAX_WIDTH) {
                                                           sibling = MAX_WIDTH;
                                                           current = total - sibling;
                                                   }
                                                   widths[i] = current;
                                                   widths[i + 1] = sibling;
                                                   cols[i].style.width = `${current}px`;
                                                   cols[i + 1].style.width = `${sibling}px`;
                                                   scheduleOverlayUpdate();
                                           };

                                           const handleMove = (moveEvent) => {
                                                   const clientX =
                                                           moveEvent.clientX ??
                                                           moveEvent.pageX ??
                                                           startX;
                                                   updateWidths(clientX);
                                           };

                                           const stopResize = () => {
                                                   if (isPointer && handle.releasePointerCapture) {
                                                           handle.releasePointerCapture(pointerId);
                                                           handle.removeEventListener(
                                                                   "pointermove",
                                                                   handleMove
                                                           );
                                                           handle.removeEventListener(
                                                                   "pointerup",
                                                                   stopResize
                                                           );
                                                           handle.removeEventListener(
                                                                   "pointercancel",
                                                                   stopResize
                                                           );
                                                   } else {
                                                           document.removeEventListener(
                                                                   "mousemove",
                                                                   handleMove
                                                           );
                                                           document.removeEventListener(
                                                                   "mouseup",
                                                                   stopResize
                                                           );
                                                   }
                                           };

                                           if (isPointer && handle.setPointerCapture) {
                                                   handle.setPointerCapture(pointerId);
                                                   handle.addEventListener("pointermove", handleMove);
                                                   handle.addEventListener("pointerup", stopResize);
                                                   handle.addEventListener(
                                                           "pointercancel",
                                                           stopResize
                                                   );
                                           } else {
                                                   document.addEventListener("mousemove", handleMove);
                                                   document.addEventListener("mouseup", stopResize);
                                           }
                                   };

                                   const listenerType = pointerSupported
                                           ? "pointerdown"
                                           : "mousedown";
                                   handle.addEventListener(listenerType, startResize);
                                   handleListeners.push({
                                           handle,
                                           type: listenerType,
                                           listener: startResize,
                                   });
                           }
                           scheduleOverlayUpdate();
                   };

                   measureWidths({ reset: true });
                   createHandles();

                   const bodyObserver = new MutationObserver(() => {
                           measureWidths();
                           createHandles();
                   });
                   bodyObserver.observe(body, { childList: true });

                   let resizeObserver = null;
                   if (typeof ResizeObserver === "function") {
                           resizeObserver = new ResizeObserver(() => {
                                   measureWidths({ reset: true });
                                   scheduleOverlayUpdate();
                           });
                           resizeObserver.observe(table);
                   }

                   const onWrapperScroll = () => {
                           scheduleOverlayUpdate();
                   };
                   wrapper.addEventListener("scroll", onWrapperScroll);

                   const onWindowResize = () => {
                           measureWidths({ reset: true });
                           scheduleOverlayUpdate();
                   };
                   window.addEventListener("resize", onWindowResize);

                   scheduleOverlayUpdate();

                   return () => {
                           cancelScheduled();
                           detachHandleListeners();
                           bodyObserver.disconnect();
                           if (resizeObserver) {
                                   resizeObserver.disconnect();
                           }
                           wrapper.removeEventListener("scroll", onWrapperScroll);
                           window.removeEventListener("resize", onWindowResize);
                           overlay.remove();
                           cols.forEach((col) => {
                                   if (col && col.style) {
                                           col.style.removeProperty("width");
                                   }
                           });
                           if (previousTableLayout) {
                                   table.style.tableLayout = previousTableLayout;
                           } else {
                                   table.style.removeProperty("table-layout");
                           }
                           if (shouldRestorePosition) {
                                   wrapper.style.removeProperty("position");
                           }
                           if (table && table.style) {
                                   table.style.removeProperty("--guarantees-vehicle-column-width");
                           }
                   };
           };

           const ensureResizableColumns = () => {
                   if (!teardown) {
                           teardown = setup();
                   }
           };

           const rebuildResizableColumns = () => {
                   if (teardown) {
                           teardown();
                           teardown = null;
                   }
                   teardown = setup();
           };

           ensureResizableColumns();

           const viewportQuery =
                   typeof window !== "undefined" &&
                   typeof window.matchMedia === "function"
                           ? window.matchMedia("(min-width: 1280px)")
                           : null;

           if (viewportQuery) {
                   const handleViewportChange = () => {
                           rebuildResizableColumns();
                   };

                   if (typeof viewportQuery.addEventListener === "function") {
                           viewportQuery.addEventListener("change", handleViewportChange);
                   } else if (typeof viewportQuery.addListener === "function") {
                           viewportQuery.addListener(handleViewportChange);
                   }
           }
   }

                function formatDate(value) {
                        if (!value) return { iso: "-", display: "-" };
                        let cleaned = String(value).replace(/[^0-9]/g, "");
                        if (cleaned.length === 8) {
                                const y = cleaned.slice(0, 4);
                                const m = cleaned.slice(4, 6);
                                const d = cleaned.slice(6, 8);
                                const iso = `${y}-${m}-${d}`;
                                const date = new Date(iso);
                                if (!isNaN(date)) {
                                        const display = new Intl.DateTimeFormat("es-ES", {
                                                day: "2-digit",
                                                month: "2-digit",
                                                year: "2-digit",
                                        }).format(date);
                                        return { iso, display };
                                }
                                return { iso, display: `${d}/${m}/${y.slice(2)}` };
                        }
                        const date = new Date(value);
                        if (!isNaN(date)) {
                                const year = date.getFullYear();
                                const month = String(date.getMonth() + 1).padStart(2, "0");
                                const day = String(date.getDate()).padStart(2, "0");
                                const iso = `${year}-${month}-${day}`;
                                const display = new Intl.DateTimeFormat("es-ES", {
                                        day: "2-digit",
                                        month: "2-digit",
                                        year: "2-digit",
                                }).format(date);
                                return { iso, display };
                        }
                        return { iso: value, display: value };
                }

                function formatPrice(value) {
                        if (value === null || value === undefined || value === "") return "-";
                        const num =
                                typeof value === "number"
                                        ? value
                                        : parseFloat(
                                                  String(value)
                                                          .replace(/[^0-9.,-]/g, "")
                                                          .replace(",", ".")
                                          );
                        if (isNaN(num)) return String(value);
                        return new Intl.NumberFormat("de-DE", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                        }).format(num);
                }

                function extractVendorType(value) {
                        if (value === null || value === undefined) {
                                return "";
                        }
                        const label = String(value).trim();
                        if (!label) {
                                return "";
                        }
                        const match = label.match(/\(([^)]+)\)/);
                        if (match && match[1]) {
                                const inside = match[1].trim();
                                if (!inside) {
                                        return "";
                                }
                                const normalizedInside = inside.toLowerCase();
                                if (normalizedInside === "concesionario" || normalizedInside === "concesionario oficial") {
                                        return "Concesionario Oficial";
                                }
                                return inside;
                        }
                        const cleaned = label.replace(/^Profesional\s*[-–:|]?\s*/i, "").trim();
                        if (!cleaned) {
                                return label;
                        }
                        const normalized = cleaned.toLowerCase();
                        if (normalized === "concesionario" || normalized === "concesionario oficial") {
                                return "Concesionario Oficial";
                        }
                        return cleaned;
                }

                function normalizeChannelLabel(label) {
                        if (label === null || label === undefined) {
                                return "";
                        }
                        const text = String(label).trim();
                        if (!text) {
                                return "";
                        }
                        const normalized = text.toLowerCase();
                        if (
                                normalized === "individual" ||
                                normalized === "particular" ||
                                normalized === "go_particular" ||
                                normalized === "go_individual"
                        ) {
                                return "Particular";
                        }
                        if (/\bindividual\b/i.test(text)) {
                                return text.replace(/\bindividual\b/gi, "Particular");
                        }
                        return text;
                }

                function normalizeChannelValue(value) {
                        if (value === null || value === undefined) {
                                return "";
                        }
                        const normalized = String(value)
                                .trim()
                                .toLowerCase();
                        if (!normalized) {
                                return "";
                        }
                        const base = normalized.startsWith("go_")
                                ? normalized.slice(3)
                                : normalized;
                        if (base === "individual") {
                                return "particular";
                        }
                        return base;
                }

                function buildPersonalName(first, last, fallback) {
                        const firstClean = typeof first === "string" ? first.trim() : "";
                        const lastClean = typeof last === "string" ? last.trim() : "";
                        if (firstClean || lastClean) {
                                return [firstClean, lastClean]
                                        .filter(Boolean)
                                        .join(" ")
                                        .trim();
                        }
                        const fallbackClean = typeof fallback === "string" ? fallback.trim() : "";
                        return fallbackClean;
                }

                function cleanDisplayValue(value) {
                        if (typeof value !== "string") {
                                return "";
                        }
                        const trimmed = value.trim();
                        if (
                                !trimmed ||
                                trimmed === "-" ||
                                trimmed === "—" ||
                                trimmed === "#"
                        ) {
                                return "";
                        }
                        return trimmed;
                }

                const { getTransferDeadlineMillis, getTransferDeadlineInfo } = (() => {
                        function parseDateTime(value) {
                                if (!value) return null;
                                if (value instanceof Date) return value;
                                if (typeof value === "number") {
                                        const fromNumber = new Date(value);
                                        if (!Number.isNaN(fromNumber.getTime())) {
                                                return fromNumber;
                                        }
                                }
                                const normalized = String(value).trim();
                                if (!normalized) return null;
                                const normalizeYearValue = (year) => {
                                        const numericYear = Number(year);
                                        if (!Number.isFinite(numericYear)) return null;
                                        if (String(year).length === 2) {
                                                return 2000 + numericYear;
                                        }
                                        return numericYear;
                                };
                                const safeDate = (year, month, day, hours = 0, minutes = 0, seconds = 0) => {
                                        const y = normalizeYearValue(year);
                                        const m = Number(month) - 1;
                                        const d = Number(day);
                                        const hh = Number(hours);
                                        const mm = Number(minutes);
                                        const ss = Number(seconds);
                                        if (!Number.isFinite(y)) return null;
                                        const candidate = new Date(y, m, d, hh, mm, ss);
                                        if (Number.isNaN(candidate.getTime())) return null;
                                        return candidate;
                                };
                                const localMatch = normalized.match(
                                        /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
                                );
                                if (localMatch) {
                                        const [, day, month, year, hours = "0", minutes = "0", seconds = "0"] = localMatch;
                                        const date = safeDate(year, month, day, hours, minutes, seconds);
                                        if (date) return date;
                                }
                                const isoMatch = normalized.match(
                                        /^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
                                );
                                if (isoMatch) {
                                        const [, year, month, day, hours = "0", minutes = "0", seconds = "0"] = isoMatch;
                                        const date = safeDate(year, month, day, hours, minutes, seconds);
                                        if (date) return date;
                                }
                                const numeric = normalized.replace(/[^0-9]/g, "");
                                const parseFromNumeric = (year, month, day, hours = "0", minutes = "0", seconds = "0") =>
                                        safeDate(year, month, day, hours, minutes, seconds);
                                if (numeric.length >= 14) {
                                        const firstChunk = Number(numeric.slice(0, 4));
                                        if (firstChunk > 1900) {
                                                const date = parseFromNumeric(
                                                        numeric.slice(0, 4),
                                                        numeric.slice(4, 6),
                                                        numeric.slice(6, 8),
                                                        numeric.slice(8, 10),
                                                        numeric.slice(10, 12),
                                                        numeric.slice(12, 14)
                                                );
                                                if (date) return date;
                                        } else {
                                                const date = parseFromNumeric(
                                                        numeric.slice(4, 8),
                                                        numeric.slice(2, 4),
                                                        numeric.slice(0, 2),
                                                        numeric.slice(8, 10),
                                                        numeric.slice(10, 12),
                                                        numeric.slice(12, 14)
                                                );
                                                if (date) return date;
                                        }
                                }
                                if (numeric.length === 12) {
                                        const firstChunk = Number(numeric.slice(0, 4));
                                        if (firstChunk > 1900) {
                                                const date = parseFromNumeric(
                                                        numeric.slice(0, 4),
                                                        numeric.slice(4, 6),
                                                        numeric.slice(6, 8),
                                                        numeric.slice(8, 10),
                                                        numeric.slice(10, 12)
                                                );
                                                if (date) return date;
                                        } else {
                                                const date = parseFromNumeric(
                                                        numeric.slice(4, 8),
                                                        numeric.slice(2, 4),
                                                        numeric.slice(0, 2),
                                                        numeric.slice(8, 10),
                                                        numeric.slice(10, 12)
                                                );
                                                if (date) return date;
                                        }
                                }
                                if (numeric.length === 8) {
                                        const firstChunk = Number(numeric.slice(0, 4));
                                        if (firstChunk > 1900) {
                                                const date = parseFromNumeric(
                                                        numeric.slice(0, 4),
                                                        numeric.slice(4, 6),
                                                        numeric.slice(6, 8)
                                                );
                                                if (date) return date;
                                        } else {
                                                const date = parseFromNumeric(
                                                        numeric.slice(4, 8),
                                                        numeric.slice(2, 4),
                                                        numeric.slice(0, 2)
                                                );
                                                if (date) return date;
                                        }
                                }
                                const parsed = new Date(normalized);
                                if (!Number.isNaN(parsed.getTime())) {
                                        return parsed;
                                }
                                return null;
                        }

                        function isSameCalendarDay(a, b) {
                                return (
                                        a.getFullYear() === b.getFullYear() &&
                                        a.getMonth() === b.getMonth() &&
                                        a.getDate() === b.getDate()
                                );
                        }

                        function getTransferDeadlineMillis(detailData, rowData, now = Date.now()) {
                                const candidates = [
                                        detailData?.desde_raw,
                                        rowData?.desde_raw,
                                        detailData?.desde,
                                        rowData?.desde,
                                ];
                                for (const candidate of candidates) {
                                        const date = parseDateTime(candidate);
                                        if (date) {
                                                const nowDate = new Date(now);
                                                if (isSameCalendarDay(date, nowDate)) {
                                                        return now + 48 * 60 * 60 * 1000;
                                                }
                                                return date.getTime() + 48 * 60 * 60 * 1000;
                                        }
                                }
                                return null;
                        }

                        function formatDeadlineDate(deadlineMs) {
                                if (!Number.isFinite(deadlineMs)) {
                                        return "";
                                }
                                const date = new Date(deadlineMs);
                                if (Number.isNaN(date.getTime())) {
                                        return "";
                                }
                                try {
                                        return new Intl.DateTimeFormat("es-ES", {
                                                day: "numeric",
                                                month: "long",
                                        }).format(date);
                                } catch (error) {
                                        console.warn("No se pudo formatear la fecha límite de transferencia", error);
                                        return "";
                                }
                        }

                        function getTransferDeadlineInfo(detailData, rowData, now = Date.now()) {
                                const deadlineMs = getTransferDeadlineMillis(detailData, rowData, now);
                                if (!Number.isFinite(deadlineMs)) {
                                        return {
                                                deadlineMs: null,
                                                expired: false,
                                                label: "",
                                        };
                                }
                                return {
                                        deadlineMs,
                                        expired: deadlineMs <= now,
                                        label: formatDeadlineDate(deadlineMs),
                                };
                        }

                        return { getTransferDeadlineMillis, getTransferDeadlineInfo };
                })();
                function clearActiveCountdown() {}

                function setupTransferCountdown() {}

                function normalizeDetailData(data) {
                        if (!data || typeof data !== "object") return data;
                        const rawDesde = data.desde;
                        const d = formatDate(rawDesde);
                        data.desde = d.iso;
                        data.desde_fmt = d.display;
                        data.desde_raw = rawDesde || "";
                        const rawHasta = data.hasta;
                        const h = formatDate(rawHasta);
                        data.hasta = h.iso;
                        data.hasta_fmt = h.display;
                        data.hasta_raw = rawHasta || "";
                        if (data.precio !== undefined) data.precio = formatPrice(data.precio);
                        if (data.precio_venta !== undefined)
                                data.precio_venta = formatPrice(data.precio_venta);
                        if (data.kilometros !== undefined) {
                                const num = parseInt(String(data.kilometros).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.kilometros = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.cilindrada === undefined && data.Cilindrada !== undefined) {
                                data.cilindrada = data.Cilindrada;
                        }
                        if (data.cilindrada !== undefined) {
                                const num = parseInt(String(data.cilindrada).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.cilindrada = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.potencia !== undefined) {
                                const num = parseInt(String(data.potencia).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.potencia = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.combustible && typeof data.combustible === "object") {
                                data.combustible = data.combustible.label || data.combustible.name || data.combustible.value || data.combustible;
                        }
                        if (data.cambio && typeof data.cambio === "object") {
                                data.cambio = data.cambio.label || data.cambio.name || data.cambio.value || data.cambio;
                        }
                        if (data.traccion && typeof data.traccion === "object") {
                                data.traccion = data.traccion.label || data.traccion.name || data.traccion.value || data.traccion;
                        }
                        if (data.traccion_camion && typeof data.traccion_camion === "object") {
                                data.traccion_camion =
                                        data.traccion_camion.label ||
                                        data.traccion_camion.name ||
                                        data.traccion_camion.value ||
                                        data.traccion_camion;
                        }
                        if (data.tipo && typeof data.tipo === "object") {
                                data.tipo = data.tipo.label || data.tipo.name || data.tipo.value || data.tipo;
                        }
                        if (typeof data.transfer_iban === "string") {
                                data.transfer_iban = data.transfer_iban.trim();
                        }
                        if (!Array.isArray(data.documents)) {
                                data.documents = [];
                        }
                        return data;
                }

                const CARD_DATE_FORMATTER = new Intl.DateTimeFormat("es-ES", {
                        day: "2-digit",
                        month: "2-digit",
                        year: "numeric",
                });
                const CARD_DAYS_FORMATTER = new Intl.NumberFormat("es-ES");

                function syncMobileCardsEmptyState() {
                        if (!mobileCardsRoot || !mobileCardsEmpty) {
                                return;
                        }
                        const hasCards = Boolean(
                                mobileCardsList && mobileCardsList.children.length > 0
                        );
                        mobileCardsEmpty.hidden = hasCards;
                        if (hasCards) {
                                mobileCardsEmpty.setAttribute("aria-hidden", "true");
                        } else {
                                mobileCardsEmpty.removeAttribute("aria-hidden");
                        }
                }

                function resetMobileCards() {
                        mobileCardsMap.clear();
                        if (!mobileCardsList) {
                                return;
                        }
                        mobileCardsList.innerHTML = "";
                        syncMobileCardsEmptyState();
                }

                function buildRowSelectorById(id) {
                        const normalized = String(id ?? "");
                        const escaped = normalized
                                .replace(/\\/g, "\\\\")
                                .replace(/"/g, '\\"')
                                .replace(/]/g, "\\]");
                        return `.guarantees-table__row[data-id="${escaped}"]`;
                }

                function findRowById(id) {
                        if (!tbody || id === undefined || id === null) {
                                return null;
                        }
                        const selector = buildRowSelectorById(id);
                        return tbody.querySelector(selector);
                }

                const AVATAR_COLOR_PALETTE = [
                        { bg: "#2563eb", fg: "#f8fafc" },
                        { bg: "#1d4ed8", fg: "#f8fafc" },
                        { bg: "#0ea5e9", fg: "#0b1021" },
                        { bg: "#0f766e", fg: "#ecfeff" },
                        { bg: "#22c55e", fg: "#052e16" },
                        { bg: "#eab308", fg: "#0f172a" },
                        { bg: "#f97316", fg: "#0f172a" },
                        { bg: "#db2777", fg: "#fff7fb" },
                        { bg: "#7c3aed", fg: "#f4f1ff" },
                        { bg: "#f43f5e", fg: "#fff1f2" },
                ];

                function hashAvatarSeed(value) {
                        const input = typeof value === "string" ? value.trim() : value != null ? String(value) : "";
                        if (!input) {
                                return 0;
                        }
                        let hash = 0;
                        for (let i = 0; i < input.length; i += 1) {
                                hash = (hash * 31 + input.charCodeAt(i)) >>> 0;
                        }
                        return hash;
                }

                function normalizeHex(hex) {
                        const sanitized = (hex || "").toString().trim().replace("#", "");
                        if (sanitized.length === 3) {
                                return sanitized
                                        .split("")
                                        .map((ch) => ch + ch)
                                        .join("");
                        }
                        if (sanitized.length === 6) {
                                return sanitized;
                        }
                        return "";
                }

                function lightenHexColor(hex, percent) {
                        const normalized = normalizeHex(hex);
                        if (!normalized) {
                                return hex;
                        }
                        const amount = Number.isFinite(percent) ? percent : 15;
                        const [r, g, b] = [0, 2, 4].map((idx) => parseInt(normalized.slice(idx, idx + 2), 16));
                        const toChannel = (channel) => {
                                const next = Math.round(channel + ((255 - channel) * amount) / 100);
                                return Math.max(0, Math.min(255, next));
                        };
                        const [nr, ng, nb] = [toChannel(r), toChannel(g), toChannel(b)];
                        return `#${nr.toString(16).padStart(2, "0")}${ng.toString(16).padStart(2, "0")}${nb
                                .toString(16)
                                .padStart(2, "0")}`;
                }

                function pickAvatarPalette(seed) {
                        if (!seed) return null;
                        const index = hashAvatarSeed(seed) % AVATAR_COLOR_PALETTE.length;
                        const entry = AVATAR_COLOR_PALETTE[index];
                        return {
                                bg: entry.bg,
                                fg: entry.fg,
                                border: lightenHexColor(entry.bg, 18),
                        };
                }

                function extractAvatarPalette(raw) {
                        if (!raw || typeof raw !== "object") {
                                return null;
                        }
                        const bg = typeof raw.bg === "string" ? raw.bg.trim() : "";
                        const bgDark = typeof raw.bg_dark === "string" ? raw.bg_dark.trim() : "";
                        const text = typeof raw.text === "string" ? raw.text.trim() : "";
                        const textDark = typeof raw.text_dark === "string" ? raw.text_dark.trim() : "";
                        if (bg === "" || bgDark === "" || text === "" || textDark === "") {
                                return null;
                        }
                        return { bg, bgDark, text, textDark };
                }

                function buildInitialsFromName(raw) {
                        if (!raw || typeof raw !== "string") {
                                return "";
                        }
                        const normalized = raw.trim();
                        if (!normalized) {
                                return "";
                        }
                        const words = normalized.split(/\s+/).filter(Boolean);
                        if (words.length === 0) {
                                return "";
                        }
                        if (words.length === 1) {
                                return words[0].slice(0, 2).toUpperCase();
                        }
                        const [first, second] = words;
                        if (first.length === 2) {
                                return first.slice(0, 2).toUpperCase();
                        }
                        if (second && /^\d/.test(second)) {
                                return (first.slice(0, 2) || first.charAt(0)).toUpperCase();
                        }
                        return `${first.charAt(0)}${second.charAt(0)}`.toUpperCase();
                }

                function normalizeAvatarUrl(url) {
                        if (typeof url !== "string") {
                                return "";
                        }

                        return url.replace(/&amp;/gi, "&").trim();
                }

                function getCardInitials(value) {
                        return buildInitialsFromName(value);
                }

                function formatCardDateLabel(iso) {
                        if (!iso || iso === "-") {
                                return "";
                        }
                        const date = new Date(iso);
                        if (Number.isNaN(date.getTime())) {
                                return "";
                        }
                        return CARD_DATE_FORMATTER.format(date);
                }

                const MS_PER_DAY = 86400000;

                function parseDateOnly(value) {
                        if (!value || value === "-" || value === "#") {
                                return null;
                        }
                        const normalized = String(value).trim();
                        if (!normalized) {
                                return null;
                        }
                        const candidate = new Date(normalized);
                        if (Number.isNaN(candidate.getTime())) {
                                return null;
                        }
                        candidate.setHours(0, 0, 0, 0);
                        return candidate;
                }

                function computeRemainingDaysLabel(estadoClase, desdeIso, hastaIso) {
                        const today = new Date();
                        today.setHours(0, 0, 0, 0);

                        const startDate = parseDateOnly(desdeIso);
                        if (startDate && startDate.getTime() > today.getTime()) {
                                const diffToStart = Math.round(
                                        (startDate.getTime() - today.getTime()) / MS_PER_DAY
                                );
                                if (diffToStart > 0) {
                                        if (diffToStart === 1) {
                                                return "(1 día para inicio)";
                                        }
                                        const formattedStart = CARD_DAYS_FORMATTER.format(diffToStart);
                                        return `(${formattedStart} días para inicio)`;
                                }
                        }

                        const endDate = parseDateOnly(hastaIso);
                        if (!endDate) {
                                return "";
                        }

                        const diffDays = Math.round(
                                (endDate.getTime() - today.getTime()) / MS_PER_DAY
                        );
                        if (diffDays < 0 || estadoClase === "expirada") {
                                return "Expirada";
                        }
                        if (diffDays === 0) {
                                return "(Caduca hoy)";
                        }
                        if (diffDays === 1) {
                                return "(1 día restante)";
                        }
                        const formatted = CARD_DAYS_FORMATTER.format(diffDays);
                        return `(${formatted} días restantes)`;
                }

                function buildGuaranteeViewModel(item) {
                        if (!item || typeof item !== "object") {
                                return null;
                        }
                        const estadoData = item.estado ?? "";
                        const estadoValue =
                                typeof estadoData === "object" && estadoData.value
                                        ? estadoData.value
                                        : typeof estadoData === "string" && estadoData
                                        ? estadoData
                                        : typeof estadoData === "number"
                                        ? String(estadoData)
                                        : "";
                        const estadoLabel =
                                typeof estadoData === "object" && estadoData.label
                                        ? estadoData.label
                                        : estadoValue || "Desconocido";
                        const estadoClase = normalizeEstadoClase(estadoValue);
                        const marcaModelo = item.marca ?? "-";
                        const matricula = item.mat ?? item.matricula ?? "-";
                        const rawDesde = item.desde ?? "";
                        const rawHasta = item.hasta ?? "";
                        const { iso: desdeIsoRaw, display: desdeDisplayRaw } = formatDate(rawDesde);
                        const { iso: hastaIsoRaw, display: hastaDisplayRaw } = formatDate(rawHasta);
                        const hasPeriod =
                                desdeDisplayRaw !== "-" &&
                                hastaDisplayRaw !== "-" &&
                                desdeDisplayRaw !== "" &&
                                hastaDisplayRaw !== "";
                        const canalVentaValueRaw =
                                item.canal_venta && Object.prototype.hasOwnProperty.call(item.canal_venta, "value")
                                        ? item.canal_venta.value
                                        : item.detail && Object.prototype.hasOwnProperty.call(item.detail, "canal_venta_value")
                                        ? item.detail.canal_venta_value
                                        : "";
                        const vendorTypeValueRaw =
                                item.detail && Object.prototype.hasOwnProperty.call(item.detail, "vendor_company_type_value")
                                        ? item.detail.vendor_company_type_value
                                        : "";
                        const normalizedChannel = normalizeChannelValue(canalVentaValueRaw);
                        const normalizedVendorType = normalizeChannelValue(vendorTypeValueRaw);
                        const isVendorParticular =
                                normalizedChannel === "particular" || normalizedVendorType === "particular";
                        let vendedorName = item.vendedor ?? "-";
                        if (isVendorParticular && item.detail && typeof item.detail === "object") {
                                const personalFull = cleanDisplayValue(
                                        Object.prototype.hasOwnProperty.call(item.detail, "concesionario_personal")
                                                ? item.detail.concesionario_personal
                                                : ""
                                );
                                const personalFirst = cleanDisplayValue(
                                        Object.prototype.hasOwnProperty.call(item.detail, "concesionario_personal_first")
                                                ? item.detail.concesionario_personal_first
                                                : ""
                                );
                                const personalLast = cleanDisplayValue(
                                        Object.prototype.hasOwnProperty.call(item.detail, "concesionario_personal_last")
                                                ? item.detail.concesionario_personal_last
                                                : ""
                                );
                                const derivedName = buildPersonalName(personalFirst, personalLast, personalFull);
                                if (derivedName) {
                                        vendedorName = derivedName;
                                }
                        }
                        const planName = item.plan ?? "";
                        const precio = formatPrice(item.precio);
                        const planPriceLabel = precio && precio !== "-" ? `${precio}€` : "";
                        const canalVentaRaw =
                                item.canal_venta && item.canal_venta.label
                                        ? item.canal_venta.label
                                        : "-";
                        const canalVenta = normalizeChannelLabel(canalVentaRaw) || "-";
                        const canalVentaSummaryRaw =
                                (item.detail && item.detail.canal_venta_summary)
                                        ? item.detail.canal_venta_summary
                                        : canalVentaRaw;
                        const canalVentaSummary =
                                normalizeChannelLabel(canalVentaSummaryRaw) || canalVenta;
                        const vendedorTypeRaw = canalVentaSummary;
                        const vendedorTypeClean = extractVendorType(vendedorTypeRaw);
                        const vendedorType =
                                normalizeChannelLabel(vendedorTypeClean || vendedorTypeRaw || "-") || "-";
                        const clienteNombre =
                                cleanDisplayValue(item.detail?.nombre_comprador) ||
                                cleanDisplayValue(item.detail?.datos_cliente_nombre_y_apellidos) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.nombre_y_apellidos) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.nombre_completo) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.nombre) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.nombre_apellidos) ||
                                cleanDisplayValue(item.detail?.comprador?.nombre) ||
                                cleanDisplayValue(item.detail?.comprador_nombre) ||
                                cleanDisplayValue(item.nombre_comprador) ||
                                cleanDisplayValue(item.cliente?.nombre_y_apellidos) ||
                                cleanDisplayValue(item.cliente?.nombre_completo) ||
                                cleanDisplayValue(item.cliente?.nombre) ||
                                cleanDisplayValue(item.cliente?.nombre_apellidos) ||
                                cleanDisplayValue(item.customer?.nombre_y_apellidos) ||
                                cleanDisplayValue(item.customer?.nombre_completo) ||
                                cleanDisplayValue(item.customer?.nombre) ||
                                cleanDisplayValue(item.customer?.nombre_apellidos) ||
                                cleanDisplayValue(item.cliente_nombre) ||
                                cleanDisplayValue(item.customer_name) ||
                                cleanDisplayValue(item.buyer?.nombre) ||
                                "-";
                        const clienteTelefonoValue =
                                cleanDisplayValue(item.detail?.telefono_comprador) ||
                                cleanDisplayValue(item.detail?.datos_cliente_telefono) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.telefono) ||
                                cleanDisplayValue(item.detail?.datos_cliente?.telefono_comprador) ||
                                cleanDisplayValue(item.detail?.comprador?.telefono) ||
                                cleanDisplayValue(item.detail?.comprador_telefono) ||
                                cleanDisplayValue(item.telefono_comprador) ||
                                cleanDisplayValue(item.cliente?.telefono) ||
                                cleanDisplayValue(item.customer?.telefono) ||
                                cleanDisplayValue(item.customer?.phone) ||
                                cleanDisplayValue(item.cliente_telefono) ||
                                cleanDisplayValue(item.customer_phone) ||
                                "";
                        const clienteTelefono = clienteTelefonoValue;
                        const clienteTelefonoDataset = clienteTelefono !== "" ? clienteTelefono : "-";
                        const shouldShowPlan = planName !== "" && planName !== "-";
                        const periodHtml = hasPeriod
                                ? `<div class="guarantees-table__period">
                                                <div><strong>Desde:</strong> <time>${desdeDisplayRaw}</time></div>
                                                <div><strong>Hasta:</strong> <time>${hastaDisplayRaw}</time></div>
                                        </div>`
                                : "-";
                        const planHtml = shouldShowPlan
                                ? `<div class="guarantees-table__plan">
                                                <span class="plan__name">${planName}</span>
                                                ${planPriceLabel ? `<span class="plan__price">${planPriceLabel}</span>` : ""}
                                        </div>`
                                : "";
                        const clientePhoneHtml =
                                clienteTelefono !== ""
                                        ? `<div class="cliente__phone">${clienteTelefono}</div>`
                                        : "";
                        const shouldShowClientColumn = isProfesional || isParticular;
                        const thirdColumnLabel = shouldShowClientColumn ? "Cliente" : "Canal de venta";
                        const thirdColumnHtml = shouldShowClientColumn
                                ? `<div class="guarantees-table__cliente">
                                <div class="cliente__name">${clienteNombre}</div>
                                ${clientePhoneHtml}
                        </div>`
                                : `<div class="guarantees-table__vendedor">
                                <div class="vendedor__name">${vendedorName}</div>
                                <div class="vendedor__type">${vendedorType}</div>
                        </div>`;
                        const metodoPago = item.detail?.metodo_pago || "";
                        const cobroRealizado = item.detail?.cobro_realizado ? "1" : "";
                        const cobroBadgeHtml =
                                isAdmin &&
                                metodoPago &&
                                metodoPago.toLowerCase().startsWith("domiciliacion") &&
                                !cobroRealizado
                                        ? `<span class="guarantees-list__badge guarantees-list__badge--pend-cobro">Pend. Domiciliación</span>`
                                        : "";
                        const estadoBadgeHtml = `<span class="guarantees-list__badge guarantees-list__badge--${estadoClase}">${estadoLabel}</span>`;
                        const cardDesde = hasPeriod ? formatCardDateLabel(desdeIsoRaw) : "";
                        const cardHasta = hasPeriod ? formatCardDateLabel(hastaIsoRaw) : "";
                        const cardValidityRange =
                                cardDesde && cardHasta ? `${cardDesde} - ${cardHasta}` : "";
                        const cardValidityDays = hasPeriod
                                ? computeRemainingDaysLabel(
                                          estadoClase,
                                          desdeIsoRaw,
                                          hastaIsoRaw
                                  )
                                : "";
                        const cardEntityName = shouldShowClientColumn ? clienteNombre : vendedorName;
                        let cardEntityMeta = shouldShowClientColumn
                                ? clienteTelefono || vendedorType
                                : vendedorType;
                        if (!cardEntityMeta || cardEntityMeta === "-") {
                                cardEntityMeta = shouldShowClientColumn ? vendedorType : "—";
                        }
                        const cardEntityIconSource =
                                (cardEntityName && cardEntityName !== "-" ? cardEntityName : "") ||
                                (vendedorName && vendedorName !== "-" ? vendedorName : "") ||
                                matricula;
                        const cardEntityIcon = getCardInitials(cardEntityIconSource);
                        const datasetPrecio = shouldShowPlan && precio && precio !== "-" ? precio : "";
                        return {
                                id: Object.prototype.hasOwnProperty.call(item, "id")
                                        ? String(item.id)
                                        : "",
                                estadoLabel,
                                estadoClase,
                                marcaModelo,
                                matricula,
                                rawDesde,
                                rawHasta,
                                desdeIso: hasPeriod ? desdeIsoRaw : "",
                                hastaIso: hasPeriod ? hastaIsoRaw : "",
                                desdeDisplay: hasPeriod ? desdeDisplayRaw : "",
                                hastaDisplay: hasPeriod ? hastaDisplayRaw : "",
                                vendedorName,
                                vendedorType,
                                vendedorTypeRaw,
                                canalVenta,
                                canalVentaSummary,
                                clienteNombre,
                                clienteTelefono,
                                clienteTelefonoDataset,
                                shouldShowPlan,
                                hasPeriod,
                                periodHtml,
                                planHtml,
                                planName,
                                planPriceLabel,
                                thirdColumnLabel,
                                thirdColumnHtml,
                                cobroBadgeHtml,
                                estadoBadgeHtml,
                                cardValidityRange,
                                cardValidityDays,
                                cardEntityName:
                                        cardEntityName && cardEntityName !== "-" ? cardEntityName : "—",
                                cardEntityMeta:
                                        cardEntityMeta && cardEntityMeta !== "-" ? cardEntityMeta : "—",
                                cardEntityIcon,
                                cardPlanName: shouldShowPlan ? planName : "",
                                cardPlanPrice: shouldShowPlan ? planPriceLabel : "",
                                datasetPrecio,
                                canalVentaValue: canalVenta,
                                metodoPago,
                                cobroRealizado,
                                concesionarioPersonal: item.detail?.concesionario_personal ?? "",
                                ibanVendedor: item.detail?.iban_vendedor || "",
                                transferIban: item.detail?.transfer_iban || "",
                        };
                }

                function createCardElement(view) {
                        const card = document.createElement("article");
                        card.className = "guarantee-card";
                        if (view.id) {
                                card.dataset.id = view.id;
                        }
                        if (view.estadoClase) {
                                card.dataset.estadoclase = view.estadoClase;
                        }
                        if (view.estadoLabel) {
                                card.dataset.estado = view.estadoLabel;
                        }
                        const statusClasses = ["guarantee-card__status"];
                        if (view.estadoClase) {
                                statusClasses.push(`guarantee-card__status--${view.estadoClase}`);
                        }
                        const statusClassAttr = statusClasses.join(" ");
                        const validitySegments = [];
                        if (view.cardValidityRange) {
                                validitySegments.push(
                                        `<span class="guarantee-card__dates">${escapeHtml(view.cardValidityRange)}</span>`
                                );
                        }
                        if (view.cardValidityDays) {
                                validitySegments.push(
                                        `<span class="guarantee-card__days">${escapeHtml(view.cardValidityDays)}</span>`
                                );
                        }
                        const validityHtml = validitySegments.length
                                ? `<div class="guarantee-card__validity">${validitySegments.join(" ")}</div>`
                                : "";
                        const planHtml = view.cardPlanName || view.cardPlanPrice
                                ? `<div class="guarantee-card__plan">
                                        ${
                                                view.cardPlanName
                                                        ? `<span class="guarantee-card__plan-name">${escapeHtml(view.cardPlanName)}</span>`
                                                        : ""
                                        }${
                                                view.cardPlanPrice
                                                        ? `<span class="guarantee-card__plan-price">${escapeHtml(view.cardPlanPrice)}</span>`
                                                        : ""
                                        }
                                </div>`
                                : "";
                        card.innerHTML = `
                                <div class="guarantee-card__header">
                                        <div class="guarantee-card__vehicle">
                                                <div class="guarantee-card__mat">
                                                        ${escapeHtml(view.matricula || "-")}
                                                </div>
                                                <div class="guarantee-card__model">${escapeHtml(view.marcaModelo || "-")}</div>
                                        </div>
                                        <span class="${statusClassAttr}">${escapeHtml(view.estadoLabel || "")}</span>
                                </div>
                                <div class="guarantee-card__details">
                                        <div class="guarantee-card__vendor">
                                                <div class="guarantee-card__vendor-icon" aria-hidden="true">${escapeHtml(view.cardEntityIcon)}</div>
                                                <div class="guarantee-card__vendor-info">
                                                        <span class="guarantee-card__vendor-name">${escapeHtml(view.cardEntityName)}</span>
                                                        <span class="guarantee-card__vendor-meta">${escapeHtml(view.cardEntityMeta)}</span>
                                                </div>
                                        </div>
                                        ${validityHtml}
                                </div>
                                <div class="guarantee-card__footer">
                                        ${planHtml}
                                        <button type="button" class="guarantee-card__action" data-mobile-detail-trigger>Ver garantía</button>
                                </div>
                        `;
                        if (!view.cardPlanName && !view.cardPlanPrice) {
                                const planEl = card.querySelector(".guarantee-card__plan");
                                if (planEl) {
                                        planEl.setAttribute("hidden", "hidden");
                                }
                        }
                        if (!validityHtml) {
                                const validityEl = card.querySelector(".guarantee-card__validity");
                                if (validityEl) {
                                        validityEl.remove();
                                }
                        }
                        const iconEl = card.querySelector(".guarantee-card__vendor-icon");
                        if (iconEl && !view.cardEntityIcon) {
                                iconEl.textContent = "";
                        }
                        return card;
                }

                function upsertMobileCard(view, { prepend = false } = {}) {
                        if (!mobileCardsList || !view || !view.id) {
                                return;
                        }
                        const card = createCardElement(view);
                        const existing = mobileCardsMap.get(view.id);
                        if (existing && existing.parentElement) {
                                existing.replaceWith(card);
                        } else if (prepend && mobileCardsList.firstChild) {
                                mobileCardsList.insertBefore(card, mobileCardsList.firstChild);
                        } else {
                                mobileCardsList.appendChild(card);
                        }
                        mobileCardsMap.set(view.id, card);
                        syncMobileCardsEmptyState();
                }

                function renderRow(item, options = {}) {
                        const view = options.viewModel || buildGuaranteeViewModel(item);
                        if (!view) {
                                return document.createElement("tr");
                        }
                        const tr = document.createElement("tr");
                        tr.className = "guarantees-table__row";
                        tr.tabIndex = 0;
                        if (view.id) {
                                tr.dataset.id = view.id;
                        }
                        tr.dataset.matricula = view.matricula || "-";
                        tr.dataset.marca_modelo = view.marcaModelo || "-";
                        tr.dataset.plan = view.shouldShowPlan ? view.planName : "";
                        tr.dataset.desde = view.hasPeriod ? view.desdeIso : "";
                        tr.dataset.desdeRaw = view.hasPeriod ? view.rawDesde : "";
                        tr.dataset.desdeFmt = view.hasPeriod ? view.desdeDisplay : "";
                        tr.dataset.hasta = view.hasPeriod ? view.hastaIso : "";
                        tr.dataset.hastaRaw = view.hasPeriod ? view.rawHasta : "";
                        tr.dataset.hastaFmt = view.hasPeriod ? view.hastaDisplay : "";
                        tr.dataset.estado = view.estadoLabel || "";
                        tr.dataset.estadoclase = view.estadoClase || "";
                        tr.dataset.vendedor_name = view.vendedorName || "";
                        tr.dataset.vendedor_type = view.vendedorType || "";
                        tr.dataset.canal_venta_summary = view.canalVentaSummary || "";
                        tr.dataset.concesionario = view.vendedorName || "";
                        tr.dataset.concesionario_personal = view.concesionarioPersonal || "";
                        tr.dataset.precio = view.datasetPrecio || "";
                        tr.dataset.canalVenta = view.canalVentaValue || "";
                        tr.dataset.metodoPago = view.metodoPago || "";
                        tr.dataset.cobroRealizado = view.cobroRealizado || "";
                        tr.dataset.ibanVendedor = view.ibanVendedor || "";
                        tr.dataset.transferIban = view.transferIban || "";
                        tr.dataset.compradorNombre = view.clienteNombre || "-";
                        tr.dataset.compradorTelefono = view.clienteTelefonoDataset || "-";

                        tr.innerHTML = `
                                <td data-label="Vehículo">
                                        <div class="guarantees-table__vehiculo">
                                                <div class="vehiculo__mat">
                                                        ${view.matricula || "-"}
                                                </div>
                                                <div class="vehiculo__marca_modelo">${view.marcaModelo || "-"}</div>
                                        </div>
                                </td>
                                <td data-label="Validez">${view.periodHtml}</td>
                                <td data-label="${view.thirdColumnLabel}">${view.thirdColumnHtml}</td>
                                <td data-label="Estado">
                                        <div class="guarantees-table__estado">
                                                ${view.estadoBadgeHtml}
                                                ${view.cobroBadgeHtml}
                                        </div>
                                </td>
                                <td data-label="Garantía">${view.planHtml}</td>
                        `;

                        const headerCells = table ? table.querySelectorAll("thead th") : [];
                        headerCells.forEach((th, i) => {
                                const width = th.getBoundingClientRect().width;
                                if (tr.children[i]) {
                                        tr.children[i].style.width = `${width}px`;
                                }
                        });

                        upsertMobileCard(view, { prepend: Boolean(options.prepend) });

                        return tr;
                }
                function renderEmptyRow() {
                        const tr = document.createElement("tr");
                        tr.className = "guarantees-table__empty-row";
                        const td = document.createElement("td");
                        const headerCount = table
                                ? table.querySelectorAll("thead th").length
                                : 1;
                        td.colSpan = Math.max(1, headerCount);
                        td.innerHTML =
                                '<div class="guarantees-table__empty-message"><p>Todavía no hay garantías.</p></div>';
                        tr.appendChild(td);
                        return tr;
                }

                function normalizeToFloat(value) {
                        if (value === null || value === undefined) {
                                return 0;
                        }
                        if (typeof value === "number") {
                                return Number.isFinite(value) ? value : 0;
                        }

                        const raw = String(value).trim();
                        if (!raw) {
                                return 0;
                        }

                        // Keep only digits, separators and sign for parsing heuristics
                        const cleaned = raw.replace(/[^0-9,.-]/g, "");
                        const hasComma = cleaned.includes(",");
                        const hasDot = cleaned.includes(".");

                        // Decide decimal separator: last occurring separator wins when both exist
                        let decimalSeparator = null;
                        if (hasComma && hasDot) {
                                decimalSeparator =
                                        cleaned.lastIndexOf(",") > cleaned.lastIndexOf(".") ? "," : ".";
                        } else if (hasComma) {
                                decimalSeparator = ",";
                        } else if (hasDot) {
                                decimalSeparator = ".";
                        }

                        if (decimalSeparator) {
                                const separatorIndex = cleaned.lastIndexOf(decimalSeparator);
                                const integerPartRaw = cleaned.slice(0, separatorIndex);
                                const decimalPartRaw = cleaned.slice(separatorIndex + 1);

                                const sign = integerPartRaw.trim().startsWith("-") ? "-" : "";
                                const integerPart = integerPartRaw.replace(/[^0-9]/g, "");
                                const decimalPart = decimalPartRaw.replace(/[^0-9]/g, "");

                                const composed = `${sign}${integerPart || "0"}.${decimalPart || "0"}`;
                                const parsed = Number(composed);
                                return Number.isFinite(parsed) ? parsed : 0;
                        }

                        const parsed = Number(cleaned.replace(/[^0-9-]/g, ""));
                        return Number.isFinite(parsed) ? parsed : 0;
                }

                function normalizeToInt(value) {
                        const number = normalizeToFloat(value);
                        return Math.max(0, Math.round(number));
                }

                function formatIntegerValue(value) {
                        return integerFormatter.format(normalizeToInt(value));
                }

                function formatCurrencyValue(value) {
                        const formatted = currencyFormatter.format(normalizeToFloat(value));
                        return formatted.replace(/[\s\u00A0]*€$/, "€");
                }

                function formatGuaranteeCount(value) {
                        const count = normalizeToInt(value);
                        const formatted = formatIntegerValue(count);
                        return count === 1 ? `${formatted} garantía` : `${formatted} garantías`;
                }

                function animateSummaryNumber(element, target, options = {}) {
                        if (!element) {
                                return;
                        }
                        const duration = typeof options.duration === "number" ? options.duration : 500;
                        const startValue =
                                typeof options.start === "number"
                                        ? options.start
                                        : normalizeToInt(element.dataset.value || element.textContent || 0);
                        const normalizedTarget = normalizeToInt(target);
                        if (numberAnimations.has(element)) {
                                cancelAnimationFrame(numberAnimations.get(element));
                        }
                        const startTime = performance.now();
                        function step(now) {
                                const progress = Math.min((now - startTime) / duration, 1);
                                const current = Math.round(
                                        startValue + (normalizedTarget - startValue) * progress
                                );
                                element.textContent = formatIntegerValue(current);
                                if (progress < 1) {
                                        numberAnimations.set(element, requestAnimationFrame(step));
                                } else {
                                        element.textContent = formatIntegerValue(normalizedTarget);
                                        element.dataset.value = String(normalizedTarget);
                                        numberAnimations.delete(element);
                                }
                        }
                        numberAnimations.set(element, requestAnimationFrame(step));
                }

                function animateSummaryCurrency(element, target, options = {}) {
                        if (!element) {
                                return;
                        }
                        const duration = typeof options.duration === "number" ? options.duration : 500;
                        const startValue =
                                typeof options.start === "number"
                                        ? options.start
                                        : normalizeToFloat(element.dataset.value || element.textContent || 0);
                        const normalizedTarget = normalizeToFloat(target);
                        if (numberAnimations.has(element)) {
                                cancelAnimationFrame(numberAnimations.get(element));
                        }
                        const startTime = performance.now();
                        function step(now) {
                                const progress = Math.min((now - startTime) / duration, 1);
                                const current = startValue + (normalizedTarget - startValue) * progress;
                                element.textContent = formatCurrencyValue(current);
                                if (progress < 1) {
                                        numberAnimations.set(element, requestAnimationFrame(step));
                                } else {
                                        element.textContent = formatCurrencyValue(normalizedTarget);
                                        element.dataset.value = String(normalizedTarget);
                                        numberAnimations.delete(element);
                                }
                        }
                        numberAnimations.set(element, requestAnimationFrame(step));
                }

                function resetAdminSummaryKpis(root) {
                        if (!root) {
                                return;
                        }
                        const valueNodes = root.querySelectorAll("[data-admin-summary-kpi-value]");
                        valueNodes.forEach((node) => {
                                node.textContent = "—";
                                node.dataset.value = "0";
                        });
                        const trendNodes = root.querySelectorAll("[data-admin-summary-kpi-trend]");
                        trendNodes.forEach((node) => {
                                node.classList.remove("positive", "negative");
                                node.classList.add("neutral");
                                node.dataset.direction = "neutral";
                                const iconHolder = node.querySelector("[data-admin-summary-trend-icon]");
                                if (iconHolder) {
                                        iconHolder.innerHTML = ADMIN_SUMMARY_TREND_ICON_UP;
                                }
                                const label = node.querySelector("[data-admin-summary-trend-label]");
                                if (label) {
                                        label.textContent = "—";
                                } else {
                                        node.textContent = "—";
                                }
                        });
                }

                function setAdminSummaryLoading(root, isLoading) {
                        if (!root) {
                                return;
                        }
                        const loading = Boolean(isLoading);
                        root.classList.toggle("is-loading", loading);
                        root.dataset.loading = loading ? "1" : "0";
                        if (loading) {
                                root.dataset.loaded = "0";
                                root._adminSummaryData = null;
                                root._adminSummaryContextData = null;
                                root._adminSummaryHighlight = null;
                                root._adminSummaryLockedHighlight = null;
                                root.classList.remove("is-dimmed");
                        }
                        const error = root.querySelector("[data-admin-summary-error]");
                        if (error) {
                                error.hidden = true;
                        }
                        if (loading) {
                                root.dataset.context = ADMIN_SUMMARY_DEFAULT_CONTEXT;
                                setVisualizationHighlight(root, null);
                                const statesList = root.querySelector("[data-admin-summary-states]");
                                if (statesList) {
                                        statesList.innerHTML = "";
                                        for (let index = 0; index < 4; index += 1) {
                                                const skeleton = document.createElement("div");
                                                skeleton.className = "legend-item is-loading";
                                                statesList.appendChild(skeleton);
                                        }
                                }
                                const actionsList = root.querySelector("[data-admin-summary-actions]");
                                if (actionsList) {
                                        actionsList.innerHTML = "";
                                        const placeholderActions = ADMIN_SUMMARY_ACTIONS.filter(
                                                (action) => !action.requiresFullAccess || canSeeVerifyCollectStates
                                        );
                                        for (let index = 0; index < placeholderActions.length; index += 1) {
                                                const skeleton = document.createElement("li");
                                                skeleton.className = "action-item is-loading";
                                                actionsList.appendChild(skeleton);
                                        }
                                }
                                const totalEl = root.querySelector("[data-admin-summary-total]");
                                if (totalEl) {
                                        totalEl.textContent = "—";
                                        totalEl.dataset.value = "0";
                                        totalEl.classList.remove("highlighted");
                                        totalEl.style.color = "";
                                }
                                const labelEl = root.querySelector("[data-admin-summary-label]");
                                if (labelEl) {
                                        labelEl.textContent = labelEl.getAttribute("data-default-label") || "Total";
                                        labelEl.dataset.contextLabel = "";
                                        labelEl.classList.remove("highlighted");
                                        labelEl.style.color = "";
                                }
                                const donut = root.querySelector("[data-admin-summary-donut]");
                                if (donut) {
                                        resetDonutChart(donut);
                                        donut.classList.remove("is-empty");
                                }
                                resetAdminSummaryKpis(root);
                        }
                }

                function showAdminSummaryError(root) {
                        if (!root) {
                                return;
                        }
                        root.classList.remove("is-loading");
                        root.dataset.loading = "0";
                        root.dataset.loaded = "0";
                        root.classList.remove("is-dimmed");
                        root._adminSummaryHighlight = null;
                        root._adminSummaryLockedHighlight = null;
                        const legend = root.querySelector("[data-admin-summary-states]");
                        if (legend) {
                                legend.innerHTML = "";
                                const item = document.createElement("div");
                                item.className = "legend-item legend-item--empty";
                                item.textContent = ADMIN_SUMMARY_ERROR_MESSAGE;
                                legend.appendChild(item);
                        }
                        setVisualizationHighlight(root, null);
                        const actionsList = root.querySelector("[data-admin-summary-actions]");
                        if (actionsList) {
                                actionsList.innerHTML = "";
                        }
                        const totalEl = root.querySelector("[data-admin-summary-total]");
                        if (totalEl) {
                                totalEl.textContent = "—";
                                totalEl.dataset.value = "0";
                                totalEl.classList.remove("highlighted");
                                totalEl.style.color = "";
                        }
                        const labelEl = root.querySelector("[data-admin-summary-label]");
                        if (labelEl) {
                                labelEl.textContent = labelEl.getAttribute("data-default-label") || "Total";
                                labelEl.dataset.contextLabel = "";
                                labelEl.classList.remove("highlighted");
                                labelEl.style.color = "";
                        }
                        const donut = root.querySelector("[data-admin-summary-donut]");
                        if (donut) {
                                resetDonutChart(donut);
                        }
                        resetAdminSummaryKpis(root);
                        const error = root.querySelector("[data-admin-summary-error]");
                        if (error) {
                                error.hidden = false;
                        }
                }

                function normalizeSummaryStates(states) {
                        if (!Array.isArray(states)) {
                                return [];
                        }
                        const items = states
                                .map((entry) => {
                                        const value = typeof entry.value === "string" ? entry.value : "";
                                        if (!value) {
                                                return null;
                                        }
                                        const label = entry.label || value;
                                        const count = Math.max(0, normalizeToInt(entry.count || 0));
                                        return { value, label, count };
                                })
                                .filter((entry) => entry && entry.count > 0);

                        if (items.length <= 1) {
                                return items;
                        }

                        const orderMap = new Map(
                                ADMIN_SUMMARY_STATE_ORDER.map((key, index) => [key, index])
                        );

                        items.sort((a, b) => {
                                const orderA = orderMap.has(a.value) ? orderMap.get(a.value) : 99;
                                const orderB = orderMap.has(b.value) ? orderMap.get(b.value) : 99;
                                if (orderA !== orderB) {
                                        return orderA - orderB;
                                }
                                if (b.count !== a.count) {
                                        return b.count - a.count;
                                }
                                return a.label.localeCompare(b.label);
                        });

                        return items;
                }

                function normalizeSummaryAmounts(amounts) {
                        const map = new Map();
                        if (!Array.isArray(amounts)) {
                                return map;
                        }
                        amounts.forEach((entry) => {
                                const value = typeof entry.value === "string" ? entry.value : "";
                                if (!value) {
                                        return;
                                }
                                const amount = normalizeToFloat(entry.amount || 0);
                                map.set(value, amount);
                        });
                        return map;
                }

                function getDonutBaseColor(donut) {
                        if (!donut) {
                                return "#10b981";
                        }
                        const styles = window.getComputedStyle ? getComputedStyle(donut) : null;
                        if (!styles) {
                                return "#10b981";
                        }
                        const baseColor = (styles.getPropertyValue("--donut-base-color") || "").trim();
                        if (baseColor) {
                                return baseColor;
                        }
                        const fallback = (styles.getPropertyValue("--admin-summary-state-activada") || "").trim();
                        return fallback || "#10b981";
                }

                function resetDonutChart(donut) {
                        if (!donut) {
                                return;
                        }
                        const baseColor = getDonutBaseColor(donut);
                        donut._adminSummarySegments = [];
                        donut._adminSummaryTotal = 0;
                        donut.style.setProperty("--segment-0-end", "0%");
                        for (let index = 1; index <= 4; index += 1) {
                                donut.style.setProperty(`--segment-${index}-end`, index === 4 ? "100%" : "0%");
                                donut.style.setProperty(`--segment-${index}-color`, baseColor);
                        }
                        donut.style.setProperty("--p", "0");
                        donut.classList.add("is-empty");
                        donut.classList.remove("is-highlighted");
                        ADMIN_SUMMARY_STATE_VALUES.forEach((value) => {
                                donut.classList.remove(`dimmed-${value}`);
                        });
                        donut.style.removeProperty("--donut-highlight-color");
                        donut.style.removeProperty("--donut-highlight-muted");
                }

                function configureDonutSegments(donut, items) {
                        if (!donut) {
                                return;
                        }
                        const positive = Array.isArray(items)
                                ? items.filter((item) => item.count > 0)
                                : [];
                        const total = positive.reduce((sum, entry) => sum + entry.count, 0);
                        donut._adminSummarySegments = [];
                        donut._adminSummaryTotal = total;
                        if (positive.length === 0 || total <= 0) {
                                resetDonutChart(donut);
                                return;
                        }
                        const baseColor = getDonutBaseColor(donut);
                        donut.classList.remove("is-empty");
                        donut.style.setProperty("--segment-0-end", "0%");
                        let cumulative = 0;
                        positive.forEach((item, index) => {
                                const palette = ADMIN_SUMMARY_STATE_COLORS[item.value] || {};
                                cumulative += item.count / total;
                                const endValue = index === positive.length - 1
                                        ? 100
                                        : Math.min(100, Math.max(0, cumulative * 100));
                                donut.style.setProperty(
                                        `--segment-${index + 1}-end`,
                                        `${endValue.toFixed(2)}%`
                                );
                                donut._adminSummarySegments.push({
                                        value: item.value,
                                        color: palette.color || "transparent",
                                        muted: palette.muted || palette.color || "transparent",
                                });
                        });
                        for (let index = positive.length + 1; index <= 4; index += 1) {
                                donut.style.setProperty(`--segment-${index}-end`, "100%");
                                donut.style.setProperty(`--segment-${index}-color`, baseColor);
                        }
                        updateDonutColors(donut, null);
                        donut.style.setProperty("--p", "0");
                        requestAnimationFrame(() => {
                                donut.style.setProperty("--p", "1");
                        });
                }

                function updateDonutColors(donut, highlightValue) {
                        if (!donut) {
                                return;
                        }
                        const baseColor = getDonutBaseColor(donut);
                        const segments = Array.isArray(donut._adminSummarySegments)
                                ? donut._adminSummarySegments
                                : [];
                        if (segments.length === 0) {
                                for (let index = 1; index <= 4; index += 1) {
                                        donut.style.setProperty(`--segment-${index}-color`, baseColor);
                                }
                                ADMIN_SUMMARY_STATE_VALUES.forEach((value) => {
                                        donut.classList.remove(`dimmed-${value}`);
                                });
                                donut.classList.remove("is-highlighted");
                        donut.style.removeProperty("--donut-highlight-color");
                        donut.style.removeProperty("--donut-highlight-muted");
                                return;
                        }
                        const highlightSegment = highlightValue
                                ? segments.find((segment) => segment.value === highlightValue)
                                : null;
                        const highlightColor = highlightSegment ? highlightSegment.color : null;
                        const highlightMuted = highlightSegment
                                ? highlightSegment.muted || highlightSegment.color
                                : null;
                        ADMIN_SUMMARY_STATE_VALUES.forEach((value) => {
                                donut.classList.toggle(
                                        `dimmed-${value}`,
                                        Boolean(highlightSegment) && value === highlightValue
                                );
                        });
                        donut.classList.toggle("is-highlighted", Boolean(highlightSegment));
                        if (highlightSegment) {
                                donut.style.setProperty(
                                        "--donut-highlight-color",
                                        highlightColor || "transparent"
                                );
                                donut.style.setProperty(
                                        "--donut-highlight-muted",
                                        highlightMuted || highlightColor || "transparent"
                                );
                        } else {
                                donut.style.removeProperty("--donut-highlight-color");
                                donut.style.removeProperty("--donut-highlight-muted");
                        }
                        segments.forEach((segment, index) => {
                                let color = segment.color;
                                if (highlightSegment) {
                                        color = segment.value === highlightValue
                                                ? highlightColor
                                                : highlightMuted;
                                }
                                donut.style.setProperty(
                                        `--segment-${index + 1}-color`,
                                        color || baseColor
                                );
                        });
                        for (let index = segments.length + 1; index <= 4; index += 1) {
                                donut.style.setProperty(`--segment-${index}-color`, baseColor);
                        }
                }

                function setVisualizationHighlight(root, highlightValue) {
                        if (!root) {
                                return;
                        }
                        const visualization = root.querySelector(".visualization-section");
                        if (!visualization) {
                                return;
                        }
                        if (highlightValue) {
                                visualization.classList.add("is-dimmed");
                                visualization.dataset.highlight = highlightValue;
                        } else {
                                visualization.classList.remove("is-dimmed");
                                delete visualization.dataset.highlight;
                        }
                }

                function sanitizeLegendState(root, value) {
                        if (!root || !value) {
                                return null;
                        }
                        const contextData = root._adminSummaryContextData || {};
                        const items = Array.isArray(contextData.items) ? contextData.items : [];
                        const match = items.find((entry) => entry.value === value);
                        return match && match.value ? match.value : null;
                }

                function applySummaryTrend(trendEl, trendData, fallbackLabel) {
                        if (!trendEl) {
                                return;
                        }
                        const rawDirection =
                                trendData && typeof trendData.direction === "string"
                                        ? trendData.direction.trim()
                                        : "";
                        const direction =
                                rawDirection === "positive"
                                        ? "positive"
                                        : rawDirection === "negative"
                                        ? "negative"
                                        : "neutral";
                        trendEl.dataset.direction = direction;
                        trendEl.classList.toggle("positive", direction === "positive");
                        trendEl.classList.toggle("negative", direction === "negative");
                        trendEl.classList.toggle("neutral", direction === "neutral");
                        const iconHolder = trendEl.querySelector("[data-admin-summary-trend-icon]");
                        const iconHtml =
                                direction === "negative"
                                        ? ADMIN_SUMMARY_TREND_ICON_DOWN
                                        : ADMIN_SUMMARY_TREND_ICON_UP;
                        if (iconHolder) {
                                iconHolder.innerHTML = iconHtml;
                        }
                        const labelNode =
                                trendEl.querySelector("[data-admin-summary-trend-label]") || trendEl;
                        const formattedRaw =
                                trendData && typeof trendData.formatted === "string"
                                        ? trendData.formatted.trim()
                                        : "";
                        const suffixRaw =
                                trendData && typeof trendData.label === "string"
                                        ? trendData.label.trim()
                                        : "";
                        const fallback =
                                typeof fallbackLabel === "string" ? fallbackLabel.trim() : "";
                        const suffix = suffixRaw || fallback;
                        let formatted = formattedRaw;
                        if (!formatted && suffix) {
                                formatted = "0%";
                        }
                        const parts = [];
                        if (formatted) {
                                parts.push(formatted);
                        }
                        if (suffix) {
                                parts.push(suffix);
                        }
                        labelNode.textContent = parts.length ? parts.join(" ") : "—";
                }

                function stripYearSuffix(label) {
                        if (typeof label !== "string") {
                                return "";
                        }
                        return label.replace(/\s+\d{4}$/u, "").trim();
                }

                function updateAdminSummaryKpis(root, context = {}) {
                        if (!root) {
                                return;
                        }
                        const container = root.querySelector("[data-admin-summary-kpis]");
                        if (!container) {
                                return;
                        }
                        const contextKey =
                                context.key || root.dataset.context || ADMIN_SUMMARY_DEFAULT_CONTEXT;
                        const amountMap =
                                context.amountMap instanceof Map
                                        ? context.amountMap
                                        : normalizeSummaryAmounts(context.amounts || []);
                        const contextLabel =
                                context && typeof context.label === "string"
                                        ? context.label.trim()
                                        : "";
                        const monthNameRaw =
                                contextKey === "month" && context && typeof context.month_name === "string"
                                        ? context.month_name.trim()
                                        : "";
                        const monthName = monthNameRaw ? stripYearSuffix(monthNameRaw) : "";
                        const countValue =
                                typeof context.count === "number"
                                        ? context.count
                                        : 0;
                        const trends =
                                context && typeof context.trends === "object" && context.trends
                                        ? context.trends
                                        : {};
                        const fallbackTrendLabel =
                                contextKey === "year" ? "vs año ant." : "vs mes ant.";

                        const amountCard = container.querySelector(
                                '[data-admin-summary-kpi="amount"]'
                        );
                        if (amountCard) {
                                const labelEl = amountCard.querySelector(
                                        "[data-admin-summary-kpi-label]"
                                );
                                if (labelEl) {
                                        labelEl.textContent =
                                                contextKey === "year"
                                                        ? "Valor acumulado"
                                                        : monthName
                                                        ? `Acumulado ${monthName}`
                                                        : "Acumulado";
                                }
                                const valueEl = amountCard.querySelector(
                                        "[data-admin-summary-kpi-value]"
                                );
                                if (valueEl) {
                                        animateSummaryCurrency(
                                                valueEl,
                                                amountMap.get("activada") || 0,
                                                { duration: 420 }
                                        );
                                }
                                const trendEl = amountCard.querySelector(
                                        "[data-admin-summary-kpi-trend]"
                                );
                                if (trendEl) {
                                        applySummaryTrend(trendEl, trends.amount || null, fallbackTrendLabel);
                                }
                        }

                        const countCard = container.querySelector(
                                '[data-admin-summary-kpi="count"]'
                        );
                        if (countCard) {
                                const labelEl = countCard.querySelector(
                                        "[data-admin-summary-kpi-label]"
                                );
                                if (labelEl) {
                                        labelEl.textContent =
                                                contextKey === "year"
                                                        ? "Total garantías"
                                                        : monthName
                                                        ? `Garantías ${monthName}`
                                                        : "Garantías este mes";
                                }
                                const valueEl = countCard.querySelector(
                                        "[data-admin-summary-kpi-value]"
                                );
                                if (valueEl) {
                                        animateSummaryNumber(valueEl, countValue, { duration: 420 });
                                }
                                const trendEl = countCard.querySelector(
                                        "[data-admin-summary-kpi-trend]"
                                );
                                if (trendEl) {
                                        applySummaryTrend(trendEl, trends.count || null, fallbackTrendLabel);
                                }
                        }
                }

                function renderAdminSummaryStates(root, context = {}) {
                        const donut = root.querySelector("[data-admin-summary-donut]");
                        const legend = root.querySelector("[data-admin-summary-states]");
                        const totalEl = root.querySelector("[data-admin-summary-total]");
                        const labelEl = root.querySelector("[data-admin-summary-label]");
                        const items = normalizeSummaryStates((context && context.states) || []);
                        const amountMap = normalizeSummaryAmounts((context && context.amounts) || []);
                        const monthNameRaw =
                                context && typeof context.month_name === "string"
                                        ? context.month_name
                                        : "";
                        const monthName = monthNameRaw ? stripYearSuffix(monthNameRaw.trim()) : "";
                        const trendsData =
                                context && typeof context.trends === "object" && context.trends
                                        ? context.trends
                                        : {};
                        const total = items.reduce((sum, item) => sum + item.count, 0);
                        const totalCount =
                                typeof context.count === "number" ? context.count : total;

                        const defaultLabel = labelEl
                                ? labelEl.getAttribute("data-default-label") || "Total"
                                : "Total";
                        const contextLabelRaw =
                                context && typeof context.label === "string" ? context.label.trim() : "";
                        const contextLabel = contextLabelRaw ? stripYearSuffix(contextLabelRaw) : "";

                        if (labelEl) {
                                const effectiveLabel = contextLabel || defaultLabel;
                                labelEl.textContent = effectiveLabel;
                                labelEl.dataset.contextLabel = effectiveLabel;
                                labelEl.classList.remove("highlighted");
                                labelEl.style.color = "";
                        }

                        if (totalEl) {
                                if (typeof totalEl.dataset.value === "undefined") {
                                        totalEl.dataset.value = "0";
                                }
                                totalEl.dataset.value = String(totalCount);
                                totalEl.textContent = formatIntegerValue(totalCount);
                                totalEl.classList.remove("highlighted");
                                totalEl.style.color = "";
                        }

                        if (donut) {
                                configureDonutSegments(donut, items);
                        }

                        updateAdminSummaryKpis(root, {
                                key: root.dataset.context || ADMIN_SUMMARY_DEFAULT_CONTEXT,
                                label: contextLabel,
                                count: totalCount,
                                amountMap,
                                month_name: monthName,
                                trends: trendsData,
                        });

                        if (!legend) {
                                return;
                        }
                        legend.innerHTML = "";
                        if (items.length === 0) {
                                const item = document.createElement("div");
                                item.className = "legend-item legend-item--empty";
                                item.textContent = "Sin datos disponibles";
                                legend.appendChild(item);
                                root._adminSummaryContextData = {
                                        items,
                                        total: totalCount,
                                        count: totalCount,
                                        label: contextLabel || defaultLabel,
                                        amounts: amountMap,
                                };
                                root._adminSummaryHighlight = null;
                                root._adminSummaryLockedHighlight = null;
                                applyLegendHighlight(root, null);
                                return;
                        }
                        items.forEach((item) => {
                                const percent = total > 0 ? Math.round((item.count / total) * 100) : 0;
                                const palette = ADMIN_SUMMARY_STATE_COLORS[item.value] || {};
                                const node = document.createElement("div");
                                node.className = "legend-item";
                                if (item.value) {
                                        node.dataset.state = item.value;
                                }
                                const color = palette.color || "var(--admin-summary-state-activada)";
                                node.innerHTML = `
                <span class="legend-color" style="background-color: ${color}"></span>
                <div class="legend-info">
                    <span class="legend-label">${escapeHtml(item.label)}</span>
                    <span class="legend-value">${formatIntegerValue(percent)}%</span>
                </div>
            `;
                                node.tabIndex = 0;
                                legend.appendChild(node);
                        });

                        const legendItems = legend.querySelectorAll(".legend-item");
                        legendItems.forEach((node) => {
                                const handleEnter = () => {
                                        const state = node.dataset.state || null;
                                        const locked = root._adminSummaryLockedHighlight || null;
                                        if (locked && locked !== state) {
                                                return;
                                        }
                                        applyLegendHighlight(root, state);
                                };
                                const handleLeave = () => {
                                        const locked = root._adminSummaryLockedHighlight || null;
                                        applyLegendHighlight(root, locked);
                                };
                                node.addEventListener("mouseenter", handleEnter);
                                node.addEventListener("focus", handleEnter);
                                node.addEventListener("mouseleave", handleLeave);
                                node.addEventListener("blur", handleLeave);
                                node.addEventListener("click", (event) => {
                                        event.preventDefault();
                                        const state = node.dataset.state || null;
                                        const locked = root._adminSummaryLockedHighlight || null;
                                        const next = locked === state ? null : state;
                                        root._adminSummaryLockedHighlight = sanitizeLegendState(root, next);
                                        applyLegendHighlight(root, root._adminSummaryLockedHighlight || null);
                                });
                                node.addEventListener("keydown", (event) => {
                                        if (event.key === "Enter" || event.key === " ") {
                                                event.preventDefault();
                                                node.click();
                                        }
                                });
                        });
                        if (!legend._adminSummaryLeaveBound) {
                                legend.addEventListener("mouseleave", () => {
                                        const locked = root._adminSummaryLockedHighlight || null;
                                        applyLegendHighlight(root, locked);
                                });
                                legend._adminSummaryLeaveBound = true;
                        }

                        root._adminSummaryContextData = {
                                items,
                                total: totalCount,
                                count: totalCount,
                                label: contextLabel || defaultLabel,
                                amounts: amountMap,
                        };
                        root._adminSummaryLockedHighlight = sanitizeLegendState(
                                root,
                                root._adminSummaryLockedHighlight || null
                        );
                        root._adminSummaryHighlight = null;
                        applyLegendHighlight(root, root._adminSummaryLockedHighlight || null);
                }

                function applyLegendHighlight(root, stateValue = null) {
                        if (!root) {
                                return;
                        }
                        const donut = root.querySelector("[data-admin-summary-donut]");
                        const totalEl = root.querySelector("[data-admin-summary-total]");
                        const labelEl = root.querySelector("[data-admin-summary-label]");
                        const legend = root.querySelector("[data-admin-summary-states]");
                        const contextData = root._adminSummaryContextData || {};
                        const items = Array.isArray(contextData.items) ? contextData.items : [];
                        const total = typeof contextData.total === "number"
                                ? contextData.total
                                : items.reduce((sum, item) => sum + (item.count || 0), 0);
                        const defaultLabel = labelEl
                                ? labelEl.getAttribute("data-default-label") || "Total"
                                : "Total";
                        const baseLabel = contextData.label || defaultLabel;
                        const sanitizedValue = sanitizeLegendState(root, stateValue);
                        const highlightItem = sanitizedValue
                                ? items.find((entry) => entry.value === sanitizedValue)
                                : null;
                        const effectiveHighlight = highlightItem && highlightItem.value ? highlightItem.value : null;

                        root._adminSummaryHighlight = effectiveHighlight;
                        if (!effectiveHighlight) {
                                root._adminSummaryLockedHighlight = sanitizeLegendState(
                                        root,
                                        root._adminSummaryLockedHighlight || null
                                );
                        }

                        if (legend) {
                                const nodes = legend.querySelectorAll(".legend-item");
                                nodes.forEach((node) => {
                                        const isActive = Boolean(effectiveHighlight) && node.dataset.state === effectiveHighlight;
                                        node.classList.toggle("is-active", isActive);
                                        node.classList.toggle("is-highlighted", isActive);
                                        node.classList.toggle("highlighted", isActive);
                                });
                        }
                        root.classList.toggle("is-dimmed", Boolean(effectiveHighlight));
                        setVisualizationHighlight(root, effectiveHighlight);

                        if (totalEl) {
                                const targetTotal = effectiveHighlight && highlightItem
                                        ? highlightItem.count || 0
                                        : total;
                                animateSummaryNumber(totalEl, targetTotal, {
                                        duration: effectiveHighlight ? 220 : 320,
                                });
                                if (effectiveHighlight && highlightItem) {
                                        const palette = ADMIN_SUMMARY_STATE_COLORS[highlightItem.value] || {};
                                        totalEl.classList.add("highlighted");
                                        totalEl.style.color = palette.color || "";
                                } else {
                                        totalEl.classList.remove("highlighted");
                                        totalEl.style.color = "";
                                }
                        }

                        if (labelEl) {
                                if (effectiveHighlight && highlightItem) {
                                        const palette = ADMIN_SUMMARY_STATE_COLORS[highlightItem.value] || {};
                                        labelEl.textContent = highlightItem.label || baseLabel;
                                        labelEl.classList.add("highlighted");
                                        labelEl.style.color = palette.color || "";
                                } else {
                                        labelEl.textContent = baseLabel;
                                        labelEl.classList.remove("highlighted");
                                        labelEl.style.color = "";
                                }
                        }

                        if (!donut) {
                                return;
                        }
                        if (items.length === 0 || total === 0) {
                                resetDonutChart(donut);
                                return;
                        }
                        updateDonutColors(donut, effectiveHighlight);
                }

                function getSummaryContextData(data, contextKey) {
                        if (!data || typeof data !== "object") {
                                return { states: [] };
                        }
                        const contexts = data.contexts && typeof data.contexts === "object" ? data.contexts : {};
                        if (contextKey && contexts[contextKey]) {
                                return contexts[contextKey];
                        }
                        if (contexts[ADMIN_SUMMARY_DEFAULT_CONTEXT]) {
                                return contexts[ADMIN_SUMMARY_DEFAULT_CONTEXT];
                        }
                        const firstContext = Object.values(contexts)[0];
                        if (firstContext) {
                                return firstContext;
                        }
                        return {
                                states: data.states || [],
                                label: "",
                                count: data.totals && data.totals.count ? data.totals.count : 0,
                        };
                }

                function updateAdminSummaryContext(root, contextKey) {
                        if (!root) {
                                return;
                        }
                        const data = root._adminSummaryData || {};
                        const contextData = getSummaryContextData(data, contextKey);
                        const effectiveKey = contextKey && contextData ? contextKey : ADMIN_SUMMARY_DEFAULT_CONTEXT;
                        root.dataset.context = effectiveKey;

                        const toggles = root.querySelectorAll("[data-admin-summary-context-toggle]");
                        toggles.forEach((toggle) => {
                                const value = toggle.value || toggle.getAttribute("value");
                                toggle.checked = value === effectiveKey;
                        });

                        renderAdminSummaryStates(root, contextData || {});
                }

                function renderAdminSummaryActions(root, pending = {}) {
                        const list = root.querySelector("[data-admin-summary-actions]");
                        if (!list) {
                                return;
                        }
                        list.innerHTML = "";
                        const visibleActions = ADMIN_SUMMARY_ACTIONS.filter((action) => {
                                if (action.requiresFullAccess && !canSeeVerifyCollectStates) {
                                        return false;
                                }
                                return true;
                        });

                        visibleActions.forEach((action) => {
                                const entry = pending[action.key] || {};
                                const amount = normalizeToFloat(entry.amount || 0);
                                const count = normalizeToInt(entry.count || 0);
                                const item = document.createElement("li");
                                item.className = "action-item";
                                item.dataset.action = action.key;
                                if (action.filterValue) {
                                        item.dataset.filterValue = action.filterValue;
                                }
                                if (action.accent) {
                                        item.style.setProperty("--action-accent", action.accent);
                                }

                                const amountLabel = formatCurrencyValue(amount);
                                const countLabel = formatGuaranteeCount(count);
                                const showAmount = action.showAmount !== false;
                                const subtitle = showAmount
                                        ? (count > 0 ? `${countLabel} · ${amountLabel}` : "Sin pendientes")
                                        : countLabel;

                                item.innerHTML = `
                <span class="action-icon" aria-hidden="true" style="background-color: var(--action-accent)">
                    ${action.icon}
                </span>
                <div class="action-details">
                    <span class="action-label">${escapeHtml(action.label)}</span>
                    <span class="action-sublabel">${escapeHtml(subtitle)}</span>
                </div>
                <span class="action-cta" aria-hidden="true">${ADMIN_SUMMARY_ACTION_ARROW_ICON}</span>
            `;

                                if (count === 0) {
                                        item.classList.add("is-empty");
                                }

                                item.addEventListener("click", () => {
                                        if (!action.filterValue || !estadoSelect) {
                                                return;
                                        }
                                        if (estadoSelect.value === action.filterValue) {
                                                applyFilters();
                                                return;
                                        }
                                        estadoSelect.value = action.filterValue;
                                        selectedEstado = action.filterValue;
                                        updateResetVisibility();
                                        applyFilters();
                                });

                                list.appendChild(item);
                        });
                }

                function renderAdminSummary(root, data = {}) {
                        if (!root) {
                                return;
                        }
                        root.classList.remove("is-loading");
                        root.dataset.loading = "0";
                        root.dataset.loaded = "1";
                        root._adminSummaryData = data || {};

                        const currentContext = root.dataset.context || ADMIN_SUMMARY_DEFAULT_CONTEXT;
                        updateAdminSummaryContext(root, currentContext);
                        renderAdminSummaryActions(root, data.pending || {});

                        const error = root.querySelector("[data-admin-summary-error]");
                        if (error) {
                                error.hidden = true;
                        }
                }

                function fetchAdminSummary(force = false) {
                        if (!canViewAdminSummary) {
                                return Promise.resolve({});
                        }
                        if (force) {
                                adminSummaryCache = null;
                        } else {
                                if (adminSummaryCache) {
                                        return Promise.resolve(adminSummaryCache);
                                }
                                if (adminSummaryPromise) {
                                        return adminSummaryPromise;
                                }
                        }

                        const headers = { Accept: "application/json" };
                        if (restNonce) {
                                headers["X-WP-Nonce"] = restNonce;
                        }

                        const request = fetch(`${restRoot}go/v1/guarantees/summary`, { headers })
                                .then((response) => {
                                        if (!response.ok) {
                                                throw new Error(
                                                        `Resumen de garantías no disponible (${response.status || ""})`
                                                );
                                        }
                                        return response.json();
                                })
                                .then((payload) => {
                                        adminSummaryCache = payload;
                                        return payload;
                                })
                                .finally(() => {
                                        adminSummaryPromise = null;
                                });

                        if (!force) {
                                adminSummaryPromise = request;
                        }

                        return request;
                }

                function loadAdminSummary(root, options = {}) {
                        if (!root) {
                                return;
                        }
                        const force = Boolean(options.force);
                        if (!force && root.dataset.loaded === "1" && adminSummaryCache) {
                                renderAdminSummary(root, adminSummaryCache);
                                return;
                        }
                        setAdminSummaryLoading(root, true);
                        fetchAdminSummary(force)
                                .then((payload) => {
                                        renderAdminSummary(root, payload || {});
                                })
                                .catch((error) => {
                                        console.error(error);
                                        root.dataset.loaded = "0";
                                        root.classList.remove("is-loading");
                                        root.dataset.loading = "0";
                                        showAdminSummaryError(root);
                                });
                }

                function initializeAdminSummary(panel) {
                        if (!canViewAdminSummary || !panel) {
                                return;
                        }
                        const root = panel.querySelector("[data-admin-summary]");
                        if (!root) {
                                return;
                        }
                        if (root.dataset.initialized === "1" && root.dataset.loaded === "1" && adminSummaryCache) {
                                renderAdminSummary(root, adminSummaryCache);
                                return;
                        }
                        const toggles = root.querySelectorAll("[data-admin-summary-context-toggle]");
                        toggles.forEach((toggle) => {
                                toggle.addEventListener("change", () => {
                                        if (!toggle.checked) {
                                                return;
                                        }
                                        updateAdminSummaryContext(root, toggle.value || toggle.getAttribute("value"));
                                });
                        });
                        const helpButton = root.querySelector("[data-admin-summary-help]");
                        if (helpButton) {
                                helpButton.addEventListener("click", () => {
                                        const event = new CustomEvent("go:summary-help", {
                                                bubbles: true,
                                                detail: { source: "admin-summary" },
                                        });
                                        root.dispatchEvent(event);
                                });
                        }
                        const preloadNode = root.querySelector("[data-admin-summary-preload]");
                        let preloadedData = null;
                        if (preloadNode) {
                                try {
                                        preloadedData = JSON.parse(preloadNode.textContent || "{}");
                                } catch (error) {
                                        console.error("No se pudo analizar el resumen precargado", error);
                                }
                                preloadNode.remove();
                        }
                        root.dataset.initialized = "1";
                        if (!root.dataset.context) {
                                root.dataset.context = ADMIN_SUMMARY_DEFAULT_CONTEXT;
                        }
                        if (preloadedData) {
                                adminSummaryCache = preloadedData;
                                renderAdminSummary(root, preloadedData || {});
                        } else {
                                loadAdminSummary(root);
                        }
                }

                function renderEmptyDetail(mode = "awaiting", options = {}) {
                        if (mode === "loading") {
                                const rawPlate =
                                        typeof options.plate === "string" ? options.plate.trim() : "";
                                const safePlate = escapeHtml(rawPlate);
                                const showHeader = Boolean(options.showHeader);
                                const title = safePlate ? `Garantía ${safePlate}` : "Cargando garantía";
                                const message = options.message
                                        ? String(options.message)
                                        : safePlate
                                        ? `Cargando datos de la garantía ${safePlate}`
                                        : "Cargando datos de la garantía…";
                                const headerHtml = showHeader
                                        ? `<div class="guarantee-detail__header guarantee-detail__header--loading">
                                                <h2 class="guarantee-detail__title">${title}</h2>
                                        </div>`
                                        : "";
                                return `
                                <div class="guarantee-detail__loading" data-empty-detail data-empty-mode="loading">
                                        ${headerHtml}
                                        <div class="guarantee-detail__loading-body" role="status" aria-live="polite">
                                                <span class="guarantee-detail__loading-spinner" aria-hidden="true"></span>
                                                <p class="guarantee-detail__loading-text">${message}</p>
                                        </div>
                                </div>
                        `;
                        }
                        const templateKey = mode === "no-results" ? "no-results" : "awaiting";
                        if (emptyTemplateMap[templateKey]) {
                                return emptyTemplateMap[templateKey];
                        }
                        if (templateKey === "no-results") {
                                const safeUrl =
                                        typeof newGuaranteeUrl === "string" && newGuaranteeUrl
                                                ? newGuaranteeUrl
                                                : "#";
                                return `
                                        <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="no-results">
                                                <h3 class="guarantee-detail__title">Añade tu primera garantía</h3>
                                                <p><a class="guarantee-detail__cta-link" href="${safeUrl}">Contrata tu primera garantía</a> para ver aquí todos sus detalles.</p>
                                        </div>
                                `;
                        }
                        const arrowHtml = arrowLeftIcon
                                ? `<span class="guarantee-detail__hint-arrow" aria-hidden="true">${arrowLeftIcon}</span>`
                                : '<span class="guarantee-detail__hint-arrow" aria-hidden="true"></span>';
                        return `
                                <div class="guarantee-detail__empty" data-empty-detail data-empty-mode="awaiting">
                                        <h3 class="guarantee-detail__title">Consulta los detalles de tus garantías</h3>
                                        <p class="guarantee-detail__hint">
                                                ${arrowHtml}
                                                Haz clic en una garantía para consultar la información completa.
                                        </p>
                                </div>
                        `;
                }

                // Nuevo: mostrar el empty panel como los de detalle, solo si no está ya activo
                function setEmptyDetailPanel(direction = "forward", mode = "awaiting", options = {}) {
                        clearActiveCountdown();
                        const currentActive = activePanel;
                        const nextPanel = activePanel === panel1 ? panel2 : panel1;
                        const isDesktop = isDesktopView();
                        const normalizedMode =
                                mode === "no-results"
                                        ? "no-results"
                                        : mode === "loading"
                                        ? "loading"
                                        : "awaiting";
                        let renderOptions = options;
                        if (normalizedMode === "loading") {
                                renderOptions = Object.assign({}, options);
                                if (typeof renderOptions.showHeader === "undefined") {
                                        renderOptions.showHeader = !isDesktop;
                                }
                        }
                        const activeHasSameMode =
                                normalizedMode === currentEmptyMode &&
                                currentActive &&
                                currentActive.classList.contains("active") &&
                                currentActive.querySelector(
                                        `[data-empty-detail][data-empty-mode="${normalizedMode}"]`
                                );
                        const nextHasSameMode =
                                normalizedMode === currentEmptyMode &&
                                nextPanel.classList.contains("active") &&
                                nextPanel.querySelector(
                                        `[data-empty-detail][data-empty-mode="${normalizedMode}"]`
                                );
                        // Si el empty ya está visible en el modo solicitado, no repetir animación
                        if (activeHasSameMode || nextHasSameMode) {
                                return;
                        }
                        nextPanel.innerHTML = renderEmptyDetail(normalizedMode, renderOptions);
                        const emptyNode = nextPanel.querySelector("[data-empty-detail]");
                        if (emptyNode) {
                                emptyNode.setAttribute("data-empty-mode", normalizedMode);
                        }
                        nextPanel.dataset.loadedId = "";
                        nextPanel.dataset.matricula = "";
                        syncPdfModalDocs(nextPanel);
                        if (normalizedMode === "awaiting") {
                                initializeAdminSummary(nextPanel);
                        }
                        activePanel = nextPanel;
                        inactivePanel = currentActive;
                        if (currentActive) {
                                currentActive.classList.remove("slide-in-left", "slide-in-right");
                        }
                        if (isDesktop) {
                                if (currentActive) {
                                        currentActive.classList.add(
                                                direction === "forward"
                                                        ? "slide-out-left"
                                                        : "slide-out-right"
                                        );
                                        currentActive.classList.remove("active");
                                        currentActive.addEventListener(
                                                "animationend",
                                                () => {
                                                        currentActive.classList.remove(
                                                                "slide-out-left",
                                                                "slide-out-right"
                                                        );
                                                        nextPanel.classList.remove(
                                                                "slide-in-left",
                                                                "slide-in-right"
                                                        );
                                                },
                                                { once: true }
                                        );
                                }
                                nextPanel.classList.add(
                                        direction === "forward" ? "slide-in-right" : "slide-in-left"
                                );
                                nextPanel.classList.add("active");
                        } else {
                                if (currentActive) {
                                        currentActive.classList.remove("slide-out-left", "slide-out-right");
                                        currentActive.classList.remove("active");
                                }
                                nextPanel.classList.remove("slide-in-left", "slide-in-right");
                                nextPanel.classList.add("active");
                        }
                        lastEmptyPanel = nextPanel;
                        currentEmptyMode = normalizedMode;
                        if (!isDesktopView()) {
                                if (normalizedMode === "loading") {
                                        openMobileDetail({ focus: false });
                                } else {
                                        closeMobileDetail({ focus: false, restoreFocus: false });
                                }
                        }
                }

                function clearSelectionAndDetail(options = {}) {
                        const preserveQuery =
                                typeof options.preserveQuery !== "undefined"
                                        ? Boolean(options.preserveQuery)
                                        : false;
                        const restoreFocus = options.restoreFocus !== false;
                        const lastRow = prevSelectedRow && prevSelectedRow.isConnected
                                ? prevSelectedRow
                                : null;
                        const requestedMode =
                                typeof options.emptyMode === "string" ? options.emptyMode : "";
                        const shouldShowLoading =
                                requestedMode === "" && pendingMatSelection && initialMatQuery;
                        const desiredMode =
                                requestedMode !== ""
                                        ? requestedMode
                                        : shouldShowLoading
                                        ? "loading"
                                        : "awaiting";
                        const desktop = isDesktopView();
                        let loadingPlate = initialMatQuery;
                        if (desiredMode === "loading" && typeof options.plate === "string") {
                                const candidate = options.plate.trim();
                                if (candidate) {
                                        loadingPlate = candidate;
                                }
                        }
                        const emptyOptions =
                                desiredMode === "loading"
                                        ? {
                                                  plate: loadingPlate,
                                                  showHeader:
                                                          typeof options.showHeader !== "undefined"
                                                                  ? Boolean(options.showHeader)
                                                                  : !desktop,
                                          }
                                        : {};
                        const rows = Array.from(
                                document.querySelectorAll(".guarantees-table__row")
                        );
                        rows.forEach((r) => r.classList.remove("selected"));
                        prevSelectedRow = null;
                        prevIdx = null;
                        setMobileSummaryOpen(false);
                        setMobileEmptyHidden(false);
                        const shouldKeepQuery =
                                pendingMatSelection ||
                                Boolean(initialMatQuery) ||
                                (preserveQuery && desktop);

                        if (!shouldKeepQuery) {
                                history.replaceState(null, "", window.location.pathname);
                                pendingMatSelection = false;
                                initialMatQuery = "";
                        }
                        setEmptyDetailPanel("forward", desiredMode, emptyOptions); // Mantén la dirección como prefieras
                        if (!desktop) {
                                if (desiredMode === "loading") {
                                        lastDetailTrigger = lastRow || lastDetailTrigger;
                                        openMobileDetail({ focus: false });
                                } else {
                                        lastDetailTrigger = lastRow || lastDetailTrigger;
                                        closeMobileDetail({ focus: false, restoreFocus });
                                }
                        }
                }

                function setResultMessage(msg = "") {
                        if (!resultMessage) {
                                return;
                        }
                        const message = typeof msg === "string" ? msg : "";
                        resultMessage.innerHTML = message;
                        const shouldShow = message.trim().length > 0;
                        resultMessage.hidden = !shouldShow;
                        resultMessage.setAttribute(
                                "aria-hidden",
                                shouldShow ? "false" : "true"
                        );
                }

                function getRequestFilters(overrides = {}) {
                        const search =
                                typeof overrides.search === "string"
                                        ? overrides.search
                                        : searchQuery;
                        const estado =
                                typeof overrides.estado === "string"
                                        ? overrides.estado
                                        : selectedEstado;
                        const plan =
                                typeof overrides.plan !== "undefined"
                                        ? overrides.plan
                                        : selectedPlan;
                        const canal =
                                typeof overrides.canal === "string"
                                        ? overrides.canal
                                        : selectedCanal;
                        const concesionario =
                                typeof overrides.concesionario !== "undefined"
                                        ? overrides.concesionario
                                        : selectedConcesionario;
                        const vendorType =
                                typeof overrides.vendorType === "string"
                                        ? overrides.vendorType
                                        : selectedVendorType;
                        const paymentMethod =
                                typeof overrides.paymentMethod === "string"
                                        ? overrides.paymentMethod
                                        : selectedPaymentMethod;
                        const commercial =
                                typeof overrides.commercial === "string"
                                        ? overrides.commercial
                                        : overrides.commercial != null
                                        ? String(overrides.commercial)
                                        : selectedCommercial;
                        const orderBy =
                                typeof overrides.orderBy === "string" && overrides.orderBy
                                        ? overrides.orderBy
                                        : selectedOrderBy;
                        const orderDirection =
                                typeof overrides.orderDirection === "string" && overrides.orderDirection
                                        ? overrides.orderDirection
                                        : selectedOrderDirection;
                        const overrideYear = hasPeriodFilters
                                ? typeof overrides.year !== "undefined"
                                        ? String(overrides.year)
                                        : selectedYear
                                : "";
                        const overrideMonthFrom = hasPeriodFilters
                                ? typeof overrides.monthFrom !== "undefined"
                                        ? String(overrides.monthFrom)
                                        : selectedMonthFrom
                                : "";
                        const overrideMonthTo = hasPeriodFilters
                                ? typeof overrides.monthTo !== "undefined"
                                        ? String(overrides.monthTo)
                                        : selectedMonthTo
                                : "";
                        const [normalizedYear, normalizedMonthFrom, normalizedMonthTo] =
                                getPeriodCacheKeyParts({
                                        year: overrideYear,
                                        monthFrom: overrideMonthFrom,
                                        monthTo: overrideMonthTo,
                                });

                        const cacheKey = buildListCacheKey(
                                search,
                                estado,
                                plan,
                                canal,
                                concesionario,
                                vendorType,
                                paymentMethod,
                                orderBy,
                                orderDirection,
                                commercial,
                                normalizedYear,
                                normalizedMonthFrom,
                                normalizedMonthTo
                        );

                        return {
                                search,
                                estado,
                                plan,
                                canal,
                                concesionario,
                                vendorType,
                                paymentMethod,
                                commercial,
                                orderBy,
                                orderDirection,
                                year: normalizedYear,
                                monthFrom: normalizedMonthFrom,
                                monthTo: normalizedMonthTo,
                                cacheKey,
                        };
                }

                function appendFiltersToParams(params, filters) {
                        if (!params || !filters) {
                                return;
                        }
                        if (filters.search) {
                                params.append("search", filters.search);
                        }
                        if (filters.estado) {
                                params.append("estado", filters.estado);
                        }
                        if (filters.plan) {
                                params.append("plan", filters.plan);
                        }
                        if (filters.canal) {
                                params.append("canal", filters.canal);
                        }
                        if (filters.concesionario) {
                                params.append("concesionario", filters.concesionario);
                        }
                        if (filters.vendorType) {
                                params.append("vendor_type", filters.vendorType);
                        }
                        if (filters.paymentMethod) {
                                params.append("payment_method", filters.paymentMethod);
                        }
                        if (filters.orderBy) {
                                params.append("order_by", filters.orderBy);
                        }
                        if (filters.orderDirection) {
                                params.append("order", filters.orderDirection);
                        }
                        if (filters.commercial) {
                                params.append("commercial", filters.commercial);
                        }
                        if (filters.year) {
                                params.append("year", filters.year);
                                if (filters.monthFrom) {
                                        params.append("month_from", filters.monthFrom);
                                }
                                if (filters.monthTo) {
                                        params.append("month_to", filters.monthTo);
                                }
                        }
                }

                async function loadPage(page = 1, options = {}) {
                        if (isLoading || (!hasMore && page !== 1)) return;
                        isLoading = true;
                        if (scrollEnd) {
                                scrollEnd.hidden = false;
                        }
                        let spinnerToken = null;
                        if (typeof options.spinnerToken === "number") {
                                spinnerToken = setSpinnerVisible(true, options.spinnerToken);
                        } else {
                                spinnerToken = setSpinnerVisible(true);
                        }
                        let previousRowCount = tbody
                                ? tbody.querySelectorAll(".guarantees-table__row").length
                                : 0;
                        let appendedRows = 0;
                        const requestFilters = getRequestFilters(options);
                        const {
                                search,
                                estado,
                                plan,
                                canal,
                                concesionario,
                                vendorType,
                                paymentMethod,
                                commercial,
                                orderBy,
                                orderDirection,
                                year,
                                monthFrom,
                                monthTo,
                                cacheKey,
                        } = requestFilters;
                        const normalizedYear = year || "";
                        const normalizedMonthFrom = monthFrom || "";
                        const normalizedMonthTo = monthTo || "";
                        try {
                                if (currentListAbort) currentListAbort.abort();
                                currentListAbort = new AbortController();
                                const params = new URLSearchParams({
                                        page,
                                        per_page: perPage,
                                });
                                appendFiltersToParams(params, requestFilters);
                                let url = `${restRoot}go/v1/guarantees?${params.toString()}`;
                                const res = await fetch(url, {
                                        headers: { "X-WP-Nonce": restNonce },
                                        signal: currentListAbort.signal,
                                });
				if (!res.ok) throw `HTTP ${res.status}`;
				totalPosts = +res.headers.get("X-WP-Total") || 0;
				totalPages = +res.headers.get("X-WP-TotalPages") || 1;
                                const { data } = await res.json();

                                const esNuevaBusqueda = page === 1;
                                const now = Date.now();
                                if (esNuevaBusqueda) {
                                        listCache.set(cacheKey, {
                                                data: data.slice(),
                                                totalPages,
                                                totalPosts,
                                                fetchedAt: now,
                                        });
                                } else if (listCache.has(cacheKey)) {
                                        const cache = listCache.get(cacheKey);
                                        cache.data.push(...data);
                                        cache.totalPages = totalPages;
                                        cache.totalPosts = totalPosts;
                                        cache.fetchedAt = now;
                                }
                                persistListCacheSnapshot(listCache);
                                if (esNuevaBusqueda) {
                                        setResultMessage("");
                                        tbody.innerHTML = "";
                                        resetMobileCards();
                                        previousRowCount = 0;
                                        clearSelectionAndDetail({ preserveQuery: pendingMatSelection }); // Limpiar selección SIEMPRE que se cambia el listado (así evitas seleccionados fantasmas)
					if (data.length > 0) {
						listContainer.scrollTop = 0;
						// Solo guardamos resultados válidos si búsqueda >= 3 caracteres y pocos resultados
						if (search.length >= 3 && data.length > 0 && data.length <= 20) {
							lastValidQuery = search;
							lastValidResults = data.slice();
						}
					}
				}
				currentPage = page;
				hasMore = currentPage < totalPages;

                                if (data.length > 0) {
                                        for (const item of data) {
                                                ensureDetailPreloaded(item);
                                                if (loadedIds.has(item.id)) continue;
                                                tbody.appendChild(renderRow(item));
                                                loadedIds.add(item.id);
                                                appendedRows += 1;
                                        }
                                        await trySelectInitialMatricula();
                                        setResultMessage("");
                                        // AUTODETAIL: Si hay **exactamente 1 resultado**, mostrar el panel sin click
                                        if (data.length === 1 && search && search.length > 0) {
                                                const row = tbody.querySelector(".guarantees-table__row");
                                                if (row && !row.classList.contains("selected")) {
                                                        try {
                                                                await activateRow(row, { updateHistory: false });
                                                        } catch (error) {
                                                                console.error(
                                                                        "❌ Error al activar la garantía automática:",
                                                                        error
                                                                );
                                                        }
                                                }
                                        }
				} else {
					// Solo mostramos resultados previos si búsqueda >= 3 caracteres y pocos resultados
					if (
						esNuevaBusqueda &&
						search.length >= 3 &&
						lastValidResults.length > 0 &&
						lastValidQuery.length >= 3 &&
						lastValidResults.length <= 20 &&
						search.length > 0 // <= SOLO si hay búsqueda, no si está vacío
						
					) {
                                                for (const item of lastValidResults) {
                                                        tbody.appendChild(renderRow(item));
                                                }
						setResultMessage(
							`No se han encontrado garantías para <strong>"${search}"</strong>. Mostrando resultados de <strong>"${lastValidQuery}"</strong>.`
						);
                                        } else if (search && page === 1) {
                                                setResultMessage(
                                                        `No se han encontrado garantías para <strong>"${search}"</strong>.`
                                                );
                                        } else if (esNuevaBusqueda && !hasActiveFilters()) {
                                                const emptyRow = renderEmptyRow();
                                                if (emptyRow) {
                                                        tbody.appendChild(emptyRow);
                                                }
                                                setResultMessage("");
                                                setEmptyDetailPanel("forward", "no-results");
                                        } else if (esNuevaBusqueda) {
                                                const emptyRow = renderEmptyRow();
                                                if (emptyRow) {
                                                        tbody.appendChild(emptyRow);
                                                }
                                                setResultMessage(
                                                        "No se han encontrado garantías con los filtros seleccionados."
                                                );
                                        }
                                }
                        } catch (err) {
                                const isAbort =
                                        err && typeof err === "object" && err.name === "AbortError";
                                if (isAbort) {
                                        // Petición cancelada deliberadamente: no mostramos error.
                                } else {
                                        console.error("❌ Error en loadPage:", err);
                                        setResultMessage(
                                                "No hemos podido cargar las garantías. Vuelve a intentarlo en unos segundos."
                                        );
                                }
                        } finally {
                                isLoading = false;
                                finalizeSpinnerVisibility(
                                        previousRowCount,
                                        appendedRows,
                                        spinnerToken
                                );
                        }
                }

                function refreshSelectedRowIndex() {
                        if (!tbody || !prevSelectedRow || !prevSelectedRow.isConnected) {
                                prevSelectedRow = null;
                                prevIdx = null;
                                return;
                        }
                        const rows = Array.from(
                                tbody.querySelectorAll(".guarantees-table__row")
                        );
                        const idx = rows.indexOf(prevSelectedRow);
                        if (idx === -1) {
                                prevSelectedRow = null;
                                prevIdx = null;
                                return;
                        }
                        prevIdx = idx;
                        lastDetailTrigger = prevSelectedRow;
                }

                function insertRealtimeRows(items = []) {
                        if (!tbody || !Array.isArray(items) || items.length === 0) {
                                return [];
                        }
                        const rowsById = new Map();
                        tbody.querySelectorAll(".guarantees-table__row").forEach((row) => {
                                const rowId = row.dataset.id ? String(row.dataset.id) : "";
                                if (rowId) {
                                        rowsById.set(rowId, row);
                                }
                        });
                        const selectedId =
                                prevSelectedRow && prevSelectedRow.dataset
                                        ? prevSelectedRow.dataset.id || null
                                        : null;
                        const fragment = document.createDocumentFragment();
                        const insertedIds = [];
                        let processed = 0;

                        for (const item of items) {
                                if (!item || !Object.prototype.hasOwnProperty.call(item, "id")) {
                                        continue;
                                }
                                const normalizedId = String(item.id);
                                if (!normalizedId) {
                                        continue;
                                }
                                processed += 1;
                                ensureDetailPreloaded(item);
                                const existingRow = rowsById.get(normalizedId) || null;
                                const wasSelected = Boolean(
                                        (existingRow && existingRow.classList.contains("selected")) ||
                                                (selectedId && normalizedId === selectedId)
                                );
                                const hadRealtime = realtimeHighlightEntries.has(normalizedId);
                                if (existingRow) {
                                        existingRow.remove();
                                        rowsById.delete(normalizedId);
                                }
                                const row = renderRow(item, { prepend: true });
                                if (wasSelected) {
                                        row.classList.add("selected");
                                        prevSelectedRow = row;
                                        lastDetailTrigger = row;
                                }
                                if (hadRealtime) {
                                        row.classList.add("guarantees-table__row--is-new");
                                        ensureRowRealtimeBadge(row, true);
                                        const card = mobileCardsMap.get(normalizedId);
                                        if (card) {
                                                card.classList.add("guarantee-card--is-new");
                                                ensureCardRealtimeBadge(card, true);
                                        }
                                } else {
                                        ensureRowRealtimeBadge(row, false);
                                        const card = mobileCardsMap.get(normalizedId);
                                        if (card) {
                                                ensureCardRealtimeBadge(card, false);
                                        }
                                }
                                fragment.appendChild(row);
                                if (!existingRow && !loadedIds.has(normalizedId)) {
                                        insertedIds.push(normalizedId);
                                }
                                loadedIds.add(normalizedId);
                        }

                        if (!processed) {
                                return [];
                        }

                        const emptyRow = tbody.querySelector(".guarantees-table__empty-row");
                        if (emptyRow) {
                                emptyRow.remove();
                        }
                        setResultMessage("");

                        const anchor = tbody.firstElementChild;
                        if (anchor) {
                                tbody.insertBefore(fragment, anchor);
                        } else {
                                tbody.appendChild(fragment);
                        }

                        insertedIds.forEach((id) => {
                                markEntryAsRealtime(id);
                        });

                        refreshSelectedRowIndex();
                        return insertedIds;
                }

                function clearRealtimeEntry(entryId) {
                        const id = String(entryId || "");
                        if (!id) {
                                return;
                        }
                        const row = findRowById(id);
                        if (row) {
                                row.classList.remove("guarantees-table__row--is-new");
                                ensureRowRealtimeBadge(row, false);
                        }
                        const card = mobileCardsMap.get(id);
                        if (card) {
                                card.classList.remove("guarantee-card--is-new");
                                ensureCardRealtimeBadge(card, false);
                        }
                        realtimeHighlightEntries.delete(id);
                }

                function markEntryAsRealtime(entryId, { rowElement = null } = {}) {
                        const id = String(entryId || "");
                        if (!id) {
                                return;
                        }
                        const row = rowElement || findRowById(id);
                        if (row) {
                                row.classList.add("guarantees-table__row--is-new");
                                ensureRowRealtimeBadge(row, true);
                        }
                        const card = mobileCardsMap.get(id);
                        if (card) {
                                card.classList.add("guarantee-card--is-new");
                                ensureCardRealtimeBadge(card, true);
                        }
                        realtimeHighlightEntries.add(id);
                }

                function ensureRowRealtimeBadge(row, active) {
                        if (!row) {
                                return;
                        }
                        const mat = row.querySelector(".vehiculo__mat");
                        if (!mat) {
                                return;
                        }
                        const badge = mat.querySelector("[data-realtime-badge]");
                        if (active) {
                                if (badge) {
                                        badge.textContent = REALTIME_BADGE_LABEL;
                                        return;
                                }
                                const indicator = document.createElement("span");
                                indicator.className = "guarantees-table__new-badge";
                                indicator.dataset.realtimeBadge = "true";
                                indicator.textContent = REALTIME_BADGE_LABEL;
                                mat.appendChild(indicator);
                                return;
                        }
                        if (badge) {
                                badge.remove();
                        }
                }

                function ensureCardRealtimeBadge(card, active) {
                        if (!card) {
                                return;
                        }
                        const mat = card.querySelector(".guarantee-card__mat");
                        if (!mat) {
                                return;
                        }
                        const badge = mat.querySelector("[data-realtime-badge]");
                        if (active) {
                                if (badge) {
                                        badge.textContent = REALTIME_BADGE_LABEL;
                                        return;
                                }
                                const indicator = document.createElement("span");
                                indicator.className = "guarantee-card__new-badge";
                                indicator.dataset.realtimeBadge = "true";
                                indicator.textContent = REALTIME_BADGE_LABEL;
                                mat.appendChild(indicator);
                                return;
                        }
                        if (badge) {
                                badge.remove();
                        }
                }

                function setupRealtimeBadgeDismissal() {
                        if (!tbody && !mobileCardsList) {
                                return;
                        }
                        const handleBadgeClick = (event) => {
                                const target = event.target;
                                if (!target || typeof target.closest !== "function") {
                                        return;
                                }
                                const badge = target.closest("[data-realtime-badge]");
                                if (!badge) {
                                        return;
                                }
                                event.preventDefault();
                                event.stopPropagation();
                                const row = badge.closest(".guarantees-table__row");
                                if (row && row.dataset.id) {
                                        clearRealtimeEntry(row.dataset.id);
                                        return;
                                }
                                const card = badge.closest(".guarantee-card");
                                if (card && card.dataset.id) {
                                        clearRealtimeEntry(card.dataset.id);
                                }
                        };
                        if (tbody) {
                                tbody.addEventListener("click", handleBadgeClick, true);
                        }
                        if (mobileCardsList) {
                                mobileCardsList.addEventListener("click", handleBadgeClick, true);
                        }
                }
                setupRealtimeBadgeDismissal();

                async function refreshListFromRealtime({ generation = null } = {}) {
                        if (!restRoot || !restNonce || !tbody) {
                                return [];
                        }
                        if (realtimeFetchController) {
                                realtimeFetchController.abort();
                        }
                        const controller = new AbortController();
                        realtimeFetchController = controller;
                        try {
                                const requestFilters = getRequestFilters();
                                const params = new URLSearchParams({
                                        page: 1,
                                        per_page: perPage,
                                });
                                appendFiltersToParams(params, requestFilters);
                                const url = `${restRoot}go/v1/guarantees?${params.toString()}`;
                                const res = await fetch(url, {
                                        headers: { "X-WP-Nonce": restNonce },
                                        signal: controller.signal,
                                        cache: "no-store",
                                });
                                if (!res.ok) {
                                        throw new Error(`HTTP ${res.status}`);
                                }
                                const totalHeader = Number(res.headers.get("X-WP-Total"));
                                const pagesHeader = Number(res.headers.get("X-WP-TotalPages"));
                                const payload = await res.json();
                                const data = Array.isArray(payload.data) ? payload.data : [];
                                const insertedIds = insertRealtimeRows(data);
                                const now = Date.now();
                                listCache.set(requestFilters.cacheKey, {
                                        data: data.slice(),
                                        totalPages:
                                                Number.isFinite(pagesHeader) && pagesHeader > 0
                                                        ? Math.floor(pagesHeader)
                                                        : 1,
                                        totalPosts:
                                                Number.isFinite(totalHeader) && totalHeader >= 0
                                                        ? Math.floor(totalHeader)
                                                        : data.length,
                                        fetchedAt: now,
                                });
                                persistListCacheSnapshot(listCache);
                                if (Number.isFinite(totalHeader) && totalHeader >= 0) {
                                        totalPosts = Math.floor(totalHeader);
                                }
                                if (Number.isFinite(pagesHeader) && pagesHeader > 0) {
                                        totalPages = Math.floor(pagesHeader);
                                }
                                hasMore = currentPage < totalPages;
                                if (scrollEnd) {
                                        scrollEnd.hidden = !hasMore;
                                        scrollEnd.setAttribute(
                                                "aria-hidden",
                                                hasMore ? "false" : "true"
                                        );
                                }
                                return insertedIds;
                        } catch (error) {
                                if (!controller.signal.aborted) {
                                        console.warn(
                                                "❌ Error al sincronizar las nuevas garantías:",
                                                error
                                        );
                                }
                                return [];
                        } finally {
                                if (realtimeFetchController === controller) {
                                        realtimeFetchController = null;
                                }
                        }
                }

                function queueRealtimeRefresh(nextGeneration) {
                        if (!Number.isFinite(nextGeneration) || nextGeneration <= 0) {
                                return;
                        }
                        resetListCacheStorageVersion(nextGeneration);
                        realtimePendingGeneration = Math.max(
                                realtimePendingGeneration,
                                Math.floor(nextGeneration)
                        );
                        triggerRealtimeRefresh();
                }

                function triggerRealtimeRefresh() {
                        if (realtimeRefreshInFlight || !realtimePendingGeneration) {
                                return;
                        }
                        const targetGeneration = realtimePendingGeneration;
                        realtimePendingGeneration = 0;
                        realtimeRefreshInFlight = true;
                        refreshListFromRealtime({ generation: targetGeneration })
                                .catch(() => {})
                                .finally(() => {
                                        realtimeRefreshInFlight = false;
                                        if (
                                                realtimePendingGeneration &&
                                                realtimePendingGeneration > targetGeneration
                                        ) {
                                                triggerRealtimeRefresh();
                                        }
                                });
                }

                function scheduleRealtimePoll(delay = null) {
                        if (realtimePollTimer) {
                                window.clearTimeout(realtimePollTimer);
                        }
                        const interval =
                                typeof delay === "number"
                                        ? delay
                                        : document.hidden
                                        ? REALTIME_POLL_HIDDEN_INTERVAL
                                        : REALTIME_POLL_INTERVAL;
                        realtimePollTimer = window.setTimeout(runRealtimePoll, interval);
                }

                async function runRealtimePoll() {
                        realtimePollTimer = null;
                        if (!restRoot || !restNonce || !tbody) {
                                return;
                        }
                        if (document.hidden) {
                                scheduleRealtimePoll();
                                return;
                        }
                        try {
                                const response = await fetch(
                                        `${restRoot}go/v1/guarantees/generation`,
                                        {
                                                headers: { "X-WP-Nonce": restNonce },
                                                cache: "no-store",
                                        }
                                );
                                if (!response.ok) {
                                        throw new Error(`HTTP ${response.status}`);
                                }
                                const payload = await response.json();
                                const remoteGeneration = Number(payload?.generation) || 0;
                                if (remoteGeneration > realtimeKnownGeneration) {
                                        realtimeKnownGeneration = remoteGeneration;
                                        queueRealtimeRefresh(remoteGeneration);
                                }
                        } catch (error) {
                                console.warn(
                                        "❌ Error consultando nuevas garantías en tiempo real:",
                                        error
                                );
                        } finally {
                                scheduleRealtimePoll();
                        }
                }

                function startRealtimeUpdates() {
                        if (realtimeStarted || !restRoot || !restNonce || !tbody) {
                                return;
                        }
                        realtimeStarted = true;
                        scheduleRealtimePoll(REALTIME_POLL_INTERVAL);
                        document.addEventListener("visibilitychange", () => {
                                if (!document.hidden) {
                                        scheduleRealtimePoll(REALTIME_POLL_INTERVAL / 2);
                                }
                        });
                }

                async function preloadByPlate(plate) {
                        const trimmedPlate = typeof plate === "string" ? plate.trim() : "";
                        if (!trimmedPlate) {
                                pendingMatSelection = false;
                                initialMatQuery = "";
                                return;
                        }
                        try {
                                const params = new URLSearchParams({
                                        search: trimmedPlate,
                                        per_page: 1,
                                });
                                const res = await fetch(
                                        `${restRoot}go/v1/guarantees?${params.toString()}`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
                                if (!res.ok) throw res.status;
                                const { data } = await res.json();
                                if (data.length === 0) {
                                        pendingMatSelection = false;
                                        initialMatQuery = "";
                                        return;
                                }
                                const item = data[0];
                                const id = item.id;
                                if (item.detail) {
                                        detailCache.set(String(id), normalizeDetailData(item.detail));
                                }
                                let row = tbody.querySelector(`.guarantees-table__row[data-id="${id}"]`);
                                if (!row) {
                                        row = renderRow(item, { prepend: true });
                                        tbody.insertBefore(row, tbody.firstChild);
                                        loadedIds.add(id);
                                }
                                try {
                                        await activateRow(row);
                                } catch (error) {
                                        console.error(
                                                "❌ Error al activar la garantía precargada:",
                                                error
                                        );
                                }
                                pendingMatSelection = false;
                                initialMatQuery = "";
                        } catch (e) {
                                console.error("❌ Error preloadByPlate:", e);
                                pendingMatSelection = false;
                                initialMatQuery = "";
                        }
                }


                function getDurationMeses(desde, hasta) {
                        const d1 = new Date(desde);
                        const d2 = new Date(hasta);
                        if (isNaN(d1) || isNaN(d2)) return "-";
                        let months =
                                (d2.getFullYear() - d1.getFullYear()) * 12 +
                                (d2.getMonth() - d1.getMonth());
                        if (d2.getDate() >= d1.getDate()) months++;
                        return months > 0 ? months : "-";
                }

                function buildRowData(row) {
                        return {
                                marca_modelo: row.dataset.marca_modelo ?? "-",
                                matricula: row.dataset.matricula ?? "-",
                                plan: row.dataset.plan ?? "-",
                                desde: row.dataset.desde ?? "-",
                                desde_raw: row.dataset.desdeRaw ?? row.dataset.desde ?? "",
                                desde_fmt:
                                        row.dataset.desdeFmt ??
                                        (row.dataset.desde
                                                ? formatDate(row.dataset.desde).display
                                                : "-"),
                                hasta: row.dataset.hasta ?? "-",
                                hasta_raw: row.dataset.hastaRaw ?? row.dataset.hasta ?? "",
                                hasta_fmt:
                                        row.dataset.hastaFmt ??
                                        (row.dataset.hasta
                                                ? formatDate(row.dataset.hasta).display
                                                : "-"),
                                estado: row.dataset.estado ?? "Desconocido",
                                estadoclase: row.dataset.estadoclase ?? "pendiente-pago",
                                concesionario: row.dataset.vendedor_name ?? "-",
                                canal_venta: row.dataset.vendedor_type ?? "-",
                                precio: row.dataset.precio ?? "-",
                                metodo_pago: row.dataset.metodoPago ?? "",
                                cobro_realizado: row.dataset.cobroRealizado === "1",
                                iban_vendedor: row.dataset.ibanVendedor ?? "",
                                transfer_iban: row.dataset.transferIban ?? "",
                                tipo: "-",
                                kilometros: "-",
                                primera_matriculacion: "-",
                                bastidor: "-",
                                precio_venta: "-",
				combustible: "-",
                                cambio: "-",
                                potencia: "-",
                                cilindrada: "-",
                                telefono_vendedor: "-",
                                email_vendedor: "-",
                                avatar_vendedor: "",
                                vendedor_url: "#",
                                certificate_url: "#",
                                condicionado_url: "#",
                                cobertura_url: "#",
                                nombre_comprador: row.dataset.compradorNombre ?? "-",
                                dni_comprador: "-",
                                telefono_comprador: (row.dataset.compradorTelefono ?? "-") || "-",
                                email_comprador: "-",
                                direccion_comprador: "-",
                                localidad_comprador: "-",
                                provincia_comprador: "-",
                                codigo_postal_comprador: "-",
                        };
                }

                function renderFastActions(telefono, email) {
                        const normalize = (value) => {
                                if (value === undefined || value === null) {
                                        return "";
                                }
                                const str = String(value).trim();
                                if (!str || str === "-") {
                                        return "";
                                }
                                return str;
                        };
                        const tel = normalize(telefono);
                        const mail = normalize(email);
                        const telHtml = tel
                                ? `<li class="fast-actions__item"><a href="tel:${tel}" class="fast-actions__link"><span class="fast-actions__icon">${phoneIcon}</span><span class="fast-actions__label">${tel}</span></a></li>`
                                : "";
                        const mailHtml = mail
                                ? `<li class="fast-actions__item"><a href="mailto:${mail}" class="fast-actions__link"><span class="fast-actions__icon">${emailIcon}</span><span class="fast-actions__label">${mail}</span></a></li>`
                                : "";
                        const content = `${telHtml}${mailHtml}`;
                        return content ? `<ul class="fast-actions">${content}</ul>` : "";
                }

                function renderFullDetail(data = {}, rowData = {}) {
    const getFieldText = (val) =>
        val && typeof val === "object" && "label" in val
            ? val.label
            : val;
    const escapeAttr = (value) =>
        String(value)
            .replace(/&/g, "&amp;")
            .replace(/"/g, "&quot;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;");
    const pickField = (field, fallback = "-") => {
        const sources = [data, rowData];
        for (const source of sources) {
            if (!source || !(field in source)) {
                continue;
            }
            const raw = getFieldText(source[field]);
            if (raw === undefined || raw === null) {
                continue;
            }
            if (typeof raw === "string") {
                const trimmed = raw.trim();
                if (!trimmed || trimmed === "-" || trimmed === "#") {
                    continue;
                }
                return raw;
            }
            return raw;
        }
        return fallback;
    };

    const pickEstadoGarantiaField = (field, fallback = "-") => {
        const sources = [data, rowData];
        for (const source of sources) {
            const estadoGroup = source?.estado_garantia;
            if (!estadoGroup || !(field in estadoGroup)) {
                continue;
            }
            const raw = getFieldText(estadoGroup[field]);
            if (raw === undefined || raw === null) {
                continue;
            }
            if (typeof raw === "string") {
                const trimmed = raw.trim();
                if (!trimmed || trimmed === "-" || trimmed === "#") {
                    continue;
                }
                return raw;
            }
            return raw;
        }
        return fallback;
    };

    const parseVehicleDate = (value) => {
        if (!value) return null;
        if (typeof value !== "string") {
            const parsed = new Date(value);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }

        const trimmed = value.trim();
        if (!trimmed) return null;

        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            const [year, month, day] = trimmed.split("-").map((part) => Number.parseInt(part, 10));
            const parsed = new Date(year, (month || 1) - 1, day || 1);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }

        const slashParts = trimmed.split(/[\\/]/);
        if (slashParts.length >= 3 && slashParts.every((part) => part.trim() !== "")) {
            const [dayStr, monthStr, yearStr] = slashParts;
            const day = Number.parseInt(dayStr, 10);
            const month = Number.parseInt(monthStr, 10);
            let year = Number.parseInt(yearStr, 10);
            if (!Number.isNaN(year) && year < 100) {
                year = year >= 70 ? 1900 + year : 2000 + year;
            }
            const parsed = new Date(year, (month || 1) - 1, day || 1);
            return Number.isNaN(parsed.getTime()) ? null : parsed;
        }

        const fallback = new Date(trimmed);
        return Number.isNaN(fallback.getTime()) ? null : fallback;
    };

    const resolveDetailAccent = (estadoClaseValue) => {
        const theme =
            document.documentElement?.getAttribute("data-theme") === "dark"
                ? "dark"
                : "light";
        const palette = {
            light: {
                activada: "#065f46",
                "pendiente-pago": "#92400e",
                "validacion-pendiente": "#1e40af",
                "pend-cobro": "#1e40af",
                "sin-finalizar": "#1f2937",
                "expira-pronto": "#92400e",
                expirada: "#991b1b",
                cancelada: "#991b1b",
            },
            dark: {
                activada: "#6ee7b7",
                "pendiente-pago": "#fcd34d",
                "validacion-pendiente": "#93c5fd",
                "pend-cobro": "#93c5fd",
                "sin-finalizar": "#1f2937",
                "expira-pronto": "#fcd34d",
                expirada: "#fca5a5",
                cancelada: "#fca5a5",
            },
        };
        const paletteForTheme = palette[theme] || palette.light;
        return paletteForTheme?.[estadoClaseValue] || "";
    };

    const computeVehicleAgeYears = (value) => {
        const parsed = parseVehicleDate(value);
        if (!parsed) return null;

        const today = new Date();
        const matriculationDate = new Date(
            parsed.getFullYear(),
            parsed.getMonth(),
            parsed.getDate()
        );

        if (Number.isNaN(matriculationDate.getTime())) return null;
        if (today < matriculationDate) return 0;

        let years = today.getFullYear() - matriculationDate.getFullYear();
        let months = today.getMonth() - matriculationDate.getMonth();
        let days = today.getDate() - matriculationDate.getDate();
        let baseDays = 0;

        if (days < 0) {
            const previousMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            baseDays = previousMonth.getDate();
            days += baseDays;
            months -= 1;
        } else {
            baseDays = new Date(today.getFullYear(), today.getMonth() + 1, 0).getDate();
        }

        if (months < 0) {
            months += 12;
            years -= 1;
        }

        const totalMonths = years * 12 + months;
        const monthFraction = baseDays > 0 ? days / baseDays : 0;
        const ageYears = (totalMonths + monthFraction) / 12;
        const normalized = ageYears < 0 ? 0 : ageYears;
        return Number(normalized.toFixed(1));
    };

    const resolveRecargoFieldSet = () => {
        if (!canAccessManagementHub) return new Set();

        const candidates = [
            data?.recargo_campos,
            data?.detail?.recargo_campos,
            rowData?.recargo_campos,
            rowData?.detail?.recargo_campos,
        ];
        const found = candidates.find((entry) => Array.isArray(entry)) || [];

        return new Set(found.map((v) => String(v)));
    };

    const recargoFields = resolveRecargoFieldSet();
    const recargoClass = (field) =>
        canAccessManagementHub && recargoFields.has(field)
            ? " detail__item--recargo"
            : "";

    const normalizeTipoValue = (value) => {
        if (typeof value !== "string") {
            return "";
        }
        return value
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .toLowerCase()
            .trim();
    };

    const mesesTotales = getDurationMeses(
        data.desde ?? rowData.desde,
        data.hasta ?? rowData.hasta
    );
    const rawDesdeIso =
        pickField("desde_raw", "") || pickField("desde", "");
    const rawHastaIso =
        pickField("hasta_raw", "") || pickField("hasta", "");

    const estadoData =
        data.estado ??
        rowData.estado ??
        data.estadoclase ??
        rowData.estadoclase ??
        "pendiente-pago";
    const estadoValue =
        estadoData && typeof estadoData === "object" && "value" in estadoData
            ? estadoData.value
            : estadoData;
    const estadoClase = normalizeEstadoClase(estadoValue);
    const isSinFinalizar = estadoClase === "sin-finalizar";
    const isPendientePago = estadoClase === "pendiente-pago";
    const isValidacionPendiente = estadoClase === "validacion-pendiente";
    const isCancelada = estadoClase === "cancelada";
    const detailAccent = resolveDetailAccent(estadoClase);
    const headerAccentStyle = detailAccent
        ? ` style="--guarantee-detail-accent:${detailAccent}"`
        : "";
    const hideDetailSections =
        isCancelada && (isParticular || isProfesional);
    const canShowReportBtn = estadoClase === "activada";
    const badgeClase = `guarantee-detail__badge guarantee-detail__badge--${estadoClase}`;
    const metodoPago = (
        data.metodo_pago ?? rowData.metodo_pago ?? ""
    )
        .toString()
        .toLowerCase()
        .trim();
    const cobroRealizado = [
        data.cobro_realizado,
        rowData.cobro_realizado,
    ].some((v) => v === true || v === 1 || v === "1");

    const transferIban = [data.transfer_iban, rowData.transfer_iban]
        .map((val) => (typeof val === "string" ? val.trim() : ""))
        .find((val) => val) || "";

    const isFilled = (val) => {
        if (val === undefined || val === null) return false;
        const str = String(val).trim();
        return str !== "" && str !== "-" && str !== "#";
    };

    const planName = pickField("plan", "-");
    const planParts = [];
    if (planName && planName !== "-") {
        planParts.push(planName);
    }
    if (mesesTotales !== "-") {
        planParts.push(`${mesesTotales} meses`);
    }
    const planPrimaryLabel =
        planParts.length > 0 ? planParts.join(" ") : planName && planName !== "-" ? planName : "";
    const planPriceRaw = pickField("precio", "");
    const planPrice = planPriceRaw && planPriceRaw !== "-" ? `${planPriceRaw} €` : "";
    const hasPlanInfo = Boolean(planPrimaryLabel);
    const hasCoverageInfo =
        isFilled(pickField("desde_fmt", "")) &&
        isFilled(pickField("hasta_fmt", ""));
    const shouldShowPlanInfo = !isSinFinalizar && (hasPlanInfo || planPrice);
    const planTitleHtml = shouldShowPlanInfo
        ? `<h3 class="guarantee-detail__plan-title">` +
              `${planPrimaryLabel ? `<span class=\"guarantee-detail__plan-name\">${planPrimaryLabel}</span>` : ""}` +
              `${planPrice ? `<span class=\"guarantee-detail__plan-price\">${planPrice}</span>` : ""}` +
              `</h3>`
        : "";
    const coverageHtml = "";
    const coverageAlertHtml =
        isSinFinalizar && (!hasPlanInfo || !hasCoverageInfo)
            ? `<p class=\"detail__alert-section detail__alert-section--coverage detail__alert-section--coverage-empty\">No has seleccionado cobertura.</p>`
            : "";
    const vendorChannelSummaryRaw = pickField("canal_venta_summary", "");
    const vendorChannelSummarySource =
        vendorChannelSummaryRaw !== ""
            ? vendorChannelSummaryRaw
            : pickField("canal_venta", "-");
    const vendorChannelSummary =
        vendorChannelSummarySource && vendorChannelSummarySource !== "-"
            ? extractVendorType(vendorChannelSummarySource) || vendorChannelSummarySource
            : vendorChannelSummarySource;
    const vendorChannelValueRaw =
        (data.canal_venta_value ?? rowData.canal_venta_value ?? "").toString();
    const vendorTypeValueRaw =
        (data.vendor_company_type_value ?? rowData.vendor_company_type_value ?? "").toString();
    const normalizedVendorChannel = normalizeChannelValue(vendorChannelValueRaw);
    const normalizedVendorType = normalizeChannelValue(vendorTypeValueRaw);
    const vendorIsParticular =
        normalizedVendorChannel === "particular" || normalizedVendorType === "particular";
    const vendorCompanyNameRaw = pickField("concesionario", "-");
    const vendorCompanyNameClean = cleanDisplayValue(vendorCompanyNameRaw) || "-";
    const vendorPersonalFull = cleanDisplayValue(pickField("concesionario_personal", ""));
    const vendorPersonalFirst = cleanDisplayValue(pickField("concesionario_personal_first", ""));
    const vendorPersonalLast = cleanDisplayValue(pickField("concesionario_personal_last", ""));
    let vendorDisplayName = vendorCompanyNameClean;
    let vendorContactName = vendorPersonalFull !== "" ? vendorPersonalFull : vendorCompanyNameClean;
    if (vendorIsParticular) {
        const personalName = buildPersonalName(
            vendorPersonalFirst,
            vendorPersonalLast,
            vendorPersonalFull
        );
        if (personalName) {
            vendorDisplayName = personalName;
        }
        vendorContactName = vendorPersonalLast !== "" ? vendorPersonalLast : vendorDisplayName;
        if (!vendorContactName) {
            vendorContactName = "—";
        }
    }
    const vendorCompanyName = vendorDisplayName;
    const vendorContactLabel = escapeHtml(vendorContactName);
    const vendorChannelLabel = vendorChannelSummary
        ? `<span class="vendor-card__channel">(${escapeHtml(vendorChannelSummary)})</span>`
        : "";
    const vendorContactDisplay = vendorContactLabel
        ? `${vendorContactLabel}${vendorChannelLabel ? ` ${vendorChannelLabel}` : ""}`
        : vendorContactLabel;
    const vendorAvatarUrl =
        data.avatar_vendedor ?? rowData.avatar_vendedor ?? "";
    const vendorAvatarPlaceholder =
        data.avatar_vendedor_placeholder ?? rowData.avatar_vendedor_placeholder ?? "";
    const vendorInitialSource = vendorCompanyName || vendorContactName || vendorContactDisplay || pickField("matricula", "");
    const vendorPalette = extractAvatarPalette(
        (data.avatar_vendedor_palette ?? rowData.avatar_vendedor_palette) || null
    );
    const vendorForceInitials = Boolean(
        data.avatar_vendedor_force_initials ?? rowData.avatar_vendedor_force_initials
    );
    const vendorStoredInitials = typeof data.avatar_vendedor_initials === "string"
        ? data.avatar_vendedor_initials
        : typeof rowData.avatar_vendedor_initials === "string"
            ? rowData.avatar_vendedor_initials
            : "";
    const vendorInitials = vendorStoredInitials
        || (vendorForceInitials ? buildInitialsFromName(vendorInitialSource) : "");
    const vendorShouldShowInitials = vendorForceInitials || (vendorPalette && vendorInitials !== "");
    let vendorAvatarWrapper = "";

    if (vendorShouldShowInitials && vendorInitials !== "") {
        const vendorStyles = [];
        if (vendorPalette) {
            vendorStyles.push(`--avatar-bg:${vendorPalette.bg}`);
            vendorStyles.push(`--avatar-color:${vendorPalette.text}`);
            vendorStyles.push(`--avatar-bg-dark:${vendorPalette.bgDark}`);
            vendorStyles.push(`--avatar-color-dark:${vendorPalette.textDark}`);
        }
        const vendorStyleAttr = vendorStyles.length ? ` style="${vendorStyles.join(";")};"` : "";
        vendorAvatarWrapper = `<div class="vendor-card__avatar-wrapper vendor-card__avatar-wrapper--initials"${vendorStyleAttr}><span class="vendor-card__avatar vendor-card__avatar--initials">${escapeHtml(vendorInitials)}</span></div>`;
    } else {
        const vendorDisplayAvatar = normalizeAvatarUrl(vendorAvatarUrl || vendorAvatarPlaceholder);
        vendorAvatarWrapper = vendorDisplayAvatar
            ? `<div class="vendor-card__avatar-wrapper"><img src="${escapeAttr(
                  vendorDisplayAvatar
              )}" alt="" class="vendor-card__avatar"></div>`
            : `<div class="vendor-card__avatar-wrapper vendor-card__avatar-wrapper--initials"><span class="vendor-card__avatar vendor-card__avatar--initials">${escapeHtml(
                  buildInitialsFromName(vendorInitialSource) || "--"
              )}</span></div>`;
    }
    const vendorActionsHtml = renderFastActions(
        data.telefono_vendedor ?? rowData.telefono_vendedor,
        data.email_vendedor ?? rowData.email_vendedor
    );
    let vendorDetailsHrefRaw = data.vendor_profile_url ?? data.vendedor_url ?? rowData.vendedor_url ?? "";
    if ((!vendorDetailsHrefRaw || vendorDetailsHrefRaw === "#") && data.vendor_slug) {
        const clientsBase = (goConfig.pages && goConfig.pages.clientes) || "";
        if (clientsBase) {
            const base = clientsBase.replace(/\/+$/, "");
            vendorDetailsHrefRaw = `${base}/${data.vendor_slug}/`;
        }
    }
    const vendorDetailsHref = escapeAttr(vendorDetailsHrefRaw && vendorDetailsHrefRaw !== "#" ? vendorDetailsHrefRaw : "#");
    const fuelRaw = (
        data.combustible ?? rowData.combustible ?? ""
    )
        .toString()
        .toLowerCase();
    const isElectric = fuelRaw
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "") === "electrico";
    const potenciaUnidad = isElectric ? "kW" : "CV";
    const vehicleAgeValue = computeVehicleAgeYears(
        pickField("primera_matriculacion_raw", "") || pickField("primera_matriculacion", "")
    );
    const vehicleAgeLabel =
        isAdmin && Number.isFinite(vehicleAgeValue)
            ? vehicleAgeValue === 1
                ? "1 año"
                : `${vehicleAgeValue.toLocaleString("es-ES", {
                      minimumFractionDigits: 1,
                      maximumFractionDigits: 1,
                  })} años`
            : "";
    const antiguedadRowHtml = vehicleAgeLabel
        ? `<li class="detail__item${recargoClass("antiguedad")}"><strong>Antigüedad:</strong> ${vehicleAgeLabel}</li>`
        : "";
    const rawTipoValue =
        data.tipo_value ??
        rowData.tipo_value ??
        "";
    const normalizedTipoValue = normalizeTipoValue(rawTipoValue);
    const tipoLabelNormalized = normalizeTipoValue(pickField("tipo", ""));
    const isTipoCamion =
        normalizedTipoValue === "camion" || tipoLabelNormalized === "camion";
    const primaryTraccionField = isTipoCamion
        ? "traccion_camion"
        : "traccion";
    const secondaryTraccionField = isTipoCamion
        ? "traccion"
        : "traccion_camion";
    const traccionValue = (() => {
        const primary = pickField(primaryTraccionField, "");
        if (cleanDisplayValue(primary)) {
            return primary;
        }
        const secondary = pickField(secondaryTraccionField, "");
        if (cleanDisplayValue(secondary)) {
            return secondary;
        }
        return "-";
    })();
    const traccionRowHtml = `<li class="detail__item${recargoClass("traccion")}"><strong>Tracción:</strong> ${traccionValue}</li>`;
    const proformaOptions =
        goConfig && goConfig.proforma && typeof goConfig.proforma.options === "object"
            ? goConfig.proforma.options
            : {};
    const shouldShowProformaDocs =
        (goConfig && goConfig.proforma && goConfig.proforma.showInDocuments === true) ||
        proformaOptions.mostrar_en_documentos === true;

    console.log(
        `[Proforma]: Mostrar en documentación? ${shouldShowProformaDocs ? "Sí" : "No"}`,
        {
            options: proformaOptions,
            showInDocumentsFlag:
                goConfig && goConfig.proforma ? goConfig.proforma.showInDocuments : undefined,
        }
    );

    const docsSource = Array.isArray(data.documents)
        ? data.documents
        : Array.isArray(rowData.documents)
        ? rowData.documents
        : [];
    const docsData = (shouldShowProformaDocs
        ? docsSource
        : docsSource.filter((doc) => (doc && doc.key ? String(doc.key) : "") !== "proforma"))
        .map((doc) => {
            const key = typeof doc.key === "string" && doc.key !== "" ? doc.key : doc.row ? `extra-${doc.row}` : "";
            const base = key && docsConfigMap.has(key) ? docsConfigMap.get(key) : null;
            const enforcedCancelledLabel = isCancelada && key === "certificate";
            const isCancelledCertificate = Boolean(
                doc.is_cancelled_certificate || doc.cancelled_certificate
            ) || enforcedCancelledLabel;
            const cancelDate = typeof doc.cancel_date === "string" ? doc.cancel_date : "";
            const listLabel =
                (typeof doc.listLabel === "string" && doc.listLabel.trim() !== "" ? doc.listLabel : null) ||
                (typeof doc.title === "string" && doc.title.trim() !== "" ? doc.title : null) ||
                (base && base.listLabel) ||
                (doc.kind === "transfer_receipt" ? "Justificante de transferencia" : "Documento");
            const downloadLabel =
                (typeof doc.downloadLabel === "string" && doc.downloadLabel.trim() !== "" ? doc.downloadLabel : null) ||
                (base && base.successLabel) ||
                "Descargar documento";
            const resolvedListLabel = isCancelledCertificate
                ? "Certificado cancelado"
                : listLabel;
            const resolvedDownloadLabel = isCancelledCertificate
                ? "Descargar certificado cancelado"
                : downloadLabel;
            let iconKey = "";
            if (base && base.icon) {
                iconKey = base.icon.toLowerCase();
            }
            if (typeof doc.icon === "string" && doc.icon.trim() !== "") {
                const candidate = doc.icon.trim().toLowerCase();
                if (!iconKey || candidate !== "download") {
                    iconKey = candidate;
                }
            }
            if (!iconKey) {
                iconKey = doc.kind === "transfer_receipt" ? "payment" : "pdf";
            }
            const rawUrl = doc.url || doc.attachment_url || doc.direct_url || "";
            const url = normalizeDocUrl(rawUrl);
            return {
                key: key || `doc-${Math.random().toString(16).slice(2)}`,
                url,
                listLabel: resolvedListLabel,
                downloadLabel: resolvedDownloadLabel,
                iconKey,
                mime: typeof doc.mime === "string" ? doc.mime : "",
                extension: typeof doc.extension === "string" ? doc.extension : "",
                filename: typeof doc.filename === "string" ? doc.filename : "",
                kind: typeof doc.kind === "string" ? doc.kind : "general",
                row: doc.row || "",
                isCancelledCertificate,
                cancelDate,
            };
        })
        .filter((doc) => doc.url)
        .filter((doc) => {
            const docKey = typeof doc.key === "string" ? doc.key : "";
            return docKey !== "cobertura" && docKey !== "condicionado";
        });
    docsData.forEach((doc) => scheduleDocumentPreload(doc.url));
    const buyerFields = [
        "nombre_comprador",
        "dni_comprador",
        "telefono_comprador",
        "email_comprador",
        "direccion_comprador",
        "localidad_comprador",
        "provincia_comprador",
        "codigo_postal_comprador",
    ];
    const hasDocs = docsData.length > 0;
    const docsButtonsHtml = docsData
        .map(
            (doc, idx) => {
                const rawClassKey = typeof doc.key === "string" ? doc.key : String(doc.key || "");
                const safeClassName = rawClassKey
                    ? rawClassKey.toLowerCase().replace(/[^a-z0-9_-]/g, "")
                    : "";
                const buttonClasses = [];
                if (safeClassName !== "") {
                    buttonClasses.push(`detail__docs-btn--${safeClassName}`);
                }
                if (doc.isCancelledCertificate) {
                    buttonClasses.push("detail__docs-btn--cancelled");
                }
                const buttonClass = buttonClasses.length ? ` ${buttonClasses.join(" ")}` : "";
                const iconMarkup = doc.iconKey === "payment"
                    ? paymentIcon
                    : doc.iconKey === "download" && downloadIcon !== ""
                    ? downloadIcon
                    : pdfIcon;
                const attrs = [
                    `data-doc-key="${escapeAttr(doc.key)}"`,
                    `data-doc-index="${idx}"`,
                    `data-doc-url="${escapeAttr(doc.url)}"`,
                ];
                if (doc.isCancelledCertificate) attrs.push('data-doc-cancelled="1"');
                if (doc.mime) attrs.push(`data-doc-mime="${escapeAttr(doc.mime)}"`);
                if (doc.extension) attrs.push(`data-doc-extension="${escapeAttr(doc.extension)}"`);
                if (doc.filename) attrs.push(`data-doc-filename="${escapeAttr(doc.filename)}"`);
                if (doc.downloadLabel) attrs.push(`data-doc-download="${escapeAttr(doc.downloadLabel)}"`);
                if (doc.kind) attrs.push(`data-doc-kind="${escapeAttr(doc.kind)}"`);
                if (doc.row) attrs.push(`data-doc-row="${escapeAttr(String(doc.row))}"`);
                return (
                    `<li class="detail__docs-item">` +
                    `<button type="button" class="detail__docs-btn${buttonClass}" ${attrs.join(" ")} aria-label="Ver documento ${escapeAttr(doc.listLabel)}">` +
                    `<span class="detail__docs-icon detail__docs-icon--${doc.iconKey}">${iconMarkup}</span>` +
                    `<span class="detail__docs-label">${doc.listLabel}</span>` +
                    `</button>` +
                    `</li>`
                );
            }
        )
        .join("");

    const addDocButtonHtml = canUploadDocuments
        ? `<li class="detail__docs-item detail__docs-item--add">` +
          `<button type="button" class="detail__docs-btn detail__docs-btn--add detail__docs-add" data-doc-key="${ADD_DOC_KEY}" data-doc-action="add" aria-label="Añadir documento">` +
          `<span class="detail__docs-icon detail__docs-icon--add">${plusIcon || "+"}</span>` +
          `<span class="detail__docs-label">Añadir documento</span>` +
          `</button>` +
          `</li>`
        : "";

    let docsListHtml = "";
    if (hasDocs || canUploadDocuments) {
        docsListHtml = `<ul class="detail__docs-list">${docsButtonsHtml}${addDocButtonHtml}</ul>`;
        if (!hasDocs) {
            docsListHtml = `<p class="detail__alert-section">Documentación no disponible</p>${docsListHtml}`;
        }
    } else {
        docsListHtml = `<p class="detail__alert-section">Documentación no disponible</p>`;
    }
    const docsSectionHtml = isSinFinalizar
        ? ""
        : `<section class="detail__section detail__section--docs">` +
              `<h3 class="detail__section-title">Documentación</h3>` +
              `${docsListHtml}` +
          `</section>`;
    const hasBuyerInfo = buyerFields.every((field) => isFilled(pickField(field, "")));
    const vehicleSectionHtml = hideDetailSections
        ? ""
        : `<section class="detail__section">` +
              `<h3>Datos del vehículo</h3>` +
              `<ul>` +
                  `<li class="detail__item"><strong>Marca/Modelo:</strong> ${pickField("marca_modelo")}</li>` +
                  `<li class="detail__item"><strong>Tipo:</strong> ${pickField("tipo", "-")}</li>` +
                  `<li class="detail__item${recargoClass("kilometros")}"><strong>Kilómetros:</strong> ${pickField("kilometros", "-")} km</li>` +
                  `<li class="detail__item${recargoClass("antiguedad")}"><strong>1ª Matriculación:</strong> ${pickField("primera_matriculacion", "-")}</li>` +
                  `${antiguedadRowHtml}` +
                  `<li class="detail__item"><strong>Matrícula:</strong> ${pickField("matricula")}</li>` +
                  `<li class="detail__item"><strong>Nº Bastidor:</strong> ${pickField("bastidor", "-")}</li>` +
                  `<li class="detail__item"><strong>Precio venta:</strong> ${pickField("precio_venta", "-")} €</li>` +
              `</ul>` +
          `</section>`;
    const technicalSectionHtml = hideDetailSections
        ? ""
        : `<section class="detail__section">` +
              `<h3>Detalles técnicos</h3>` +
              `<ul>` +
                  `<li class="detail__item${recargoClass("combustible")}"><strong>Combustible:</strong> ${pickField("combustible", "-")}</li>` +
                  `<li class="detail__item${recargoClass("cambio")}"><strong>Cambio:</strong> ${pickField("cambio", "-")}</li>` +
                  `${traccionRowHtml}` +
                  `<li class="detail__item${recargoClass("potencia")}"><strong>Potencia:</strong> ${pickField("potencia", "-")} ${potenciaUnidad}</li>` +
                  `<li class="detail__item"><strong>Cilindrada:</strong> ${pickField("cilindrada", "-")} CC</li>` +
              `</ul>` +
          `</section>`;
    const customerSectionHtml = hideDetailSections
        ? ""
        : `<section class="detail__section detail__section--datos_cliente">`
              + `<h3>Datos del cliente</h3>`
              + (hasBuyerInfo
                    ? `
                        <ul>
                                <li><strong>Nombre:</strong> ${pickField("nombre_comprador", "-")}</li>
                                <li><strong>DNI/NIE:</strong> ${pickField("dni_comprador", "-")}</li>
                                <li><strong>Teléfono:</strong> ${pickField("telefono_comprador", "-")}</li>
                                <li><strong>Email:</strong> ${pickField("email_comprador", "-")}</li>
                                <li><strong>Dirección:</strong> ${pickField("direccion_comprador", "-")}</li>
                                <li><strong>Localidad:</strong> ${pickField("localidad_comprador", "-")}</li>
                                <li><strong>Provincia:</strong> ${pickField("provincia_comprador", "-")}</li>

                                <li><strong>Código Postal:</strong> ${pickField("codigo_postal_comprador", "-")}</li>
                        </ul>
                        ${renderFastActions(
                            data.telefono_comprador ?? rowData.telefono_comprador,
                            data.email_comprador ?? rowData.email_comprador
                        )}
                    `
                    : `<p class="detail__alert-section">Faltan datos del cliente</p>`)
              + `</section>`;
    const showChannelSection = isAdmin;
    const showActions = canManageDetailActions;
    const showManagementHub = canAccessManagementHub;
    const parseTimelineDate = (value) => {
        if (typeof parseDateTime === "function") {
            return parseDateTime(value);
        }
        if (!value) return null;
        const normalized = String(value).trim();
        if (!normalized) return null;

        const normalizeYearValue = (year) => {
            const numericYear = Number(year);
            if (!Number.isFinite(numericYear)) return null;
            if (String(year).length === 2) {
                return 2000 + numericYear;
            }
            return numericYear;
        };

        const safeDate = (year, month, day, hours = 0, minutes = 0, seconds = 0) => {
            const y = normalizeYearValue(year);
            const m = Number(month);
            const d = Number(day);
            const hh = Number(hours);
            const mm = Number(minutes);
            const ss = Number(seconds);
            if (!Number.isFinite(y) || !Number.isFinite(m) || !Number.isFinite(d)) {
                return null;
            }
            if (m < 1 || m > 12 || d < 1 || d > 31) {
                return null;
            }
            const candidate = new Date(y, m - 1, d, hh, mm, ss);
            if (Number.isNaN(candidate.getTime())) return null;
            if (
                candidate.getFullYear() !== y ||
                candidate.getMonth() !== m - 1 ||
                candidate.getDate() !== d
            ) {
                return null;
            }
            return candidate;
        };

        const localMatch = normalized.match(
            /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
        );
        if (localMatch) {
            const [, day, month, year, hours = "0", minutes = "0", seconds = "0"] = localMatch;
            const date = safeDate(year, month, day, hours, minutes, seconds);
            if (date) return date;
        }

        const isoMatch = normalized.match(
            /^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
        );
        if (isoMatch) {
            const [, year, month, day, hours = "0", minutes = "0", seconds = "0"] = isoMatch;
            const date = safeDate(year, month, day, hours, minutes, seconds);
            if (date) return date;
        }

        const numeric = normalized.replace(/[^0-9]/g, "");
        if (numeric.length >= 8) {
            const numericDatePart = numeric.slice(-8);
            const numericCandidates = [
                {
                    year: numericDatePart.slice(4, 8),
                    month: numericDatePart.slice(2, 4),
                    day: numericDatePart.slice(0, 2),
                },
                {
                    year: numericDatePart.slice(0, 4),
                    month: numericDatePart.slice(4, 6),
                    day: numericDatePart.slice(6, 8),
                },
            ];

            for (const candidate of numericCandidates) {
                const date = safeDate(candidate.year, candidate.month, candidate.day);
                if (date) return date;
            }
        }

        const fallbackDate = new Date(normalized);
        return Number.isNaN(fallbackDate.getTime()) ? null : fallbackDate;
    };

    const formatTimelineDate = (raw) => {
        const fallback = () => {
            if (typeof raw === "string") {
                const trimmedRaw = raw.trim();
                if (trimmedRaw !== "") {
                    return trimmedRaw;
                }
            }
            if (raw !== undefined && raw !== null) {
                const stringified = String(raw).trim();
                if (stringified !== "") {
                    return stringified;
                }
            }
            return "—";
        };
        const parsed = parseTimelineDate(raw);
        const parsedDate =
            parsed instanceof Date
                ? parsed
                : parsed
                ? (() => {
                      const candidate = new Date(parsed);
                      return Number.isNaN(candidate.getTime()) ? null : candidate;
                  })()
                : null;
        if (!parsedDate) {
            return fallback();
        }
        try {
            const formatter = new Intl.DateTimeFormat("es-ES", {
                day: "2-digit",
                month: "short",
                year: "numeric",
            });
            const parts = formatter.formatToParts(parsedDate);
            const day = parts.find((part) => part.type === "day")?.value ?? "";
            const month = parts.find((part) => part.type === "month")?.value ?? "";
            const year = parts.find((part) => part.type === "year")?.value ?? "";
            if (day && month && year) {
                const normalizedMonth = month.replace(/\.$/, "").toLowerCase();
                return `${day} ${normalizedMonth}, ${year}`;
            }
            return formatter.format(parsedDate);
        } catch (error) {
            return fallback();
        }
    };

    const formatCancellationDate = (raw) => {
        if (!raw) return "";
        const parsed = parseTimelineDate(raw);
        if (parsed instanceof Date) {
            try {
                const formatted = new Intl.DateTimeFormat("es-ES", {
                    day: "2-digit",
                    month: "long",
                    year: "numeric",
                }).format(parsed);
                return formatted;
            } catch (err) {
                // ignore
            }
        }
        const fallback = formatTimelineDate(raw);
        return fallback !== "—" ? fallback : String(raw).trim();
    };

    const contractDateCandidates = [
        pickEstadoGarantiaField("fecha_contratacion", ""),
        pickEstadoGarantiaField("fecha_contratacion_fmt", ""),
        pickEstadoGarantiaField("fecha_contratacion_raw", ""),
        pickField("fecha_contratacion", ""),
        pickField("fecha_contratacion_fmt", ""),
        pickField("fecha_contratacion_raw", ""),
        pickField("estado_cambio_fecha", ""),
        pickField("estado_cambio_en", ""),
        pickField("estado_actualizado_en", ""),
        pickField("estado_updated_at", ""),
        pickField("estado_updated", ""),
        pickField("estado_modificado_en", ""),
        pickField("estado_modificado", ""),
        pickField("estado_garantia_updated_at", ""),
        pickField("estado_garantia_modificado", ""),
    ];
    const publicationDateCandidates = [
        pickField("published_at_fmt", ""),
        pickField("published_at", ""),
        pickField("published_on", ""),
        pickField("publish_date", ""),
        pickField("fecha_publicacion", ""),
        pickField("fecha_publicado", ""),
        pickField("publicado_en", ""),
        pickField("post_date", ""),
        pickField("post_date_gmt", ""),
        pickField("post_modified", ""),
        pickField("post_modified_gmt", ""),
    ];
    const creationDateCandidates = [
        pickField("created_at_fmt", ""),
        pickField("created_at", ""),
        pickField("created", ""),
        pickField("post_date", ""),
    ];
    const coverageStartDateRaw = pickField("desde_fmt", "");
    const coverageEndDateRaw = pickField("hasta_fmt", "");
    const coverageStartDate = parseTimelineDate(coverageStartDateRaw);
    const coverageEndDate = parseTimelineDate(coverageEndDateRaw);
    const hasCoverageStart = isFilled(coverageStartDateRaw);
    const hasCoverageEnd = isFilled(coverageEndDateRaw);
    const creationDateValue =
        creationDateCandidates.find((value) => isFilled(value)) || "";
    const contratoFechaContratacion =
        [
            pickEstadoGarantiaField("fecha_contratacion", ""),
            pickEstadoGarantiaField("fecha_contratacion_fmt", ""),
            pickEstadoGarantiaField("fecha_contratacion_raw", ""),
            pickField("fecha_contratacion", ""),
            pickField("fecha_contratacion_fmt", ""),
            pickField("fecha_contratacion_raw", ""),
        ].find((value) => isFilled(value)) || "";
    const shouldUseContractDate =
        estadoClase === "activada" || isPendientePago;
    const contractDateRaw = (() => {
        if (shouldUseContractDate) {
            return contratoFechaContratacion || creationDateValue;
        }
        if (isSinFinalizar) {
            return creationDateValue;
        }
        return (
            publicationDateCandidates.find((value) => isFilled(value)) ||
            contractDateCandidates.find((value) => isFilled(value)) ||
            creationDateValue ||
            ""
        );
    })();
    const today = (() => {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), now.getDate());
    })();
    const msPerDay = 24 * 60 * 60 * 1000;
    const daysBetween = (targetDate) => {
        if (!(targetDate instanceof Date)) {
            return null;
        }
        const diff = targetDate.getTime() - today.getTime();
        return Math.max(0, Math.ceil(diff / msPerDay));
    };
    const daysUntilStart = daysBetween(coverageStartDate);
    const daysUntilEnd = daysBetween(coverageEndDate);

    let inlineCountdownValue = "";

    if (coverageStartDate instanceof Date && coverageStartDate.getTime() > today.getTime()) {
        inlineCountdownValue =
            daysUntilStart !== null && Number.isFinite(daysUntilStart)
                ? `${daysUntilStart} días para inicio`
                : "";
    } else if (coverageEndDate instanceof Date) {
        inlineCountdownValue =
            daysUntilEnd !== null && Number.isFinite(daysUntilEnd)
                ? `Expira en ${daysUntilEnd} días`
                : "";
    }

    const inlineCountdownHtml = inlineCountdownValue
        ? `<div class="detail__inline-meta detail__inline-meta--countdown">` +
              `<span>${inlineCountdownValue}</span>` +
          `</div>`
        : "";

    const parseBreakdownAmount = (value) => {
        if (value === undefined || value === null || value === "") {
            return null;
        }
        const numeric = normalizeToFloat(value);
        return Number.isFinite(numeric) ? numeric : null;
    };
    const formatBreakdownAmount = (value) => {
        const numeric = parseBreakdownAmount(value);
        if (numeric === null) return "";
        return numeric.toFixed(2).replace(".", ",");
    };
    const canShowInlineUtilities = !(isProfesional || isParticular);
    const inlineUtilitiesHtml =
        canShowInlineUtilities && (canAccessManagementHub || inlineCountdownHtml)
            ? `<div class="detail__inline-utilities">` +
                  `${inlineCountdownHtml}` +
                  `${canAccessManagementHub
                      ? `<button type="button" class="detail__inline-cta" data-management-open>` +
                            `<span class="detail__inline-cta-icon" aria-hidden="true">${managementInlineIcon}</span>` +
                            `<span class="detail__inline-cta-text">Gestionar garantía</span>` +
                        `</button>`
                      : ""}` +
              `</div>`
            : "";
    const timelineValueClass = isCancelada
        ? "detail__timeline-value detail__timeline-value--cancelled"
        : "detail__timeline-value";
    const billingTimelineHtml = `${inlineUtilitiesHtml}<div class="detail__timeline-list">` +
        `<div class="detail__timeline detail__timeline--contract">` +
            `<div class="detail__timeline-point">` +
                `<span class="detail__timeline-label">${
                    isSinFinalizar ? "Iniciada" : "Fecha contratación"
                }</span>` +
                `<span class="${timelineValueClass}">${formatTimelineDate(
                    isSinFinalizar ? creationDateValue : contractDateRaw
                )}</span>` +
            `</div>` +
        `</div>` +
        `<div class="detail__timeline detail__timeline--range">` +
            `<div class="detail__timeline-point">` +
                `<span class="detail__timeline-label">Inicio cobertura</span>` +
                `<span class="${timelineValueClass}">${
                    hasCoverageStart ? formatTimelineDate(coverageStartDateRaw) : "—"
                }</span>` +
            `</div>` +
            `<div class="detail__timeline-connector" aria-hidden="true">` +
                `<svg class="detail__timeline-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">` +
                    `<line x1="5" y1="12" x2="19" y2="12"></line>` +
                    `<polyline points="12 5 19 12 12 19"></polyline>` +
                `</svg>` +
            `</div>` +
            `<div class="detail__timeline-point detail__timeline-point--end">` +
                `<span class="detail__timeline-label">Vencimiento</span>` +
                `<span class="${timelineValueClass}">${
                    hasCoverageEnd ? formatTimelineDate(coverageEndDateRaw) : "—"
                }</span>` +
            `</div>` +
        `</div>` +
    `</div>`;
    const breakdownSource =
        [
            data?.descuentos_y_recargos,
            data?.garantia_contratada?.descuentos_y_recargos,
            data?.detail?.descuentos_y_recargos,
            data?.detail?.garantia_contratada?.descuentos_y_recargos,
            rowData?.descuentos_y_recargos,
            rowData?.garantia_contratada?.descuentos_y_recargos,
        ].find((candidate) => candidate && typeof candidate === "object") || {};

    const listadoDescuentosRecargos = Array.isArray(
        breakdownSource.listado_descuentos_recargos
    )
        ? breakdownSource.listado_descuentos_recargos
        : [];

    const parsedBreakdownRows = listadoDescuentosRecargos
        .map((row) => ({
            concepto:
                typeof row.concepto === "string" && row.concepto.trim() !== ""
                    ? row.concepto.trim()
                    : "",
            valor: parseBreakdownAmount(row.valor ?? row.importe ?? null),
            destacado:
                row.destacado === true || row.destacado === 1 || row.destacado === "1",
        }))
        .filter((row) => row.concepto !== "" || row.valor !== null);

    const billingSegments = [];
    parsedBreakdownRows.forEach((row, idx) => {
        const nextIsTotal = parsedBreakdownRows[idx + 1]?.destacado || false;

        if (row.destacado && idx > 0) {
            billingSegments.push(
                '<div class="detail__billing-divider" role="presentation"></div>'
            );
        }

        const amountLabel = formatBreakdownAmount(row.valor);
        const rowClasses = ["detail__billing-row"];
        if (row.destacado) rowClasses.push("detail__billing-row--total");
        if (nextIsTotal) rowClasses.push("detail__billing-row--pre-divider");
        billingSegments.push(
            `<div class="${rowClasses.join(" ")}">` +
                `<span>${escapeHtml(row.concepto || `Línea ${idx + 1}`)}</span>` +
                `<span class="detail__billing-amount">${escapeHtml(
                    amountLabel ? `${amountLabel}€` : "—"
                )}</span>` +
            `</div>`
        );
    });

    if (
        billingSegments.length === 0 &&
        (breakdownSource.precio_base !== undefined || planPrice)
    ) {
        const baseAmount = formatBreakdownAmount(breakdownSource.precio_base);
        if (baseAmount) {
            billingSegments.push(
                `<div class="detail__billing-row"><span>Precio base</span><span class="detail__billing-amount">${escapeHtml(
                    `${baseAmount}€`
                )}</span></div>`
            );
        }
        if (planPrice) {
            if (billingSegments.length) {
                billingSegments.push(
                    '<div class="detail__billing-divider" role="presentation"></div>'
                );
            }
            billingSegments.push(
                `<div class="detail__billing-row detail__billing-row--total"><span>Total facturado</span><span class="detail__billing-amount">${escapeHtml(
                    planPrice
                )}</span></div>`
            );
        }
    }

    const billingBreakdownHtml =
        billingSegments.length > 0
            ? `<div class="detail__billing-breakdown">${billingSegments.join("")}</div>`
            : `<div class="detail__billing-breakdown detail__billing-breakdown--empty">` +
              `<div class="detail__billing-row"><span>Sin desglose disponible</span><span class="detail__billing-amount">—</span></div>` +
              `</div>`;

    const highlightedRow =
        parsedBreakdownRows.find((row) => row.destacado) || null;

    const billingTotalAmount = (() => {
        const highlightedAmount =
            highlightedRow && formatBreakdownAmount(highlightedRow.valor)
                ? `${formatBreakdownAmount(highlightedRow.valor)} €`
                : "";
        if (highlightedAmount) return highlightedAmount;
        if (planPrice) return planPrice;
        const fallbackBase = formatBreakdownAmount(breakdownSource.precio_base);
        return fallbackBase ? `${fallbackBase} €` : "";
    })();
    const billingToggleHtml = canAccessManagementHub
        ? `<details class="detail__transfer-toggle detail__billing-toggle">` +
        `<summary class="detail__transfer-toggle-summary">` +
            `<span class="detail__transfer-toggle-label">Detalle de facturación</span>` +
            `<span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--closed" aria-hidden="true">${arrowDownIcon || "&#9660;"}</span>` +
            `<span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--open" aria-hidden="true">${arrowUpIcon || "&#9650;"}</span>` +
        `</summary>` +
        `<div class="detail__transfer-toggle-content">${billingBreakdownHtml}</div>` +
    `</details>`
        : "";
    const billingSectionHtml = `<section class="detail__section detail__section--billing">` +
        `${billingTimelineHtml}` +
        `${billingToggleHtml}` +
    `</section>`;
    const managementSectionHtml = "";

    const sinFinalButtons = [];
    if (canContinueGuarantee) {
        sinFinalButtons.push(
            `<button type="button" aria-label="Continuar con la garantía" class="guarantee-detail__btn guarantee-detail__btn--continue">` +
                `<span class="guarantee-detail__btn-icon">${continueIcon}</span>` +
                `<span class="guarantee-detail__btn-text">Continuar con la garantía</span>` +
            `</button>`
        );
    }
    const sinFinalActionsHtml = sinFinalButtons.length
        ? `<div class="guarantee-detail__btn-container">${sinFinalButtons.join("")}</div>`
        : "";
    if (isSinFinalizar) {
        return `
                <div class="guarantee-detail__inner">
                <div class="guarantee-detail__header"${headerAccentStyle}>
                        <h2>Garantía ${pickField("matricula")}</h2>
                        ${coverageHtml}
                        <div><p class="detail__alert-section">Completa los datos pendientes para tramitar la garantía</p></div>
                        <div class="${badgeClase}">${pickField("estado", "Desconocido")}</div>
                </div>
                ${billingSectionHtml}
                ${managementSectionHtml}
                ${coverageAlertHtml}
                ${showChannelSection
                        ? `<section class="detail__section detail__section--channel">
                                <h3 class="detail__section-title">
                                        <span>Canal de venta</span>
                                </h3>
                                <div class="vendor-card">
                                <div class="vendor-card__header">
                                        <div class="vendor-card__primary">
                                                ${vendorAvatarWrapper}
                                                <div class="vendor-card__info">
                                                        <p class="vendor-card__name">${vendorCompanyName}</p>
                                                        <p class="vendor-card__contact">${vendorContactDisplay}</p>
                                                </div>
                                        </div>
                                        ${vendorDetailsHref
                                            ? `<a href="${vendorDetailsHref}" class="fast-actions__link vendor-card__quick-link">` +
                                                  `<span class="fast-actions__icon" aria-hidden="true">${personIcon}</span>` +
                                                  `<span class="fast-actions__label">Ver cliente</span>` +
                                              `</a>`
                                            : ""}
                                </div>
                                        ${vendorActionsHtml
                                            ? `<div class="vendor-card__actions">${vendorActionsHtml}</div>`
                                            : ``}
                                        <div class="vendor-card__footer">
                                                <a href="${vendorDetailsHref}" class="vendor-card__cta vendor-card__cta--details">
                                                        <span class="vendor-card__cta-icon" aria-hidden="true">${personIcon}</span>
                                                        <span class="vendor-card__cta-label">Ver ficha del cliente</span>
                                                </a>
                                                <button type="button" class="vendor-card__cta vendor-card__cta--contact">
                                                        <span class="vendor-card__cta-icon" aria-hidden="true">${personAddIcon}</span>
                                                        <span class="vendor-card__cta-label">Añadir contacto</span>
                                                </button>
                                        </div>
                                </div>
                        </section>`
                        : ""}
                ${docsSectionHtml}
                ${vehicleSectionHtml}
                ${technicalSectionHtml}
                ${customerSectionHtml}
                ${sinFinalActionsHtml}
        </div>`;
    }

    const adminPendingDomiciliacion =
        canManageDetailActions && metodoPago.startsWith("domiciliacion") && !cobroRealizado;
    const deadlineInfo = getTransferDeadlineInfo(data, rowData);
    const transferDeadlineMs = deadlineInfo.deadlineMs;
    const hasTransferDeadline = Number.isFinite(transferDeadlineMs);
    const transferDeadlineExpired = hasTransferDeadline && Boolean(deadlineInfo.expired);
    const shouldShowConfirmBtn =
        showActions && (isPendientePago || isValidacionPendiente || adminPendingDomiciliacion);
    const confirmLabel =
        adminPendingDomiciliacion || metodoPago.startsWith("domiciliacion")
            ? "Confirmar domiciliación"
            : "Confirmar pago";
    const professionalActionButtons = [];
    if (
        isProfesional &&
        isPendientePago &&
        metodoPago === "transferencia" &&
        (!hasTransferDeadline || !transferDeadlineExpired)
    ) {
        professionalActionButtons.push(
            `<button type="button" class="guarantee-detail__btn guarantee-detail__btn--transfer-report" aria-label="Ya he realizado la transferencia">` +
                `<span class="guarantee-detail__btn-icon">${paymentIcon}</span>` +
                `<span class="guarantee-detail__btn-text">Ya he realizado la transferencia</span>` +
            `</button>`
        );
    }

    const adminActionButtons = [];
    if (showActions) {
        if (shouldShowConfirmBtn) {
            adminActionButtons.push(
                `<button type="button" class="guarantee-detail__btn guarantee-detail__btn--confirm" aria-label="${confirmLabel}">` +
                    `<span class="guarantee-detail__btn-icon">${paymentIcon}</span>` +
                    `<span class="guarantee-detail__btn-text">${confirmLabel}</span>` +
                `</button>`
            );
        }
        if (canShowReportBtn) {
            adminActionButtons.push(
                `<button type="button" class="guarantee-detail__btn guarantee-detail__btn--report" aria-label="Abrir expediente para esta garantía">` +
                    `<span class="guarantee-detail__btn-icon">${warningIcon}</span>` +
                    `<span class="guarantee-detail__btn-text">Abrir expediente</span>` +
                `</button>`
            );
        }
    }
    const combinedActionButtons = [
        ...professionalActionButtons,
        ...adminActionButtons,
    ];
    const actionsHtml = combinedActionButtons.length
        ? `<div class="guarantee-detail__btn-container">${combinedActionButtons.join("")}</div>`
        : "";
    const deleteActionHtml = "";

    const { vendorDisplayHtml, adminEntityHtml } = (() => {
        const vendorCompanyData =
            data && typeof data.vendor_company === "object" && data.vendor_company !== null
                ? data.vendor_company
                : null;
        const normalizeName = (value) => (typeof value === "string" ? value.trim() : "");
        const vendorTradeName = normalizeName(vendorCompanyData?.trade_name);
        const vendorCompanyLabel = normalizeName(vendorCompanyData?.name);
        const vendorFallbackName = vendorCompanyName !== "-" ? vendorCompanyName : "";
        const vendorLegalName = normalizeName(vendorCompanyData?.legal_name);
        const vendorDisplayName =
            vendorTradeName || vendorCompanyLabel || normalizeName(vendorFallbackName) || vendorLegalName;
        const vendorDisplayHtml = vendorDisplayName ? `<strong>${escapeHtml(vendorDisplayName)}</strong>` : "";
        return {
            vendorDisplayHtml,
            adminEntityHtml: vendorDisplayHtml || "El cliente",
        };
    })();
    const deadlineLabelHtml = deadlineInfo.label ? escapeHtml(deadlineInfo.label) : "";
    const professionalExpiredHtml = deadlineLabelHtml
        ? `El plazo para realizar la transferencia venció el ${deadlineLabelHtml}. Ponte en contacto con tu comercial asignado.`
        : escapeHtml(
              "El plazo para realizar la transferencia venció. Ponte en contacto con tu comercial asignado."
          );
    const adminExpiredBaseHtml = deadlineLabelHtml
        ? `El plazo para realizar la transferencia venció el ${deadlineLabelHtml}.`
        : escapeHtml("El plazo para realizar la transferencia ha vencido.");
    const adminExpiredHtml = vendorDisplayHtml
        ? `${adminExpiredBaseHtml} Ponte en contacto con ${vendorDisplayHtml}.`
        : `${adminExpiredBaseHtml} Ponte en contacto con el cliente.`;
    const professionalActiveHtml = hasTransferDeadline && deadlineLabelHtml
        ? `Recuerda realizar la transferencia antes del ${deadlineLabelHtml} para activar la garantía.`
        : escapeHtml("Recuerda realizar la transferencia para activar tu garantía.");
    const adminActiveHtml = hasTransferDeadline && deadlineLabelHtml
        ? `${adminEntityHtml} tiene hasta el ${deadlineLabelHtml} para realizar la transferencia.`
        : `${adminEntityHtml} debe realizar la transferencia para activar la garantía.`;
    const buildNoteHtml = (activeHtml, expiredHtml) => {
        const classes = ["detail__payment-note"];
        let content = activeHtml;
        if (hasTransferDeadline && deadlineInfo.expired) {
            classes.push("detail__payment-note--expired");
            content = expiredHtml;
        }
        return `<p class="${classes.join(" ")}">${content}</p>`;
    };
    const professionalNoteHtml = buildNoteHtml(professionalActiveHtml, professionalExpiredHtml);
    const adminNoteHtml = buildNoteHtml(adminActiveHtml, adminExpiredHtml);
    const professionalValidationHtml = escapeHtml(
        "Has confirmado que has realizado la transferencia. El equipo de 360VO revisará la información y activará tu garantía en un plazo máximo de 72 horas. Recibirás un correo cuando esté activa."
    );
    const validationBaseHtml = vendorDisplayHtml
        ? `${vendorDisplayHtml} ha indicado que ha realizado la transferencia.`
        : escapeHtml("El cliente ha indicado que ha realizado la transferencia.");
            const adminValidationHtml = `${validationBaseHtml} Revisa la operación y activa la garantía cuando proceda.`;
            const staffValidationHtml = validationBaseHtml;
                const cancellationDateRaw = pickEstadoGarantiaField("fecha_cancelacion", "");
        const cancellationDateFmt = pickEstadoGarantiaField("fecha_cancelacion_fmt", cancellationDateRaw);
        const cancellationReason = pickEstadoGarantiaField("motivo_cancelacion", "");
        const cancellationOther = pickEstadoGarantiaField("otra_causa", "");

        const paymentHtml = (() => {
            if (isCancelada) {
                const cancelDateLabel = formatCancellationDate(cancellationDateFmt || cancellationDateRaw);
                const cancelTarget = vendorDisplayHtml || "<strong>360VO</strong>";
                const cancelText = cancelDateLabel
                    ? `La garantía ha sido cancelada el ${cancelDateLabel}.`
                    : "La garantía ha sido cancelada.";
                const reasonDisplay = cancellationReason && cancellationReason !== "Otra causa"
                    ? cancellationReason
                    : (cancellationOther || cancellationReason);
                const contactLine =
                    isProfesional || isParticular
                        ? "Ponte en contacto con tu comercial asignado o con el Departamento Comercial de 360VO."
                        : `Ponte en contacto con ${cancelTarget}.`;
                const reasonRow = reasonDisplay
                    ? `<span class="detail__payment-note-row"><span class="detail__payment-note-label">Motivo:</span> <span class="detail__payment-note-value">${escapeHtml(reasonDisplay)}</span></span>`
                    : "";
                const cancelNoteHtml =
                    `<p class="detail__payment-note detail__payment-note--cancelled">` +
                        `<span class="detail__payment-note-icon" aria-hidden="true">${warningIcon}</span>` +
                        `<span class="detail__payment-note-text">` +
                            `<span class="detail__payment-note-row">${cancelText}</span>` +
                            `${reasonRow}` +
                            `<span class="detail__payment-note-row detail__payment-note-subtext">${contactLine}</span>` +
                        `</span>` +
                    `</p>`;
                return `<section class="detail__section detail__section--payment">` +
                        `${cancelNoteHtml}` +
                        `</section>`;
            }
        if (isAdmin && metodoPago.startsWith("domiciliacion") && !cobroRealizado) {
            const concepto = `Garantía ${pickField("matricula")}`;
            const cantidad = `${pickField("precio", "0")} €`;
            const iban =
                data.iban_vendedor ||
                rowData.iban_vendedor ||
                "ES00 0000 0000 0000 0000 0000";
            return `<section class="detail__section detail__section--payment">
                                <p class="detail__payment-note detail__payment-note--domiciliacion">Cobro pendiente por domiciliación bancaria.</p>
                                <table class="detail__transfer-table">
                                        <tbody>
                                                <tr data-copy-row><th>Concepto</th><td data-copy-cell data-tooltip="Copiar concepto"><span data-concepto>${concepto}</span><button type="button" class="detail__copy-btn" data-copy="[data-concepto]" data-label="Copiar concepto" data-done="Concepto copiado" data-toast="Concepto copiado al portapapeles." aria-label="Copiar concepto">${copyIcon}</button></td></tr>
                                                <tr data-copy-row><th>Cantidad</th><td data-copy-cell data-tooltip="Copiar cantidad"><span data-amount>${cantidad}</span><button type="button" class="detail__copy-btn" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad">${copyIcon}</button></td></tr>
                                                <tr data-copy-row><th>IBAN</th><td data-copy-cell data-tooltip="Copiar IBAN"><span data-iban>${iban}</span><button type="button" class="detail__copy-btn" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN">${copyIcon}</button></td></tr>
                                        </tbody>
                                </table>
                                <div class="detail__copy-toast" aria-hidden="true"></div>
                        </section>`;
        }
        if ((isPendientePago || isValidacionPendiente) && metodoPago === "transferencia") {
            const concepto = `Garantía ${pickField("matricula")}`;
            const cantidad = `${pickField("precio", "0")} €`;
            const ibanRow = transferIban
                ? `<tr data-copy-row><th>IBAN</th><td data-copy-cell data-tooltip="Copiar IBAN"><span data-iban>${transferIban}</span><button type="button" class="detail__copy-btn" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN">${copyIcon}</button></td></tr>`
                : "";
            const noteHtml = isValidacionPendiente
                ? `<p class="detail__payment-note detail__payment-note--validation">${
                      isAdmin
                          ? isCoreAdmin
                              ? adminValidationHtml
                              : staffValidationHtml
                          : professionalValidationHtml
                  }</p>`
                : isAdmin
                ? adminNoteHtml
                : professionalNoteHtml;
            const tableHtml = `<table class="detail__transfer-table">
                                        <tbody>
                                                <tr data-copy-row><th>Concepto</th><td data-copy-cell data-tooltip="Copiar concepto"><span data-concepto>${concepto}</span><button type="button" class="detail__copy-btn" data-copy="[data-concepto]" data-label="Copiar concepto" data-done="Concepto copiado" data-toast="Concepto copiado al portapapeles." aria-label="Copiar concepto">${copyIcon}</button></td></tr>
                                                <tr data-copy-row><th>Cantidad</th><td data-copy-cell data-tooltip="Copiar cantidad"><span data-amount>${cantidad}</span><button type="button" class="detail__copy-btn" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad">${copyIcon}</button></td></tr>
                                                ${ibanRow}
                                        </tbody>
                                </table>`;
            const toggleLabel = "Ver detalles de la transferencia";
            const closedIcon = arrowDownIcon || "&#9660;";
            const openIcon = arrowUpIcon || "&#9650;";
            const tableMarkup = isAdmin || isValidacionPendiente
                ? `<details class="detail__transfer-toggle">
                                        <summary class="detail__transfer-toggle-summary">
                                                <span class="detail__transfer-toggle-label">${toggleLabel}</span>
                                                <span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--closed" aria-hidden="true">${closedIcon}</span>
                                                <span class="detail__transfer-toggle-icon detail__transfer-toggle-icon--open" aria-hidden="true">${openIcon}</span>
                                        </summary>
                                        <div class="detail__transfer-toggle-content">${tableHtml}</div>
                                </details>`
                : tableHtml;
            return `<section class="detail__section detail__section--payment">
                                ${noteHtml}
                                ${tableMarkup}
                                <div class="detail__copy-toast" aria-hidden="true"></div>
                        </section>`;
        }
        if (!isAdmin && isPendientePago) {
            return `<section class="detail__section detail__section--payment">
                                <p class="detail__payment-note">El pago se procesará mediante domiciliación bancaria.</p>
                        </section>`;
        }
        return "";
    })();
        return `
        <div class="guarantee-detail__inner">
                <div class="guarantee-detail__header"${headerAccentStyle}>
                        <h2>Garantía ${pickField("matricula")}</h2>
                        ${planTitleHtml}
                        ${coverageHtml}
                        <div class="${badgeClase}">${pickField("estado", "Desconocido")}</div>
                </div>
                ${paymentHtml}
                ${billingSectionHtml}
                ${managementSectionHtml}
                ${showChannelSection
                        ? `<section class="detail__section detail__section--channel">
                                <h3 class="detail__section-title">
                                        <span>Canal de venta</span>
                                </h3>
                                <div class="vendor-card">
                                <div class="vendor-card__header">
                                        <div class="vendor-card__primary">
                                                ${vendorAvatarWrapper}
                                                <div class="vendor-card__info">
                                                        <p class="vendor-card__name">${vendorCompanyName}</p>
                                                        <p class="vendor-card__contact">${vendorContactDisplay}</p>
                                                </div>
                                        </div>
                                        ${vendorDetailsHref
                                            ? `<a href="${vendorDetailsHref}" class="fast-actions__link vendor-card__quick-link">` +
                                                  `<span class="fast-actions__icon" aria-hidden="true">${personIcon}</span>` +
                                                  `<span class="fast-actions__label">Ver cliente</span>` +
                                              `</a>`
                                            : ""}
                                </div>
                                        ${vendorActionsHtml
                                            ? `<div class="vendor-card__actions">${vendorActionsHtml}</div>`
                                            : ``}
                                        <div class="vendor-card__footer">
                                                <a href="${vendorDetailsHref}" class="vendor-card__cta vendor-card__cta--details">
                                                        <span class="vendor-card__cta-icon" aria-hidden="true">${personIcon}</span>
                                                        <span class="vendor-card__cta-label">Ver ficha del cliente</span>
                                                </a>
                                                <button type="button" class="vendor-card__cta vendor-card__cta--contact">
                                                        <span class="vendor-card__cta-icon" aria-hidden="true">${personAddIcon}</span>
                                                        <span class="vendor-card__cta-label">Añadir contacto</span>
                                                </button>
                                        </div>
                                </div>
                        </section>`
                        : ""}
                ${docsSectionHtml}
                ${vehicleSectionHtml}
                ${technicalSectionHtml}
                ${customerSectionHtml}
                ${actionsHtml}
                ${deleteActionHtml}
        </div>
    `;
}

function syncPdfModalDocs(panel) {
        const modal = document.querySelector(".pdf-modal");
        if (!modal) return;
        const modalList = modal.querySelector(".pdf-modal__docs-list");
        const panelList = panel.querySelector(".detail__docs-list");
        if (modalList) {
                modalList.innerHTML = "";
        }
        if (modalList && panelList) {
                const panelButtons = Array.from(
                        panelList.querySelectorAll(".detail__docs-btn")
                );
                panelButtons.forEach((btn, idx) => {
                        btn.dataset.docIndex = String(idx);
                });
        panelButtons.forEach((btn, idx) => {
                const item = btn.closest(".detail__docs-item");
                if (!item) return;
                const clone = item.cloneNode(true);
                const cloneBtn = clone.querySelector(
                        ".detail__docs-btn"
                );
                if (cloneBtn) {
                        cloneBtn.dataset.docIndex = String(idx);
                }
                modalList.appendChild(clone);
        });
        updateModalControls(modal);
    } else if (modalList) {
        modalList.innerHTML = "";
        updateModalControls(modal);
    }
    const subtitle = modal.querySelector(".pdf-modal-subttitle");
        if (subtitle) {
                const mat = panel.dataset.matricula || "";
                subtitle.textContent = mat ? `Garantía ${mat}` : "";
        }
}

function updateModalControls(modal) {
        if (!modal) return;
        const buttons = Array.from(
                modal.querySelectorAll(".pdf-modal__docs-list .detail__docs-btn")
        );
        const docButtons = buttons.filter((btn) => btn.dataset.docKey !== ADD_DOC_KEY);
        const download = modal.querySelector(".pdf-modal__download");
        const nav = modal.querySelector(".pdf-modal__nav");
        const docList = modal.querySelector(".pdf-modal__docs-list");
        const header = modal.querySelector(".pdf-modal__header");
        const prevBtn = modal.querySelector(".pdf-modal__nav-btn--prev");
        const nextBtn = modal.querySelector(".pdf-modal__nav-btn--next");
        const hasSingleDoc = docButtons.length === 1;
        const hideNav = docButtons.length <= 1;
        if (download) {
                download.hidden = docButtons.length === 0;
        }
        if (nav) {
                nav.hidden = hideNav;
        }
        if (docList) {
                docList.hidden = hasSingleDoc;
        }
        if (header) {
                header.hidden = docButtons.length <= 1;
        }
        if (prevBtn) {
                prevBtn.hidden = hideNav;
                prevBtn.disabled = hideNav;
        }
        if (nextBtn) {
                nextBtn.hidden = hideNav;
                nextBtn.disabled = hideNav;
        }
}

async function activateRow(row, options = {}) {
                        if (!row) {
                                return;
                        }
                        if (!isDesktopView()) {
                                setMobileEmptyHidden(true);
                        }
                        setMobileSummaryOpen(false);
                        const rows = Array.from(
                                document.querySelectorAll(".guarantees-table__row")
                        );
                        const idx = rows.indexOf(row);
                        if (idx === -1) {
                                return;
                        }

                        const previousIdx = prevIdx;
                        let forward;
                        if (options.direction === "forward") {
                                forward = true;
                        } else if (options.direction === "back") {
                                forward = false;
                        } else {
                                forward = previousIdx === null || idx > previousIdx;
                        }

                        rows.forEach((r) => {
                                if (r !== row) {
                                        r.classList.remove("selected");
                                }
                        });
                        row.classList.add("selected");

                        prevSelectedRow = row;
                        prevIdx = idx;
                        lastDetailTrigger = row;
                        openMobileDetail({ focus: true });
                        pendingMatSelection = false;
                        initialMatQuery = "";

                        const matricula = row.dataset.matricula || "";
                        if (options.updateHistory !== false) {
                                if (matricula) {
                                        history.replaceState(
                                                null,
                                                "",
                                                `?matricula=${encodeURIComponent(matricula)}`
                                        );
                                } else {
                                        history.replaceState(null, "", window.location.pathname);
                                }
                        }

                        const cacheKey = row.dataset.id ? String(row.dataset.id) : "";

                        const currentActive = activePanel;
                        const nextPanel = activePanel === panel1 ? panel2 : panel1;
                        nextPanel.classList.remove("is-loading");
                        const isDesktop = isDesktopView();
                        const rowData = buildRowData(row);
                        const cachedDetail = cacheKey ? detailCache.get(cacheKey) : null;

                        if (cachedDetail) {
                                nextPanel.innerHTML = renderFullDetail(cachedDetail, rowData);
                                setupTransferCountdown(nextPanel);
                                nextPanel.dataset.matricula =
                                        cachedDetail.matricula || rowData.matricula || "";
                                nextPanel.dataset.plan = cachedDetail.plan || rowData.plan || "";
                                hydrateManagementDataset(nextPanel, cachedDetail, rowData);
                                syncManagementDetailFields(nextPanel);
                                syncPdfModalDocs(nextPanel);
                        } else {
                                const fallbackPlate =
                                        rowData.matricula || row.dataset.matricula || initialMatQuery || "";
                                const loadingOptions = { plate: fallbackPlate };
                                if (!isDesktop) {
                                        loadingOptions.showHeader = true;
                                }
                                nextPanel.innerHTML = renderEmptyDetail("loading", loadingOptions);
                                nextPanel.classList.add("is-loading");
                                nextPanel.dataset.matricula = rowData.matricula || fallbackPlate || "";
                                nextPanel.dataset.plan = rowData.plan || "";
                                hydrateManagementDataset(nextPanel, {}, rowData);
                                syncManagementDetailFields(nextPanel);
                                syncPdfModalDocs(nextPanel);
                        }

                        nextPanel.dataset.loadedId = cacheKey;

                        activePanel = nextPanel;
                        inactivePanel = currentActive;
                        if (currentActive) {
                                currentActive.classList.remove("slide-in-left", "slide-in-right");
                        }

                        if (isDesktop) {
                                if (currentActive) {
                                        currentActive.classList.add(
                                                forward ? "slide-out-left" : "slide-out-right"
                                        );
                                        currentActive.classList.remove("active");
                                        currentActive.addEventListener(
                                                "animationend",
                                                () => {
                                                        currentActive.classList.remove(
                                                                "slide-out-left",
                                                                "slide-out-right"
                                                        );
                                                        nextPanel.classList.remove(
                                                                "slide-in-left",
                                                                "slide-in-right"
                                                        );
                                                },
                                                { once: true }
                                        );
                                }

                                nextPanel.classList.add(forward ? "slide-in-right" : "slide-in-left");
                                nextPanel.classList.add("active");
                        } else {
                                if (currentActive) {
                                        currentActive.classList.remove("slide-out-left", "slide-out-right");
                                        currentActive.classList.remove("active");
                                }
                                nextPanel.classList.remove("slide-in-left", "slide-in-right");
                                nextPanel.classList.add("active");
                        }

                        if (cacheKey && !cachedDetail) {
                                try {
                                        const data = await fetchDetail(cacheKey);
                                        if (nextPanel.dataset.loadedId === String(cacheKey)) {
                                                nextPanel.innerHTML = renderFullDetail(data, rowData);
                                                setupTransferCountdown(nextPanel);
                                                nextPanel.dataset.matricula =
                                                        data.matricula || rowData.matricula || "";
                                                nextPanel.dataset.plan = data.plan || rowData.plan || "";
                                                hydrateManagementDataset(nextPanel, data, rowData);
                                                syncManagementDetailFields(nextPanel);
                                                syncPdfModalDocs(nextPanel);
                                                nextPanel.classList.remove("is-loading");
                                        }
                                } catch (error) {
                                        console.error("❌ Error fetch detalle:", error);
                                } finally {
                                        nextPanel.classList.remove("is-loading");
                                }
                        }
                }

                function initRowSelection() {
                        tbody.addEventListener("click", async function (e) {
                                const row = e.target.closest(".guarantees-table__row");
                                if (!row) return;
                                const rows = Array.from(
                                        document.querySelectorAll(".guarantees-table__row")
                                );
                                const idx = rows.indexOf(row);
                                if (idx === -1) return;
                                const previousIdx = prevIdx;

                                if (row.classList.contains("selected")) {
                                        lastDetailTrigger = row;
                                        rows.forEach((r) => r.classList.remove("selected"));
                                        prevSelectedRow = null;
                                        prevIdx = null;
                                        history.replaceState(null, "", window.location.pathname);
                                        setEmptyDetailPanel(
                                                previousIdx !== null && idx < previousIdx ? "back" : "forward",
                                                "awaiting"
                                        );
                                        return;
                                }

                                try {
                                        await activateRow(row, {
                                                direction:
                                                        previousIdx === null || idx > previousIdx
                                                                ? "forward"
                                                                : "back",
                                        });
                                } catch (error) {
                                        console.error("❌ Error al activar la garantía:", error);
                                }
                        });

                        tbody.addEventListener("keydown", function (e) {
                                if (e.key !== "Enter") return;
                                const row = e.target.closest(".guarantees-table__row");
                                if (row) row.click();
                        });
                }
                initRowSelection();

                if (mobileCardsList) {
                        mobileCardsList.addEventListener("click", async (event) => {
                                const trigger = event.target.closest("[data-mobile-detail-trigger]");
                                if (!trigger) {
                                        return;
                                }
                                const card = trigger.closest(".guarantee-card");
                                if (!card || !card.dataset.id) {
                                        return;
                                }
                                const row = findRowById(card.dataset.id);
                                if (!row) {
                                        return;
                                }
                                try {
                                        await activateRow(row, { direction: "forward" });
                                } catch (error) {
                                        console.error("❌ Error al abrir la garantía desde la tarjeta:", error);
                                }
                        });
                }

                syncMobileCardsEmptyState();

                (() => {
                        const modal = document.querySelector(".pdf-modal");
                        if (!modal) return;
                        const iframe = modal.querySelector(".pdf-modal__iframe");
                        const dl = modal.querySelector(".pdf-modal__download");
                        const prevBtn = modal.querySelector(".pdf-modal__nav-btn--prev");
                        const nextBtn = modal.querySelector(".pdf-modal__nav-btn--next");
                        const navContainer = modal.querySelector(".pdf-modal__nav");
                        const spinner = modal.querySelector(".pdf-modal__spinner");
                        const uploadView = modal.querySelector(".pdf-modal__upload");
                        let currentIdx = -1;

                        iframe.addEventListener("load", () => {
                                if (!spinner) {
                                        return;
                                }
                                const currentSrc = iframe.getAttribute("src") || "";
                                if (!currentSrc || currentSrc === "about:blank") {
                                        return;
                                }
                                spinner.classList.remove("active");
                        });

                        function getButtons() {
                                return Array.from(
                                        modal.querySelectorAll(".pdf-modal__docs-list .detail__docs-btn")
                                );
                        }

                        function showUploadView() {
                                if (spinner) spinner.classList.remove("active");
                                if (uploadView) uploadView.hidden = false;
                                if (iframe) {
                                        iframe.hidden = true;
                                        iframe.src = "";
                                }
                                if (dl) {
                                        dl.hidden = true;
                                        dl.removeAttribute("href");
                                }
                                if (navContainer) {
                                        navContainer.hidden = true;
                                }
                                if (prevBtn) {
                                        prevBtn.hidden = true;
                                        prevBtn.disabled = true;
                                }
                                if (nextBtn) {
                                        nextBtn.hidden = true;
                                        nextBtn.disabled = true;
                                }
                        }

        function showPdfView(buttons) {
                if (uploadView) uploadView.hidden = true;
                if (iframe) iframe.hidden = false;
                                if (dl) dl.hidden = false;
                                const docButtons = buttons.filter(
                                        (btn) => btn.dataset.docKey !== ADD_DOC_KEY
                                );
                                const hideNav = docButtons.length <= 1;
                                const hideHeader = docButtons.length === 1;
                                const docList = modal.querySelector(".pdf-modal__docs-list");
                                if (navContainer) navContainer.hidden = hideNav;
                                if (prevBtn) prevBtn.hidden = hideNav;
                                if (nextBtn) nextBtn.hidden = hideNav;
                                if (prevBtn) prevBtn.disabled = hideNav;
                                if (nextBtn) nextBtn.disabled = hideNav;
                if (docList) docList.hidden = hideHeader;
        }

        const toggleDocButtonLoading = (btn, isLoading) => {
                if (!btn) return;
                btn.classList.toggle("detail__docs-btn--loading", Boolean(isLoading));
        };

        function openDocByIndex(idx) {
                const buttons = getButtons();
                if (idx < 0 || idx >= buttons.length) return;
                const prevIdx = currentIdx;
                currentIdx = idx;
                const btn = buttons[idx];
                buttons.forEach((b) => b.classList.remove("detail__docs-btn--loading"));
                const rawUrl = btn.dataset.docUrl || "";
                const docKey = btn.dataset.docKey || "";
                const isAddDoc = docKey === ADD_DOC_KEY;
                                buttons.forEach((b, i) => b.classList.toggle("active", i === idx));
                                if (prevBtn) {
                                        prevBtn.disabled = idx === 0;
                                }
                                if (nextBtn) {
                                        nextBtn.disabled = idx === buttons.length - 1;
                                }

                if (isAddDoc) {
                        showUploadView();
                        toggleDocButtonLoading(btn, false);
                        return;
                }

                const url = normalizeDocUrl(rawUrl);
                if (!url) {
                        if (spinner) spinner.classList.remove("active");
                        if (iframe) {
                                iframe.hidden = false;
                                iframe.src = "about:blank";
                        }
                        toggleDocButtonLoading(btn, false);
                        return;
                }

                                showPdfView(buttons);

                if (dl) {
                    const dlUrl = url.includes("?")
                            ? `${url}&download=1`
                            : `${url}?download=1`;
                    dl.href = dlUrl;
                    const downloadText = btn.dataset.docDownload || "Descargar documento";
                    const downloadLabel = dl.querySelector(".pdf-modal__download-text");
                    if (downloadLabel) {
                        downloadLabel.textContent = downloadText;
                    } else {
                        dl.textContent = downloadText;
                    }
                    dl.setAttribute("aria-label", downloadText);
                    if (btn.dataset.docFilename) {
                            dl.download = btn.dataset.docFilename;
                    } else {
                            dl.removeAttribute("download");
                    }
                    dl.hidden = false;
                }

                                const applyIframeSrc = (srcUrl) => {
                                        if (!iframe || currentIdx !== idx) {
                                                return;
                                        }
                                        iframe.hidden = false;
                                        iframe.src = srcUrl;
                                        if (spinner) spinner.classList.remove("active");
                                };

                const cachedObjectUrl = getCachedDocumentObjectUrl(url);
                if (cachedObjectUrl) {
                        if (spinner) spinner.classList.remove("active");
                        applyIframeSrc(cachedObjectUrl);
                        toggleDocButtonLoading(btn, false);
                        return;
                }

                toggleDocButtonLoading(btn, true);
                if (spinner) spinner.classList.add("active");
                if (iframe) {
                        iframe.hidden = false;
                        iframe.src = "about:blank";
                }

                                ensureDocumentPreloaded(url)
                                        .then((objectUrl) => {
                                        if (!objectUrl) {
                                                throw new Error("Documento no disponible");
                                        }
                                        applyIframeSrc(objectUrl);
                                        toggleDocButtonLoading(btn, false);
                                })
                                .catch((error) => {
                                        if (currentIdx !== idx) {
                                                return;
                                        }
                                        console.error(
                                                "No se pudo cargar el documento",
                                                error
                                        );
                                        if (spinner) spinner.classList.remove("active");
                                        toggleDocButtonLoading(btn, false);
                                });
        }

                        const canUseHistory = () =>
                                panelHistory &&
                                typeof panelHistory.push === "function" &&
                                (!desktopMediaQuery || !desktopMediaQuery.matches);
                        let modalHistoryRegistered = false;

                        function ensureModalHistory() {
                                if (!canUseHistory() || modalHistoryRegistered) {
                                        return;
                                }
                                modalHistoryRegistered = true;
                                panelHistory.push("detail-docs", () => {
                                        closeModal({ silentHistory: true });
                                });
                        }

                        function releaseModalHistory({ silent = false } = {}) {
                                if (!modalHistoryRegistered) {
                                        return;
                                }
                                if (canUseHistory() && !silent) {
                                        panelHistory.close("detail-docs");
                                }
                                modalHistoryRegistered = false;
                        }

                        function openModal() {
                                modal.classList.add("visible");
                                ensureModalHistory();
                        }

                        function openDocByKey(key) {
                                if (!key) return;
                                const buttons = getButtons();
                                const idx = buttons.findIndex(
                                        (button) => button.dataset.docKey === key
                                );
                                if (idx !== -1) {
                                        openDocByIndex(idx);
                                }
                        }

                        document.addEventListener("click", (e) => {
                                const btn = e.target.closest(".detail__docs-btn");
                                if (btn) {
                                        const key = btn.dataset.docKey || "";
                                        if (key) {
                                                openDocByKey(key);
                                        } else {
                                                const idx = parseInt(btn.dataset.docIndex || "0", 10);
                                                openDocByIndex(idx);
                                        }
                                        openModal();
                                }
                        });

                        prevBtn.addEventListener("click", () => openDocByIndex(currentIdx - 1));
                        nextBtn.addEventListener("click", () => openDocByIndex(currentIdx + 1));

                        function closeModal(options = {}) {
                                const silentHistory = Boolean(options.silentHistory);
                                modal.classList.remove("visible");
                                if (iframe) {
                                        iframe.src = "";
                                }
                                if (dl) {
                                        dl.href = "#";
                                }
                                currentIdx = -1;
                                if (prevBtn) {
                                        prevBtn.disabled = true;
                                        prevBtn.hidden = false;
                                }
                                if (nextBtn) {
                                        nextBtn.disabled = true;
                                        nextBtn.hidden = false;
                                }
                                if (uploadView) {
                                        uploadView.hidden = true;
                                }
                                if (iframe) {
                                        iframe.hidden = false;
                                }
                                if (navContainer) {
                                        navContainer.hidden = false;
                                }
                                if (dl) {
                                        dl.hidden = false;
                                }
                                if (spinner) spinner.classList.remove("active");
                                getButtons().forEach((b) => b.classList.remove("active"));
                                updateModalControls(modal);
                                releaseModalHistory({ silent: silentHistory });
                        }

                        modal.querySelector(".pdf-modal__close").addEventListener("click", closeModal);
                        modal.addEventListener(
                                "click",
                                (e) => e.target === modal && closeModal()
                        );
                        document.addEventListener("keydown", (e) => {
                                if (e.key === "Escape" && modal.classList.contains("visible")) {
                                        closeModal();
                                }
                        });
                })();

                (() => {
                        const filters = document.querySelector(".guarantees-list__filters"),
                                header = document.querySelector(".top-bar");
                        let filtersSticky = filters
                                ? filters.classList.contains("sticky-active")
                                : false;
                        if (filters && header) {
                                new IntersectionObserver(
                                        ([entry]) => {
                                                const isSticky = !entry.isIntersecting;
                                                filters.classList.toggle("sticky-active", isSticky);
                                                if (listContainer) {
                                                        listContainer.classList.toggle(
                                                                "sticky-active",
                                                                isSticky
                                                        );
                                                }
                                                if (detail) {
                                                        detail.classList.toggle(
                                                                "sticky-active",
                                                                isSticky
                                                        );
                                                }
                                                if (isSticky && !filtersSticky) {
                                                        if (
                                                                typeof window !== "undefined" &&
                                                                typeof window.dispatchEvent === "function"
                                                        ) {
                                                                window.dispatchEvent(
                                                                        new CustomEvent(
                                                                                "go360:notifications:close"
                                                                        )
                                                                );
                                                                window.dispatchEvent(
                                                                        new CustomEvent("go360:profile:close")
                                                                );
                                                        }
                                                }
                                                filtersSticky = isSticky;
                                        },
                                        { root: null, threshold: 0, rootMargin: "-50px" }
                                ).observe(header);
                        }
                        const getListScrollTop = () => {
                                if (isDesktopView()) {
                                        if (
                                                tableScrollContainer &&
                                                tableScrollContainer.scrollHeight >
                                                        tableScrollContainer.clientHeight
                                        ) {
                                                return tableScrollContainer.scrollTop || 0;
                                        }
                                        if (listContainer) {
                                                return listContainer.scrollTop || 0;
                                        }
                                }
                                return (
                                        window.pageYOffset ||
                                        document.documentElement.scrollTop ||
                                        document.body.scrollTop ||
                                        0
                                );
                        };
                        const onScroll = () => {
                                const activePanel = detail
                                        ? detail.querySelector(".guarantee-detail__panel.active")
                                        : null;
                                const detailScrolled = activePanel ? activePanel.scrollTop > 10 : false;
                                document.body.classList.toggle(
                                        "scrolled",
                                        getListScrollTop() > 10 || detailScrolled
                                );
                        };
                        if (listContainer) {
                                listContainer.addEventListener("scroll", onScroll);
                        }
                        if (
                                tableScrollContainer &&
                                tableScrollContainer !== listContainer
                        ) {
                                tableScrollContainer.addEventListener(
                                        "scroll",
                                        onScroll
                                );
                        }
                        if (detail) {
                                detail.querySelectorAll(".guarantee-detail__panel").forEach((p) =>
                                        p.addEventListener("scroll", onScroll)
                                );
                        }
                        window.addEventListener("scroll", onScroll, { passive: true });
                })();

                function buildFilterValueMap(items) {
                        const map = new Map();
                        if (!Array.isArray(items)) {
                                return map;
                        }
                        items.forEach((item) => {
                                if (item === null || typeof item === "undefined") {
                                        return;
                                }
                                if (typeof item === "string") {
                                        const key = item.trim();
                                        if (!key) {
                                                return;
                                        }
                                        if (!map.has(key)) {
                                                map.set(key, { count: 0 });
                                        }
                                        return;
                                }
                                if (typeof item === "object") {
                                        const key = (item.value || item.slug || "").toString().trim();
                                        if (!key) {
                                                return;
                                        }
                                        const rawCount = Number.parseInt(item.count, 10);
                                        const count = Number.isFinite(rawCount) && rawCount > 0 ? rawCount : 0;
                                        if (map.has(key)) {
                                                const existing = map.get(key);
                                                existing.count = Math.max(existing.count, count);
                                                existing.data = item;
                                        } else {
                                                map.set(key, { count, data: item });
                                        }
                                }
                        });
                        return map;
                }

                function syncChannelOptionVisibility(channels = [], vendorTypes = []) {
                        if (!canalSelect) {
                                return;
                        }

                        const channelMap = buildFilterValueMap(channels);
                        const vendorTypeMap = buildFilterValueMap(vendorTypes);
                        let selectionChanged = false;

                        Array.from(canalSelect.options).forEach((option) => {
                                if (option.value === "") {
                                        return;
                                }
                                const channel = option.dataset.channel || option.value || "";
                                const vendorType = option.dataset.vendorType || "";
                                let visible = true;
                                if (vendorType) {
                                        if (vendorTypeMap.size === 0) {
                                                visible = true;
                                        } else {
                                                const entry = vendorTypeMap.get(vendorType);
                                                if (!entry) {
                                                        visible = false;
                                                } else if (
                                                        Object.prototype.hasOwnProperty.call(entry, "count")
                                                ) {
                                                        const rawCount = Number(entry.count);
                                                        visible = Number.isNaN(rawCount) ? true : rawCount > 0;
                                                } else {
                                                        visible = true;
                                                }
                                        }
                                } else if (channel) {
                                        if (channelMap.size > 0) {
                                                const entry = channelMap.get(channel);
                                                if (!entry) {
                                                        visible = false;
                                                } else if (
                                                        Object.prototype.hasOwnProperty.call(entry, "count")
                                                ) {
                                                        const rawCount = Number(entry.count);
                                                        visible = Number.isNaN(rawCount) ? true : rawCount > 0;
                                                } else {
                                                        visible = true;
                                                }
                                        }
                                }
                                option.hidden = !visible;
                                option.disabled = !visible;
                                option.style.display = visible ? "" : "none";
                        });

                        const selectedOption = canalSelect.options[canalSelect.selectedIndex];
                        if (selectedOption && (selectedOption.hidden || selectedOption.disabled)) {
                                canalSelect.value = "";
                                selectedCanal = "";
                                selectedVendorType = "";
                                selectionChanged = true;
                        }

                        refreshClientsVisibility();

                        if (selectionChanged) {
                                applyFilters();
                        }
                }

                function setOrderSelection(key, { trigger = true } = {}) {
                        if (!orderOptions.has(key)) {
                                return;
                        }
                        const config = orderOptions.get(key) || {};
                        selectedOrderKey = key;
                        selectedOrderBy = config.orderBy || "created";
                        selectedOrderDirection =
                                config.direction === "asc" ? "asc" : "desc";

                        if (orderLabelNode && config.label) {
                                orderLabelNode.textContent = config.label;
                        }

                        if (orderMenu) {
                                orderMenu
                                        .querySelectorAll("[data-sort-key]")
                                        .forEach((button) => {
                                                const isActive =
                                                        button.dataset.sortKey === key;
                                                button.classList.toggle(
                                                        "is-active",
                                                        isActive
                                                );
                                                button.setAttribute(
                                                        "aria-checked",
                                                        isActive ? "true" : "false"
                                                );
                                        });
                        }

                        if (trigger) {
                                applyFilters();
                        }

                        updateResetVisibility();
                }

                function openOrderMenu() {
                        if (!orderRoot || !orderMenu || !orderToggle) {
                                return;
                        }
                        orderRoot.setAttribute("data-open", "true");
                        orderToggle.setAttribute("aria-expanded", "true");
                        orderMenu.removeAttribute("hidden");

                        if (!orderMenuPortalParent) {
                                orderMenuPortalParent = orderMenu.parentElement || null;
                        }
                        if (orderMenuPortalParent && !orderMenuPlaceholder) {
                                orderMenuPlaceholder = document.createComment("order-menu-placeholder");
                                orderMenuPortalParent.insertBefore(orderMenuPlaceholder, orderMenu);
                        }
                        if (orderMenu.parentElement !== document.body) {
                                document.body.appendChild(orderMenu);
                        }

                        orderMenu.style.position = "fixed";
                        orderMenu.style.maxHeight = "calc(100vh - 32px)";
                        orderMenu.style.overflowY = "auto";
                        orderMenu.style.zIndex = "9999";

                        const computeMinWidth = () => {
                                const hostRect = orderRoot.getBoundingClientRect();
                                const hostWidth = hostRect.width || 0;
                                const menuRect = orderMenu.getBoundingClientRect();
                                const currentWidth = menuRect.width || 0;
                                const width = Math.max(currentWidth, hostWidth, 220);
                                orderMenu.style.minWidth = `${Math.round(width)}px`;
                                return width;
                        };

                        const reposition = () => {
                                if (!orderMenu || !orderToggle) {
                                        return;
                                }
                                const rect = orderToggle.getBoundingClientRect();
                                const viewportWidth = window.innerWidth || document.documentElement.clientWidth || 0;
                                const viewportHeight = window.innerHeight || document.documentElement.clientHeight || 0;
                                const padding = 16;
                                const gap = 8;
                                const menuWidth = computeMinWidth();
                                let left = rect.right - menuWidth;
                                if (left < padding) {
                                        left = padding;
                                }
                                if (left + menuWidth > viewportWidth - padding) {
                                        left = Math.max(padding, viewportWidth - padding - menuWidth);
                                }
                                const menuHeight = orderMenu.getBoundingClientRect().height || 0;
                                let top = rect.bottom + gap;
                                if (menuHeight > 0 && top + menuHeight > viewportHeight - padding) {
                                        const altTop = rect.top - gap - menuHeight;
                                        top = Math.max(padding, altTop);
                                }
                                orderMenu.style.left = `${Math.round(left)}px`;
                                orderMenu.style.top = `${Math.round(top)}px`;
                        };

                        reposition();
                        window.addEventListener("resize", reposition);
                        window.addEventListener("scroll", reposition, true);

                        detachOrderMenuPortal = () => {
                                window.removeEventListener("resize", reposition);
                                window.removeEventListener("scroll", reposition, true);
                                orderMenu.style.position = "";
                                orderMenu.style.left = "";
                                orderMenu.style.top = "";
                                orderMenu.style.minWidth = "";
                                orderMenu.style.maxHeight = "";
                                orderMenu.style.overflowY = "";
                                orderMenu.style.zIndex = "";
                                if (orderMenuPortalParent) {
                                        if (orderMenuPlaceholder && orderMenuPlaceholder.parentNode === orderMenuPortalParent) {
                                                orderMenuPortalParent.insertBefore(orderMenu, orderMenuPlaceholder);
                                                orderMenuPlaceholder.remove();
                                        } else {
                                                orderMenuPortalParent.appendChild(orderMenu);
                                        }
                                }
                                orderMenuPlaceholder = null;
                        };
                }

                function closeOrderMenu() {
                        if (!orderRoot || !orderMenu || !orderToggle) {
                                return;
                        }
                        orderRoot.removeAttribute("data-open");
                        orderToggle.setAttribute("aria-expanded", "false");
                        if (typeof detachOrderMenuPortal === "function") {
                                detachOrderMenuPortal();
                                detachOrderMenuPortal = null;
                        }
                        orderMenu.setAttribute("hidden", "");
                }

                if (orderMenu) {
                        const buttons = Array.from(
                                orderMenu.querySelectorAll("[data-sort-key]")
                        );
                        buttons.forEach((button) => {
                                const key = button.dataset.sortKey || "";
                                if (!key) {
                                        return;
                                }
                                const orderBy = button.dataset.orderBy || "created";
                                const direction =
                                        (button.dataset.orderDirection || "desc").toLowerCase() ===
                                        "asc"
                                                ? "asc"
                                                : "desc";
                                const label = (button.textContent || "").trim();
                                orderOptions.set(key, {
                                        orderBy,
                                        direction,
                                        label,
                                });
                        });

                        let initialKey = orderToggle
                                ? orderToggle.getAttribute("data-default-sort") || ""
                                : "";
                        if (!initialKey || !orderOptions.has(initialKey)) {
                                const defaultButton = orderMenu.querySelector(
                                        "[data-default][data-sort-key]"
                                );
                                if (
                                        defaultButton &&
                                        orderOptions.has(defaultButton.dataset.sortKey || "")
                                ) {
                                        initialKey = defaultButton.dataset.sortKey;
                                }
                        }
                        if (!initialKey) {
                                const first = orderOptions.keys().next();
                                if (!first.done) {
                                        initialKey = first.value;
                                }
                        }
                        if (initialKey) {
                                setOrderSelection(initialKey, { trigger: false });
                        }

                        buttons.forEach((button) => {
                                button.addEventListener("click", () => {
                                        const key = button.dataset.sortKey || "";
                                        if (!key) {
                                                return;
                                        }
                                        closeOrderMenu();
                                        setOrderSelection(key);
                                });
                        });
                }

                if (orderToggle && orderMenu) {
                        orderToggle.addEventListener("click", (event) => {
                                event.preventDefault();
                                const isOpen = orderRoot?.getAttribute("data-open") === "true";
                                if (isOpen) {
                                        closeOrderMenu();
                                } else {
                                        openOrderMenu();
                                }
                        });
                }

                if (orderRoot && orderMenu) {
                        document.addEventListener("click", (event) => {
                                if (orderMenu.hasAttribute("hidden")) {
                                        return;
                                }
                                if (orderRoot.contains(event.target)) {
                                        return;
                                }
                                closeOrderMenu();
                        });

                        document.addEventListener("keydown", (event) => {
                                if (event.key !== "Escape") {
                                        return;
                                }
                                if (orderMenu.hasAttribute("hidden")) {
                                        return;
                                }
                                closeOrderMenu();
                                if (orderToggle) {
                                        orderToggle.focus();
                                }
                        });
                }

                async function fetchFilters() {
                        try {
                                const res = await fetch(
                                        `${restRoot}go/v1/guarantees/filters`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
                                if (!res.ok) throw res.status;
                                const {
                                        estados = [],
                                        planes = [],
                                        concesionarios = [],
                                        payment_methods: paymentMethods = [],
                                        channels = [],
                                        vendor_types: vendorTypes = [],
                                        commercials = [],
                                        periods = {},
                                } = await res.json();
                                if (estadoSelect) {
                                        estadoSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        estados.forEach((est) => {
                                                const opt = document.createElement("option");
                                                const val = typeof est === "object" ? est.value : est;
                                                const lbl = typeof est === "object" ? est.label : est;
                                                const normalizedValue = String(val);
                                                if (
                                                        isProfesional &&
                                                        normalizedValue === "expira_pronto"
                                                ) {
                                                        return;
                                                }
                                                if (
                                                        !canSeeVerifyCollectStates &&
                                                        [
                                                                "pendiente_cobro",
                                                                "validacion_pendiente",
                                                        ].includes(normalizedValue)
                                                ) {
                                                        return;
                                                }
                                                if (shouldRestrictEmptyStateOptions) {
                                                        const rawCount =
                                                                typeof est === "object" &&
                                                                est !== null &&
                                                                Object.prototype.hasOwnProperty.call(
                                                                        est,
                                                                        "count"
                                                                )
                                                                        ? Number(est.count)
                                                                        : null;
                                                        if (
                                                                rawCount !== null &&
                                                                !Number.isNaN(rawCount) &&
                                                                rawCount <= 0
                                                        ) {
                                                                return;
                                                        }
                                                }
                                                opt.value = val;
                                                opt.textContent = lbl;
                                                estadoSelect.appendChild(opt);
                                        });
                                }
                                if (planSelect) {
                                        planSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        planes.forEach((pl) => {
                                                const opt = document.createElement("option");
                                                opt.value = pl.id;
                                                opt.textContent = pl.title;
                                                planSelect.appendChild(opt);
                                        });
                                }
                                if (Array.isArray(concesionarios)) {
                                        rawClients = concesionarios.map((client) => ({
                                                id: client.id,
                                                name: client.name,
                                                type: client.type || "",
                                        }));
                                        populateClientsSelect(selectedVendorType);
                                }
                                if (Array.isArray(commercials)) {
                                        rawCommercials = commercials
                                                .map((commercial) => {
                                                        const rawId =
                                                                commercial &&
                                                                typeof commercial.id !== "undefined"
                                                                        ? commercial.id
                                                                        : commercial &&
                                                                          typeof commercial.ID !== "undefined"
                                                                          ? commercial.ID
                                                                          : 0;
                                                        const id = Number.parseInt(rawId, 10);
                                                        const primaryName =
                                                                commercial &&
                                                                typeof commercial.name === "string"
                                                                        ? commercial.name
                                                                        : "";
                                                        const fallbackName =
                                                                commercial &&
                                                                typeof commercial.display_name === "string"
                                                                        ? commercial.display_name
                                                                        : "";
                                                        return {
                                                                id: Number.isFinite(id) ? id : 0,
                                                                name: primaryName || fallbackName,
                                                        };
                                                })
                                                .filter((item) => item.id > 0 && item.name);
                                } else {
                                        rawCommercials = [];
                                }
                                populateCommercialSelect();
                                if (paymentSelect) {
                                        paymentSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        paymentMethods.forEach((method) => {
                                                const opt = document.createElement("option");
                                                if (method && typeof method === "object") {
                                                        opt.value = method.value;
                                                        opt.textContent = method.label;
                                                } else {
                                                        const value = typeof method === "string" ? method : "";
                                                        opt.value = value;
                                                        opt.textContent = value;
                                                }
                                                paymentSelect.appendChild(opt);
                                        });
                                }
                                syncPeriodFilters(periods);
                                syncChannelOptionVisibility(channels, vendorTypes);
                                refreshClientsVisibility();
                                updateResetVisibility();
                                if (advancedPanel && !advancedPanel.hasAttribute("hidden")) {
                                        requestAnimationFrame(updateAdvancedHeight);
                                }
                        } catch (e) {
                                console.error("❌ Error fetching filters:", e);
                        }
                }

                function applyFilters() {
                        currentPage = 1;
                        hasMore = true;
                        lastValidQuery = "";
                        lastValidResults = [];
                        const filtersSnapshot = getRequestFilters();
                        const cacheKey = filtersSnapshot.cacheKey;
                        tbody.innerHTML = "";
                        loadedIds.clear();
                        clearSelectionAndDetail();
                        setResultMessage("");
                        if (scrollEnd) {
                                scrollEnd.hidden = false;
                        }
                        const spinnerToken = setSpinnerVisible(true);
                        if (currentListAbort) currentListAbort.abort();
                        isLoading = false;
                        if (listCache.has(cacheKey)) {
                                renderFromCache(listCache.get(cacheKey), spinnerToken);
                                return;
                        }
                        loadPage(1, { spinnerToken });
                }

                if (estadoSelect)
                        estadoSelect.addEventListener("change", () => {
                                selectedEstado = estadoSelect.value;
                                applyFilters();
                                updateResetVisibility();
                        });
                if (planSelect)
                        planSelect.addEventListener("change", () => {
                                selectedPlan = planSelect.value;
                                applyFilters();
                                updateResetVisibility();
                        });
                if (paymentSelect)
                        paymentSelect.addEventListener("change", () => {
                                selectedPaymentMethod = paymentSelect.value;
                                applyFilters();
                                updateResetVisibility();
                        });
                if (canalSelect)
                        canalSelect.addEventListener("change", () => {
                                refreshClientsVisibility();
                                applyFilters();
                                updateResetVisibility();
                        });
                if (concesionarioSelect)
                        concesionarioSelect.addEventListener("change", () => {
                                selectedConcesionario = concesionarioSelect.value;
                                applyFilters();
                                updateResetVisibility();
                        });
                if (commercialSelect)
                        commercialSelect.addEventListener("change", () => {
                                selectedCommercial = commercialSelect.value;
                                applyFilters();
                                updateResetVisibility();
                        });

                if (yearSelect)
                        yearSelect.addEventListener("change", () => {
                                const value = yearSelect.value || "";
                                if (!value) {
                                        selectedYear = "";
                                        if (hasPeriodFilters) {
                                                const defaultYear = periodDefaults.year || "";
                                                const defaultFrom = periodDefaults.monthFrom || "";
                                                const defaultTo = periodDefaults.monthTo || "";
                                                selectedMonthFrom = defaultFrom;
                                                selectedMonthTo = defaultTo;
                                                if (monthFromSelect) {
                                                        monthFromSelect.value = defaultFrom || "";
                                                }
                                                if (monthToSelect) {
                                                        monthToSelect.value = defaultTo || "";
                                                }
                                                if (defaultYear && yearSelect.value !== defaultYear) {
                                                        yearSelect.value = "";
                                                }
                                        }
                                        updateMonthFieldVisibility();
                                        applyFilters();
                                        updateResetVisibility();
                                        return;
                                }
                                selectedYear = value;
                                const months = getMonthsForYear(value);
                                let defaultFrom =
                                        months.length > 0
                                                ? months[0]
                                                : periodDefaults.monthFrom || "1";
                                let defaultTo =
                                        value === String(currentPeriodYear)
                                                ? String(currentPeriodMonth)
                                                : months.length > 0
                                                ? months[months.length - 1]
                                                : periodDefaults.monthTo || "12";
                                const normalizedFrom =
                                        normalizeMonthValue(defaultFrom) || "1";
                                let normalizedTo =
                                        normalizeMonthValue(defaultTo) || normalizedFrom;
                                if (Number(normalizedFrom) > Number(normalizedTo)) {
                                        normalizedTo = normalizedFrom;
                                }
                                selectedMonthFrom = normalizedFrom;
                                selectedMonthTo = normalizedTo;
                                if (monthFromSelect) {
                                        monthFromSelect.value = normalizedFrom;
                                }
                                if (monthToSelect) {
                                        monthToSelect.value = normalizedTo;
                                }
                                updateMonthFieldVisibility();
                                applyFilters();
                                updateResetVisibility();
                        });

                if (monthFromSelect)
                        monthFromSelect.addEventListener("change", () => {
                                if (!hasPeriodFilters) {
                                        return;
                                }
                                if (!selectedYear) {
                                        const defaultFrom = periodDefaults.monthFrom || "";
                                        selectedMonthFrom = defaultFrom;
                                        monthFromSelect.value = defaultFrom || "";
                                        updateResetVisibility();
                                        return;
                                }
                                const normalized = normalizeMonthValue(
                                        monthFromSelect.value
                                );
                                if (!normalized) {
                                        const defaultFrom = periodDefaults.monthFrom || "";
                                        selectedMonthFrom = defaultFrom;
                                        monthFromSelect.value = defaultFrom || "";
                                } else {
                                        selectedMonthFrom = normalized;
                                        const normalizedTo = normalizeMonthValue(
                                                selectedMonthTo
                                        );
                                        if (
                                                normalizedTo &&
                                                Number(normalized) > Number(normalizedTo)
                                        ) {
                                                selectedMonthTo = normalized;
                                                if (monthToSelect) {
                                                        monthToSelect.value = normalized;
                                                }
                                        }
                                }
                                applyFilters();
                                updateResetVisibility();
                        });

                if (monthToSelect)
                        monthToSelect.addEventListener("change", () => {
                                if (!hasPeriodFilters) {
                                        return;
                                }
                                if (!selectedYear) {
                                        const defaultTo = periodDefaults.monthTo || "";
                                        selectedMonthTo = defaultTo;
                                        monthToSelect.value = defaultTo || "";
                                        updateResetVisibility();
                                        return;
                                }
                                const normalized = normalizeMonthValue(
                                        monthToSelect.value
                                );
                                if (!normalized) {
                                        const defaultTo = periodDefaults.monthTo || "";
                                        selectedMonthTo = defaultTo;
                                        monthToSelect.value = defaultTo || "";
                                } else {
                                        selectedMonthTo = normalized;
                                        const normalizedFrom = normalizeMonthValue(
                                                selectedMonthFrom
                                        );
                                        if (
                                                normalizedFrom &&
                                                Number(normalizedFrom) > Number(normalized)
                                        ) {
                                                selectedMonthFrom = normalized;
                                                if (monthFromSelect) {
                                                        monthFromSelect.value = normalized;
                                                }
                                        }
                                }
                                applyFilters();
                                updateResetVisibility();
                        });

                if (moreFiltersToggle && advancedPanel) {
                        const labelNode = moreFiltersToggle.querySelector(
                                ".guarantees-list__more-filters-label"
                        );
                        const defaultLabel =
                                moreFiltersToggle.getAttribute("data-default-label") ||
                                (labelNode ? labelNode.textContent || "" : "");
                        const activeLabel =
                                moreFiltersToggle.getAttribute("data-active-label") || defaultLabel;

                        setAdvancedOpen = (open) => {
                                if (!advancedPanel) return;
                                if (open) {
                                        advancedPanel.removeAttribute("hidden");
                                } else {
                                        advancedPanel.setAttribute("hidden", "");
                                }
                                moreFiltersToggle.setAttribute(
                                        "aria-expanded",
                                        open ? "true" : "false"
                                );
                                moreFiltersToggle.setAttribute(
                                        "data-open",
                                        open ? "true" : "false"
                                );
                                if (filtersRoot) {
                                        if (open) {
                                                filtersRoot.setAttribute(
                                                        "data-advanced-open",
                                                        "true"
                                                );
                                        } else {
                                                filtersRoot.removeAttribute(
                                                        "data-advanced-open"
                                                );
                                        }
                                }
                                if (labelNode) {
                                        labelNode.textContent = open
                                                ? activeLabel
                                                : defaultLabel;
                                }
                                requestAnimationFrame(updateAdvancedHeight);
                        };

                        moreFiltersToggle.addEventListener("click", () => {
                                if (!isDesktopView()) {
                                        setMobileFiltersOpen(true);
                                }
                                const isOpen = !advancedPanel.hasAttribute("hidden");
                                setAdvancedOpen(!isOpen);
                        });
                }

                refreshClientsVisibility();
                updateAdvancedHeight();
                fetchFilters();

                const input = document.getElementById("buscador_mis_garantias");
                const closeIcon = document.querySelector(".guarantees-list__close-icon");
                let debounceTimer = null;
                const DEBOUNCE_MS = 300;

                if (resetFiltersBtn) {
                        resetFiltersBtn.addEventListener("click", () => {
                                if (input) {
                                        input.value = "";
                                }
                                if (closeIcon) {
                                        closeIcon.classList.remove("visible");
                                }
                                searchQuery = "";
                                lastValidQuery = "";
                                lastValidResults = [];
                                if (estadoSelect) estadoSelect.value = "";
                                if (planSelect) planSelect.value = "";
                                if (paymentSelect) paymentSelect.value = "";
                                if (canalSelect) canalSelect.value = "";
                                if (concesionarioSelect) concesionarioSelect.value = "";
                                if (commercialSelect) commercialSelect.value = "";
                                selectedEstado = "";
                                selectedPlan = "";
                                selectedCanal = "";
                                selectedConcesionario = "";
                                selectedVendorType = "";
                                selectedPaymentMethod = "";
                                selectedCommercial = "";
                                if (hasPeriodFilters) {
                                        const defaultYear = periodDefaults.year || "";
                                        const defaultFrom = periodDefaults.monthFrom || "";
                                        const defaultTo = periodDefaults.monthTo || "";
                                        selectedYear = defaultYear;
                                        selectedMonthFrom = defaultFrom;
                                        selectedMonthTo = defaultTo;
                                        if (yearSelect) {
                                                yearSelect.value = defaultYear || "";
                                        }
                                        if (monthFromSelect) {
                                                monthFromSelect.value = defaultFrom || "";
                                        }
                                        if (monthToSelect) {
                                                monthToSelect.value = defaultTo || "";
                                        }
                                        updateMonthFieldVisibility();
                                }
                                currentPage = 1;
                                hasMore = true;
                                refreshClientsVisibility();
                                if (orderToggle) {
                                        const defaultSort =
                                                orderToggle.getAttribute("data-default-sort") ||
                                                "created_desc";
                                        if (defaultSort) {
                                                setOrderSelection(defaultSort, { trigger: false });
                                        }
                                } else {
                                        selectedOrderKey = "created_desc";
                                        selectedOrderBy = "created";
                                        selectedOrderDirection = "desc";
                                }
                                setResultMessage("");
                                applyFilters();
                                if (advancedPanel && !advancedPanel.hasAttribute("hidden")) {
                                        requestAnimationFrame(updateAdvancedHeight);
                                } else {
                                        updateBaseFiltersHeight();
                                        rootElement.style.setProperty(
                                                "--go-advanced-filters-height",
                                                "0px"
                                        );
                                }
                                updateResetVisibility();
                        });
                }

                function doSearch(query) {
                        searchQuery = query;
                        currentPage = 1;
                        hasMore = true;
                        const filtersSnapshot = getRequestFilters({ search: searchQuery });
                        const cacheKey = filtersSnapshot.cacheKey;
                        tbody.innerHTML = "";
                        loadedIds.clear();
                        clearSelectionAndDetail();
                        if (scrollEnd) {
                                scrollEnd.hidden = false;
                        }
                        const spinnerToken = setSpinnerVisible(true);
                        if (currentListAbort) currentListAbort.abort();
                        isLoading = false;
                        if (listCache.has(cacheKey)) {
                                renderFromCache(listCache.get(cacheKey), spinnerToken);
                                return;
                        }
                        loadPage(1, { search: searchQuery, spinnerToken });
                        updateResetVisibility();
                }

                input.addEventListener("input", () => {
                        const value = input.value.trim();
                        if (value.length > 0) {
                                closeIcon.classList.add("visible");
                        } else {
                                closeIcon.classList.remove("visible");
                                // SI EL INPUT QUEDA VACÍO, LIMPIA VARIABLES
                                lastValidQuery = "";
                                lastValidResults = [];
                        }
                        searchQuery = value;
                        updateResetVisibility();
                        if (debounceTimer) clearTimeout(debounceTimer);
                        debounceTimer = setTimeout(() => {
                                doSearch(value);
                        }, DEBOUNCE_MS);
                });

		closeIcon.addEventListener("click", () => {
			input.value = "";
			closeIcon.classList.remove("visible");
			input.focus();
			input.select();
			if (debounceTimer) clearTimeout(debounceTimer);
			searchQuery = "";
			currentPage = 1;
			hasMore = true;
                        lastValidQuery = "";
                        lastValidResults = [];
                        loadPage(1);
                        updateResetVisibility();
                });

                if (pendingMatSelection && initialMatQuery) {
                        setEmptyDetailPanel("forward", "loading", {
                                plate: initialMatQuery,
                                showHeader: !isDesktopView(),
                        });
                }
                const initialFiltersSnapshot = getRequestFilters();
                const initialCacheKey = initialFiltersSnapshot.cacheKey;
                let initialLoadPromise;
                const cachedInitialEntry = listCache.get(initialCacheKey);
                if (isCacheEntryUsable(cachedInitialEntry)) {
                        loadedIds.clear();
                        renderFromCache(cachedInitialEntry);
                        if (isCacheEntryFresh(cachedInitialEntry)) {
                                initialLoadPromise = Promise.resolve();
                        } else {
                                const spinnerToken = setSpinnerVisible(true);
                                initialLoadPromise = loadPage(1, { spinnerToken });
                        }
                } else {
                        initialLoadPromise = loadPage(1);
                }
                if (initialMatQuery) {
                        initialLoadPromise
                                .catch(() => {})
                                .finally(() => {
                                        if (initialMatQuery) {
                                                preloadByPlate(initialMatQuery);
                                        }
                                });
                }

                if (initialLoadPromise && typeof initialLoadPromise.finally === "function") {
                        initialLoadPromise.finally(() => {
                                startRealtimeUpdates();
                        });
                } else {
                        startRealtimeUpdates();
                }
        });
})();

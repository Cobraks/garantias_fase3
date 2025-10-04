// assets/js/modules/form-autosave.js
"use strict";

import FormCache from "./form-cache.js";
import {
        getRestRoot,
        getRestNonce,
        getIcon,
        getUserRole,
        getCurrentUserId,
        getMisGarantiasUrl,
        getNuevaGarantiaUrl,
        getDocumentUrl,
} from "./config.js";
import { AVAILABLE_DOCS } from "./docs-config.js";
import { getSelectedModalidadId, getVisibleModalidades } from "./form-state.js";
import { debounce, setError } from "./form-utils.js";
import { calcularRecargos, getDescuentosAplicables } from "./form-calculations.js";

const pdfCache = new Map();

const CHANNEL_NORMALIZATION = {
        profesional: "profesional",
        go_profesional: "profesional",
        particular: "particular",
        go_particular: "particular",
        gestoria: "gestoria",
        go_gestoria: "gestoria",
};

function normalizeChannel(value) {
        if (!value) return "";
        const key = String(value).toLowerCase();
        return CHANNEL_NORMALIZATION[key] || "";
}

async function loadStaticPdf(url, options = {}) {
        if (!url) return null;

        const isDynamicEndpoint = /\/wp-json\/go\/v1\/guarantees\//.test(url);
        const shouldCache = options.cache !== undefined ? !!options.cache : !isDynamicEndpoint;
        const cacheKey = options.cacheKey || url;

        if (shouldCache && pdfCache.has(cacheKey)) {
                return pdfCache.get(cacheKey);
        }

        const fetchOptions = {};
        if (isDynamicEndpoint) {
                fetchOptions.cache = "no-store";
        }

        const response = await fetch(url, fetchOptions);
        if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
        }
        const bytes = await response.arrayBuffer();
        if (shouldCache) {
                pdfCache.set(cacheKey, bytes);
        }
        return bytes;
}

function stableSerialize(value) {
        if (value === null || value === undefined) return null;
        if (typeof value !== "object") return value;
        if (Array.isArray(value)) {
                return value.map((item) => stableSerialize(item));
        }
        return Object.keys(value)
                .sort()
                .reduce((acc, key) => {
                        acc[key] = stableSerialize(value[key]);
                        return acc;
                }, {});
}

function encodeSignaturePayload(payload) {
        const normalized = JSON.stringify(stableSerialize(payload));
        try {
                return window.btoa(unescape(encodeURIComponent(normalized)));
        } catch (err) {
                console.warn("[AUTOSAVE] signature encode fallback", err);
                return normalized;
        }
}

export default function initAutosave() {
        const form = document.getElementById("form-garantia");
        if (!form) return;

        console.log("[AUTOSAVE] init module");

        const vehiculoFields = [
                "tipo_vehiculo",
                "marca",
                "modelo",
                "kilometros",
                "fecha_primera_matriculacion",
                "matricula",
                "numero_bastidor",
                "precio_venta",
                "combustible",
                "cambio",
                "traccion",
                "traccion_camion",
                "potencia",
                "potencia_kw",
                "cilindrada",
                "mma",
                "doble_motor",
        ];

        const clienteFieldMap = {
                nombre_apellidos: "nombre_y_apellidos",
                dni: "dni",
                telefono: "telefono",
                correo: "email",
                direccion: "direccion",
                localidad: "localidad",
                provincia: "provincia",
                codigo_postal: "codigo_postal",
        };

        const numericFields = [
                "kilometros",
                "precio_venta",
                "potencia",
                "potencia_kw",
                "cilindrada",
                "codigo_postal",
        ];

        const normalizePrice = (str) => {
                if (typeof str !== "string") return str;

                const raw = str.trim();
                if (!raw) return "";

                let cleaned = raw.replace(/\s+/g, "").replace(/[^0-9.,-]/g, "");
                if (!cleaned) return "";

                const isNegative = cleaned.startsWith("-");
                cleaned = cleaned.replace(/-/g, "");

                if (cleaned.includes(",")) {
                        cleaned = cleaned.replace(/\./g, "");
                        cleaned = cleaned.replace(/,/g, ".");
                } else if (cleaned.includes(".")) {
                        const lastDot = cleaned.lastIndexOf(".");
                        const intPart = cleaned.slice(0, lastDot).replace(/\./g, "");
                        const decPart = cleaned.slice(lastDot + 1).replace(/\./g, "");
                        if (decPart && decPart.length <= 2) {
                                cleaned = `${intPart || "0"}.${decPart}`;
                        } else {
                                cleaned = `${intPart}${decPart}`;
                        }
                }

                cleaned = cleaned.replace(/[^0-9.]/g, "");
                if (!cleaned) return "";

                const num = parseFloat(`${isNegative ? "-" : ""}${cleaned}`);
                return Number.isNaN(num) ? "" : num;
        };

        const skipFields = new Set([
                "duracion",
                "metodo_pago",
                "canal-venta",
                "usuario-rol",
                "fecha_inicio_garantia",
        ]);

        const container = document.querySelector(".form-container") || document.body;
        const status = document.createElement("div");
        status.className = "autosave-status autosave-status--hidden";
        status.innerHTML = `
                <span class="autosave-status__spinner"></span>
                <span class="autosave-status__icon">${getIcon("save")}</span>
                <span class="autosave-status__text">Guardando</span>`;
        container.appendChild(status);

        const spinner = status.querySelector(".autosave-status__spinner");
        const icon = status.querySelector(".autosave-status__icon");
        const text = status.querySelector(".autosave-status__text");
        icon.style.display = "none";

        let draftId = localStorage.getItem("go_draft_id");
        let draftUuid = localStorage.getItem("go_draft_uuid");
        const params = new URLSearchParams(window.location.search);
        const urlId = params.get("id");
        const urlUuid = params.get("uuid");
        if (!draftId && urlId) {
                draftId = urlId;
                localStorage.setItem("go_draft_id", draftId);
        }
        if (!draftUuid && urlUuid) {
                draftUuid = urlUuid;
                localStorage.setItem("go_draft_uuid", draftUuid);
        }
        let saving = false;

        const navButtons = document.querySelector(".nav-buttons");
        const successBlock = document.getElementById("form-success");
        const summaryContainer = document.querySelector(".summary-container");
        const tabs = document.querySelector(".tabs");
        const nextBtn = document.getElementById("form_next_btn");
        let latestDocLinks = {};
        let latestCertificateUrl = "";
        let postFinalizePromise = null;
        let loadingTimeoutId = null;

        const certificateState = {
                currentSignature: null,
                currentPromise: null,
                pendingArgs: null,
                lastResolvedSignature: null,
                lastResolvedUrl: "",
        };

        function refreshDocumentLinks(docLinks = {}, { reset = false, error = false } = {}) {
                if (!successBlock) return;
                const docsContainer = successBlock.querySelector(
                        ".form-success__docs"
                );
                const loading = successBlock.querySelector(
                        ".form-success__loading"
                );
                console.log("[AUTOSAVE] refreshDocumentLinks", {
                        docLinks,
                        reset,
                        error,
                        latestDocKeys: Object.keys(latestDocLinks || {}),
                });
                if (!docsContainer) return;

                if (reset) {
                        latestDocLinks = {};
                }

                if (docLinks && typeof docLinks === "object") {
                        latestDocLinks = Object.assign({}, latestDocLinks, docLinks);
                }

                const certificateLink = docsContainer.querySelector('[data-doc="certificate"]');
                docsContainer.querySelectorAll("[data-doc]").forEach((link) => {
                        link.hidden = true;
                        link.removeAttribute("href");
                        link.removeAttribute("target");
                        link.removeAttribute("rel");
                        link.textContent = "";
                        link.removeAttribute("aria-disabled");
                });

                const availableDocs = [];
                let certificateReady = false;
                for (const doc of AVAILABLE_DOCS) {
                        const url = latestDocLinks?.[doc.key];
                        if (!url) continue;
                        const link = docsContainer.querySelector(
                                `[data-doc="${doc.key}"]`
                        );
                        if (!link) continue;
                        const iconHtml = `<span class="document-card__icon" aria-hidden="true">${getIcon(
                                "pdf"
                        )}</span>`;
                        const titleHtml = `<span class="document-card__title">${doc.successLabel}</span>`;
                        link.href = url;
                        link.target = "_blank";
                        link.rel = "noopener";
                        link.innerHTML = `${iconHtml}${titleHtml}`;
                        link.hidden = false;
                        if (doc.key === "certificate") {
                                certificateReady = true;
                        }
                        availableDocs.push(link);
                }

                if (!certificateReady && certificateLink) {
                        const spinnerHtml =
                                '<span class="document-card__icon" aria-hidden="true"><span class="form-success__loading-spinner" style="width:1.5rem;height:1.5rem;"></span></span>' +
                                '<span class="document-card__title">Espera por favor...</span>';
                        certificateLink.innerHTML = spinnerHtml;
                        certificateLink.hidden = false;
                        certificateLink.setAttribute("aria-disabled", "true");
                }

                if (availableDocs.length > 0) {
                        docsContainer.hidden = false;
                        if (loading) {
                                loading.hidden = true;
                                loading.style.display = "none";
                                console.log("[AUTOSAVE] loading hidden (docs ready)");
                                if (loadingTimeoutId) {
                                        clearTimeout(loadingTimeoutId);
                                        loadingTimeoutId = null;
                                }
                        }
                } else {
                        const hasPendingCertificate = Boolean(certificateLink) && !certificateReady;
                        docsContainer.hidden = !hasPendingCertificate;
                        if (loading) {
                                loading.hidden = false;
                                loading.style.display = "";
                                const text = loading.querySelector(
                                        ".form-success__loading-text"
                                );
                                if (text) {
                                        text.textContent = error
                                                ? "No se pudieron generar los documentos"
                                                : "Generando documentos...";
                                }
                        }
                }
        }

        function updateSuccessDocuments(docLinks = {}) {
                if (docLinks.certificate) {
                        latestCertificateUrl = docLinks.certificate;
                }
                refreshDocumentLinks(docLinks, { reset: false });
                if (successBlock) {
                        const loading = successBlock.querySelector(
                                ".form-success__loading"
                        );
                        if (loading && docLinks.certificate) {
                                loading.hidden = true;
                                loading.style.display = "none";
                                console.log("[AUTOSAVE] loading hidden (certificate)", docLinks.certificate);
                                if (loadingTimeoutId) {
                                        clearTimeout(loadingTimeoutId);
                                        loadingTimeoutId = null;
                                }
                        }
                }
        }

        function markDocumentError() {
                console.warn("[AUTOSAVE] markDocumentError", latestDocLinks);
                if (!latestDocLinks || Object.keys(latestDocLinks).length === 0) {
                        refreshDocumentLinks({}, { reset: false, error: true });
                        if (loadingTimeoutId) {
                                clearTimeout(loadingTimeoutId);
                                loadingTimeoutId = null;
                        }
                }
        }

        function triggerContractNotice(notifyUrl, uuid) {
                if (!notifyUrl || !uuid) return;

                const payload = JSON.stringify({ uuid });
                const sendFallback = () =>
                        fetch(notifyUrl, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": getRestNonce(),
                                },
                                body: payload,
                        }).catch((err) => {
                                console.error("[AUTOSAVE] notify fetch failed", err);
                        });

                if (navigator.sendBeacon) {
                        try {
                                const blob = new Blob([payload], {
                                        type: "application/json",
                                });
                                const sent = navigator.sendBeacon(notifyUrl, blob);
                                if (sent) {
                                        console.log("[AUTOSAVE] notify beacon dispatched");
                                        return;
                                }
                        } catch (beaconErr) {
                                console.warn("[AUTOSAVE] notify beacon error", beaconErr);
                        }
                }

                sendFallback();
        }

        function computeCertificateSignature(rawArgs = {}) {
                const {
                        templateUrl,
                        coberturaUrl,
                        condicionadoUrl,
                        datosVehiculo,
                        datosCliente,
                        garantia,
                        estadoGarantia,
                        firmaSello,
                } = normalizeCertificateArgs(rawArgs);

                if (!templateUrl || !datosVehiculo || Object.keys(datosVehiculo).length === 0) {
                        return "";
                }

                const payload = {
                        templateUrl,
                        coberturaUrl,
                        condicionadoUrl,
                        datosVehiculo,
                        datosCliente,
                        garantia,
                        estadoGarantia,
                        firmaSello,
                };

                return encodeSignaturePayload(payload);
        }

        function startCertificateGeneration(argsWithSignature) {
                certificateState.currentSignature = argsWithSignature.signature;
                console.log("[AUTOSAVE] certificate generation start", {
                        draftId: argsWithSignature.draftId,
                        signature: argsWithSignature.signature,
                });

                certificateState.currentPromise = generateCertificateAndUpload(argsWithSignature)
                        .then((result) => {
                                const nextArgs = certificateState.pendingArgs;
                                certificateState.pendingArgs = null;
                                if (result) {
                                        certificateState.lastResolvedSignature = result.signature || argsWithSignature.signature;
                                        certificateState.lastResolvedUrl = result.url || "";
                                }
                                if (result?.url) {
                                        latestCertificateUrl = result.url;
                                        updateSuccessDocuments({ certificate: result.url });
                                }
                                if (nextArgs && nextArgs.signature !== result?.signature) {
                                        console.log("[AUTOSAVE] certificate regeneration queued", {
                                                draftId: nextArgs.draftId,
                                        });
                                        return startCertificateGeneration(nextArgs);
                                }
                                return result;
                        })
                        .catch((err) => {
                                console.error("[AUTOSAVE] certificate generation error", err);
                                const nextArgs = certificateState.pendingArgs;
                                certificateState.pendingArgs = null;
                                if (nextArgs) {
                                        console.log("[AUTOSAVE] retrying certificate generation", {
                                                draftId: nextArgs.draftId,
                                        });
                                        return startCertificateGeneration(nextArgs);
                                }
                                markDocumentError();
                                throw err;
                        })
                        .finally(() => {
                                if (!certificateState.pendingArgs) {
                                        certificateState.currentPromise = null;
                                        certificateState.currentSignature = null;
                                }
                        });

                return certificateState.currentPromise;
        }

        function queueCertificateGeneration(rawArgs) {
                const signature = computeCertificateSignature(rawArgs);
                if (!signature) {
                    return Promise.resolve({ url: latestCertificateUrl || "", signature: "" });
                }

                if (
                        certificateState.lastResolvedSignature === signature &&
                        (certificateState.lastResolvedUrl || latestCertificateUrl)
                ) {
                        return Promise.resolve({
                                url: certificateState.lastResolvedUrl || latestCertificateUrl || "",
                                signature,
                        });
                }

                const argsWithSignature = { ...rawArgs, signature };

                if (!certificateState.currentPromise) {
                        certificateState.pendingArgs = null;
                        return startCertificateGeneration(argsWithSignature);
                }

                if (certificateState.currentSignature === signature) {
                        return certificateState.currentPromise;
                }

                certificateState.pendingArgs = argsWithSignature;
                return certificateState.currentPromise;
        }

        function normalizeCertificateArgs(rawArgs = {}) {
                const normalized = { ...rawArgs };
                if (normalized.estadoGarantia && typeof normalized.estadoGarantia === "object") {
                        const estado = { ...normalized.estadoGarantia };
                        delete estado.estado_contratacion;
                        normalized.estadoGarantia = estado;
                }
                return normalized;
        }

        function scheduleCertificateGeneration(rawArgs, { immediate = false } = {}) {
                const args = normalizeCertificateArgs(rawArgs);
                const task = () => queueCertificateGeneration(args);
                if (immediate) {
                        return task();
                }

                return new Promise((resolve, reject) => {
                        const runner = () => {
                                task().then(resolve).catch(reject);
                        };

                        if (typeof window.requestIdleCallback === "function") {
                                window.requestIdleCallback(
                                        () => {
                                                runner();
                                        },
                                        { timeout: 1000 }
                                );
                        } else {
                                window.setTimeout(runner, 0);
                        }
                });
        }

        async function generateCertificateAndUpload({
                draftId,
                templateUrl,
                coberturaUrl,
                condicionadoUrl,
                datosVehiculo,
                datosCliente,
                garantia,
                estadoGarantia,
                firmaSello,
                signature,
        }) {
                if (!draftId || !templateUrl) {
                        return { url: latestCertificateUrl || "", signature: signature || "" };
                }

                console.log("[AUTOSAVE] generateCertificateAndUpload:start", {
                        draftId,
                        templateUrl,
                        signature,
                });
                console.time("certificate_fetch_template");
                const pdfBytes = await fetch(templateUrl).then((r) => r.arrayBuffer());
                console.timeEnd("certificate_fetch_template");
                const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
                console.log("[AUTOSAVE] PDF template loaded", { draftId });
                await import("../fontkit.umd.min.js");
                pdfDoc.registerFontkit(globalThis.fontkit);
                const form = pdfDoc.getForm();

                const fontUrl = new URL("../../fonts/RobotoMono-Regular.ttf", import.meta.url);
                console.time("certificate_fetch_font");
                const robotoBytes = await fetch(fontUrl).then((r) => r.arrayBuffer());
                console.timeEnd("certificate_fetch_font");
                const robotoMono = await pdfDoc.embedFont(robotoBytes);
                const robotoName = robotoMono.name;
                console.log("[AUTOSAVE] Font embedded", { robotoName });

                const formatDate = (iso) => {
                        if (!iso) return "";
                        const date = new Date(iso);
                        if (Number.isNaN(date.getTime())) return "";
                        return date.toLocaleDateString("es-ES", {
                                day: "numeric",
                                month: "long",
                                year: "numeric",
                        });
                };

                const numberFormatter = new Intl.NumberFormat("es-ES", {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 2,
                });

                const formatNumber = (num) => {
                        if (num === undefined || num === null || num === "") return "";
                        const n = Number(num);
                        return Number.isNaN(n) ? "" : numberFormatter.format(n);
                };

                const getSelectText = (id) => {
                        const el = document.getElementById(id);
                        if (el && el.tagName === "SELECT") {
                                return el.options[el.selectedIndex]?.text || "";
                        }
                        return "";
                };

                const getSelectValue = (id) => {
                        const el = document.getElementById(id);
                        if (el && el.tagName === "SELECT") {
                                return el.value || "";
                        }
                        return "";
                };

                const tipoVehiculoValue = (
                        getSelectValue("tipo_vehiculo") || datosVehiculo.tipo_vehiculo || ""
                )
                        .toString()
                        .toLowerCase();

                const getPdfTraccionValue = () => {
                        if (tipoVehiculoValue === "camion") {
                                return (
                                        getSelectText("traccion_camion") ||
                                        datosVehiculo.traccion_camion ||
                                        datosVehiculo.traccion ||
                                        ""
                                );
                        }
                        return (
                                getSelectText("traccion") ||
                                datosVehiculo.traccion ||
                                datosVehiculo.traccion_camion ||
                                ""
                        );
                };

                const pdfFieldMap = {
                        pdf_id_matricula: datosVehiculo.matricula,
                        pdf_nombre_apellidos: datosCliente.nombre_y_apellidos,
                        pdf_nif: datosCliente.dni,
                        pdf_direccion: datosCliente.direccion,
                        pdf_cp: datosCliente.codigo_postal,
                        pdf_localidad: datosCliente.localidad,
                        pdf_provincia:
                                getSelectText("provincia") || datosCliente.provincia,
                        pdf_telefono: datosCliente.telefono,
                        pdf_email: datosCliente.email,
                        pdf_matricula: datosVehiculo.matricula,
                        pdf_fecha_primera_mat: formatDate(
                                datosVehiculo.primera_matriculacion
                        ),
                        pdf_marca: getSelectText("marca") || datosVehiculo.marca,
                        pdf_modelo: getSelectText("modelo") || datosVehiculo.modelo,
                        pdf_cc: formatNumber(datosVehiculo.cilindrada),
                        pdf_bastidor: datosVehiculo.numero_bastidor,
                        pdf_km: formatNumber(datosVehiculo.kilometros),
                        pdf_cv: formatNumber(datosVehiculo.potencia),
                        pdf_traccion: getPdfTraccionValue(),
                        pdf_combustible:
                                getSelectText("combustible") ||
                                datosVehiculo.combustible,
                        pdf_cambio: getSelectText("cambio") || datosVehiculo.cambio,
                        pdf_tipo_vehiculo:
                                getSelectText("tipo_vehiculo") ||
                                datosVehiculo.tipo_vehiculo,
                        pdf_periodo_cobertura: getSelectText("duracion"),
                        pdf_fecha_inicio: formatDate(estadoGarantia?.inicio),
                        pdf_fecha_finalizacion: formatDate(
                                estadoGarantia?.finalizacion
                        ),
                };

                console.time("certificate_fill_fields");
                Object.entries(pdfFieldMap).forEach(([name, val]) => {
                        if (val === undefined || val === null || val === "") return;
                        try {
                                const field = form.getTextField(name);
                                field.setText(String(val));
                                field.setFontSize(9);
                                field.acroField.setDefaultAppearance(
                                        `0.5 0.5 0.5 rg /${robotoName} 9 Tf`
                                );
                                field.updateAppearances(robotoMono);
                        } catch (e) {
                                // campo inexistente
                                console.warn("[AUTOSAVE] Missing PDF field", name, e);
                        }
                });
                console.timeEnd("certificate_fill_fields");

                try {
                        const dobleMotorFieldValue = datosVehiculo.doble_motor;
                        const dobleMotorField = form.getCheckBox("pdf_doble_motor");
                        if (dobleMotorFieldValue === "doble_motor_si") {
                                dobleMotorField.check();
                        } else {
                                dobleMotorField.uncheck();
                        }
                } catch (e) {
                        // campo inexistente
                }

                try {
                        if (firmaSello.add_firma_sello) {
                                let fsField;
                                try {
                                        fsField = form.getField("pdf_firma_vendedor");
                                } catch (e) {
                                        fsField = undefined;
                                }
                                const widgets = fsField?.acroField?.getWidgets?.() || [];
                                if (widgets.length) {
                                        const widget = widgets[0];
                                        const { x, y, width, height } = widget.getRectangle();
                                        const page = pdfDoc.getPages()[0];
                                        if (firmaSello.sello) {
                                                console.time("certificate_fetch_sello");
                                                const selloBytes = await fetch(
                                                        firmaSello.sello
                                                ).then((r) => r.arrayBuffer());
                                                console.timeEnd("certificate_fetch_sello");
                                                const selloImg = firmaSello.sello.match(/\.png$/i)
                                                        ? await pdfDoc.embedPng(selloBytes)
                                                        : await pdfDoc.embedJpg(selloBytes);
                                                let selloWidth = width * 0.6;
                                                let selloHeight =
                                                        (selloImg.height / selloImg.width) *
                                                        selloWidth;
                                                const selloX = x + (width - selloWidth) / 2;
                                                const selloY =
                                                        y + height - selloHeight * 0.7;
                                                const angle = Math.random() * 10 - 5;
                                                page.drawImage(selloImg, {
                                                        x: selloX,
                                                        y: selloY,
                                                        width: selloWidth,
                                                        height: selloHeight,
                                                        rotate: PDFLib.degrees(angle),
                                                });
                                        }
                                        if (firmaSello.firma) {
                                                console.time("certificate_fetch_firma");
                                                const firmaBytes = await fetch(
                                                        firmaSello.firma
                                                ).then((r) => r.arrayBuffer());
                                                console.timeEnd("certificate_fetch_firma");
                                                const firmaImg = firmaSello.firma.match(/\.png$/i)
                                                        ? await pdfDoc.embedPng(firmaBytes)
                                                        : await pdfDoc.embedJpg(firmaBytes);
                                                let firmaWidth = width;
                                                let firmaHeight =
                                                        (firmaImg.height / firmaImg.width) *
                                                        firmaWidth;
                                                if (firmaHeight > height) {
                                                        firmaHeight = height;
                                                        firmaWidth =
                                                                (firmaImg.width /
                                                                        firmaImg.height) *
                                                                firmaHeight;
                                                }
                                                const firmaX = x + (width - firmaWidth) / 2;
                                                const firmaY = y;
                                                page.drawImage(firmaImg, {
                                                        x: firmaX,
                                                        y: firmaY,
                                                        width: firmaWidth,
                                                        height: firmaHeight,
                                                });
                                        }
                                }
                        }
                } catch (e) {
                        console.error("[AUTOSAVE] firma/sello error", e);
                }

                form.flatten();

                const appendUrls = [
                        coberturaUrl,
                        condicionadoUrl,
                        getDocumentUrl("reclamacion"),
                ].filter(Boolean);

                for (const url of appendUrls) {
                        try {
                                const isDynamicDoc = /\/wp-json\/go\/v1\/guarantees\//.test(url);
                                const bytes = await loadStaticPdf(url, {
                                        cache: !isDynamicDoc,
                                });
                                if (!bytes) continue;
                                const staticDoc = await PDFLib.PDFDocument.load(bytes);
                                const pages = await pdfDoc.copyPages(
                                        staticDoc,
                                        staticDoc.getPageIndices()
                                );
                                pages.forEach((page) => pdfDoc.addPage(page));
                        } catch (appendErr) {
                                console.error(
                                        "[AUTOSAVE] append static pdf error",
                                        url,
                                        appendErr
                                );
                        }
                }

                console.time("certificate_save_pdf");
                const filled = await pdfDoc.save();
                console.timeEnd("certificate_save_pdf");
                console.log("[AUTOSAVE] PDF saved", { size: filled?.byteLength || 0 });
                const uploadRes = await fetch(
                        `${getRestRoot()}go/v1/guarantees/${draftId}/certificate`,
                        {
                                method: "POST",
                                headers: {
                                        "X-WP-Nonce": getRestNonce(),
                                        "X-Go360-Cert-Signature": signature || "",
                                },
                                body: filled,
                        }
                );
                const uploadJson = await uploadRes.json();
                console.log("[AUTOSAVE] Certificate upload response", {
                        status: uploadRes.status,
                        ok: uploadRes.ok,
                        body: uploadJson,
                });
                if (!uploadRes.ok) {
                        throw new Error(
                                uploadJson?.message || "certificate_upload_failed"
                        );
                }

                const url = uploadJson.certificate_url || "";
                if (url) {
                        console.log("[AUTOSAVE] certificate uploaded", {
                                draftId,
                                url,
                        });
                } else {
                        console.warn("[AUTOSAVE] certificate upload missing URL", uploadJson);
                }
                return { url, signature: signature || "" };
        }

        async function triggerPostFinalizeTasks({
                draftId,
                draftUuid,
                responseJson,
                datosVehiculo,
                datosCliente,
                garantia,
                estadoGarantia,
                firmaSello,
        }) {
                const docLinks = {
                        condicionado: responseJson.condicionado_url || "",
                        cobertura: responseJson.cobertura_url || "",
                };

                if (docLinks.condicionado || docLinks.cobertura) {
                        updateSuccessDocuments(docLinks);
                }

                if (responseJson.template_url) {
                        scheduleCertificateGeneration(
                                {
                                        draftId,
                                        templateUrl: responseJson.template_url,
                                        coberturaUrl: responseJson.cobertura_url,
                                        condicionadoUrl: responseJson.condicionado_url,
                                        datosVehiculo,
                                        datosCliente,
                                        garantia,
                                        estadoGarantia,
                                        firmaSello,
                                },
                                { immediate: false }
                        )
                                .then((result) => {
                                        if (result?.url) {
                                                updateSuccessDocuments({ certificate: result.url });
                                        }
                                })
                                .catch((err) => {
                                        console.error("[AUTOSAVE] certificate finalize error", err);
                                        markDocumentError();
                                });
                }
        }

        function queuePostFinalizeTasks(args) {
                if (postFinalizePromise) return;
                postFinalizePromise = triggerPostFinalizeTasks(args).finally(() => {
                        postFinalizePromise = null;
                });
        }

        function fadeOut(el, hide = true) {
                if (!el) return;
                el.classList.add("fade-out");
                const duration =
                        parseFloat(
                                getComputedStyle(el).transitionDuration || "0"
                        ) * 1000;
                if (hide) {
                        setTimeout(() => (el.style.display = "none"), duration || 300);
                }
        }

        async function loadDraft() {
                if (!draftId) return;
                try {
                        const res = await fetch(
                                `${getRestRoot()}go/v1/guarantees/${draftId}`,
                                { headers: { "X-WP-Nonce": getRestNonce() } }
                        );
                        if (!res.ok) return;
                        const data = await res.json();
                        if (data.uuid && !draftUuid) {
                                draftUuid = data.uuid;
                                localStorage.setItem("go_draft_uuid", draftUuid);
                        }
                        const map = {
                                tipo_vehiculo: data.tipo_value,
                                marca: data.marca,
                                modelo: data.modelo,
                                kilometros: data.kilometros,
                                fecha_primera_matriculacion:
                                        data.primera_matriculacion,
                                matricula:
                                        data.matricula && data.matricula !== "-"
                                                ? data.matricula
                                                : "",
                                numero_bastidor: data.bastidor,
                                precio_venta: data.precio_venta,
                                combustible: data.combustible_value,
                                cambio: data.cambio_value,
                                traccion: data.traccion,
                                traccion_camion: data.traccion_camion,
                                potencia: data.potencia,
                                potencia_kw: data.potencia_kw,
                                cilindrada: data.cilindrada,
                                nombre_apellidos: data.nombre_comprador,
                                dni: data.dni_comprador,
                                telefono: data.telefono_comprador,
                                correo: data.email_comprador,
                                direccion: data.direccion_comprador,
                                localidad: data.localidad_comprador,
                                provincia: data.provincia_comprador,
                                codigo_postal: data.codigo_postal_comprador,
                        };
                        Object.entries(map).forEach(([id, val]) => {
                                if (
                                        val === undefined ||
                                        val === null ||
                                        val === "" ||
                                        val === "-"
                                )
                                        return;
                                const el = document.getElementById(id);
                                if (!el) return;
                                el.value = val;
                                el.dispatchEvent(
                                        new Event("input", { bubbles: true })
                                );
                                el.dispatchEvent(
                                        new Event("change", { bubbles: true })
                                );
                        });
                } catch (e) {
                        console.error("[AUTOSAVE] loadDraft", e);
                }
        }

        loadDraft();

        function launchConfetti() {
                const layer = successBlock?.querySelector(
                        ".form-success__confetti"
                );
                if (!layer) return;
                const colors = [
                        "#e2001b",
                        "#2563eb",
                        "#ffd700",
                        "#4CAF50",
                        "#9C27B0",
                ];
                for (let i = 0; i < 120; i++) {
                        const piece = document.createElement("span");
                        piece.className = "confetti-piece";
                        piece.style.left = `${Math.random() * 100}%`;
                        const size = Math.random() * 6 + 6;
                        piece.style.width = `${size}px`;
                        piece.style.height = `${size}px`;
                        piece.style.backgroundColor =
                                colors[Math.floor(Math.random() * colors.length)];
                        piece.style.animationDuration = `${
                                Math.random() * 2 + 3
                        }s`;
                        piece.style.animationDelay = `${Math.random()}s`;
                        const drift = (Math.random() - 0.5) * 200;
                        piece.style.setProperty("--drift", `${drift}px`);
                        const front = Math.random() > 0.5;
                        piece.style.zIndex = front ? 3 : 0;
                        const parent = front ? successBlock : layer;
                        parent.appendChild(piece);
                        setTimeout(() => piece.remove(), 5000);
                }
        }

        function parseGuaranteeStartDate(value) {
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
                const isoMatch = normalized.match(
                        /^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
                );
                if (isoMatch) {
                        const [, year, month, day, hours = "0", minutes = "0", seconds = "0"] = isoMatch;
                        const candidate = new Date(
                                Number(year),
                                Number(month) - 1,
                                Number(day),
                                Number(hours),
                                Number(minutes),
                                Number(seconds)
                        );
                        if (!Number.isNaN(candidate.getTime())) {
                                return candidate;
                        }
                }
                const localMatch = normalized.match(
                        /^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/
                );
                if (localMatch) {
                        const [, day, month, year, hours = "0", minutes = "0", seconds = "0"] = localMatch;
                        const candidate = new Date(
                                Number(year),
                                Number(month) - 1,
                                Number(day),
                                Number(hours),
                                Number(minutes),
                                Number(seconds)
                        );
                        if (!Number.isNaN(candidate.getTime())) {
                                return candidate;
                        }
                }
                return null;
        }

        function isSameCalendarDay(dateA, dateB) {
                return (
                        dateA.getFullYear() === dateB.getFullYear() &&
                        dateA.getMonth() === dateB.getMonth() &&
                        dateA.getDate() === dateB.getDate()
                );
        }

        function calculateTransferDeadline(startValue, nowValue = new Date()) {
                const startDate = parseGuaranteeStartDate(startValue);
                if (!startDate) return null;
                const nowDate = nowValue instanceof Date ? nowValue : new Date(nowValue);
                if (Number.isNaN(nowDate.getTime())) return null;
                const baseDate = isSameCalendarDay(startDate, nowDate) ? nowDate : startDate;
                const deadline = new Date(baseDate.getTime() + 48 * 60 * 60 * 1000);
                if (Number.isNaN(deadline.getTime())) return null;
                return deadline;
        }

        function formatTransferDeadlineLabel(startValue) {
                const deadline = calculateTransferDeadline(startValue);
                if (!deadline) return "";
                try {
                        return new Intl.DateTimeFormat("es-ES", {
                                day: "numeric",
                                month: "long",
                        }).format(deadline);
                } catch (error) {
                        console.warn("No se pudo formatear la fecha límite de transferencia", error);
                        return "";
                }
        }

        function showSuccess(method, plate, amount, planName, months, docLinks, extras = {}) {
                console.log("[AUTOSAVE] showSuccess", { docLinks });
                fadeOut(form, false);
                fadeOut(navButtons);
                fadeOut(tabs);
                if (summaryContainer) {
                        summaryContainer.classList.add("slide-out");
                        container.classList.add("form-container--full");
                        summaryContainer.addEventListener(
                                "transitionend",
                                () => {
                                        summaryContainer.style.display = "none";
                                        form.style.display = "none";
                                        revealSuccess(method, plate, amount, planName, months, docLinks, extras);
                                },
                                { once: true }
                        );
                } else {
                        setTimeout(() => {
                                form.style.display = "none";
                                revealSuccess(method, plate, amount, planName, months, docLinks, extras);
                        }, 300);
                }
        }

        function revealSuccess(method, plate, amount, planName, months, docLinks = {}, extras = {}) {
                console.log("[AUTOSAVE] revealSuccess", { docLinks });
                if (!successBlock) return;
                successBlock.style.display = "block";
                requestAnimationFrame(() => successBlock.classList.add("is-visible"));
                const plan = successBlock.querySelector("[data-plan]");
                if (plan) {
                        const parts = [];
                        if (typeof planName === "string" && planName.trim()) {
                                parts.push(planName.trim());
                        }
                        if (months) {
                                const monthsNumber = Number(months);
                                if (!Number.isNaN(monthsNumber) && monthsNumber > 0) {
                                        parts.push(`${monthsNumber} meses`);
                                } else if (typeof months === "string" && months.trim()) {
                                        parts.push(months.trim());
                                }
                        }
                        const planDescription = parts.join(" ");
                        plan.textContent = planDescription
                                ? `Cobertura ${planDescription}`
                                : "Cobertura";
                }
                const message = successBlock.querySelector(
                        ".form-success__message"
                );
                if (message) {
                        const cleanPlan =
                                typeof planName === "string" && planName.trim()
                                        ? planName.trim()
                                        : "";
                        const intro = cleanPlan
                                ? `Gracias por contratar la garantía ${cleanPlan}.`
                                : "Gracias por contratar tu garantía.";
                        message.textContent =
                                `${intro} Estamos preparando la documentación y recibirás un correo de confirmación en unos instantes. ` +
                                "Puedes descargarla ahora o acceder cuando quieras desde Mis Garantías.";
                        message.hidden = false;
                }
                const loading = successBlock.querySelector(
                        ".form-success__loading"
                );
                if (loading) {
                        loading.hidden = false;
                        loading.style.display = "";
                        const text = loading.querySelector(
                                ".form-success__loading-text"
                        );
                        if (text) {
                                text.textContent = "Generando documentos...";
                        }
                        if (loadingTimeoutId) {
                                clearTimeout(loadingTimeoutId);
                        }
                        loadingTimeoutId = window.setTimeout(() => {
                                console.warn("[AUTOSAVE] success spinner forced hide after timeout");
                                loading.hidden = true;
                                loading.style.display = "none";
                                const timeoutText = loading.querySelector(
                                        ".form-success__loading-text"
                                );
                                if (timeoutText) {
                                        timeoutText.textContent =
                                                "Los documentos estarán disponibles en Mis Garantías";
                                }
                        }, 15000);
                }
                refreshDocumentLinks(docLinks || {}, { reset: true });
                const detailsLink = successBlock.querySelector(
                        ".form-success__details-link"
                );
                if (detailsLink) {
                        const baseUrl = getMisGarantiasUrl();
                        const plateValue =
                                typeof plate === "string" ? plate.trim().toUpperCase() : "";
                        if (baseUrl && plateValue) {
                                let href = baseUrl;
                                try {
                                        const detailUrl = new URL(baseUrl, window.location.origin);
                                        detailUrl.searchParams.set("matricula", plateValue);
                                        href = detailUrl.toString();
                                } catch (err) {
                                        const separator = baseUrl.includes("?") ? "&" : "?";
                                        href = `${baseUrl}${separator}matricula=${encodeURIComponent(
                                                plateValue
                                        )}`;
                                }
                                detailsLink.href = href;
                                const refSpan = detailsLink.querySelector("[data-ref-text]");
                                if (refSpan) {
                                        refSpan.textContent = plateValue;
                                }
                                detailsLink.hidden = false;
                        } else {
                                detailsLink.hidden = true;
                        }
                }
                const newLink = successBlock.querySelector(".form-success__new");
                if (newLink) {
                        const nuevaUrl = getNuevaGarantiaUrl();
                        if (nuevaUrl) {
                                newLink.href = nuevaUrl;
                        }
                }
                const pay = successBlock.querySelector(".form-success__payment");
                if (pay) {
                        const transfer = pay.querySelector(".form-success__transfer");
                        const showTransfer = method === "transferencia";
                        pay.hidden = !showTransfer;
                        if (transfer) {
                                transfer.hidden = !showTransfer;
                        }
                        if (showTransfer && transfer) {
                                const deadlineLabel =
                                        typeof extras.transferDeadlineLabel === "string"
                                                ? extras.transferDeadlineLabel.trim()
                                                : "";
                                const transferNote = transfer.querySelector(
                                        ".form-success__transfer-note"
                                );
                                if (transferNote) {
                                        const message = deadlineLabel
                                                ? `Realiza el pago antes del ${deadlineLabel}.`
                                                : "Realiza el pago lo antes posible.";
                                        let noteTextNode = transferNote.querySelector(
                                                ".form-success__transfer-note-text"
                                        );
                                        if (!noteTextNode) {
                                                noteTextNode = document.createElement("span");
                                                noteTextNode.className = "form-success__transfer-note-text";
                                                transferNote.appendChild(noteTextNode);
                                        }
                                        noteTextNode.textContent = message;
                                }
                                const reference = plate
                                        ? `Garantía ${plate.toUpperCase()}`
                                        : "";
                                transfer
                                        .querySelectorAll("[data-ref],[data-ref-text]")
                                        .forEach((el) => (el.textContent = reference));
                                const amountText =
                                        amount !== undefined && amount !== null
                                                ? `${Number(amount).toLocaleString("es-ES", {
                                                          minimumFractionDigits: 2,
                                                          maximumFractionDigits: 2,
                                                  })} €`
                                                : "";
                                transfer
                                        .querySelectorAll("[data-amount],[data-amount-text]")
                                        .forEach((el) => (el.textContent = amountText));

                                const ibanValue =
                                        typeof extras.transferIban === "string"
                                                ? extras.transferIban.trim()
                                                : "";
                                const ibanTargets = transfer.querySelectorAll(
                                        "[data-iban],[data-iban-text]"
                                );
                                ibanTargets.forEach((el) => {
                                        el.textContent = ibanValue;
                                });
                                const ibanRow = transfer.querySelector("[data-iban]")?.closest("tr");
                                if (ibanRow) {
                                        ibanRow.hidden = !ibanValue;
                                }
                                const ibanCopyBtn = transfer.querySelector(
                                        'button[data-copy="[data-iban]"]'
                                );
                                if (ibanCopyBtn) {
                                        if (ibanValue) {
                                                ibanCopyBtn.removeAttribute("disabled");
                                                ibanCopyBtn.removeAttribute("aria-disabled");
                                        } else {
                                                ibanCopyBtn.setAttribute("disabled", "true");
                                                ibanCopyBtn.setAttribute("aria-disabled", "true");
                                        }
                                }

                                const emailTarget = transfer.querySelector("[data-email]");
                                if (emailTarget) {
                                        const emailAddress =
                                                emailTarget.dataset.copyValue ||
                                                emailTarget.textContent.trim();
                                        if (emailAddress) {
                                                emailTarget.dataset.copyValue = emailAddress;
                                                const emailLink = emailTarget.querySelector(
                                                        "[data-email-link]"
                                                );
                                                if (emailLink) {
                                                        const baseSubject =
                                                                "Justificante de transferencia Garantía";
                                                        const subject = plate
                                                                ? `${baseSubject} ${plate.toUpperCase()}`
                                                                : baseSubject;
                                                        emailLink.href = `mailto:${emailAddress}?subject=${encodeURIComponent(
                                                                subject
                                                        )}`;
                                                        emailLink.textContent =
                                                                emailLink.dataset.emailBase || emailAddress;
                                                }
                                        }
                                }
                        }
                }
                function copyText(text) {
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                return navigator.clipboard.writeText(text);
                        }
                        const textarea = document.createElement("textarea");
                        textarea.value = text;
                        document.body.appendChild(textarea);
                        textarea.select();
                        document.execCommand("copy");
                        document.body.removeChild(textarea);
                        return Promise.resolve();
                }

                function showToast(message) {
                        const toast = successBlock.querySelector(
                                ".form-success__toast"
                        );
                        if (toast) {
                                toast.textContent = message || "Copiado al portapapeles.";
                                toast.classList.add("show");
                                setTimeout(
                                        () => toast.classList.remove("show"),
                                        2000
                                );
                        }
                }

                function handleSuccessCopy(btn) {
                        if (!btn) return;
                        const selector = btn.getAttribute("data-copy");
                        if (!selector) return;
                        const target = successBlock.querySelector(selector);
                        if (!target) return;
                        const text = (
                                target.dataset.copyValue || target.textContent || ""
                        ).trim();
                        if (!text) return;
                        copyText(text).then(() => {
                                const label = btn.querySelector(
                                        ".form-success__copy-text"
                                );
                                if (label) {
                                        const original = btn.dataset.label || label.textContent;
                                        label.textContent = btn.dataset.done || "Copiado";
                                        setTimeout(
                                                () => (label.textContent = original),
                                                2000
                                        );
                                } else {
                                        const original =
                                                btn.dataset.label ||
                                                btn.getAttribute("aria-label") ||
                                                "";
                                        const done = btn.dataset.done || "Copiado";
                                        if (done) {
                                                btn.setAttribute("aria-label", done);
                                                setTimeout(() => {
                                                        if (original) {
                                                                btn.setAttribute(
                                                                        "aria-label",
                                                                        original
                                                                );
                                                        }
                                                }, 2000);
                                        }
                                }
                                showToast(btn.dataset.toast);
                        });
                }

                successBlock.querySelectorAll("[data-copy]").forEach((btn) => {
                        btn.addEventListener("click", () => handleSuccessCopy(btn));
                });

                successBlock.addEventListener("click", (event) => {
                        const copyButton = event.target.closest("[data-copy]");
                        if (copyButton && successBlock.contains(copyButton)) {
                                return;
                        }

                        const row = event.target.closest("tr[data-copy-row]");
                        if (row && successBlock.contains(row)) {
                                const btn = row.querySelector("[data-copy]");
                                if (btn) {
                                        handleSuccessCopy(btn);
                                }
                                return;
                        }

                        const cell = event.target.closest("[data-copy-cell]");
                        if (!cell || !successBlock.contains(cell)) return;
                        const btn = cell.querySelector("[data-copy]");
                        if (!btn) return;
                        handleSuccessCopy(btn);
                });
                const sound = successBlock.querySelector("#form-success__sound");
                if (sound) {
                        sound.currentTime = 0;
                        sound.play().catch(() => {});
                }
                launchConfetti();
        }

        async function sendAutosave(finalize = false) {
                if (saving) return;
                saving = true;

                console.log("[AUTOSAVE] Triggered", { draftId, finalize });

                status.classList.remove("autosave-status--hidden");
                spinner.style.display = "inline-block";
                icon.style.display = "none";
                text.textContent = finalize ? "Generando documentos..." : "Guardando";
                if (finalize && nextBtn) {
                        nextBtn.classList.add("is-loading");
                        nextBtn.disabled = true;
                }

                const payload = {};
                const datosVehiculo = {};
                const datosCliente = {};

                FormCache.inputs.forEach((input) => {
                        if (!input.id || skipFields.has(input.id)) return;
                        let value = input.value;
                        if (numericFields.includes(input.id)) {
                                if (input.id === "precio_venta") {
                                        value = normalizePrice(value);
                                } else {
                                        value = value.replace(/\./g, "").replace(",", ".");
                                }
                        }
                        if (vehiculoFields.includes(input.id)) {
                                datosVehiculo[input.id] = value;
                        } else if (clienteFieldMap[input.id]) {
                                datosCliente[clienteFieldMap[input.id]] = value;
                        } else {
                                payload[input.id] = value;
                        }
                });

                if (datosVehiculo.fecha_primera_matriculacion) {
                        datosVehiculo.primera_matriculacion =
                                datosVehiculo.fecha_primera_matriculacion;
                        delete datosVehiculo.fecha_primera_matriculacion;
                }

                if (
                        datosVehiculo.combustible === "electrico" &&
                        datosVehiculo.potencia
                ) {
                        const kw = parseFloat(datosVehiculo.potencia);
                        if (!isNaN(kw)) {
                                datosVehiculo.potencia_kw = datosVehiculo.potencia;
                                datosVehiculo.potencia = Math.round(
                                        kw * 1.3596
                                ).toString();
                        }
                }

                if (Object.keys(datosVehiculo).length) {
                        payload.datos_vehiculo = datosVehiculo;
                        if (datosVehiculo.matricula) {
                                payload.matricula = datosVehiculo.matricula;
                        }
                }

                if (Object.keys(datosCliente).length) {
                        payload.datos_cliente = datosCliente;
                }

                // --- Garantía contratada ---
                const garantia = {};
                const userRole = getUserRole();
                const modalidadId = getSelectedModalidadId();
                if (modalidadId) {
                        garantia.garantia = modalidadId;
                        const modalidad = getVisibleModalidades().find(
                                (m) => String(m.ID) === String(modalidadId)
                        );
                        if (modalidad) {
                                const detalles = modalidad.acf?.detalles_modalidad || {};
                                const rawPlanName =
                                        detalles.nombre_mostrar ||
                                        modalidad.title ||
                                        "";
                                const planDisplayName =
                                        typeof rawPlanName === "string"
                                                ? rawPlanName.trim()
                                                : "";
                                if (planDisplayName) {
                                        garantia.plan_display_name = planDisplayName;
                                }
                                const tipo = Array.isArray(modalidad.tipo_garantia)
                                        ? modalidad.tipo_garantia[0]
                                        : modalidad.tipo_garantia;
                                const nivel = Array.isArray(modalidad.nivel_garantia)
                                        ? modalidad.nivel_garantia[0]
                                        : modalidad.nivel_garantia;
                                if (tipo) garantia.tipo_garantia = tipo;
                                if (nivel) garantia.nivel_garantia = nivel;

                                const dr = {};
                                const recargosEl = document.querySelector(
                                        ".form__plan.selected .form__plan-recargos-precios"
                                );
                                if (recargosEl) {
                                        const txt = recargosEl.textContent;
                                        const baseMatch = txt.match(/Precio base:\s*([0-9.,]+)/i);
                                        const finalMatch = txt.match(/Precio final \+ IVA:\s*([0-9.,]+)/i);
                                        if (baseMatch) {
                                                dr.precio_base = normalizePrice(baseMatch[1]);
                                        }
                                        if (finalMatch) {
                                                garantia.precio = normalizePrice(finalMatch[1]);
                                        }
                                }

                                const listado = [];
                                const descuentos = await getDescuentosAplicables(modalidad);
                                descuentos.forEach((d) => {
                                        listado.push({
                                                tipo: "descuento",
                                                porcentaje: Math.round(d.porcentaje * 10000) / 100,
                                                razon: d.nombre,
                                        });
                                });
                                const valoresRecargo = { ...datosVehiculo };
                                if (datosVehiculo.primera_matriculacion) {
                                        valoresRecargo.fecha_primera_matriculacion =
                                                datosVehiculo.primera_matriculacion;
                                }
                                const breakdown = calcularRecargos(
                                        modalidad,
                                        valoresRecargo
                                );
                                if (breakdown && Array.isArray(breakdown.detalles)) {
                                        breakdown.detalles.forEach((det) => {
                                                listado.push({
                                                        tipo: "recargo",
                                                        porcentaje:
                                                                Math.round(
                                                                        det.porcentajeAplicado * 10000
                                                                ) / 100,
                                                        razon: det.descripcion || "",
                                                });
                                        });
                                }
                                if (listado.length) {
                                        dr.listado_descuentos_recargos = listado;
                                }
                                if (Object.keys(dr).length) {
                                        garantia.descuentos_y_recargos = dr;
                                }
                        }
                }

                const duracionEl = document.getElementById("duracion");
                if (duracionEl && duracionEl.value) {
                        garantia.meses_contratados = duracionEl.value;
                }

                const metodoPagoEl = document.getElementById("metodo_pago");
                if (metodoPagoEl && metodoPagoEl.value) {
                        garantia.metodo_pago = metodoPagoEl.value;
                }

                if (garantia.precio === undefined || garantia.precio === "") {
                        const resumenPrecioEl = document.querySelector(
                                "#final-summary .item--destacado .valor"
                        );
                        if (resumenPrecioEl) {
                                garantia.precio = normalizePrice(
                                        resumenPrecioEl.textContent
                                );
                        }
                }

                const normalizedRole = String(userRole).toLowerCase();
                if (
                        normalizedRole === "admin" ||
                        normalizedRole === "go_garantias" ||
                        normalizedRole === "go_director_comercial"
                ) {
                        const canal = document.getElementById("canal-venta");
                        if (canal && canal.value) {
                                const canalNormalizado = normalizeChannel(canal.value);
                                garantia.canal_venta = canalNormalizado || "profesional";
                        } else {
                                garantia.canal_venta = "profesional";
                        }
                        const usuario = document.getElementById("usuario-rol");
                        if (usuario && usuario.value) {
                                garantia.concesionario_empresa_profesional = usuario.value;
                        }
                } else if (normalizedRole === "comercial" || normalizedRole === "go_comercial") {
                        garantia.canal_venta = "profesional";
                        const usuario = document.getElementById("usuario-rol");
                        if (usuario && usuario.value) {
                                garantia.concesionario_empresa_profesional = usuario.value;
                        }
               } else if (normalizedRole === "profesional" || normalizedRole === "go_profesional") {
                        garantia.canal_venta = "profesional";
                        const currentId = getCurrentUserId();
                        if (currentId) {
                                garantia.concesionario_empresa_profesional = currentId;
                        }
               } else if (normalizedRole === "go_particular" || normalizedRole === "particular") {
                        garantia.canal_venta = "particular";
                }

                if (Object.keys(garantia).length) {
                        payload.garantia_contratada = garantia;
                }

                // --- Estado de la garantía ---
                const inicioEl = document.getElementById("fecha_inicio_garantia");
                if (inicioEl && inicioEl.value) {
                        const estado = { inicio: inicioEl.value };
                        const meses = parseInt(garantia.meses_contratados || 0, 10);
                        if (!isNaN(meses) && meses > 0) {
                                const end = new Date(inicioEl.value);
                                end.setMonth(end.getMonth() + meses);
                                end.setDate(end.getDate() - 1);
                                estado.finalizacion = end.toISOString().split("T")[0];
                        }
                        payload.estado_garantia = estado;
                }

                if (!payload.estado_garantia) payload.estado_garantia = {};
                if (finalize) {
                        payload.post_status = "publish";
                        if (garantia.metodo_pago === "domiciliacion") {
                                payload.estado_garantia.estado_contratacion = "activada";
                        } else {
                                payload.estado_garantia.estado_contratacion = "pendiente_pago";
                        }
                } else {
                        payload.estado_garantia.estado_contratacion = "sin_finalizar";
                }

                console.log("[AUTOSAVE] payload", payload);

                try {
                        const res = await fetch(`${getRestRoot()}go/v1/guarantees/autosave`, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": getRestNonce(),
                                },
                                body: JSON.stringify({ id: draftId, uuid: draftUuid, data: payload }),
                        });
                        console.log("[AUTOSAVE] response status", res.status);
                        const json = await res.json();
                        console.log("[AUTOSAVE] response json", json);
                        const firmaSello = json.firma_sello || {};
                        if (!res.ok) {
                                if (res.status === 409 && json?.message) {
                                        alert(json.message);
                                        const plateInput = document.getElementById("matricula");
                                        if (plateInput) setError(plateInput, json.message);
                                }
                                status.classList.add("autosave-status--hidden");
                                return;
                        }
                        if (json.id) {
                                draftId = json.id;
                                localStorage.setItem("go_draft_id", draftId);
                                console.log("[AUTOSAVE] stored draftId", draftId);
                        }
                        if (json.uuid) {
                                draftUuid = json.uuid;
                                localStorage.setItem("go_draft_uuid", draftUuid);
                                console.log("[AUTOSAVE] stored draftUuid", draftUuid);
                        }
                        if (!finalize && json.template_url) {
                                loadStaticPdf(json.template_url).catch((err) => {
                                        console.debug("[AUTOSAVE] template preload error", err);
                                });
                        }

                        const certificateArgs = json.template_url
                                ? {
                                          draftId,
                                          templateUrl: json.template_url,
                                          coberturaUrl: json.cobertura_url,
                                          condicionadoUrl: json.condicionado_url,
                                          datosVehiculo,
                                          datosCliente,
                                          garantia,
                                          estadoGarantia: payload.estado_garantia || {},
                                          firmaSello,
                                  }
                                : null;

                        if (!finalize && certificateArgs) {
                                scheduleCertificateGeneration(certificateArgs, {
                                        immediate: true,
                                }).catch((err) => {
                                        console.error("[AUTOSAVE] certificate queue error", err);
                                });
                        }

                        if (finalize) {
                                const docLinks = {
                                        certificate: latestCertificateUrl || "",
                                        condicionado: json.condicionado_url || "",
                                        cobertura: json.cobertura_url || "",
                                };
                                const extras = {
                                        transferIban:
                                                typeof json.transfer_iban === "string"
                                                        ? json.transfer_iban.trim()
                                                        : "",
                                };
                                const transferDeadlineLabel = formatTransferDeadlineLabel(
                                        payload.estado_garantia?.inicio
                                );
                                if (transferDeadlineLabel) {
                                        extras.transferDeadlineLabel = transferDeadlineLabel;
                                }
                                showSuccess(
                                        garantia.metodo_pago,
                                        datosVehiculo.matricula,
                                        garantia.precio,
                                        garantia.plan_display_name ||
                                                garantia.nivel_garantia ||
                                                "",
                                        garantia.meses_contratados,
                                        docLinks,
                                        extras
                                );
                                queuePostFinalizeTasks({
                                        draftId,
                                        draftUuid,
                                        responseJson: json,
                                        datosVehiculo,
                                        datosCliente,
                                        garantia,
                                        estadoGarantia: payload.estado_garantia || {},
                                        firmaSello,
                                });
                                if (json.notify_url && draftUuid) {
                                        triggerContractNotice(json.notify_url, draftUuid);
                                }
                        }
                        spinner.style.display = "none";
                        icon.style.display = "inline-block";
                        text.textContent = "Guardado";
                        setTimeout(() => {
                                status.classList.add("autosave-status--hidden");
                        }, 1500);
                } catch (e) {
                        console.error("[AUTOSAVE] error", e);
                        status.classList.add("autosave-status--hidden");
                } finally {
                        if (finalize && nextBtn) {
                                nextBtn.classList.remove("is-loading");
                                nextBtn.disabled = false;
                        }
                        saving = false;
                }
        }

        const debounced = debounce(sendAutosave, 300);

        FormCache.nextButton?.addEventListener(
                "click",
                () => {
                const finalize =
                                FormCache.currentTab ===
                                FormCache.fieldsets.length - 1;
                        console.log("[AUTOSAVE] next button click finalize=", finalize);
                        debounced(finalize);
                },
                { capture: true }
        );
        FormCache.prevButton?.addEventListener("click", () => debounced(false));
        FormCache.tabs?.forEach((tab) => tab.addEventListener("click", () => debounced(false)));
}

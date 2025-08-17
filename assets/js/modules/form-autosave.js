// assets/js/modules/form-autosave.js
"use strict";

import FormCache from "./form-cache.js";
import {
        getRestRoot,
        getRestNonce,
        getIcon,
        getUserRole,
        getCurrentUserId,
} from "./config.js";
import { getSelectedModalidadId, getVisibleModalidades } from "./form-state.js";
import { debounce, setError } from "./form-utils.js";
import { calcularRecargos, getDescuentosAplicables } from "./form-calculations.js";

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
                const cleaned = str.replace(/[^0-9.,]/g, "");
                const lastComma = cleaned.lastIndexOf(",");
                const lastDot = cleaned.lastIndexOf(".");
                const sep = lastComma > lastDot ? "," : ".";
                const parts = cleaned.split(sep);
                const intPart = parts[0].replace(/[.,]/g, "");
                const decPart = parts[1] ? parts[1].replace(/[.,]/g, "") : "";
                const num = decPart
                        ? parseFloat(`${intPart}.${decPart}`)
                        : parseFloat(intPart);
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
        let saving = false;

        const navButtons = document.querySelector(".nav-buttons");
        const successBlock = document.getElementById("form-success");
        const summaryContainer = document.querySelector(".summary-container");
        const tabs = document.querySelector(".tabs");
        const nextBtn = document.getElementById("form_next_btn");

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

        function launchConfetti() {
                const back = successBlock?.querySelector(
                        ".form-success__confetti"
                );
                if (!back) return;
                const colors = [
                        "#e2001b",
                        "#2563eb",
                        "#ffd700",
                        "#4CAF50",
                        "#9C27B0",
                ];
                for (let i = 0; i < 80; i++) {
                        const piece = document.createElement("span");
                        piece.className = "confetti-piece";
                        piece.style.left = Math.random() * 100 + "%";
                        piece.style.backgroundColor =
                                colors[Math.floor(Math.random() * colors.length)];
                        piece.style.animationDelay = Math.random() * 0.5 + "s";
                        const parent = Math.random() > 0.5 ? successBlock : back;
                        parent.appendChild(piece);
                        piece.style.zIndex = parent === back ? 0 : 3;
                        setTimeout(() => piece.remove(), 3500);
                }
        }

        function showSuccess(method, plate, amount, level, months) {
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
                                        revealSuccess(method, plate, amount, level, months);
                                },
                                { once: true }
                        );
                } else {
                        setTimeout(() => {
                                form.style.display = "none";
                                revealSuccess(method, plate, amount, level, months);
                        }, 300);
                }
        }

        function revealSuccess(method, plate, amount, level, months) {
                if (!successBlock) return;
                successBlock.style.display = "block";
                requestAnimationFrame(() => successBlock.classList.add("is-visible"));
                const plan = successBlock.querySelector("[data-plan]");
                if (plan) {
                        const lvl = level ? level.replace(/[-_]/g, " ") : "";
                        const monthsText = months ? `${months} meses` : "";
                        const text = [lvl, monthsText].filter(Boolean).join(" ");
                        plan.textContent = text.replace(/\b\w/g, (c) => c.toUpperCase());
                }
                if (method === "transferencia" || method === "domiciliacion") {
                        const pay = successBlock.querySelector(".form-success__payment");
                        if (pay) {
                                pay.hidden = false;
                                if (method === "transferencia") {
                                        const transfer = pay.querySelector(
                                                ".form-success__transfer"
                                        );
                                        if (transfer) {
                                                transfer.hidden = false;
                                                const reference = plate ? `Garantía ${plate}` : "";
                                                transfer
                                                        .querySelectorAll("[data-ref],[data-ref-text]")
                                                        .forEach((el) => (el.textContent = reference));
                                                const amountText = amount
                                                        ? `${Number(amount).toLocaleString("es-ES", {
                                                                  minimumFractionDigits: 2,
                                                                  maximumFractionDigits: 2,
                                                          })} €`
                                                        : "";
                                                transfer
                                                        .querySelectorAll("[data-amount],[data-amount-text]")
                                                        .forEach((el) => (el.textContent = amountText));
                                        }
                                }
                        }
                }
                successBlock.querySelectorAll("[data-copy]").forEach((btn) => {
                        btn.addEventListener("click", () => {
                                const target = successBlock.querySelector(
                                        btn.getAttribute("data-copy")
                                );
                                if (target) {
                                        navigator.clipboard.writeText(
                                                target.textContent.trim()
                                        );
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
                                                btn.setAttribute("aria-label", done);
                                                setTimeout(() => {
                                                        if (original) btn.setAttribute("aria-label", original);
                                                }, 2000);
                                        }
                                        const toast = successBlock.querySelector(
                                                ".form-success__toast"
                                        );
                                        if (toast) {
                                                toast.classList.add("show");
                                                setTimeout(
                                                        () => toast.classList.remove("show"),
                                                        2000
                                                );
                                        }
                                }
                        });
                });
                launchConfetti();
        }

        async function sendAutosave(finalize = false) {
                if (saving) return;
                saving = true;

                console.log("[AUTOSAVE] Triggered", { draftId });

                status.classList.remove("autosave-status--hidden");
                spinner.style.display = "inline-block";
                icon.style.display = "none";
                text.textContent = "Guardando";
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

                if (userRole === "admin") {
                        const canal = document.getElementById("canal-venta");
                        if (canal && canal.value) {
                                garantia.canal_venta = canal.value;
                        }
                        const usuario = document.getElementById("usuario-rol");
                        if (usuario && usuario.value) {
                                garantia.concesionario_empresa_profesional = usuario.value;
                        }
                } else if (userRole === "comercial") {
                        garantia.canal_venta = "profesional";
                        const usuario = document.getElementById("usuario-rol");
                        if (usuario && usuario.value) {
                                garantia.concesionario_empresa_profesional = usuario.value;
                        }
                } else if (userRole === "profesional") {
                        garantia.canal_venta = "profesional";
                        const currentId = getCurrentUserId();
                        if (currentId) {
                                garantia.concesionario_empresa_profesional = currentId;
                        }
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
                        payload.estado_garantia.estado_contratacion = "pendiente_pago";
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
                        if (finalize) {
                                showSuccess(
                                        garantia.metodo_pago,
                                        datosVehiculo.matricula,
                                        garantia.precio,
                                        garantia.nivel_garantia,
                                        garantia.meses_contratados
                                );
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
                        debounced(finalize);
                },
                { capture: true }
        );
        FormCache.prevButton?.addEventListener("click", () => debounced(false));
        FormCache.tabs?.forEach((tab) => tab.addEventListener("click", () => debounced(false)));
}

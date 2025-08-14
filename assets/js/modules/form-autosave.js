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

        async function sendAutosave() {
                console.log("[AUTOSAVE] Triggered", { draftId });

                status.classList.remove("autosave-status--hidden");
                spinner.style.display = "inline-block";
                icon.style.display = "none";
                text.textContent = "Guardando";

                const payload = {};
                const datosVehiculo = {};
                const datosCliente = {};

                FormCache.inputs.forEach((input) => {
                        if (!input.id || skipFields.has(input.id)) return;
                        let value = input.value;
                        if (numericFields.includes(input.id)) {
                                value = value.replace(/\./g, "").replace(",", ".");
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
                        }
                        const recargosEl = document.querySelector(
                                ".form__plan.selected .form__plan-recargos-precios"
                        );
                        if (recargosEl) {
                                const txt = recargosEl.textContent;
                                const baseMatch = txt.match(/Precio base:\s*([0-9.,]+)/i);
                                const finalMatch = txt.match(/Precio final \+ IVA:\s*([0-9.,]+)/i);
                                if (baseMatch) {
                                        garantia.descuentos_y_recargos = {
                                                precio_base: baseMatch[1]
                                                        .replace(/\./g, "")
                                                        .replace(",", "."),
                                        };
                                }
                                if (finalMatch) {
                                        garantia.precio = finalMatch[1]
                                                .replace(/\./g, "")
                                                .replace(",", ".");
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
                        spinner.style.display = "none";
                        icon.style.display = "inline-block";
                        text.textContent = "Guardado";
                        setTimeout(() => {
                                status.classList.add("autosave-status--hidden");
                        }, 1500);
                } catch (e) {
                        console.error("[AUTOSAVE] error", e);
                        status.classList.add("autosave-status--hidden");
                }
        }

        const debounced = debounce(sendAutosave, 300);

        FormCache.nextButton?.addEventListener("click", debounced);
        FormCache.prevButton?.addEventListener("click", debounced);
        FormCache.tabs?.forEach((tab) => tab.addEventListener("click", debounced));
}

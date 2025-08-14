// assets/js/modules/form-autosave.js
"use strict";

import FormCache from "./form-cache.js";
import { getRestRoot, getRestNonce, getIcon } from "./config.js";
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
                        if (!input.id) return;
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

                if (Object.keys(datosVehiculo).length) {
                        payload.datos_vehiculo = datosVehiculo;
                        if (datosVehiculo.matricula) {
                                payload.matricula = datosVehiculo.matricula;
                        }
                }

                if (Object.keys(datosCliente).length) {
                        payload.datos_cliente = datosCliente;
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

// assets/js/modules/form-dynamic-fields.js
"use strict";

/*
    - Lógica de visibilidad y dependencias entre campos del formulario.
    - Gestiona el caso especial de "camion" mostrando traccion_camion y ocultando traccion.
    - También muestra/oculta el select de doble motor en función del combustible seleccionado.
*/

import { updateNextButtonState } from "./form-navigation.js";
import { debouncedUpdateSummary } from "./form-summary.js";

// Flota los labels de los selects si tienen valor (también en dinámicos)
function updateSelectFloatingLabels() {
	document
		.querySelectorAll(".form__input-container select.form__select")
		.forEach((select) => {
			const container = select.closest(".form__input-container");
			if (!container) return;
			if (select.value && select.value !== "") {
				container.classList.add("has-value");
			} else {
				container.classList.remove("has-value");
			}
		});
}

function toggleVehiculoFields() {
	const tipoVehiculo = document.getElementById("tipo_vehiculo");
        const traccionContainer = document.querySelector(
                ".form__input-container--traccion"
        );
        const traccionCamionContainer = document.querySelector(
                ".form__input-container--traccion-camion"
        );

	if (!tipoVehiculo) return;

        const isCamion = tipoVehiculo.value === "camion";
        const isMoto = tipoVehiculo.value === "moto";
        const isTodoterreno = tipoVehiculo.value === "todoterreno";

        // Tracción normal
        if (traccionContainer) {
                const traccion = traccionContainer.querySelector("#traccion");
                const ocultarTraccion = isCamion || isMoto;
                traccionContainer.style.display = ocultarTraccion ? "none" : "";
                if (traccion) {
                        if (traccion.dataset.originalRequired == null) {
                                traccion.dataset.originalRequired = traccion.required
                                        ? "true"
                                        : "false";
                        }
                        if (ocultarTraccion) {
                                traccion.value = "";
                                traccion.required = false;
                        } else {
                                if (traccion.dataset.originalRequired !== "false") {
                                        traccion.required = true;
                                }
                                if (isTodoterreno) {
                                        const opcion4x4 = Array.from(traccion.options || []).find(
                                                (option) => option.value === "4x4"
                                        );
                                        if (opcion4x4) {
                                                const valorAnterior = traccion.value;
                                                traccion.value = "4x4";
                                                if (valorAnterior !== "4x4") {
                                                        traccion.dispatchEvent(
                                                                new Event("change", { bubbles: true })
                                                        );
                                                }
                                        }
                                }
                        }
                }
        }
        // Tracción camión y MMA
        if (traccionCamionContainer) {
                const traccionCamion = traccionCamionContainer.querySelector("#traccion_camion");
                traccionCamionContainer.style.display = isCamion ? "" : "none";
                if (traccionCamion) {
                        if (traccionCamion.dataset.originalRequired == null) {
                                traccionCamion.dataset.originalRequired =
                                        traccionCamion.required ? "true" : "false";
                        }
                        if (!isCamion) {
                                traccionCamion.value = "";
                                traccionCamion.required = false;
                        } else if (traccionCamion.dataset.originalRequired !== "false") {
                                traccionCamion.required = true;
                        }
                }
        }
        // Actualiza el estado de validación y resumen
        updateNextButtonState();
        debouncedUpdateSummary();
	// <- AQUÍ ESTÁ LA CLAVE: actualiza labels flotantes tras cambios
	updateSelectFloatingLabels();
}

function updatePotenciaUnits() {
        const combustible = document.getElementById("combustible")?.value;
        const potenciaLabel = document.querySelector("label[for='potencia']");
        const potenciaSuffix = document
                .getElementById("potencia")
                ?.closest(".form__input-container")
                ?.querySelector(".form__suffix");
        const summaryUnit = document.getElementById("summary-potencia-unit");
        const isElectrico = combustible === "electrico";
        if (potenciaLabel)
                potenciaLabel.textContent = isElectrico
                        ? "Potencia (kW)"
                        : "Potencia (CV)";
        if (potenciaSuffix)
                potenciaSuffix.textContent = isElectrico ? "kW" : "CV";
        if (summaryUnit)
                summaryUnit.textContent = isElectrico ? "kW" : "CV";
}

function toggleCombustibleDependientes() {
        const combustible = document.getElementById("combustible");
        const dobleMotorContainer = document.querySelector(
                ".form__input-container--doble_motor"
        );
	if (!combustible || !dobleMotorContainer) return;

        const val = combustible.value;
        const isElectrico = val === "electrico";
        const isHibrido = val === "hibrido";
        const dobleMotor = dobleMotorContainer.querySelector("#doble_motor");
        const cambio = document.getElementById("cambio");
        const opcionesCambio = cambio ? Array.from(cambio.options || []) : [];
        const opcionAutomatico = opcionesCambio.find(
                (option) => option.value === "automatico"
        );
        const shouldRestrictCambio = isElectrico || isHibrido;
        const forzarAutomatico = shouldRestrictCambio && opcionAutomatico;

        dobleMotorContainer.style.display = isElectrico ? "" : "none";

        if (dobleMotor) {
                if (dobleMotor.dataset.originalRequired == null) {
                        dobleMotor.dataset.originalRequired = dobleMotor.required
                                ? "true"
                                : "false";
                }

                if (!isElectrico) {
                        dobleMotor.value = "";
                        dobleMotor.required = false;
                } else if (dobleMotor.dataset.originalRequired !== "false") {
                        dobleMotor.required = true;
                }
        }

        if (cambio && opcionesCambio.length > 0) {
                opcionesCambio.forEach((option) => {
                        if (option.dataset.originalDisabled == null) {
                                option.dataset.originalDisabled = option.disabled
                                        ? "true"
                                        : "false";
                        }

                        if (!option.value) return;

                        const esAutomatico = option.value === "automatico";
                        if (shouldRestrictCambio && !esAutomatico) {
                                option.disabled = true;
                                option.hidden = true;
                        } else {
                                option.hidden = false;
                                option.disabled = option.dataset.originalDisabled === "true";
                        }
                });

                if (forzarAutomatico && cambio.value !== "automatico") {
                        cambio.value = "automatico";
                        cambio.dispatchEvent(new Event("change", { bubbles: true }));
                }
        }

        // Actualiza floating label tras cambio
        updateSelectFloatingLabels();

        // Refresca validación/resumen
        updateNextButtonState();
        updatePotenciaUnits();
        debouncedUpdateSummary();
}

function initDynamicFields() {
	const tipoVehiculo = document.getElementById("tipo_vehiculo");
	if (tipoVehiculo) {
		tipoVehiculo.addEventListener("change", toggleVehiculoFields);
		// Estado inicial:
		toggleVehiculoFields();
	}

        const combustible = document.getElementById("combustible");
        if (combustible) {
                combustible.addEventListener("change", toggleCombustibleDependientes);
                // Estado inicial:
                toggleCombustibleDependientes();
                updatePotenciaUnits();
        }

	// También flota labels de selects al cargar (en caso de edición)
	updateSelectFloatingLabels();

	// Añade evento a todos los selects para que floten labels cuando el usuario cambie valor
	document
		.querySelectorAll(".form__input-container select.form__select")
		.forEach((select) => {
			select.addEventListener("change", updateSelectFloatingLabels);
		});
}

export default initDynamicFields;

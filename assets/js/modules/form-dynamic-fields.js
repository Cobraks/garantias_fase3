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
                        } else if (traccion.dataset.originalRequired !== "false") {
                                traccion.required = true;
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
	const debeMostrar = ["electrico", "hibrido", "gpl_gnc"].includes(val);
	dobleMotorContainer.style.display = debeMostrar ? "" : "none";
	if (!debeMostrar) {
		const dobleMotor = dobleMotorContainer.querySelector("#doble_motor");
		if (dobleMotor) dobleMotor.value = "";
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

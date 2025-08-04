// assets/js/modules/form-cache.js
"use strict";

/*
    - Lógica de visibilidad y dependencias entre campos del formulario.
    - Ahora gestiona el caso especial de "camion" mostrando traccion_camion y mma, ocultando traccion.
    - También muestra/oculta el select de doble motor en función del combustible seleccionado.
*/

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
	const mmaContainer = document.querySelector(".form__input-container--mma");

	if (!tipoVehiculo) return;

	const isCamion = tipoVehiculo.value === "camion";

	// Tracción normal
	if (traccionContainer) {
		traccionContainer.style.display = isCamion ? "none" : "";
		if (isCamion) {
			const traccion = traccionContainer.querySelector("#traccion");
			if (traccion) traccion.value = "";
		}
	}
	// Tracción camión y MMA
	if (traccionCamionContainer) {
		traccionCamionContainer.style.display = isCamion ? "" : "none";
		if (!isCamion) {
			const traccionCamion =
				traccionCamionContainer.querySelector("#traccion_camion");
			if (traccionCamion) traccionCamion.value = "";
		}
	}
	if (mmaContainer) {
		mmaContainer.style.display = isCamion ? "" : "none";
		if (!isCamion) {
			const mma = mmaContainer.querySelector("#mma");
			if (mma) mma.value = "";
		}
	}

	// Actualiza el estado de validación y resumen
	if (typeof window.updateNextButtonState === "function") {
		window.updateNextButtonState();
	}
	if (typeof window.debouncedUpdateSummary === "function") {
		window.debouncedUpdateSummary();
	}
	// <- AQUÍ ESTÁ LA CLAVE: actualiza labels flotantes tras cambios
	updateSelectFloatingLabels();
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
	if (typeof window.updateNextButtonState === "function") {
		window.updateNextButtonState();
	}
	if (typeof window.debouncedUpdateSummary === "function") {
		window.debouncedUpdateSummary();
	}
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

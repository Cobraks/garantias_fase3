// assets/js/modules/form-example-data.js
"use strict";

import { updateNextButtonState } from "./form-navigation.js";
import { debouncedUpdateSummary } from "./form-summary.js";

/*
    - Rellena todos los campos del formulario con datos de ejemplo realistas.
    - Solo se ejecuta si existe el botón #rellenar_ejemplo.
    - Actualiza estado del botón Siguiente y del resumen tras rellenar.
*/

function rellenarDatosEjemplo() {
	const datos = {
		tipo_vehiculo: "turismo", // value exacto del option
		marca: "BMW", // value exacto del option
		modelo: "Serie 1", // value exacto del option
		traccion: "delantera", // value exacto del option
		combustible: "gasolina", // value exacto del option
		cambio: "manual", // value exacto del option
		kilometros: "50000",
		fecha_primera_matriculacion: "2018-05-10",
		matricula: "1234JKL",
		numero_bastidor: "WBA8D61070A123456",
		precio_venta: "17500",
		potencia: "150",
		cilindrada: "3003",
		dni: "12345678Z",
		nombre_apellidos: "Juan Pérez García",
		telefono: "600123456",
		correo: "juan.perez@example.com",
		direccion: "Calle Ejemplo 45",
		localidad: "Madrid",
		provincia: "Madrid",
		codigo_postal: "28001",
		// Añade aquí cualquier campo adicional exacto de tu formulario
		// Ejemplo:
		// color: "negro",
		// garantia: "12_meses",
	};

	Object.entries(datos).forEach(([id, valor]) => {
		const elem = document.getElementById(id);
		if (elem) {
			elem.value = valor;
			elem.dispatchEvent(new Event("input", { bubbles: true }));
			if (elem.tagName === "SELECT") {
				elem.dispatchEvent(new Event("change", { bubbles: true }));
			}
		}
	});

        // --- Refresca botón Siguiente y resumen ---
        updateNextButtonState();
        debouncedUpdateSummary();
}

function initExampleData() {
	const btn = document.getElementById("rellenar_ejemplo");
	if (btn) btn.addEventListener("click", rellenarDatosEjemplo);
}

export default initExampleData;

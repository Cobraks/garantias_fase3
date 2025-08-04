// assets/js/modules/form-submission.js
"use strict";

/*
    - Gestiona la lógica final de envío del formulario.
    - Puede serializar datos, validar última vez y enviar por AJAX, fetch, etc.
    - Por ahora, solo muestra un alert como placeholder.
*/

export default function initSubmission() {
	const form = document.getElementById("form-nueva-garantia");
	if (!form) return;

	form.addEventListener("submit", function (e) {
		e.preventDefault();
		// Aquí podrías serializar y enviar por AJAX/fetch...
		alert("¡Formulario enviado correctamente! (esto es solo un ejemplo)");
	});
}

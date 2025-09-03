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

        form.addEventListener("submit", async function (e) {
                e.preventDefault();

                const idField = form.querySelector('input[name="garantia_id"]');
                const postId  = idField ? idField.value : '';

                const button = form.querySelector('button[type="submit"]');
                if (button) {
                        button.disabled = true;
                        button.textContent = "Generando certificado...";
                }

                try {
                        const res  = await fetch(`/wp-json/go/v1/guarantees/${postId}/docs`, {
                                method: "POST",
                                credentials: "same-origin",
                        });
                        const data = await res.json();
                        console.log("Documento generado", data);
                } catch (err) {
                        console.error("Error generando PDF", err);
                } finally {
                        if (button) {
                                button.disabled = false;
                        }
                }
        });
}

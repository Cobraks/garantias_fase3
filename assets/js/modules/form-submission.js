// assets/js/modules/form-submission.js
"use strict";

/*
    - Gestiona la lógica final de envío del formulario.
    - Puede serializar datos, validar última vez y enviar por AJAX, fetch, etc.
    - Por ahora, solo muestra un alert como placeholder.
*/

export default function initSubmission() {
        const form = document.getElementById("form-garantia");
        if (!form) return;

        form.addEventListener("submit", async function (e) {
                e.preventDefault();

                const idField = form.querySelector('input[name="garantia_id"]');
                const postId  = idField ? idField.value : '';

                let data = null;
                try {
                        const res = await fetch(`/wp-json/go/v1/guarantees/${postId}/docs`, {
                                method: "POST",
                                credentials: "same-origin",
                        });
                        data = await res.json();
                } catch (err) {
                        console.error("Error generando PDF", err);
                }

                const success  = document.getElementById("form-success");
                const loading  = success ? success.querySelector("#doc-loading") : null;
                const download = success ? success.querySelector("#doc-download") : null;

                if (data && data.hash && download) {
                        download.href = `/garantias-online/descargar/${data.hash}/`;
                        download.hidden = false;
                }
                if (loading) {
                        loading.remove();
                }
        });
}

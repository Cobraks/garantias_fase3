// assets/js/modules/form-submission.js
"use strict";

/*
    - Gestiona la lógica final de envío del formulario.
    - Puede serializar datos, validar última vez y enviar por AJAX, fetch, etc.
    - Por ahora, solo muestra un alert como placeholder.
*/

export default function initSubmission() {
        const form = document.getElementById("form-garantia");
        if (!form) {
                console.warn("[GO] form-garantia no encontrado");
                return;
        }

        form.addEventListener("submit", async function (e) {
                e.preventDefault();

                const idField = form.querySelector('input[name="garantia_id"]');
                const postId  = idField ? idField.value : '';
                console.log("[GO] Envío de garantía", { postId });

                let data = null;
                try {
                        console.log("[GO] Generando documentación...", `/wp-json/go/v1/guarantees/${postId}/docs`);
                        const res = await fetch(`/wp-json/go/v1/guarantees/${postId}/docs`, {
                                method: "POST",
                                credentials: "same-origin",
                        });
                        console.log("[GO] Respuesta docs", res.status);
                        data = await res.json();
                        console.log("[GO] Datos devueltos", data);
                } catch (err) {
                        console.error("[GO] Error generando PDF", err);
                }

                const success  = document.getElementById("form-success");
                const loading  = success ? success.querySelector("#doc-loading") : null;
                const download = success ? success.querySelector("#doc-download") : null;

                if (data && data.hash && download) {
                        console.log("[GO] Hash recibido", data.hash);
                        download.href = `/garantias-online/descargar/${data.hash}/`;
                        download.hidden = false;
                } else {
                        console.warn("[GO] No se recibió hash para la descarga");
                }
                if (loading) {
                        console.log("[GO] Ocultando spinner de generación");
                        loading.remove();
                }
        });
}

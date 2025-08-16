// assets/js/modules/form-submission.js
"use strict";

import { getRestRoot, getRestNonce } from "./config.js";

/*
    - Gestiona la lógica final de envío del formulario.
    - Envía la garantía creada al servidor para marcarla como contratada.
*/

export default function initSubmission() {
        const form = document.getElementById("form-nueva-garantia");
        if (!form) return;

        form.addEventListener("submit", async function (e) {
                e.preventDefault();

                const draftId = localStorage.getItem("go_draft_id");
                if (!draftId) {
                        alert("No se ha encontrado la garantía a enviar");
                        return;
                }

                try {
                        const res = await fetch(`${getRestRoot()}go/v1/guarantees/contract`, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": getRestNonce(),
                                },
                                body: JSON.stringify({ id: draftId }),
                        });

                        const json = await res.json();
                        if (!res.ok) {
                                alert(json?.message || "Error al contratar la garantía");
                                return;
                        }

                        localStorage.removeItem("go_draft_id");
                        alert("¡Garantía contratada correctamente!");
                } catch (err) {
                        console.error("[SUBMISSION]", err);
                        alert("Error al contratar la garantía");
                }
        });
}

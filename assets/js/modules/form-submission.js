// assets/js/modules/form-submission.js
"use strict";

/*
    - Gestiona la lógica final de envío del formulario.
    - Al pulsar "Contratar" publica la garantía en borrador mediante la API REST.
*/

import { getRestRoot, getRestNonce } from "./config.js";

export default function initSubmission() {
        const form = document.getElementById("form-nueva-garantia");
        if (!form) return;

        form.addEventListener("submit", async function (e) {
                e.preventDefault();
                const draftId = localStorage.getItem("go_draft_id");
                if (!draftId) {
                        alert("No se encontró la garantía en borrador.");
                        return;
                }
                try {
                        const res = await fetch(`${getRestRoot()}go/v1/guarantees/${draftId}/publish`, {
                                method: "POST",
                                headers: {
                                        "X-WP-Nonce": getRestNonce(),
                                },
                        });
                        const json = await res.json();
                        if (!res.ok) throw json?.message || "Error al publicar la garantía.";
                        localStorage.removeItem("go_draft_id");
                        localStorage.removeItem("go_draft_uuid");
                        alert("Garantía contratada correctamente.");
                } catch (err) {
                        console.error("[SUBMIT]", err);
                        alert(err);
                }
        });
}

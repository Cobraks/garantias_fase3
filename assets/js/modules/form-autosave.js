// assets/js/modules/form-autosave.js
"use strict";

import FormCache from "./form-cache.js";
import { getRestRoot, getRestNonce, getIcon } from "./config.js";
import { debounce } from "./form-utils.js";

export default function initAutosave() {
        const form = document.getElementById("form-garantia");
        if (!form) return;

        const container = document.querySelector(".form-container") || document.body;
        const status = document.createElement("div");
        status.className = "autosave-status autosave-status--hidden";
        status.innerHTML = `
                <span class="autosave-status__spinner"></span>
                <span class="autosave-status__icon">${getIcon("save")}</span>
                <span class="autosave-status__text">Guardando</span>`;
        container.appendChild(status);

        const spinner = status.querySelector(".autosave-status__spinner");
        const icon = status.querySelector(".autosave-status__icon");
        const text = status.querySelector(".autosave-status__text");
        icon.style.display = "none";

        let draftId = localStorage.getItem("go_draft_id");

        async function sendAutosave() {
                status.classList.remove("autosave-status--hidden");
                spinner.style.display = "inline-block";
                icon.style.display = "none";
                text.textContent = "Guardando";

                const formData = new FormData(form);
                const payload = {};
                formData.forEach((value, key) => {
                        payload[key] = value;
                });
                try {
                        const res = await fetch(
                                `${getRestRoot()}go/v1/guarantees/autosave`,
                                {
                                        method: "POST",
                                        headers: {
                                                "Content-Type": "application/json",
                                                "X-WP-Nonce": getRestNonce(),
                                        },
                                        body: JSON.stringify({ id: draftId, data: payload }),
                                }
                        );
                        const json = await res.json();
                        if (json.id) {
                                draftId = json.id;
                                localStorage.setItem("go_draft_id", draftId);
                        }
                        spinner.style.display = "none";
                        icon.style.display = "inline-block";
                        text.textContent = "Guardado";
                        setTimeout(() => {
                                status.classList.add("autosave-status--hidden");
                        }, 1500);
                } catch (e) {
                        status.classList.add("autosave-status--hidden");
                }
        }

        const debounced = debounce(sendAutosave, 300);

        FormCache.nextButton?.addEventListener("click", debounced);
        FormCache.prevButton?.addEventListener("click", debounced);
        FormCache.tabs?.forEach((tab) => tab.addEventListener("click", debounced));
}

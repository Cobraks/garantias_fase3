(() => {
	document.addEventListener("DOMContentLoaded", () => {
		console.log("DOM loaded — inicializando mis_garantias.js");

                const tbody = document.querySelector("tbody[data-current-page]");
                const table = tbody.closest("table");
                const listContainer = document.querySelector(".guarantees-list");
                const scrollEnd = listContainer.querySelector("#scroll-end");
                const spinner = scrollEnd.querySelector(".spinner");

                const restRoot =
                        (window.__GO_CONFIG__ && window.__GO_CONFIG__.rest && window.__GO_CONFIG__.rest.root) ||
                        (window.GO_REST && window.GO_REST.root) ||
                        "/wp-json/";
                const restNonce =
                        (window.__GO_CONFIG__ && window.__GO_CONFIG__.rest && window.__GO_CONFIG__.rest.nonce) ||
                        (window.GO_REST && window.GO_REST.nonce) ||
                        "";
                const userRole =
                        (window.__GO_CONFIG__ &&
                                window.__GO_CONFIG__.user &&
                                window.__GO_CONFIG__.user.role) ||
                        "user";
                const isAdmin =
                        ["administrator", "admin", "go_garantias", "go_comercial"].includes(userRole);
                const isProfesional = userRole === "go_profesional";
                const copyIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M360-240q-33 0-56.5-23.5T280-320v-480q0-33 23.5-56.5T360-880h360q33 0 56.5 23.5T800-800v480q0 33-23.5 56.5T720-240H360Zm0-80h360v-480H360v480ZM200-80q-33 0-56.5-23.5T120-160v-560h80v560h440v80H200Zm160-240v-480 480Z"/></svg>';
                const phoneIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M798-120q-125 0-247-54.5T329-329Q229-429 174.5-551T120-798q0-18 12-30t30-12h162q14 0 25 9.5t13 22.5l26 140q2 16-1 27t-11 19l-97 98q20 37 47.5 71.5T387-386q31 31 65 57.5t72 48.5l94-94q9-9 23.5-13.5T670-390l138 28q14 4 23 14.5t9 23.5v162q0 18-12 30t-30 12ZM241-600l66-66-17-94h-89q5 41 14 81t26 79Zm358 358q39 17 79.5 27t81.5 13v-88l-94-19-67 67ZM241-600Zm358 358Z"/></svg>';
                const emailIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M160-160q-33 0-56.5-23.5T80-240v-480q0-33 23.5-56.5T160-800h640q33 0 56.5 23.5T880-720v480q0 33-23.5 56.5T800-160H160Zm320-280L160-640v400h640v-400L480-440Zm0-80 320-200H160l320 200ZM160-640v-80 480-400Z"/></svg>';
                const userIcon = '<svg height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M234-276q51-39 114-61.5T480-360q69 0 132 22.5T726-276q35-41 54.5-93T800-480q0-133-93.5-226.5T480-800q-133 0-226.5 93.5T160-480q0 59 19.5 111t54.5 93Zm246-164q-59 0-99.5-40.5T340-580q0-59 40.5-99.5T480-720q59 0 99.5 40.5T620-580q0 59-40.5 99.5T480-440Zm0 360q-83 0-156-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127 85.5T480-80Zm0-80q53 0 100-15.5t86-44.5q-39-29-86-44.5T480-280q-53 0-100 15.5T294-220q39 29 86 44.5T480-160Zm0-360q26 0 43-17t17-43q0-26-17-43t-43-17q-26 0-43 17t-17 43q0 26 17 43t43 17Zm0-60Zm0 360Z"/></svg>';
                const warningIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m40-120 440-760 440 760H40Zm138-80h604L480-720 178-200Zm302-40q17 0 28.5-11.5T520-280q0-17-11.5-28.5T480-320q-17 0-28.5 11.5T440-280q0 17 11.5 28.5T480-240Zm-40-120h80v-200h-80v200Zm40-100Z"/></svg>';
                const heartIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="m480-120-58-52q-101-91-167-157T150-447.5Q111-500 95.5-544T80-634q0-94 63-157t157-63q52 0 99 22t81 62q34-40 81-62t99-22q94 0 157 63t63 157q0 46-15.5 90T810-447.5Q771-395 705-329T538-172l-58 52Zm0-108q96-86 158-147.5t98-107q36-45.5 50-81t14-70.5q0-60-40-100t-100-40q-47 0-87 26.5T518-680h-76q-15-41-55-67.5T300-774q-60 0-100 40t-40 100q0 35 14 70.5t50 81q36 45.5 98 107T480-228Zm0-273Z"/></svg>';
                const shareIcon = '<svg height="24" viewBox="0 -960 960 960" width="24" fill="currentColor"><path d="M680-80q-50 0-85-35t-35-85q0-6 3-28L282-392q-16 15-37 23.5t-45 8.5q-50 0-85-35t-35-85q0-50 35-85t85-35q24 0 45 8.5t37 23.5l281-164q-2-7-2.5-13.5T560-760q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35q-24 0-45-8.5T598-672L317-508q2 7 2.5 13.5t.5 14.5q0 8-.5 14.5T317-452l281 164q16-15 37-23.5t45-8.5q50 0 85 35t35 85q0 50-35 85t-85 35Zm0-80q17 0 28.5-11.5T720-200q0-17-11.5-28.5T680-240q-17 0-28.5 11.5T640-200q0 17 11.5 28.5T680-160ZM200-440q17 0 28.5-11.5T240-480q0-17-11.5-28.5T200-520q-17 0-28.5 11.5T160-480q0 17 11.5 28.5T200-440Zm480-280q17 0 28.5-11.5T720-760q0-17-11.5-28.5T680-800q-17 0-28.5 11.5T640-760q0 17 11.5 28.5T680-720Zm0 520ZM200-480Zm480-280Z"/></svg>';
                const paymentIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M560-440q-50 0-85-35t-35-85q0-50 35-85t85-35q50 0 85 35t35 85q0 50-35 85t-85 35ZM280-320q-33 0-56.5-23.5T200-400v-320q0-33 23.5-56.5T280-800h560q33 0 56.5 23.5T920-720v320q0 33-23.5 56.5T840-320H280Zm80-80h400q0-33 23.5-56.5T840-480v-160q-33 0-56.5-23.5T760-720H360q0 33-23.5 56.5T280-640v160q33 0 56.5 23.5T360-400Zm440 240H120q-33 0-56.5-23.5T40-240v-440h80v440h680v80ZM280-400v-320 320Z"/></svg>';
                const continueIcon = '<svg xmlns="http://www.w3.org/2000/svg" height="24px" viewBox="0 -960 960 960" width="24px" fill="currentColor"><path d="M200-200h57l391-391-57-57-391 391v57Zm-80 80v-170l528-527q12-11 26.5-17t30.5-6q16 0 31 6t26 18l55 56q12 11 17.5 26t5.5 30q0 16-5.5 30.5T817-647L290-120H120Zm640-584-56-56 56 56Zm-141 85-28-29 57 57-29-28Z"/></svg>';
                const saveStatus = document.createElement("div");
                saveStatus.className = "autosave-status autosave-status--hidden";
                saveStatus.innerHTML =
                        '<span class="autosave-status__spinner"></span>' +
                        '<span class="autosave-status__icon" style="display:none"></span>' +
                        '<span class="autosave-status__text">Guardando</span>';
                document.body.appendChild(saveStatus);
                const DEFAULT_PER = 12;
                let perPage = DEFAULT_PER;
		let currentPage = 1;
		let totalPages = 1;
		let totalPosts = 0;
		let isLoading = false;
		let hasMore = true;
		let searchQuery = "";
		let lastValidQuery = "";
		let lastValidResults = [];
                const detail = document.querySelector(".guarantee-detail");
                let panel1 = document.getElementById("detail-panel-1");
                let panel2 = document.getElementById("detail-panel-2");
                let lastEmptyPanel = panel1;

                const filterSelects = document.querySelectorAll(
                        ".guarantees-list__filter"
                );
                const estadoSelect = filterSelects[0];
                const planSelect = filterSelects[1];
                const canalSelect = filterSelects[2];
                const concesionarioSelect = filterSelects[3];
                let selectedEstado = "";
                let selectedPlan = "";
                let selectedCanal = "";
                let selectedConcesionario = "";
                let currentListAbort = null;

		let resultMessage = document.querySelector(
			".guarantees-list__result-message"
		);
		if (!resultMessage) {
			resultMessage = document.createElement("div");
			resultMessage.className = "guarantees-list__result-message";
			resultMessage.style.display = "none";
			table.insertAdjacentElement("afterend", resultMessage);
		}

                if (!panel1 || !panel2) {
                        detail.innerHTML =
                                '<div class="guarantee-detail__panel active" id="detail-panel-1">' +
                                detail.innerHTML +
                                '</div><div class="guarantee-detail__panel" id="detail-panel-2"></div>';
                        panel1 = document.getElementById("detail-panel-1");
                        panel2 = document.getElementById("detail-panel-2");
                }

		let activePanel = panel1;
		let inactivePanel = panel2;
		activePanel.classList.add("active");
		inactivePanel.classList.remove("active");
                let prevSelectedRow = null;
                let prevIdx = null;
                const urlMat = new URLSearchParams(window.location.search).get("matricula");
                const detailCache = new Map();
                const detailPromises = new Map();
                const loadedIds = new Set();

                function fetchDetail(id) {
                        if (detailCache.has(id)) {
                                return Promise.resolve(detailCache.get(id));
                        }
                        if (detailPromises.has(id)) {
                                return detailPromises.get(id);
                        }
                        const p = fetch(`${restRoot}go/v1/guarantees/${id}`, {
                                headers: { "X-WP-Nonce": restNonce },
                        })
                                .then((res) => {
                                        if (!res.ok) throw res.status;
                                        return res.json();
                                })
                                .then((json) => {
                                        const data = normalizeDetailData(json);
                                        detailCache.set(id, data);
                                        detailPromises.delete(id);
                                        return data;
                                })
                                .catch((err) => {
                                        detailPromises.delete(id);
                                        throw err;
                                });
                        detailPromises.set(id, p);
                        return p;
                }

                document.addEventListener("click", (e) => {
                        const btn = e.target.closest(
                                ".guarantee-detail__btn--continue"
                        );
                        if (!btn) return;
                        const panel = btn.closest(".guarantee-detail__panel");
                        const id = panel?.dataset.loadedId;
                        if (id) {
                                localStorage.setItem("go_draft_id", id);
                                const data = detailCache.get(id);
                                if (data && data.uuid) {
                                        localStorage.setItem(
                                                "go_draft_uuid",
                                                data.uuid
                                        );
                                }
                        }
                        const data = id ? detailCache.get(id) : null;
                        const uuid = data && data.uuid ? data.uuid : "";
                        window.location.href =
                                "/garantias-online/nueva-garantia?uuid=" +
                                encodeURIComponent(uuid);
                });

                function handleConfirmClick(e) {
                        const btn = e.target.closest(
                                ".guarantee-detail__btn--confirm"
                        );
                        if (!btn) return;
                        const panel = btn.closest(".guarantee-detail__panel");
                        const id = panel?.dataset.loadedId;
                        if (!id) return;
                        const cacheData = detailCache.get(id);
                        const uuid = cacheData && cacheData.uuid ? cacheData.uuid : "";
                        if (!uuid) return;
                        const textSpan = btn.querySelector(
                                ".guarantee-detail__btn-text"
                        );
                        const originalText = textSpan
                                ? textSpan.textContent
                                : "";
                        let spinner = btn.querySelector(
                                ".guarantee-detail__btn-spinner"
                        );
                        if (!spinner) {
                                spinner = document.createElement("span");
                                spinner.className =
                                        "guarantee-detail__btn-spinner";
                                if (textSpan) {
                                        btn.insertBefore(spinner, textSpan);
                                } else {
                                        btn.appendChild(spinner);
                                }
                        }
                        if (textSpan) {
                                textSpan.textContent =
                                        "Activando garantía";
                        }
                        btn.disabled = true;
                        saveStatus.classList.remove(
                                "autosave-status--hidden"
                        );
                        let row;
                        const metodo = cacheData?.metodo_pago || "";
                        const body = {
                                id,
                                uuid,
                                data: {
                                        estado_garantia: {
                                                estado_contratacion: "activada",
                                        },
                                },
                        };
                        if (metodo === "domiciliacion_bancaria") {
                                body.data.garantia_contratada = {
                                        estado_cobro: { cobro_realizado: true },
                                };
                        }
                        fetch(`${restRoot}go/v1/guarantees/autosave`, {
                                method: "POST",
                                headers: {
                                        "Content-Type": "application/json",
                                        "X-WP-Nonce": restNonce,
                                },
                                body: JSON.stringify(body),
                        })
                                .then((res) => {
                                        if (!res.ok) throw res.status;
                                        detailCache.delete(id);
                                        row = tbody.querySelector(
                                                `.guarantees-table__row[data-id="${id}"]`
                                        );
                                        if (row) {
                                                row.dataset.estadoclase = "activada";
                                                row.dataset.estado = "Activada";
                                                row.dataset.cobroRealizado = "1";
                                                const badge = row.querySelector(
                                                        ".guarantees-list__badge"
                                                );
                                                if (badge) {
                                                        badge.textContent = "Activada";
                                                        badge.className =
                                                                "guarantees-list__badge guarantees-list__badge--activada";
                                                }
                                                const cobroBadge = row.querySelector(
                                                        ".guarantees-list__badge--pend-cobro"
                                                );
                                                if (cobroBadge) {
                                                        cobroBadge.remove();
                                                }
                                        }
                                        return fetch(
                                                `${restRoot}go/v1/guarantees/${id}`,
                                                { headers: { "X-WP-Nonce": restNonce } }
                                        );
                                })
                                .then((detailRes) => {
                                        if (!detailRes.ok) throw detailRes.status;
                                        const rowData = row ? buildRowData(row) : {};
                                        return detailRes
                                                .json()
                                                .then((json) => {
                                                        const data = normalizeDetailData(json);
                                                        detailCache.set(id, data);
                                                        panel.innerHTML = renderFullDetail(
                                                                data,
                                                                rowData,
                                                                []
                                                        );
                                                });
                                })
                                .catch((err) => {
                                        console.error("Error confirm payment:", err);
                                })
                                .finally(() => {
                                        btn.disabled = false;
                                        saveStatus.classList.add(
                                                "autosave-status--hidden"
                                        );
                                        if (textSpan) {
                                                textSpan.textContent =
                                                        originalText ||
                                                        "Confirmar pago";
                                        }
                                        if (spinner) {
                                                spinner.remove();
                                        }
                                });
                }
                document.addEventListener("click", handleConfirmClick);

                document.addEventListener("click", (e) => {
                        const btn = e.target.closest("[data-copy]");
                        if (!btn) return;
                        const panel = btn.closest(".guarantee-detail__panel");
                        const target = panel.querySelector(btn.dataset.copy);
                        if (!target) return;
                        const text = target.textContent.trim();
                        if (navigator.clipboard && navigator.clipboard.writeText) {
                                navigator.clipboard.writeText(text).then(() => {
                                        const toast = panel.querySelector(
                                                ".detail__copy-toast"
                                        );
                                        if (toast) {
                                                toast.textContent =
                                                        btn.dataset.toast || "Copiado";
                                                toast.classList.add("show");
                                                setTimeout(
                                                        () => toast.classList.remove("show"),
                                                        2000
                                                );
                                        }
                                        const original = btn.getAttribute(
                                                "aria-label"
                                        );
                                        const done = btn.dataset.done || "Copiado";
                                        btn.setAttribute("aria-label", done);
                                        setTimeout(() => {
                                                if (original) {
                                                        btn.setAttribute(
                                                                "aria-label",
                                                                original
                                                        );
                                                }
                                        }, 2000);
                                });
                        }
                });

                function normalizeEstadoClase(estado) {
                        if (!estado) return "pendiente-pago";
                        return String(estado)
                                .toLowerCase()
                                .normalize("NFD")
                                .replace(/[\u0300-\u036f]/g, "")
                                .replace(/[^a-z0-9]+/g, "-")
                                .replace(/^-+|-+$/g, "");
                }

                function formatDate(value) {
                        if (!value) return { iso: "-", display: "-" };
                        let cleaned = String(value).replace(/[^0-9]/g, "");
                        if (cleaned.length === 8) {
                                const y = cleaned.slice(0, 4);
                                const m = cleaned.slice(4, 6);
                                const d = cleaned.slice(6, 8);
                                const iso = `${y}-${m}-${d}`;
                                const date = new Date(iso);
                                if (!isNaN(date)) {
                                        const display = new Intl.DateTimeFormat("es-ES", {
                                                day: "numeric",
                                                month: "long",
                                                year: "numeric",
                                        })
                                                .format(date)
                                                .replace(/ de /g, " ");
                                        return { iso, display };
                                }
                                return { iso, display: `${d}/${m}/${y}` };
                        }
                        const date = new Date(value);
                        if (!isNaN(date)) {
                                const iso = date.toISOString().slice(0, 10);
                                const display = new Intl.DateTimeFormat("es-ES", {
                                        day: "numeric",
                                        month: "long",
                                        year: "numeric",
                                })
                                        .format(date)
                                        .replace(/ de /g, " ");
                                return { iso, display };
                        }
                        return { iso: value, display: value };
                }

                function formatPrice(value) {
                        if (value === null || value === undefined || value === "") return "-";
                        const num =
                                typeof value === "number"
                                        ? value
                                        : parseFloat(
                                                  String(value)
                                                          .replace(/[^0-9.,-]/g, "")
                                                          .replace(",", ".")
                                          );
                        if (isNaN(num)) return String(value);
                        return new Intl.NumberFormat("es-ES", {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2,
                        }).format(num);
                }

                function normalizeDetailData(data) {
                        if (!data || typeof data !== "object") return data;
                        const d = formatDate(data.desde);
                        data.desde = d.iso;
                        data.desde_fmt = d.display;
                        const h = formatDate(data.hasta);
                        data.hasta = h.iso;
                        data.hasta_fmt = h.display;
                        if (data.precio !== undefined) data.precio = formatPrice(data.precio);
                        if (data.precio_venta !== undefined)
                                data.precio_venta = formatPrice(data.precio_venta);
                        if (data.kilometros !== undefined) {
                                const num = parseInt(String(data.kilometros).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.kilometros = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.cilindrada === undefined && data.Cilindrada !== undefined) {
                                data.cilindrada = data.Cilindrada;
                        }
                        if (data.cilindrada !== undefined) {
                                const num = parseInt(String(data.cilindrada).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.cilindrada = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.potencia !== undefined) {
                                const num = parseInt(String(data.potencia).replace(/[^0-9]/g, ""), 10);
                                if (!isNaN(num)) {
                                        data.potencia = num.toLocaleString("es-ES");
                                }
                        }
                        if (data.combustible && typeof data.combustible === "object") {
                                data.combustible = data.combustible.label || data.combustible.name || data.combustible.value || data.combustible;
                        }
                        if (data.cambio && typeof data.cambio === "object") {
                                data.cambio = data.cambio.label || data.cambio.name || data.cambio.value || data.cambio;
                        }
                        if (data.tipo && typeof data.tipo === "object") {
                                data.tipo = data.tipo.label || data.tipo.name || data.tipo.value || data.tipo;
                        }
                        return data;
                }

                function renderRow(item) {
                        const estadoData = item.estado || "";
                        const estadoValue =
                                typeof estadoData === "object" && estadoData.value
                                        ? estadoData.value
                                        : typeof estadoData === "string" && estadoData
                                        ? estadoData
                                        : typeof estadoData === "number"
                                        ? String(estadoData)
                                        : "";
                        const estadoLabel =
                                typeof estadoData === "object" && estadoData.label
                                        ? estadoData.label
                                        : estadoValue || "Desconocido";
                        const estadoClase = normalizeEstadoClase(estadoValue);
                        const marca_modelo = item.marca ?? "-";
                        const mat = item.mat ?? item.matricula ?? "-";
                        const { iso: desdeIso, display: desde } = formatDate(item.desde);
                        const { iso: hastaIso, display: hasta } = formatDate(item.hasta);
                        const vendedor_name = item.vendedor ?? "-";
                        const plan = item.plan ?? "";
                        const precio = formatPrice(item.precio);
                        const canal_venta =
                                item.canal_venta && item.canal_venta.label
                                        ? item.canal_venta.label
                                        : "-";
                        const vendedor_type = canal_venta;

                        const hasPlan = plan !== "" && plan !== "-";
                        const hasPeriod =
                                desde !== "-" &&
                                hasta !== "-" &&
                                desde !== "" &&
                                hasta !== "";

                        const periodHtml = hasPeriod
                                ? `<div class="guarantees-table__period">
                                                <div><strong>Desde:</strong> <time>${desde}</time></div>
                                                <div><strong>Hasta:</strong> <time>${hasta}</time></div>
                                        </div>`
                                : "-";
                        const planHtml = hasPlan
                                ? `<div class="guarantees-table__plan">
                                                <span class="plan__name">${plan}</span>
                                                <span class="plan__price">${precio}€</span>
                                        </div>`
                                : "";

                        const tr = document.createElement("tr");
                        tr.className = "guarantees-table__row";
                        tr.tabIndex = 0;
                        tr.dataset.id = item.id;
                        tr.dataset.matricula = mat;
                        tr.dataset.marca_modelo = marca_modelo;
                        tr.dataset.plan = hasPlan ? plan : "";
                        tr.dataset.desde = hasPeriod ? desdeIso : "";
                        tr.dataset.desdeFmt = hasPeriod ? desde : "";
                        tr.dataset.hasta = hasPeriod ? hastaIso : "";
                        tr.dataset.hastaFmt = hasPeriod ? hasta : "";
                        tr.dataset.estado = estadoLabel;
                        tr.dataset.estadoclase = estadoClase;
                        tr.dataset.vendedor_name = vendedor_name;
                        tr.dataset.vendedor_type = vendedor_type;
                        tr.dataset.precio = hasPlan ? precio : "";
                        tr.dataset.canalVenta = canal_venta;
                        tr.dataset.metodoPago = item.detail.metodo_pago || "";
                        tr.dataset.cobroRealizado = item.detail.cobro_realizado ? "1" : "";
                        tr.dataset.ibanVendedor = item.detail.iban_vendedor || "";

                        const cobroBadgeHtml =
                                tr.dataset.metodoPago === "domiciliacion_bancaria" &&
                                !tr.dataset.cobroRealizado
                                        ? `<span class="guarantees-list__badge guarantees-list__badge--pend-cobro">pend. cobro</span>`
                                        : "";

                        tr.innerHTML = `
                                <td data-label="Vehículo">
                                        <div class="guarantees-table__vehiculo">
                                                <strong>${marca_modelo}</strong>
                                                <div class="vehiculo__mat">${mat}</div>
                                        </div>
                                </td>
                                <td data-label="Validez">${periodHtml}</td>
                                <td data-label="Vendedor">
                                        <div class="guarantees-table__vendedor">
                                                <div class="vendedor__name">${vendedor_name}</div>
                                                <div class="vendedor__type">${vendedor_type}</div>
                                        </div>
                                </td>
                                <td data-label="Garantía">
                                        ${planHtml}
                                          <span class="guarantees-list__badge guarantees-list__badge--${estadoClase}">
                                                  ${estadoLabel}
                                          </span>
                                          ${cobroBadgeHtml}
                                </td>
                        `;
                        return tr;
                }

		function renderEmptyDetail() {
			return `
				<div class="guarantee-detail__empty">
					<h3 class="guarantee-detail__title">Ninguna garantía seleccionada</h3>
					<p>Haz clic en una fila para ver sus detalles aquí.</p>
				</div>
			`;
		}

		// Nuevo: mostrar el empty panel como los de detalle, solo si no está ya activo
		function setEmptyDetailPanel(direction = "forward") {
			const currentActive = activePanel;
			const nextPanel = activePanel === panel1 ? panel2 : panel1;
			// Si el empty ya está visible, no repetir animación
			if (
				nextPanel.classList.contains("active") &&
				nextPanel.innerHTML.includes("Ninguna garantía seleccionada")
			) {
				return;
			}
			nextPanel.innerHTML = renderEmptyDetail();
			nextPanel.dataset.loadedId = "";
			activePanel = nextPanel;
			inactivePanel = currentActive;
			currentActive.classList.add(
				direction === "forward" ? "slide-out-left" : "slide-out-right"
			);
			nextPanel.classList.add(
				direction === "forward" ? "slide-in-right" : "slide-in-left"
			);
			nextPanel.classList.add("active");
			currentActive.classList.remove("active");
			currentActive.addEventListener(
				"animationend",
				() => {
					currentActive.classList.remove("slide-out-left", "slide-out-right");
					nextPanel.classList.remove("slide-in-left", "slide-in-right");
				},
				{ once: true }
			);
			lastEmptyPanel = nextPanel;
		}

		function clearSelectionAndDetail() {
			const rows = Array.from(
				document.querySelectorAll(".guarantees-table__row")
			);
			rows.forEach((r) => r.classList.remove("selected"));
			prevSelectedRow = null;
			prevIdx = null;
			history.replaceState(null, "", window.location.pathname);
			setEmptyDetailPanel("forward"); // Mantén la dirección como prefieras
		}

		function setResultMessage(msg = "") {
			resultMessage.innerHTML = msg;
			resultMessage.style.display = msg ? "block" : "none";
		}

                async function loadPage(page = 1, options = {}) {
                        if (isLoading || !hasMore) return;
                        isLoading = true;
                        spinner.style.display = "";
                        const search =
                                typeof options.search === "string" ? options.search : searchQuery;
                        const estado =
                                typeof options.estado === "string" ? options.estado : selectedEstado;
                        const plan =
                                typeof options.plan !== "undefined"
                                        ? options.plan
                                        : selectedPlan;
                        const canal =
                                typeof options.canal === "string" ? options.canal : selectedCanal;
                        const concesionario =
                                typeof options.concesionario !== "undefined"
                                        ? options.concesionario
                                        : selectedConcesionario;
                        try {
                                if (currentListAbort) currentListAbort.abort();
                                currentListAbort = new AbortController();
                                const params = new URLSearchParams({
                                        page,
                                        per_page: perPage,
                                });
                                if (search && search.length > 0)
                                        params.append("search", search);
                                if (estado) params.append("estado", estado);
                                if (plan) params.append("plan", plan);
                                if (canal) params.append("canal", canal);
                                if (concesionario)
                                        params.append("concesionario", concesionario);
                                let url = `${restRoot}go/v1/guarantees?${params.toString()}`;
                                const res = await fetch(url, {
                                        headers: { "X-WP-Nonce": restNonce },
                                        signal: currentListAbort.signal,
                                });
				if (!res.ok) throw `HTTP ${res.status}`;
				totalPosts = +res.headers.get("X-WP-Total") || 0;
				totalPages = +res.headers.get("X-WP-TotalPages") || 1;
				const { data } = await res.json();

				const esNuevaBusqueda = page === 1;
				if (esNuevaBusqueda) {
					setResultMessage("");
					tbody.innerHTML = "";
					clearSelectionAndDetail(); // Limpiar selección SIEMPRE que se cambia el listado (así evitas seleccionados fantasmas)
					if (data.length > 0) {
						listContainer.scrollTop = 0;
						// Solo guardamos resultados válidos si búsqueda >= 3 caracteres y pocos resultados
						if (search.length >= 3 && data.length > 0 && data.length <= 20) {
							lastValidQuery = search;
							lastValidResults = data.slice();
						}
					}
				}
				currentPage = page;
				hasMore = currentPage < totalPages;

                                if (data.length > 0) {
                                        for (const item of data) {
                                                if (item.detail) {
                                                        detailCache.set(String(item.id), normalizeDetailData(item.detail));
                                                }
                                                if (loadedIds.has(item.id)) continue;
                                                tbody.appendChild(renderRow(item));
                                                loadedIds.add(item.id);
                                        }
					setResultMessage("");
					// AUTODETAIL: Si hay **exactamente 1 resultado**, mostrar el panel sin click
					if (data.length === 1 && search && search.length > 0) {
						const row = tbody.querySelector(".guarantees-table__row");
						if (row && !row.classList.contains("selected")) {
							row.classList.add("selected");
							const id = row.dataset.id;
							const rowData = buildRowData(row);
							const currentActive = activePanel;
							const nextPanel = activePanel === panel1 ? panel2 : panel1;

                                                        if (detailCache.has(id)) {
                                                                const dataDetalle = detailCache.get(id);
                                                                nextPanel.innerHTML = renderFullDetail(
                                                                        dataDetalle,
                                                                        rowData,
                                                                        []
                                                                );
                                                        } else {
                                                                nextPanel.classList.add("loading");
                                                                nextPanel.innerHTML = '<div class="spinner" aria-hidden="true"></div>';
                                                                fetchDetail(id)
                                                                        .then((dataDetalle) => {
                                                                                if (nextPanel.dataset.loadedId === String(id)) {
                                                                                        nextPanel.innerHTML = renderFullDetail(
                                                                                                dataDetalle,
                                                                                                rowData,
                                                                                                []
                                                                                        );
                                                                                }
                                                                        })
                                                                        .catch(() => {})
                                                                        .finally(() => {
                                                                                nextPanel.classList.remove("loading");
                                                                        });
                                                        }
                                                        nextPanel.dataset.loadedId = id;
                                                        activePanel = nextPanel;
                                                        inactivePanel = currentActive;
                                                        currentActive.classList.remove("active");
                                                        nextPanel.classList.add("active");
						}
					}
				} else {
					// Solo mostramos resultados previos si búsqueda >= 3 caracteres y pocos resultados
					if (
						esNuevaBusqueda &&
						search.length >= 3 &&
						lastValidResults.length > 0 &&
						lastValidQuery.length >= 3 &&
						lastValidResults.length <= 20 &&
						search.length > 0 // <= SOLO si hay búsqueda, no si está vacío
						
					) {
                                                for (const item of lastValidResults) {
                                                        tbody.appendChild(renderRow(item));
                                                }
						setResultMessage(
							`No se han encontrado garantías para <strong>"${search}"</strong>. Mostrando resultados de <strong>"${lastValidQuery}"</strong>.`
						);
					} else if (search && page === 1) {
						setResultMessage(
							`No se han encontrado garantías para <strong>"${search}"</strong>.`
						);
					}
				}
			} catch (err) {
				console.error("❌ Error en loadPage:", err);
                        } finally {
                                isLoading = false;
                                spinner.style.display = hasMore ? "" : "none";
                        }
                }

                async function preloadByPlate(plate) {
                        try {
                                const params = new URLSearchParams({
                                        search: plate,
                                        per_page: 1,
                                });
                                const res = await fetch(
                                        `${restRoot}go/v1/guarantees?${params.toString()}`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
                                if (!res.ok) throw res.status;
                                const { data } = await res.json();
                                if (data.length === 0) return;
                                const item = data[0];
                                const id = item.id;
                                if (item.detail) {
                                        detailCache.set(String(id), normalizeDetailData(item.detail));
                                }
                                let row = tbody.querySelector(`.guarantees-table__row[data-id="${id}"]`);
                                if (!row) {
                                        row = renderRow(item);
                                        tbody.insertBefore(row, tbody.firstChild);
                                        loadedIds.add(id);
                                }
                                if (prevSelectedRow) prevSelectedRow.classList.remove("selected");
                                row.classList.add("selected");
                                prevSelectedRow = row;
                                const rowData = buildRowData(row);
                                const currentActive = activePanel;
                                const nextPanel = activePanel === panel1 ? panel2 : panel1;
                                nextPanel.classList.add("loading");
                                nextPanel.innerHTML = '<div class="spinner" aria-hidden="true"></div>';
                                fetchDetail(id)
                                        .then((detailData) => {
                                                if (nextPanel.dataset.loadedId === String(id)) {
                                                        nextPanel.innerHTML = renderFullDetail(detailData, rowData, []);
                                                }
                                        })
                                        .catch(() => {})
                                        .finally(() => {
                                                nextPanel.classList.remove("loading");
                                        });
                                nextPanel.dataset.loadedId = id;
                                activePanel = nextPanel;
                                inactivePanel = currentActive;
                                currentActive.classList.remove("active");
                                nextPanel.classList.add("active");
                        } catch (e) {
                                console.error("❌ Error preloadByPlate:", e);
                        }
                }


                function getDurationMeses(desde, hasta) {
			const d1 = new Date(desde);
			const d2 = new Date(hasta);
			if (isNaN(d1) || isNaN(d2)) return "-";
			let months =
				(d2.getFullYear() - d1.getFullYear()) * 12 +
				(d2.getMonth() - d1.getMonth());
			if (d2.getDate() >= d1.getDate()) months++;
			return months > 0 ? months : "-";
		}
		function getRestantesMeses(hasta) {
			const hoy = new Date();
			const fin = new Date(hasta);
			if (isNaN(fin)) return "-";
			let months =
				(fin.getFullYear() - hoy.getFullYear()) * 12 +
				(fin.getMonth() - hoy.getMonth());
			if (fin.getDate() >= hoy.getDate()) months++;
			return months > 0 ? months : "-";
		}

                function buildRowData(row) {
                        return {
                                marca_modelo: row.dataset.marca_modelo ?? "-",
                                matricula: row.dataset.matricula ?? "-",
                                plan: row.dataset.plan ?? "-",
                                desde: row.dataset.desde ?? "-",
                                desde_fmt:
                                        row.dataset.desdeFmt ??
                                        (row.dataset.desde
                                                ? formatDate(row.dataset.desde).display
                                                : "-"),
                                hasta: row.dataset.hasta ?? "-",
                                hasta_fmt:
                                        row.dataset.hastaFmt ??
                                        (row.dataset.hasta
                                                ? formatDate(row.dataset.hasta).display
                                                : "-"),
                                estado: row.dataset.estado ?? "Desconocido",
                                estadoclase: row.dataset.estadoclase ?? "pendiente-pago",
                                concesionario: row.dataset.vendedor_name ?? "-",
                                canal_venta: row.dataset.vendedor_type ?? "-",
                                precio: row.dataset.precio ?? "-",
                                metodo_pago: row.dataset.metodoPago ?? "",
                                cobro_realizado: row.dataset.cobroRealizado === "1",
                                iban_vendedor: row.dataset.ibanVendedor ?? "",
                                tipo: "-",
                                kilometros: "-",
                                primera_matriculacion: "-",
                                bastidor: "-",
                                precio_venta: "-",
				combustible: "-",
                                cambio: "-",
                                potencia: "-",
                                cilindrada: "-",
                                telefono_vendedor: "-",
                                email_vendedor: "-",
                                avatar_vendedor: "",
                                vendedor_url: "#",
                                contrato_url: "#",
                                condicionado_url: "#",
                                cobertura_url: "#",
                                factura_url: "#",
                                nombre_comprador: "-",
                                dni_comprador: "-",
                                telefono_comprador: "-",
                                email_comprador: "-",
                                direccion_comprador: "-",
                                localidad_comprador: "-",
                                provincia_comprador: "-",
                                codigo_postal_comprador: "-",
                        };
                }

                function renderFastActions(
                        telefono,
                        email,
                        skeletons = [],
                        telField = "telefono_vendedor",
                        emailField = "email_vendedor"
                ) {
                        const tel = skeletons.includes(telField) ? "" : telefono ?? "";
                        const mail = skeletons.includes(emailField) ? "" : email ?? "";
                        const telHtml = tel
                                ? `<li class="fast-actions__item"><a href="tel:${tel}" class="fast-actions__link"><span class="fast-actions__icon">${phoneIcon}</span><span class="fast-actions__label">${tel}</span></a></li>`
                                : "";
                        const mailHtml = mail
                                ? `<li class="fast-actions__item"><a href="mailto:${mail}" class="fast-actions__link"><span class="fast-actions__icon">${emailIcon}</span><span class="fast-actions__label">${mail}</span></a></li>`
                                : "";
                        const content = `${telHtml}${mailHtml}`;
                        return content ? `<ul class="fast-actions">${content}</ul>` : "";
                }

                function renderFullDetail(data, rowData, skeletons = []) {
    const getFieldText = (val) =>
        val && typeof val === "object" && "label" in val
            ? val.label
            : val;
    const skeleton = (field, fallback = "-") =>
        skeletons.includes(field)
            ? `<span class="skeleton skeleton--${field}"></span>`
            : getFieldText(data[field]) ?? getFieldText(rowData[field]) ?? fallback;

    const mesesTotales = getDurationMeses(data.desde, data.hasta);
    const mesesRestantes = getRestantesMeses(data.hasta);

    const estadoValue =
        (data.estado && data.estado.value) ||
        rowData.estadoclase ||
        "pendiente-pago";
    const estadoClase = normalizeEstadoClase(estadoValue);
    const isSinFinalizar = estadoClase === "sin-finalizar";
    const isPendientePago = estadoClase === "pendiente-pago";
    const badgeClase = `guarantee-detail__badge guarantee-detail__badge--${estadoClase}`;
    const metodoPago = (
        data.metodo_pago ?? rowData.metodo_pago ?? ""
    )
        .toString()
        .trim();
    const cobroRealizado = [
        data.cobro_realizado,
        rowData.cobro_realizado,
    ].some((v) => v === true || v === 1 || v === "1");

    const planTitle = `${data.plan ?? "-"}${
        mesesTotales !== "-" ? " " + mesesTotales + " meses" : ""
    }`;
    const fuelRaw = (
        data.combustible ?? rowData.combustible ?? ""
    )
        .toString()
        .toLowerCase();
    const isElectric = fuelRaw
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "") === "electrico";
    const potenciaUnidad = isElectric ? "kW" : "CV";

    const isFilled = (val) => {
        if (val === undefined || val === null) return false;
        const str = String(val).trim();
        return str !== "" && str !== "-" && str !== "#";
    };
    const docFields = [
        "contrato_url",
        "condicionado_url",
        "cobertura_url",
        "factura_url",
    ];
    const buyerFields = [
        "nombre_comprador",
        "dni_comprador",
        "telefono_comprador",
        "email_comprador",
        "direccion_comprador",
        "localidad_comprador",
        "provincia_comprador",
        "codigo_postal_comprador",
    ];
    const hasGuaranteeInfo =
        isFilled(data.plan) && isFilled(data.desde_fmt) && isFilled(data.hasta_fmt);
    const hasDocs = docFields.every((field) => isFilled(data[field]));
    const hasBuyerInfo = buyerFields.every((field) => isFilled(data[field]));
    const showChannelSection = isAdmin;
    const showActions = isAdmin;

    if (isSinFinalizar) {
        return `
        <div class="guarantee-detail__inner">
                <div class="guarantee-detail__header">
                        <h2>Garantía ${skeleton("matricula")}</h2>
                        ${hasGuaranteeInfo
                            ? `<h3 class="guarantee-detail__plan-title">${planTitle}</h3>
                        <div><p>${skeleton("desde_fmt")} — ${skeleton("hasta_fmt")}
                                <span class="guarantee-detail__plan-duration">(${mesesRestantes !== "-" ? mesesRestantes + " meses restantes" : "-"})</span></p></div>`
                            : ""}
                        <div><p class="detail__alert-section">Completa los datos pendientes para tramitar la garantía</p></div>
                        <div class="${badgeClase}">${skeleton("estado", "Desconocido")}</div>
                </div>
                ${(showActions || isProfesional) ? `<div class="guarantee-detail__btn-container">
                        <button type="button" aria-label="Continuar con la garantía" class="guarantee-detail__btn guarantee-detail__btn--continue">
                                <span class="guarantee-detail__btn-icon">${continueIcon}</span>
                                <span class="guarantee-detail__btn-text">Continuar con la garantía</span>
                        </button>
                        ${showActions ? `<button type="button" class="guarantee-detail__btn guarantee-detail__btn--fav" aria-label="Guardar en favoritos"><span class="guarantee-detail__btn-icon">${heartIcon}</span></button>
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--share" aria-label="Compartir"><span class="guarantee-detail__btn-icon">${shareIcon}</span></button>` : ``}
                </div>` : ``}
                ${showChannelSection
                        ? `<section class="detail__section detail__section--channel">
                                <h3 class="detail__section-title">Canal de venta</h3>
                                <div class="vendor-card">
                                        ${data.avatar_vendedor ?? rowData.avatar_vendedor
                                            ? `<img src="${data.avatar_vendedor ?? rowData.avatar_vendedor}" alt="" class="vendor-card__avatar">`
                                            : `<span class="vendor-card__avatar vendor-card__avatar--icon">${userIcon}</span>`}
                                        <div class="vendor-card__info">
                                                <p class="vendor-card__name">${skeleton("concesionario", "-")}</p>
                                                <p class="vendor-card__role">${skeleton("canal_venta", "-")}</p>
                                        </div>
                                </div>
                                ${renderFastActions(
                                        data.telefono_vendedor ?? rowData.telefono_vendedor,
                                        data.email_vendedor ?? rowData.email_vendedor,
                                        skeletons
                                )}
                                <a href="${data.vendedor_url ?? rowData.vendedor_url ?? '#'}" class="vendor-card__details-link">Ver detalles del cliente</a>
                        </section>`
                        : ""}
                <section class="detail__section">
                        <h3>Datos del vehículo</h3>
                        <ul>
                                <li><strong>Marca/Modelo:</strong> ${skeleton("marca_modelo")}</li>
                                <li><strong>Tipo:</strong> ${skeleton("tipo", "-")}</li>
                                <li><strong>Kilómetros:</strong> ${skeleton("kilometros", "-")} km</li>
                                <li><strong>1ª Matriculación:</strong> ${skeleton("primera_matriculacion", "-")}</li>
                                <li><strong>Matrícula:</strong> ${skeleton("matricula")}</li>
                                <li><strong>Nº Bastidor:</strong> ${skeleton("bastidor", "-")}</li>
                                <li><strong>Precio venta:</strong> ${skeleton("precio_venta", "-")} €</li>
                        </ul>
                </section>
                <section class="detail__section">
                        <h3>Detalles técnicos</h3>
                        <ul>
                                <li><strong>Combustible:</strong> ${skeleton("combustible", "-")}</li>
                                <li><strong>Cambio:</strong> ${skeleton("cambio", "-")}</li>
                                <li><strong>Potencia:</strong> ${skeleton("potencia", "-")} ${potenciaUnidad}</li>
                                <li><strong>Cilindrada:</strong> ${skeleton("cilindrada", "-")} CC</li>
                        </ul>
                </section>
                <section class="detail__section detail__section--docs">
                        <h3 class="detail__section-title">Documentación</h3>
                        ${hasDocs
                            ? `<ul class="detail__docs-list">
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("contrato_url", "#")}" aria-label="Ver documento Contrato">
                                                <span class="detail__docs-label">Contrato</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("condicionado_url", "#")}" aria-label="Ver documento Condicionado">
                                                <span class="detail__docs-label">Condicionado</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("cobertura_url", "#")}" aria-label="Ver documento Cobertura">
                                                <span class="detail__docs-label">Cobertura</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("factura_url", "#")}" aria-label="Ver documento Factura">
                                                <span class="detail__docs-label">Factura</span>
                                        </button>
                                </li>
                        </ul>`
                            : `<p class="detail__alert-section">Documentación no disponible</p>`}
                </section>
                <section class="detail__section">
                        <h3>Datos del cliente</h3>
                        ${hasBuyerInfo
                            ? `<ul>
                                <li><strong>Nombre:</strong> ${skeleton("nombre_comprador", "-")}</li>
                                <li><strong>DNI/NIE:</strong> ${skeleton("dni_comprador", "-")}</li>
                                <li><strong>Teléfono:</strong> ${skeleton("telefono_comprador", "-")}</li>
                                <li><strong>Email:</strong> ${skeleton("email_comprador", "-")}</li>
                                <li><strong>Dirección:</strong> ${skeleton("direccion_comprador", "-")}</li>
                                <li><strong>Localidad:</strong> ${skeleton("localidad_comprador", "-")}</li>
                                <li><strong>Provincia:</strong> ${skeleton("provincia_comprador", "-")}</li>
                        
                                <li><strong>Código Postal:</strong> ${skeleton("codigo_postal_comprador", "-")}</li>
                        </ul>
                        ${renderFastActions(
                                data.telefono_comprador ?? rowData.telefono_comprador,
                                data.email_comprador ?? rowData.email_comprador,
                                skeletons,
                                "telefono_comprador",
                                "email_comprador"
                        )}`
                            : `<p class="detail__alert-section">Faltan datos del cliente</p>`}
                </section>
        </div>`;
    }

    const showConfirmBtn =
        showActions &&
        isPendientePago &&
        !(isAdmin && metodoPago === "domiciliacion_bancaria" && !cobroRealizado);
    const actionsHtml = showActions
        ? showConfirmBtn
            ? `<div class="guarantee-detail__btn-container">
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--confirm" aria-label="Confirmar pago">
                                <span class="guarantee-detail__btn-icon">${paymentIcon}</span>
                                <span class="guarantee-detail__btn-text">${metodoPago === "domiciliacion_bancaria" ? "Marcar garantía como pagada" : "Confirmar pago"}</span>
                        </button>
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--fav" aria-label="Guardar en favoritos"><span class="guarantee-detail__btn-icon">${heartIcon}</span></button>
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--share" aria-label="Compartir"><span class="guarantee-detail__btn-icon">${shareIcon}</span></button>
                </div>`
            : `<div class="guarantee-detail__btn-container">
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--report" aria-label="Abrir expediente para esta garantía">
                                <span class="guarantee-detail__btn-icon">${warningIcon}</span>
                                <span class="guarantee-detail__btn-text">Abrir expediente</span>
                        </button>
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--fav" aria-label="Guardar en favoritos"><span class="guarantee-detail__btn-icon">${heartIcon}</span></button>
                        <button type="button" class="guarantee-detail__btn guarantee-detail__btn--share" aria-label="Compartir"><span class="guarantee-detail__btn-icon">${shareIcon}</span></button>
                </div>`
        : ``;

    const paymentHtml = (() => {
        if (isAdmin && metodoPago === "domiciliacion_bancaria" && !cobroRealizado) {
            const concepto = `Garantía ${skeleton("matricula")}`;
            const cantidad = `${skeleton("precio", "0")} €`;
            const iban =
                data.iban_vendedor ||
                rowData.iban_vendedor ||
                "ES00 0000 0000 0000 0000 0000";
            return `<section class="detail__section detail__section--payment">
                                <p class="detail__payment-note detail__payment-note--domiciliacion">Cobro pendiente por domiciliación bancaria.</p>
                                <button type="button" class="guarantee-detail__btn guarantee-detail__btn--confirm">
                                        <span class="guarantee-detail__btn-icon">${paymentIcon}</span>
                                        <span class="guarantee-detail__btn-text">Marcar garantía como pagada</span>
                                </button>
                                <table class="detail__transfer-table">
                                        <tbody>
                                                <tr><th>Concepto</th><td><span data-concepto>${concepto}</span><button type="button" class="detail__copy-btn" data-copy="[data-concepto]" data-label="Copiar concepto" data-done="Concepto copiado" data-toast="Concepto copiado al portapapeles." aria-label="Copiar concepto">${copyIcon}</button></td></tr>
                                                <tr><th>Cantidad</th><td><span data-amount>${cantidad}</span><button type="button" class="detail__copy-btn" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad">${copyIcon}</button></td></tr>
                                                <tr><th>IBAN</th><td><span data-iban>${iban}</span><button type="button" class="detail__copy-btn" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN">${copyIcon}</button></td></tr>
                                        </tbody>
                                </table>
                                <div class="detail__copy-toast" aria-hidden="true"></div>
                        </section>`;
        }
        if (!isAdmin && isPendientePago) {
            if (metodoPago === "transferencia") {
                const concepto = `Garantía ${skeleton("matricula")}`;
                const cantidad = `${skeleton("precio", "0")} €`;
                const iban = "ES00 0000 0000 0000 0000 0000";
                return `<section class="detail__section detail__section--payment">
                                <p class="detail__payment-note">Recuerda realizar la transferencia para activar tu garantía.</p>
                                <table class="detail__transfer-table">
                                        <tbody>
                                                <tr><th>Concepto</th><td><span data-concepto>${concepto}</span><button type="button" class="detail__copy-btn" data-copy="[data-concepto]" data-label="Copiar concepto" data-done="Concepto copiado" data-toast="Concepto copiado al portapapeles." aria-label="Copiar concepto">${copyIcon}</button></td></tr>
                                                <tr><th>Cantidad</th><td><span data-amount>${cantidad}</span><button type="button" class="detail__copy-btn" data-copy="[data-amount]" data-label="Copiar cantidad" data-done="Cantidad copiada" data-toast="Cantidad copiada al portapapeles." aria-label="Copiar cantidad">${copyIcon}</button></td></tr>
                                                <tr><th>IBAN</th><td><span data-iban>${iban}</span><button type="button" class="detail__copy-btn" data-copy="[data-iban]" data-label="Copiar IBAN" data-done="IBAN copiado" data-toast="IBAN copiado al portapapeles." aria-label="Copiar IBAN">${copyIcon}</button></td></tr>
                                        </tbody>
                                </table>
                                <div class="detail__copy-toast" aria-hidden="true"></div>
                        </section>`;
            }
            return `<section class="detail__section detail__section--payment">
                                <p class="detail__payment-note">El pago se procesará mediante domiciliación bancaria.</p>
                        </section>`;
        }
        return "";
    })();
    return `
        <div class="guarantee-detail__inner">
                <div class="guarantee-detail__header">
                        <h2>Garantía ${skeleton("matricula")}</h2>
                        <h3 class="guarantee-detail__plan-title">${planTitle}</h3>
                        <div>
                                <p>${skeleton("desde_fmt")} — ${skeleton("hasta_fmt")}
                                <span class="guarantee-detail__plan-duration">(${mesesRestantes !== "-" ? mesesRestantes + " meses restantes" : "-"})</span></p>
                        </div>
                        <div class="${badgeClase}">${skeleton("estado", "Desconocido")}</div>
                </div>
                ${paymentHtml}
                ${actionsHtml}
                ${showChannelSection
                        ? `<section class="detail__section detail__section--channel">
                                <h3 class="detail__section-title">Canal de venta</h3>
                                <div class="vendor-card">
                                        ${data.avatar_vendedor ?? rowData.avatar_vendedor
                                            ? `<img src="${data.avatar_vendedor ?? rowData.avatar_vendedor}" alt="" class="vendor-card__avatar">`
                                            : `<span class="vendor-card__avatar vendor-card__avatar--icon">${userIcon}</span>`}
                                        <div class="vendor-card__info">
                                                <p class="vendor-card__name">${skeleton("concesionario", "-")}</p>
                                                <p class="vendor-card__role">${skeleton("canal_venta", "-")}</p>
                                        </div>
                                </div>
                                ${renderFastActions(
                                        data.telefono_vendedor ?? rowData.telefono_vendedor,
                                        data.email_vendedor ?? rowData.email_vendedor,
                                        skeletons
                                )}
                                <a href="${data.vendedor_url ?? rowData.vendedor_url ?? '#'}" class="vendor-card__details-link">Ver detalles del cliente</a>
                        </section>`
                        : ""}
                <section class="detail__section">
                        <h3>Datos del vehículo</h3>
                        <ul>
                                <li><strong>Marca/Modelo:</strong> ${skeleton("marca_modelo")}</li>
                                <li><strong>Tipo:</strong> ${skeleton("tipo", "-")}</li>
                                <li><strong>Kilómetros:</strong> ${skeleton("kilometros", "-")} km</li>
                                <li><strong>1ª Matriculación:</strong> ${skeleton("primera_matriculacion", "-")}</li>
                                <li><strong>Matrícula:</strong> ${skeleton("matricula")}</li>
                                <li><strong>Nº Bastidor:</strong> ${skeleton("bastidor", "-")}</li>
                                <li><strong>Precio venta:</strong> ${skeleton("precio_venta", "-")} €</li>
                        </ul>
                </section>
                <section class="detail__section">
                        <h3>Detalles técnicos</h3>
                        <ul>
                                <li><strong>Combustible:</strong> ${skeleton("combustible", "-")}</li>
                                <li><strong>Cambio:</strong> ${skeleton("cambio", "-")}</li>
                                <li><strong>Potencia:</strong> ${skeleton("potencia", "-")} ${potenciaUnidad}</li>
                                <li><strong>Cilindrada:</strong> ${skeleton("cilindrada", "-")} CC</li>
                        </ul>
                </section>
                <section class="detail__section detail__section--docs">
                        <h3 class="detail__section-title">Documentación</h3>
                        <ul class="detail__docs-list">
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("contrato_url", "#")}" aria-label="Ver documento Contrato">
                                                <span class="detail__docs-label">Contrato</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("condicionado_url", "#")}" aria-label="Ver documento Condicionado">
                                                <span class="detail__docs-label">Condicionado</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("cobertura_url", "#")}" aria-label="Ver documento Cobertura">
                                                <span class="detail__docs-label">Cobertura</span>
                                        </button>
                                </li>
                                <li class="detail__docs-item">
                                        <button type="button" class="detail__docs-btn" data-doc-url="${skeleton("factura_url", "#")}" aria-label="Ver documento Factura">
                                                <span class="detail__docs-label">Factura</span>
                                        </button>
                                </li>
                        </ul>
                </section>
                <section class="detail__section">
                        <h3>Datos del cliente</h3>
                        <ul>
                                <li><strong>Nombre:</strong> ${skeleton("nombre_comprador", "-")}</li>
                                <li><strong>DNI/NIE:</strong> ${skeleton("dni_comprador", "-")}</li>
                                <li><strong>Teléfono:</strong> ${skeleton("telefono_comprador", "-")}</li>
                                <li><strong>Email:</strong> ${skeleton("email_comprador", "-")}</li>
                                <li><strong>Dirección:</strong> ${skeleton("direccion_comprador", "-")}</li>
                                <li><strong>Localidad:</strong> ${skeleton("localidad_comprador", "-")}</li>
                                <li><strong>Provincia:</strong> ${skeleton("provincia_comprador", "-")}</li>
                                <li><strong>Código Postal:</strong> ${skeleton("codigo_postal_comprador", "-")}</li>
                        </ul>
                        ${renderFastActions(
                                data.telefono_comprador ?? rowData.telefono_comprador,
                                data.email_comprador ?? rowData.email_comprador,
                                skeletons,
                                "telefono_comprador",
                                "email_comprador"
                        )}
                </section>
        </div>
    `;
}

function initRowSelection() {
			tbody.addEventListener("click", async function (e) {
				const row = e.target.closest(".guarantees-table__row");
				if (!row) return;
				const rows = Array.from(
					document.querySelectorAll(".guarantees-table__row")
				);
				const idx = rows.indexOf(row);

				// Deselección
				if (row.classList.contains("selected")) {
					rows.forEach((r) => r.classList.remove("selected"));
					prevSelectedRow = null;
					prevIdx = null;
					history.replaceState(null, "", window.location.pathname);
					setEmptyDetailPanel(idx > prevIdx ? "forward" : "back");
					return;
				}

				rows.forEach((r) => r.classList.remove("selected"));
				row.classList.add("selected");
				const id = row.dataset.id;
				history.replaceState(
					null,
					"",
					`?matricula=${encodeURIComponent(row.dataset.matricula)}`
				);
				const forward = prevIdx === null || idx > prevIdx;
				prevIdx = idx;
				const currentActive = activePanel;
				const nextPanel = activePanel === panel1 ? panel2 : panel1;

				const rowData = buildRowData(row);

                                if (detailCache.has(id)) {
                                        const data = detailCache.get(id);
                                        nextPanel.innerHTML = renderFullDetail(data, rowData, []);
                                } else {
                                        nextPanel.classList.add("loading");
                                        nextPanel.innerHTML = '<div class="spinner" aria-hidden="true"></div>';
                                }
                                nextPanel.dataset.loadedId = id;

                                activePanel = nextPanel;
                                inactivePanel = currentActive;
                                currentActive.classList.add(
                                        forward ? "slide-out-left" : "slide-out-right"
                                );
                                nextPanel.classList.add(forward ? "slide-in-right" : "slide-in-left");
                                nextPanel.classList.add("active");
                                currentActive.classList.remove("active");
                                currentActive.addEventListener(
                                        "animationend",
                                        () => {
                                                currentActive.classList.remove("slide-out-left", "slide-out-right");
                                                nextPanel.classList.remove("slide-in-left", "slide-in-right");
                                        },
                                        { once: true }
                                );

                                prevSelectedRow = row;

                                if (!detailCache.has(id)) {
                                        try {
                                                const data = await fetchDetail(id);
                                                if (nextPanel.dataset.loadedId === String(id)) {
                                                        nextPanel.innerHTML = renderFullDetail(data, rowData, []);
                                                }
                                        } catch (e) {
                                                console.error("❌ Error fetch detalle:", e);
                                        } finally {
                                                nextPanel.classList.remove("loading");
                                        }
                                }
                        });

                        tbody.addEventListener("keydown", function (e) {
                                if (e.key !== "Enter") return;
                                const row = e.target.closest(".guarantees-table__row");
                                if (row) row.click();
                        });
                }
		initRowSelection();

		(() => {
			const modal = document.querySelector(".pdf-modal");
			if (!modal) return;
			const iframe = modal.querySelector(".pdf-modal__iframe");
			const dl = modal.querySelector(".pdf-modal__download");

			document.addEventListener("click", (e) => {
				if (e.target.closest(".detail__docs-btn")) {
					const btn = e.target.closest(".detail__docs-btn");
					iframe.src = btn.dataset.docUrl;
					dl.href = btn.dataset.docUrl;
					modal.classList.add("visible");
				}
			});

			modal.querySelector(".pdf-modal__close").addEventListener("click", () => {
				modal.classList.remove("visible");
				iframe.src = "";
			});
			modal.addEventListener(
				"click",
				(e) => e.target === modal && modal.classList.remove("visible")
			);
			document.addEventListener(
				"keydown",
				(e) =>
					e.key === "Escape" &&
					modal.classList.contains("visible") &&
					modal.classList.remove("visible")
			);
		})();

                (() => {
                        const filters = document.querySelector(".guarantees-list__filters"),
                                header = document.querySelector(".top-bar");
			if (filters && header) {
				new IntersectionObserver(
					([e]) => {
						const a = !e.isIntersecting;
						filters.classList.toggle("sticky-active", a);
						listContainer.classList.toggle("sticky-active", a);
						detail.classList.toggle("sticky-active", a);
					},
					{ root: null, threshold: 0, rootMargin: "-50px" }
				).observe(header);
			}
                        const onScroll = () => {
                                const activePanel = detail.querySelector(".guarantee-detail__panel.active");
                                const detailScrolled = activePanel ? activePanel.scrollTop > 10 : false;
                                document.body.classList.toggle(
                                        "scrolled",
                                        listContainer.scrollTop > 10 || detailScrolled
                                );
                        };
                        listContainer.addEventListener("scroll", onScroll);
                        detail.querySelectorAll(".guarantee-detail__panel").forEach((p) =>
                                p.addEventListener("scroll", onScroll)
                        );
                })();

                async function fetchFilters() {
                        try {
                                const res = await fetch(
                                        `${restRoot}go/v1/guarantees/filters`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
                                if (!res.ok) throw res.status;
                                const {
                                        estados = [],
                                        planes = [],
                                        concesionarios = [],
                                } = await res.json();
                                if (estadoSelect) {
                                        estadoSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        estados.forEach((est) => {
                                                const opt = document.createElement("option");
                                                const val = typeof est === "object" ? est.value : est;
                                                const lbl = typeof est === "object" ? est.label : est;
                                                opt.value = val;
                                                opt.textContent = lbl;
                                                estadoSelect.appendChild(opt);
                                        });
                                }
                                if (planSelect) {
                                        planSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        planes.forEach((pl) => {
                                                const opt = document.createElement("option");
                                                opt.value = pl.id;
                                                opt.textContent = pl.title;
                                                planSelect.appendChild(opt);
                                        });
                                }
                                if (concesionarioSelect) {
                                        concesionarioSelect
                                                .querySelectorAll("option:not(:first-child)")
                                                .forEach((o) => o.remove());
                                        concesionarios.forEach((c) => {
                                                const opt = document.createElement("option");
                                                opt.value = c.id;
                                                opt.textContent = c.name;
                                                concesionarioSelect.appendChild(opt);
                                        });
                                }
                        } catch (e) {
                                console.error("❌ Error fetching filters:", e);
                        }
                }

                function applyFilters() {
                        currentPage = 1;
                        hasMore = true;
                        lastValidQuery = "";
                        lastValidResults = [];
                        loadPage(1);
                }

                if (estadoSelect)
                        estadoSelect.addEventListener("change", () => {
                                selectedEstado = estadoSelect.value;
                                applyFilters();
                        });
                if (planSelect)
                        planSelect.addEventListener("change", () => {
                                selectedPlan = planSelect.value;
                                applyFilters();
                        });
                if (canalSelect)
                        canalSelect.addEventListener("change", () => {
                                selectedCanal = canalSelect.value;
                                applyFilters();
                        });
                if (concesionarioSelect)
                        concesionarioSelect.addEventListener("change", () => {
                                selectedConcesionario = concesionarioSelect.value;
                                applyFilters();
                        });

                fetchFilters();

                const input = document.getElementById("buscador_mis_garantias");
                const closeIcon = document.querySelector(".guarantees-list__close-icon");
		let debounceTimer = null;
		const DEBOUNCE_MS = 300;

		function doSearch(query) {
			searchQuery = query;
			currentPage = 1;
			hasMore = true;
			loadPage(1, { search: searchQuery });
		}

		input.addEventListener("input", () => {
			const value = input.value.trim();
			if (value.length > 0) {
				closeIcon.classList.add("visible");
			} else {
				closeIcon.classList.remove("visible");
				// SI EL INPUT QUEDA VACÍO, LIMPIA VARIABLES
				lastValidQuery = "";
				lastValidResults = [];
			}
			if (debounceTimer) clearTimeout(debounceTimer);
			debounceTimer = setTimeout(() => {
				doSearch(value);
			}, DEBOUNCE_MS);
		});

		closeIcon.addEventListener("click", () => {
			input.value = "";
			closeIcon.classList.remove("visible");
			input.focus();
			input.select();
			if (debounceTimer) clearTimeout(debounceTimer);
			searchQuery = "";
			currentPage = 1;
			hasMore = true;
			lastValidQuery = "";
			lastValidResults = [];
			loadPage(1);
		});

                new IntersectionObserver(
                        (entries) => {
                                if (entries[0].isIntersecting && hasMore && !isLoading) {
                                        loadPage(currentPage + 1);
                                }
                        },
                        { root: listContainer, threshold: 0.1, rootMargin: "200px 0px" }
                ).observe(scrollEnd);

                loadPage(1);
                if (urlMat) {
                        preloadByPlate(urlMat);
                }
        });
})();

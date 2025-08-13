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
			detail.innerHTML = `
				<div class="guarantee-detail__panel active" id="detail-panel-1">
					${detail.innerHTML}
				</div>
				<div class="guarantee-detail__panel" id="detail-panel-2"></div>
			`;
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

		function normalizeEstadoClase(estado) {
			if (!estado) return "pendiente";
			let val = estado
				.toLowerCase()
				.normalize("NFD")
				.replace(/[\u0300-\u036f]/g, "")
				.replace(/[^a-z0-9]/g, "");
			if (val.startsWith("expir")) return "expirada";
			if (val.startsWith("pendi")) return "pendiente";
			if (val.startsWith("activa")) return "activada";
			return val || "pendiente";
		}

		function renderRow(item) {
			const estado =
				typeof item.estado === "string" && item.estado
					? item.estado
					: typeof item.estado === "number"
					? String(item.estado)
					: "Desconocido";
			const estadoClase = normalizeEstadoClase(estado);
			const marca_modelo = item.marca ?? "-";
			const mat = item.mat ?? item.matricula ?? "-";
			const desde = item.desde ?? "-";
			const hasta = item.hasta ?? "-";
			const vendedor_name = item.vendedor ?? "-";
			const plan = item.plan ?? "-";
			const precio = item.precio ?? "-";
			const canal_venta =
				item.canal_venta && item.canal_venta.label
					? item.canal_venta.label
					: "-";
			const vendedor_type = canal_venta;

			const tr = document.createElement("tr");
			tr.className = "guarantees-table__row";
			tr.tabIndex = 0;
			tr.dataset.id = item.id;
			tr.dataset.matricula = mat;
			tr.dataset.marca_modelo = marca_modelo;
			tr.dataset.plan = plan;
			tr.dataset.desde = desde;
			tr.dataset.hasta = hasta;
			tr.dataset.estado = estado;
			tr.dataset.estadoclase = estadoClase;
			tr.dataset.vendedor_name = vendedor_name;
			tr.dataset.vendedor_type = vendedor_type;
			tr.dataset.precio = precio;
			tr.dataset.canalVenta = canal_venta;

			tr.innerHTML = `
				<td data-label="Vehículo">
					<div class="guarantees-table__vehiculo">
						<strong>${marca_modelo}</strong>
						<div class="vehiculo__mat">${mat}</div>
					</div>
				</td>
				<td data-label="Validez">
					<div class="guarantees-table__period">
						<div><strong>Desde:</strong> <time>${desde}</time></div>
						<div><strong>Hasta:</strong> <time>${hasta}</time></div>
					</div>
				</td>
				<td data-label="Vendedor">
					<div class="guarantees-table__vendedor">
						<div class="vendedor__name">${vendedor_name}</div>
						<div class="vendedor__type">${vendedor_type}</div>
					</div>
				</td>
				<td data-label="Garantía">
					<div class="guarantees-table__plan">
						<span class="plan__name">${plan}</span>
						<span class="plan__price">${precio}€</span>
					</div>
					<span class="guarantees-list__badge guarantees-list__badge--${estadoClase}">
						${estado}
					</span>
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
						tbody.appendChild(renderRow(item));
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
								nextPanel.innerHTML = renderFullDetail(
									rowData,
									rowData,
									skeletonFields
								);
								(async () => {
									try {
										const res = await fetch(
                                                                                `${restRoot}go/v1/guarantees/${id}`,
                                                                                        {
                                                                                                headers: { "X-WP-Nonce": restNonce },
                                                                                        }
                                                                                );
										if (!res.ok) throw res.status;
										const dataDetalle = await res.json();
										detailCache.set(id, dataDetalle);
										if (nextPanel.dataset.loadedId === String(id)) {
											nextPanel.innerHTML = renderFullDetail(
												dataDetalle,
												rowData,
												[]
											);
										}
									} catch (e) {}
								})();
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

		async function loadAllAndSelect() {
			isLoading = true;
			spinner.style.display = "";
			try {
                                let res = await fetch(
                                        `${restRoot}go/v1/guarantees?page=1&per_page=1`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
				if (!res.ok) throw res.status;
				totalPosts = +res.headers.get("X-WP-Total") || 0;
                                res = await fetch(
                                        `${restRoot}go/v1/guarantees?page=1&per_page=${totalPosts}`,
                                        { headers: { "X-WP-Nonce": restNonce } }
                                );
				if (!res.ok) throw res.status;
				const { data } = await res.json();
				tbody.innerHTML = "";
				setResultMessage("");
				for (const item of data) {
					tbody.appendChild(renderRow(item));
				}
				const allRows = Array.from(
					document.querySelectorAll(".guarantees-table__row")
				);
				const idx = allRows.findIndex((r) => r.dataset.matricula === urlMat);
				if (idx >= 0) {
					allRows[idx].scrollIntoView({ block: "center" });
					setTimeout(() => allRows[idx].click(), 100);
				}
			} catch (err) {
				console.error("❌ Error en loadAllAndSelect:", err);
			} finally {
				isLoading = false;
				spinner.style.display = "none";
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
				hasta: row.dataset.hasta ?? "-",
				estado: row.dataset.estado ?? "Desconocido",
				estadoclase: row.dataset.estadoclase ?? "pendiente",
				concesionario: row.dataset.vendedor_name ?? "-",
				canal_venta: row.dataset.vendedor_type ?? "-",
				precio: row.dataset.precio ?? "-",
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
				contrato_url: "#",
				condicionado_url: "#",
				cobertura_url: "#",
				factura_url: "#",
				nombre_comprador: "-",
				dni_comprador: "-",
				telefono_comprador: "-",
				email_comprador: "-",
				direccion_comprador: "-",
			};
		}

		const skeletonFields = [
			"tipo",
			"kilometros",
			"primera_matriculacion",
			"bastidor",
			"precio_venta",
			"combustible",
			"cambio",
			"potencia",
			"cilindrada",
			"telefono_vendedor",
			"email_vendedor",
			"nombre_comprador",
			"dni_comprador",
			"telefono_comprador",
			"email_comprador",
			"direccion_comprador",
		];

		function renderFastActions(vendedor, telefono, email, skeletons = []) {
			const label = skeletons.includes("concesionario")
				? `<span class="skeleton skeleton--nombre"></span>`
				: vendedor ?? "-";
			const tel = skeletons.includes("telefono_vendedor")
				? ""
				: telefono ?? "-";
			const mail = skeletons.includes("email_vendedor") ? "" : email ?? "-";
			return `
				<ul class="fast-actions">
					<li class="fast-actions__item">
						<a href="tel:${tel}" class="fast-actions__link">
							<span class="fast-actions__label">${label}</span>
						</a>
					</li>
					<li class="fast-actions__item">
						<a href="mailto:${mail}" class="fast-actions__link">
							<span class="fast-actions__label">${label}</span>
						</a>
					</li>
				</ul>
			`;
		}

		function renderFullDetail(data, rowData, skeletons = []) {
			const skeleton = (field, fallback = "-") =>
				skeletons.includes(field)
					? `<span class="skeleton skeleton--${field}"></span>`
					: data[field] ?? rowData[field] ?? fallback;

			const mesesTotales = getDurationMeses(data.desde, data.hasta);
			const mesesRestantes = getRestantesMeses(data.hasta);

			const estadoActual = data.estadoclase || data.estado || "pendiente";
			const badgeClase = `guarantee-detail__badge guarantee-detail__badge--${normalizeEstadoClase(
				estadoActual
			)}`;

			const planTitle = `${data.plan ?? "-"}${
				mesesTotales !== "-" ? " " + mesesTotales + " meses" : ""
			}`;

			return `
				<div class="guarantee-detail__inner">
					<div class="guarantee-detail__header">
						<h2>Garantía ${skeleton("matricula")}</h2>
						<h3 class="guarantee-detail__plan-title">${planTitle}</h3>
						<div>
							<p>${skeleton("desde")} — ${skeleton("hasta")}
							<span class="guarantee-detail__plan-duration">(${
								mesesRestantes !== "-"
									? mesesRestantes + " meses restantes"
									: "-"
							})</span></p>
						</div>
						<div class="${badgeClase}">${skeleton("estado", "Desconocido")}</div>
					</div>
					<div class="guarantee-detail__btn-container">
						<button type="button" class="guarantee-detail__btn guarantee-detail__btn--report" aria-label="Abrir expediente para esta garantía">

							<span class="guarantee-detail__btn-text">Abrir expediente</span>
						</button>
						<button type="button" class="guarantee-detail__btn guarantee-detail__btn--fav" aria-label="Guardar en favoritos"></button>
						<button type="button" class="guarantee-detail__btn guarantee-detail__btn--share" aria-label="Compartir"></button>
					</div>
					<section class="detail__section detail__section--fast-actions">
						<h3 class="detail__section-title">Canal de venta</h3>
							<p>${skeleton("canal_venta")}, ${skeleton("concesionario")}</p>
							${renderFastActions(
								data.concesionario ?? rowData.concesionario,
								data.telefono_vendedor ?? rowData.telefono_vendedor,
								data.email_vendedor ?? rowData.email_vendedor,
								skeletons
							)}
					</section>
					<section class="detail__section">
						<h3>Datos del vehículo</h3>
						<ul>
							<li><strong>Marca/Modelo:</strong> ${skeleton("marca_modelo")}</li>
							<li><strong>Tipo:</strong> ${skeleton("tipo", "-")}</li>
							<li><strong>Kilómetros:</strong> ${skeleton("kilometros", "-")} km</li>
							<li><strong>1ª Matriculación:</strong> ${skeleton(
								"primera_matriculacion",
								"-"
							)}</li>
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
							<li><strong>Potencia:</strong> ${skeleton("potencia", "-")}</li>
							<li><strong>Cilindrada:</strong> ${skeleton("cilindrada", "-")}</li>
						</ul>
					</section>
					<section class="detail__section detail__section--docs">
						<h3 class="detail__section-title">Documentación</h3>
						<ul class="detail__docs-list">
							<li class="detail__docs-item">
								<button type="button" class="detail__docs-btn" data-doc-url="${skeleton(
									"contrato_url",
									"#"
								)}" aria-label="Ver documento Contrato">
									<span class="detail__docs-label">Contrato</span>
								</button>
							</li>
							<li class="detail__docs-item">
								<button type="button" class="detail__docs-btn" data-doc-url="${skeleton(
									"condicionado_url",
									"#"
								)}" aria-label="Ver documento Condicionado">
									<span class="detail__docs-label">Condicionado</span>
								</button>
							</li>
							<li class="detail__docs-item">
								<button type="button" class="detail__docs-btn" data-doc-url="${skeleton(
									"cobertura_url",
									"#"
								)}" aria-label="Ver documento Cobertura">
									<span class="detail__docs-label">Cobertura</span>
								</button>
							</li>
							<li class="detail__docs-item">
								<button type="button" class="detail__docs-btn" data-doc-url="${skeleton(
									"factura_url",
									"#"
								)}" aria-label="Ver documento Factura">
									<span class="detail__docs-label">Factura</span>
								</button>
							</li>
						</ul>
					</section>
					<section class="detail__section">
						<h3>Datos del comprador</h3>
						<ul>
							<li><strong>Nombre:</strong> ${skeleton("nombre_comprador", "-")}</li>
							<li><strong>DNI/NIE:</strong> ${skeleton("dni_comprador", "-")}</li>
							<li><strong>Teléfono:</strong> ${skeleton("telefono_comprador", "-")}</li>
							<li><strong>Email:</strong> ${skeleton("email_comprador", "-")}</li>
							<li><strong>Dirección:</strong> ${skeleton("direccion_comprador", "-")}</li>
						</ul>
						<ul class="fast-actions">
							<li class="fast-actions__item">
								<a href="tel:${skeleton("telefono_comprador", "")}" class="fast-actions__link">
									<span class="fast-actions__label">Cliente</span>
								</a>
							</li>
							<li class="fast-actions__item">
								<a href="mailto:${skeleton("email_comprador", "")}" class="fast-actions__link">
									<span class="fast-actions__label">Cliente</span>
								</a>
							</li>
						</ul>
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
					nextPanel.innerHTML = renderFullDetail(
						rowData,
						rowData,
						skeletonFields
					);
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
					nextPanel.classList.add("loading");
					try {
                                            const res = await fetch(`${restRoot}go/v1/guarantees/${id}`, {
                                                    headers: { "X-WP-Nonce": restNonce },
						});
						if (!res.ok) throw res.status;
						const data = await res.json();
						detailCache.set(id, data);
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

			tbody.addEventListener(
				"mouseenter",
				async function (e) {
					const row = e.target.closest(".guarantees-table__row");
					if (!row) return;
					const id = row.dataset.id;
					if (detailCache.has(id)) return;
					try {
                                            const res = await fetch(`${restRoot}go/v1/guarantees/${id}`, {
                                                    headers: { "X-WP-Nonce": restNonce },
						});
						if (!res.ok) throw res.status;
						const data = await res.json();
						detailCache.set(id, data);
					} catch (e) {
						// Nada
					}
				},
				true
			);
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
			const onScroll = () =>
				document.body.classList.toggle(
					"scrolled",
					listContainer.scrollTop > 10 || detail.scrollTop > 10
				);
			listContainer.addEventListener("scroll", onScroll);
                        detail.addEventListener("scroll", onScroll);
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
                                                opt.value = est;
                                                opt.textContent = est;
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

		if (!urlMat) {
			new IntersectionObserver(
				(entries) => {
					if (entries[0].isIntersecting && hasMore && !isLoading) {
                                                loadPage(currentPage + 1);
					}
				},
				{ root: listContainer, threshold: 0.1, rootMargin: "200px 0px" }
			).observe(scrollEnd);
			loadPage(1);
		} else {
			loadAllAndSelect();
		}
	});
})();

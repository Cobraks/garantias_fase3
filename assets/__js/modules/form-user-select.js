"use strict";

function loadUsuariosPorRol(rol, selectId) {
	const restRoot = window.GO_REST?.root ?? "/wp-json/";
	const restNonce = window.GO_REST?.nonce ?? "";

	const select = document.getElementById(selectId);
	if (!select) return;

	select.innerHTML = '<option value="">Cargando...</option>';

	fetch(`${restRoot}go/v1/usuarios?role=${encodeURIComponent(rol)}`, {
		method: "GET",
		credentials: "include",
		headers: {
			"X-WP-Nonce": restNonce,
			Accept: "application/json",
		},
	})
		.then((resp) => {
			if (!resp.ok) throw new Error("REST Error");
			return resp.json();
		})
		.then((data) => {
			select.innerHTML = "";

			const defaultOption = document.createElement("option");
			defaultOption.value = "";
			defaultOption.textContent = "Selecciona vendedor";
			defaultOption.disabled = true;
			defaultOption.selected = true;
			select.appendChild(defaultOption);

			if (Array.isArray(data) && data.length) {
				data.forEach((user) => {
					const option = document.createElement("option");
					option.value = user.id;
					option.textContent = user.display_name;
					select.appendChild(option);
				});
			}

			select.dispatchEvent(new Event("change", { bubbles: true }));
		})
		.catch((err) => {
			select.innerHTML = '<option value="">Error cargando usuarios</option>';
			console.warn("[form-user-select] loadUsuariosPorRol error:", err);
		});
}

function mostrarBloqueUsuarioYcargar(rol) {
	const wrapUsuario = document.getElementById("wrap-select-usuario");
	const usuarioSelect = document.getElementById("usuario-rol");
	if (!wrapUsuario || !usuarioSelect) return;
	wrapUsuario.style.display = "";
	usuarioSelect.setAttribute("required", "");
	usuarioSelect.innerHTML = '<option value="">Cargando...</option>';
	loadUsuariosPorRol(rol, "usuario-rol");
}

async function fetchEstadoSepa(targetUserId = null) {
	const restRoot = window.GO_REST?.root ?? "/wp-json/";
	const restNonce = window.GO_REST?.nonce ?? "";
	let url = `${restRoot}go/v1/estado-sepa`;

	if (window.userRole === "admin" && targetUserId) {
		url += `?id=${encodeURIComponent(targetUserId)}`;
	}

	try {
		const res = await fetch(url, {
			method: "GET",
			credentials: "include",
			headers: {
				"X-WP-Nonce": restNonce,
				Accept: "application/json",
			},
			cache: "no-cache",
		});
		if (!res.ok) throw new Error("Error al obtener estado SEPA");
		const json = await res.json();
		return !!json.estado_sepa;
	} catch (e) {
		console.warn("[form-user-select] fallo al obtener estado SEPA:", e);
		return false;
	}
}

async function refreshMetodoPagoPorUsuario(targetUserId) {
	const tieneSepa = await fetchEstadoSepa(targetUserId);
	const select = document.getElementById("metodo_pago");
	const mensaje = document.querySelector(".mensaje_falta_sepa");
	if (!select) return;

	select.innerHTML = "";
	const optTrans = document.createElement("option");
	optTrans.value = "transferencia";
	optTrans.textContent = "Transferencia bancaria";
	select.appendChild(optTrans);

	if (tieneSepa) {
		const optDomic = document.createElement("option");
		optDomic.value = "domiciliacion";
		optDomic.textContent = "Domiciliación bancaria";
		optDomic.selected = true;
		select.appendChild(optDomic);
		optTrans.selected = false;
		if (mensaje) mensaje.style.display = "none";
	} else {
		optTrans.selected = true;
		if (mensaje) mensaje.style.display = "flex";
	}

	const container = select.closest(".form__input-container");
	if (container) {
		if (select.value) container.classList.add("has-value");
		else container.classList.remove("has-value");
	}

	if (typeof window.updateNextButtonState === "function") {
		window.updateNextButtonState();
	}
}

function setupSepaWatcher() {
	const canalSelect = document.getElementById("canal-venta");
	const usuarioSelect = document.getElementById("usuario-rol");

	if (usuarioSelect) {
		usuarioSelect.addEventListener("change", () => {
			let target = null;
			if (usuarioSelect.value) {
				target = usuarioSelect.value;
			} else {
				target = window.currentUserId || null;
			}
			refreshMetodoPagoPorUsuario(target);
		});
	}

	if (canalSelect) {
		canalSelect.addEventListener("change", () => {
			let target = null;
			if (window.userRole === "profesional") {
				target = window.currentUserId;
			} else if (window.userRole === "admin") {
				const v = document.getElementById("usuario-rol");
				target = (v && v.value) || window.currentUserId;
			} else if (window.userRole === "comercial") {
				const v = document.getElementById("usuario-rol");
				target = (v && v.value) || window.currentUserId;
			} else {
				target = window.currentUserId;
			}
			refreshMetodoPagoPorUsuario(target);
		});
	}

	let initialTarget = null;
	if (window.userRole === "profesional") {
		initialTarget = window.currentUserId;
	} else if (window.userRole === "admin") {
		const v = document.getElementById("usuario-rol");
		initialTarget = (v && v.value) || window.currentUserId;
	} else if (window.userRole === "comercial") {
		const v = document.getElementById("usuario-rol");
		initialTarget = (v && v.value) || window.currentUserId;
	} else {
		initialTarget = window.currentUserId;
	}
	refreshMetodoPagoPorUsuario(initialTarget);
}

function initUserSelect() {
	const canalSelect = document.getElementById("canal-venta");
	const usuarioSelect = document.getElementById("usuario-rol");
	const wrapUsuario = document.getElementById("wrap-select-usuario");
	const userRole = window.userRole || document.body.dataset.userRole || "";

	if (userRole === "admin" && canalSelect && usuarioSelect && wrapUsuario) {
		let initialValue = canalSelect.value;
		let profesionalOption =
			canalSelect.querySelector('option[value="profesional"]') ||
			canalSelect.querySelector('option[value="go_profesional"]');

		if (!initialValue && profesionalOption) {
			profesionalOption.selected = true;
			canalSelect.value = profesionalOption.value;
			initialValue = profesionalOption.value;
		}

		if (initialValue) {
			mostrarBloqueUsuarioYcargar(initialValue);
		} else {
			wrapUsuario.style.display = "none";
		}

		canalSelect.addEventListener("change", function () {
			const rol = this.value;
			if (!rol) {
				wrapUsuario.style.display = "none";
				if (usuarioSelect) usuarioSelect.innerHTML = "";
				return;
			}
			mostrarBloqueUsuarioYcargar(rol);
		});
	}

	const isComercial =
		document.body.classList.contains("rol-comercial") ||
		userRole === "comercial";
	if (isComercial && usuarioSelect) {
		loadUsuariosPorRol("go_profesional", "usuario-rol");
	}

	setupSepaWatcher();
}

export default initUserSelect;

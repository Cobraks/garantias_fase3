// assets/js/modules/form-user-select.js
"use strict";

import {
        getRestRoot,
        getRestNonce,
        getUserRole,
        getCurrentUserId,
} from "./config.js";
import { updateNextButtonState } from "./form-navigation.js";

/**
 * Lógica para cargar dinámicamente los usuarios por rol (Admin/Comercial) en el formulario de garantía.
 * Depende de la configuración proporcionada desde PHP.
 */

function loadUsuariosPorRol(rol, selectId) {
        const restRoot = getRestRoot();
        const restNonce = getRestNonce();

	const select = document.getElementById(selectId);
	if (!select) return;

	// Vacía el select y muestra cargando
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

			// Añadimos opción por defecto siempre
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
                                        const companyName = user.company_name || (user.company && user.company.name) || "";
                                        const personalName = user.personal_name || user.display_name || "";
                                        const label = companyName || personalName || user.email || `Usuario #${user.id}`;
                                        option.textContent = label;
                                        option.dataset.companyName = companyName;
                                        option.dataset.personalName = personalName;
                                        select.appendChild(option);
                                });
			}

			// Disparar evento para que otros listeners (como SEPA) reaccionen a valor inicial si ya hay uno
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
        usuarioSelect.setAttribute("required", ""); // obligatorio
        usuarioSelect.innerHTML = '<option value="">Cargando...</option>';
        loadUsuariosPorRol(rol, "usuario-rol");
}

function ocultarBloqueUsuario() {
        const wrapUsuario = document.getElementById("wrap-select-usuario");
        const usuarioSelect = document.getElementById("usuario-rol");
        if (!wrapUsuario || !usuarioSelect) return;

        wrapUsuario.style.display = "none";
        usuarioSelect.removeAttribute("required");
        usuarioSelect.innerHTML = "";
        usuarioSelect.value = "";
        usuarioSelect.dispatchEvent(new Event("change", { bubbles: true }));
}

/**
 * Consulta estado SEPA para un usuario objetivo.
 * Si se pasa targetUserId (para admin), lo usa. Si no, cae sobre currentUserId.
 */
async function fetchEstadoSepa(targetUserId = null) {
        const restRoot = getRestRoot();
        const restNonce = getRestNonce();
	let url = `${restRoot}go/v1/estado-sepa`;

	// Si admin y nos pasan objetivo, lo añadimos
        if (getUserRole() === "admin" && targetUserId) {
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

/**
 * Reconstruye el select de método de pago y mensaje en base a estado SEPA.
 * @param {string|number|null} targetUserId - profesional/vendedor seleccionado (o null)
 */
async function refreshMetodoPagoPorUsuario(targetUserId) {
	const tieneSepa = await fetchEstadoSepa(targetUserId);
	const select = document.getElementById("metodo_pago");
	const mensaje = document.querySelector(".mensaje_falta_sepa");
	if (!select) return;

	// Reconstruir opciones: siempre transferencia
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
		// No hay SEPA: sólo transferencia
		optTrans.selected = true;
		if (mensaje) mensaje.style.display = "flex";
	}

	// Floating label / has-value visual
	const container = select.closest(".form__input-container");
	if (container) {
		if (select.value) container.classList.add("has-value");
		else container.classList.remove("has-value");
	}

	// Revalidar estado siguiente si procede
        updateNextButtonState();
}

/**
 * Inicializa la observación de cambios para disparar actualización SEPA/método de pago
 */
function setupSepaWatcher() {
	const canalSelect = document.getElementById("canal-venta");
	const usuarioSelect = document.getElementById("usuario-rol");

	// Cuando cambia el usuario seleccionado (admin/comercial), refrescar método pago
	if (usuarioSelect) {
		usuarioSelect.addEventListener("change", () => {
			let target = null;
                        if (usuarioSelect.value) {
                                target = usuarioSelect.value;
                        } else {
                                target = getCurrentUserId() || null;
                        }
			refreshMetodoPagoPorUsuario(target);
		});
	}

	// Si canal cambia (por ejemplo admin elige otro canal y se recarga profesional),
	// también puede influir indirectamente porque se carga nuevo usuario.
	if (canalSelect) {
		canalSelect.addEventListener("change", () => {
			// Después de que se carguen usuarios, el propio loadUsuariosPorRol disparará el cambio en usuario-rol
			// Pero por si no hay usuario-rol aún seleccionado, hacemos refresh con fallback
			let target = null;
                        const role = getUserRole();
                       if (role === "profesional" || role === "go_profesional") {
                               target = getCurrentUserId();
                       } else if (role === "admin" || role === "comercial") {
                                const v = document.getElementById("usuario-rol");
                                target = (v && v.value) || getCurrentUserId();
                        } else {
                                target = getCurrentUserId();
                        }
			refreshMetodoPagoPorUsuario(target);
		});
	}

	// Inicial: disparar al cargar con el objetivo apropiado
	let initialTarget = null;
       const role = getUserRole();
       if (role === "profesional" || role === "go_profesional") {
               initialTarget = getCurrentUserId();
       } else if (role === "admin" || role === "comercial") {
                const v = document.getElementById("usuario-rol");
                initialTarget = (v && v.value) || getCurrentUserId();
        } else {
                initialTarget = getCurrentUserId();
        }
	refreshMetodoPagoPorUsuario(initialTarget);
}

function initUserSelect() {
	const canalSelect = document.getElementById("canal-venta");
	const usuarioSelect = document.getElementById("usuario-rol");
	const wrapUsuario = document.getElementById("wrap-select-usuario");
        const userRole = getUserRole() || document.body.dataset.userRole || "";

	// --- Para Admin: fuerza selección por defecto y carga usuarios al cargar ---
        if (userRole === "admin" && canalSelect && usuarioSelect && wrapUsuario) {
                const initialValue = canalSelect.value;

                if (initialValue) {
                        mostrarBloqueUsuarioYcargar(initialValue);
                } else {
                        ocultarBloqueUsuario();
                }

                // Listener al cambiar canal
                canalSelect.addEventListener("change", function () {
                        const rol = this.value;
                        if (!rol) {
                                ocultarBloqueUsuario();
                                return;
                        }
                        mostrarBloqueUsuarioYcargar(rol);
                });
        }

	// --- Para Comerciales: carga usuarios asignados al cargar el form ---
	const isComercial =
		document.body.classList.contains("rol-comercial") ||
		userRole === "comercial";
	if (isComercial && usuarioSelect) {
		loadUsuariosPorRol("go_profesional", "usuario-rol");
	}

	// Arranca el watcher de SEPA / método de pago
	setupSepaWatcher();
}

// Export como función de inicialización
export default initUserSelect;

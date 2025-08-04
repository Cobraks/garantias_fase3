// assets/js/form-cache.js
"use strict";

/*
    Centraliza el estado del wizard del formulario multistep.
    Sincroniza la modalidad seleccionada con form-state.js para evitar globals dispersos.
*/

import {
	getSelectedModalidadId as getSharedSelectedModalidadId,
	setSelectedModalidadId as setSharedSelectedModalidadId,
	subscribeSelectedModalidad,
} from "./modules/form-state.js";

const FormCache = {
	currentTab: 0,
	tabs: null,
	fieldsets: null,
	prevButton: null,
	nextButton: null,
	inputs: null,
	tabCompletion: [],
	usuario_rol: null,
	canal_venta: null,
	selectedModalidadId: null,

	setTabCompletion(index, value) {
		this.tabCompletion[index] = value;
	},
};

// Sincronizar modalidad seleccionada con el estado compartido
function syncSelectedModalidad() {
	// Inicial desde shared
	const shared = getSharedSelectedModalidadId();
	if (shared != null) {
		FormCache.selectedModalidadId = shared;
	}

	// Si alguien actualiza desde form-state, lo reflejamos aquí
	subscribeSelectedModalidad((id) => {
		FormCache.selectedModalidadId = id;
	});
}

// Inicialización
function init() {
	FormCache.tabs = Array.from(document.querySelectorAll(".tabs__link"));
	FormCache.fieldsets = Array.from(
		document.querySelectorAll(".form__tab-content")
	);
	FormCache.prevButton = document.querySelector(".btn.btn-secondary");
	FormCache.nextButton = document.querySelector(".btn.btn-primary");
	FormCache.inputs = Array.from(
		document.querySelectorAll(".form__input, .form__select, .form__checkbox")
	);

	// Ajustar longitud de tabCompletion según cantidad de tabs
	FormCache.tabCompletion = new Array(FormCache.tabs.length).fill(false);

	// Estado inicial limpio
	FormCache.usuario_rol = null;
	FormCache.canal_venta = null;
	FormCache.selectedModalidadId = null;

	// Sincronizar con form-state
	syncSelectedModalidad();

	// Legacy: exposición opcional para compatibilidad temporal
	if (typeof window !== "undefined") {
		window.FormCache = FormCache;
	}
}

export default {
	init,
	// acceso encapsulado
	get currentTab() {
		return FormCache.currentTab;
	},
	set currentTab(val) {
		FormCache.currentTab = val;
	},
	get tabs() {
		return FormCache.tabs;
	},
	get fieldsets() {
		return FormCache.fieldsets;
	},
	get prevButton() {
		return FormCache.prevButton;
	},
	get nextButton() {
		return FormCache.nextButton;
	},
	get inputs() {
		return FormCache.inputs;
	},
	get tabCompletion() {
		return FormCache.tabCompletion;
	},
	setTabCompletion: FormCache.setTabCompletion,
	get usuario_rol() {
		return FormCache.usuario_rol;
	},
	set usuario_rol(val) {
		FormCache.usuario_rol = val;
	},
	get canal_venta() {
		return FormCache.canal_venta;
	},
	set canal_venta(val) {
		FormCache.canal_venta = val;
	},
	get selectedModalidadId() {
		return FormCache.selectedModalidadId;
	},
	set selectedModalidadId(val) {
		FormCache.selectedModalidadId = val;
		// Propagar al estado compartido
		setSharedSelectedModalidadId(val);
	},
};

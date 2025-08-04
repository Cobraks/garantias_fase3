//assets/js/form-cache.js
"use strict";
/*
    Centraliza el estado global del formulario multistep.
    Preparado para crecer (tabCompletion, usuario/canal, etc.)
*/
const FormCache = {
	currentTab: 0,
	tabs: null,
	fieldsets: null,
	prevButton: null,
	nextButton: null,
	inputs: null,
	tabCompletion: [false, false, false, false],

	// Selección legacy de usuario/canal
	usuario_rol: null,
        canal_venta: null,

	setTabCompletion(index, value) {
		this.tabCompletion[index] = value;
	},
};

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

	// Más robusto: reinicia completions si cambia número de tabs
	FormCache.tabCompletion = new Array(FormCache.tabs.length).fill(false);

	// Estado inicial limpio
        FormCache.usuario_rol = null;
        FormCache.canal_venta = null;

        // (Opcional por ahora) legacy para compatibilidad entre módulos
        // Eliminado acceso global via window.
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
};

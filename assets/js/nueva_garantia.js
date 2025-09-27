"use strict";

// ===== Importar módulos =====
import FormCache from "./modules/form-cache.js";
import FormUI from "./modules/form-ui.js";
import { setupPlanSelection } from "./modules/plan-selection.js";
import initNavigation from "./modules/form-navigation.js";
import initValidation from "./modules/form-validation.js";
import initSummary from "./modules/form-summary.js";
import initSubmission from "./modules/form-submission.js";
import initDynamicFields from "./modules/form-dynamic-fields.js";
import initCalculations from "./modules/form-calculations.js";
import { isExampleDataButtonEnabled } from "./modules/config.js";
import initUserSelect from "./modules/form-user-select.js";
import initContratacionSummary from "./modules/form-contratacion-summary.js";
import initAutosave from "./modules/form-autosave.js";


// ===== Inicialización global =====
document.addEventListener("DOMContentLoaded", () => {
	// 1. Inicializa la caché global de nodos y estado del formulario
	FormCache.init();

        // 2. Inicializa helpers visuales y botones UI (clear, mensajes, scroll, etc)
        FormUI.init();

        // 3. Selección de plan
        setupPlanSelection();

        // 4. Lógica dinámica de campos (visibilidad, dependencias)
        initDynamicFields();

        // 5. Validación de todos los campos y gestión de errores en tiempo real
        initValidation();

        // 6. Navegación por pestañas y estado del wizard
        initNavigation();

        // 7. Resumen (panel derecho), gestión de errores y edición rápida
        initSummary();

        // 8. Cálculo de tarifas o precios (si existe lógica)
        initCalculations();

        // 9. Botón rellenar datos de ejemplo
        if (isExampleDataButtonEnabled()) {
                import("./modules/form-example-data.js")
                        .then(({ default: initExampleData }) => {
                                if (typeof initExampleData === "function") {
                                        initExampleData();
                                }
                        })
                        .catch((error) => {
                                console.error("Error cargando el módulo de datos de ejemplo", error);
                        });
        }

        // 10. Lógica de envío/finalización
        initSubmission();

        // 11. Lógica de selects dinámicos de usuario/canal (nuevo módulo)
        initUserSelect(FormCache);

        initContratacionSummary();
        initAutosave();
});

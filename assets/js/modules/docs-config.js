// assets/js/modules/docs-config.js
"use strict";

export const AVAILABLE_DOCS = [
        {
                key: "certificate",
                field: "certificate_url",
                routeType: "certificado",
                listLabel: "Certificado",
                successLabel: "Descargar certificado",
        },
        {
                key: "cobertura",
                field: "cobertura_url",
                routeType: "cobertura",
                listLabel: "Cobertura",
                successLabel: "Descargar cobertura",
        },
        {
                key: "condicionado",
                field: "condicionado_url",
                routeType: "condicionado",
                listLabel: "Condicionado",
                successLabel: "Descargar condicionado",
        },
];

export function getDocConfigByKey(key) {
        return AVAILABLE_DOCS.find((doc) => doc.key === key) || null;
}


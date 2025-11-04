// assets/js/modules/docs-config.js
"use strict";

export const AVAILABLE_DOCS = [
        {
                key: "certificate",
                field: "certificate_url",
                routeType: "certificado",
                listLabel: "Certificado completo",
                successLabel: "Descargar certificado completo",
                icon: "pdf",
        },
        {
                key: "cobertura",
                field: "cobertura_url",
                routeType: "cobertura",
                listLabel: "Cobertura",
                successLabel: "Descargar cobertura",
                icon: "pdf",
        },
        {
                key: "condicionado",
                field: "condicionado_url",
                routeType: "condicionado",
                listLabel: "Condicionado",
                successLabel: "Descargar condicionado",
                icon: "pdf",
        },
        {
                key: "sepa-signed",
                field: "",
                routeType: "",
                listLabel: "Mandato SEPA",
                successLabel: "Descargar mandato SEPA",
                icon: "payment",
        },
];

export function getDocConfigByKey(key) {
        return AVAILABLE_DOCS.find((doc) => doc.key === key) || null;
}


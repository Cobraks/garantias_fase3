import { PDFDocument } from "../pdf-lib.min.js";

function listFields(form) {
        return form.getFields().map((f) => f.getName());
}

export async function generateCertificate({ templateUrl, combustible, cp, nombre }) {
        if (!templateUrl) return null;
        const existingPdfBytes = await fetch(templateUrl, { credentials: "same-origin" }).then((r) => r.arrayBuffer());
        const pdfDoc = await PDFDocument.load(existingPdfBytes);
        const form = pdfDoc.getForm();
        const available = listFields(form);

        const setField = (name, value) => {
                if (!value) return;
                if (!available.includes(name)) {
                        console.error(`[pdf-certificate] field ${name} not found`, available);
                        return;
                }
                try {
                        form.getTextField(name).setText(String(value));
                } catch (e) {
                        console.error(`[pdf-certificate] unable to set ${name}`, e, available);
                }
        };

        setField("pdf_combustible", combustible);
        setField("pdf_cp", cp);
        setField("pdf_nombre_apellidos", nombre);

        form.flatten();
        return await pdfDoc.saveAsBase64({ dataUri: false });
}

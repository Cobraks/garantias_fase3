import { PDFDocument } from "../pdf-lib.min.js";

export async function generateCertificate({ templateUrl, combustible, cp, nombre }) {
        if (!templateUrl) return null;
        const existingPdfBytes = await fetch(templateUrl).then((r) => r.arrayBuffer());
        const pdfDoc = await PDFDocument.load(existingPdfBytes);
        const form = pdfDoc.getForm();
        if (combustible) {
                form.getTextField("pdf_combustible").setText(String(combustible));
        }
        if (cp) {
                form.getTextField("pdf_cp").setText(String(cp));
        }
        if (nombre) {
                form.getTextField("pdf_nombre_apellidos").setText(String(nombre));
        }
        form.flatten();
        const pdfBytes = await pdfDoc.save();
        let binary = "";
        const len = pdfBytes.length;
        for (let i = 0; i < len; i++) {
                binary += String.fromCharCode(pdfBytes[i]);
        }
        return btoa(binary);
}

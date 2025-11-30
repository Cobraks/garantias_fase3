const numberFormatter = new Intl.NumberFormat("de-DE", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
});

const ROLE_NORMALIZATION = {
        admin: "admin",
};

const pdfCache = new Map();

async function loadStaticPdf(url, options = {}) {
        if (!url) return null;

        const isDynamicEndpoint = /\/wp-json\/go\/v1\/guarantees\//.test(url);
        const shouldCache = options.cache !== undefined ? !!options.cache : !isDynamicEndpoint;
        const cacheKey = options.cacheKey || url;

        if (shouldCache && pdfCache.has(cacheKey)) {
                return pdfCache.get(cacheKey);
        }

        const fetchOptions = {};
        if (isDynamicEndpoint) {
            fetchOptions.cache = "no-store";
        }

        const response = await fetch(url, fetchOptions);
        if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
        }
        const bytes = await response.arrayBuffer();
        if (shouldCache) {
                pdfCache.set(cacheKey, bytes);
        }
        return bytes;
}

function pickFirstNonEmpty(values) {
        if (!Array.isArray(values)) return "";
        for (const value of values) {
                if (value === undefined || value === null) continue;
                const str = String(value).trim();
                if (str) return str;
        }
        return "";
}

export function normalizeVendorInfo(info = {}) {
        if (!info || typeof info !== "object") return {};
        const name = pickFirstNonEmpty([info.razonSocial, info.name, info.companyName, info.fullName]);
        const cif = pickFirstNonEmpty([info.cif, info.nif, info.taxId]);
        const addressLine = pickFirstNonEmpty([
                info.address,
                info.direccion,
                info.street,
                info.streetAddress,
                info.calle,
        ]);
        const postalCode = pickFirstNonEmpty([info.postalCode, info.cp, info.zip]);
        const city = pickFirstNonEmpty([info.city, info.localidad, info.municipio]);
        const province = pickFirstNonEmpty([info.province, info.provincia, info.state]);
        const phone = pickFirstNonEmpty([info.phone, info.telefono]);
        const email = pickFirstNonEmpty([info.email, info.correo]);

        const role = (() => {
                const raw = pickFirstNonEmpty([info.role, info.rol, info.type]);
                const normalized = raw.toLowerCase();
                return ROLE_NORMALIZATION[normalized] || raw;
        })();

        return {
                name,
                cif,
                addressLine,
                postalCode,
                city,
                province,
                phone,
                email,
                role,
        };
}

export function encodeSignaturePayload(payload) {
        const normalized = JSON.stringify(payload);
        try {
                return window.btoa(unescape(encodeURIComponent(normalized)));
        } catch (err) {
                return normalized;
        }
}

function addDays(baseDate, days) {
        const date = baseDate instanceof Date ? new Date(baseDate.getTime()) : new Date(baseDate);
        if (Number.isNaN(date.getTime())) return null;
        date.setDate(date.getDate() + days);
        return date;
}

function mmToPt(mm) {
        return (mm * 72) / 25.4;
}

function parseDateValue(value) {
        if (!value) return null;
        const date = value instanceof Date ? value : new Date(value);
        return Number.isNaN(date.getTime()) ? null : date;
}

function formatShortEsDate(value) {
        const date = parseDateValue(value);
        if (!date) return "";
        const months = [
                "ene.",
                "feb.",
                "mar.",
                "abr.",
                "may.",
                "jun.",
                "jul.",
                "ago.",
                "sep.",
                "oct.",
                "nov.",
                "dic.",
        ];
        return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
}

function resolveRect(form, fieldName) {
        try {
                const field = form.getTextField(fieldName);
                const widgets = field?.acroField?.getWidgets?.() || [];
                if (widgets.length) {
                        const rect = widgets[0].getRectangle();
                        if (
                                typeof rect?.x === "number" &&
                                typeof rect?.y === "number" &&
                                typeof rect?.width === "number" &&
                                typeof rect?.height === "number"
                        ) {
                                return rect;
                        }
                }
        } catch (err) {
                console.warn("[DOC] Missing anchor", fieldName, err);
        }
        return null;
}

function resolveAnyRect(form, ...names) {
        for (const name of names) {
            if (!name) continue;
            const rect = resolveRect(form, name);
            if (rect) return { name, rect };
        }
        return null;
}

async function loadFont(pdfDoc, url, cacheKey) {
        const bytes = await loadStaticPdf(url, { cache: true, cacheKey });
        if (!bytes) return null;
        return pdfDoc.embedFont(bytes);
}

function buildInvoiceReference(referenceValue, fallback) {
        if (referenceValue && referenceValue.trim() !== "") {
                return referenceValue.trim();
        }
        return fallback || "";
}

export async function generateDocumentPdf({
        templateUrl,
        items,
        coverageLabel: rawCoverageLabel,
        matricula,
        marca,
        modelo,
        emissionDate,
        dueDate,
        coverageStart,
        coverageEnd,
        vendorInfo,
        paymentMethod,
        transferIban,
        referenceValue,
        referenceLabel = "Ref.",
        highlightTotal = false,
}) {
        if (!templateUrl || !Array.isArray(items) || items.length === 0) {
                return { pdfBytes: null };
        }

        const pdfBytes = await loadStaticPdf(templateUrl, { cache: true });
        if (!pdfBytes) {
                return { pdfBytes: null };
        }

        const pdfDoc = await PDFLib.PDFDocument.load(pdfBytes);
        await import("../fontkit.umd.min.js");
        pdfDoc.registerFontkit(globalThis.fontkit);
        const form = pdfDoc.getForm();
        const page = pdfDoc.getPages()[0];

        const fontUrl = new URL("../../fonts/RobotoMono-Regular.ttf", import.meta.url);
        const boldFontUrl = new URL("../../fonts/RobotoMono-Bold.ttf", import.meta.url);
        const robotoMono = await loadFont(pdfDoc, fontUrl.href, "font:roboto-mono");
        const robotoMonoBold = await loadFont(pdfDoc, boldFontUrl.href, "font:roboto-mono-bold");

        let interRegular = null;
        let interMedium = null;
        let interBold = null;
        let interExtraBold = null;
        let interExtraBoldDisplay = null;
        try {
                const interRegularUrl = new URL("../../fonts/Inter_18pt-Regular.ttf", import.meta.url);
                interRegular = await loadFont(pdfDoc, interRegularUrl.href, "font:inter-18pt-regular");
        } catch (err) {
                console.warn("[DOC] Inter Regular load failed", err);
        }
        try {
                const interMediumUrl = new URL("../../fonts/Inter_18pt-Medium.ttf", import.meta.url);
                interMedium = await loadFont(pdfDoc, interMediumUrl.href, "font:inter-18pt-medium");
        } catch (err) {
                console.warn("[DOC] Inter Medium load failed", err);
        }
        try {
                const interBoldUrl = new URL("../../fonts/Inter_18pt-Bold.ttf", import.meta.url);
                interBold = await loadFont(pdfDoc, interBoldUrl.href, "font:inter-18pt-bold");
        } catch (err) {
                console.warn("[DOC] Inter Bold load failed", err);
        }
        try {
                const interExtraBoldUrl = new URL("../../fonts/Inter_18pt-ExtraBold.ttf", import.meta.url);
                interExtraBold = await loadFont(pdfDoc, interExtraBoldUrl.href, "font:inter-18pt-extrabold");
        } catch (err) {
                console.warn("[DOC] Inter ExtraBold load failed", err);
        }
        try {
                const interExtraBoldDisplayUrl = new URL("../../fonts/Inter_28pt-ExtraBold.ttf", import.meta.url);
                interExtraBoldDisplay = await loadFont(pdfDoc, interExtraBoldDisplayUrl.href, "font:inter-28pt-extrabold");
        } catch (err) {
                console.warn("[DOC] Inter ExtraBold display load failed", err);
        }

        const conceptoRect = resolveRect(form, "concepto_1");
        const importeRect = resolveRect(form, "importe_1");
        const coverageLabel = (rawCoverageLabel || "").trim();
        const defaultRowHeight = mmToPt(12.7);
        const rowHeight = (conceptoRect?.height || importeRect?.height || defaultRowHeight) * 1;
        const fontSize = 10;
        const borderThickness = 0.35;
        const normalizedVendorInfo = normalizeVendorInfo(vendorInfo || {});
        const normalizedPaymentMethod = typeof paymentMethod === "string" ? paymentMethod.trim().toLowerCase() : "";
        const normalizedTransferIban = typeof transferIban === "string" ? transferIban.trim() : "";

        const resolvedEmissionLabel = formatShortEsDate(emissionDate) || formatShortEsDate(new Date());
        const resolvedDueLabel = formatShortEsDate(dueDate) || formatShortEsDate(addDays(new Date(), 2));
        const coverageStartLabel = formatShortEsDate(coverageStart);
        const coverageEndLabel = formatShortEsDate(coverageEnd);

        const refEmisionRect = resolveRect(form, "ref_emision");
        if (refEmisionRect) {
                const labelFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                const valueFont = interRegular || robotoMono;
                const referenceLines = [
                        {
                                label: referenceLabel,
                                value: buildInvoiceReference(referenceValue, matricula),
                        },
                        {
                                label: "Emisión:",
                                value: resolvedEmissionLabel,
                        },
                        {
                                label: "Vencimiento:",
                                value: resolvedDueLabel,
                                color: PDFLib.rgb(0.8, 0, 0),
                        },
                ];

                const referenceFontSize = 10;
                const referenceLineHeight = 12;
                const baseY = refEmisionRect.y;
                const extraSpacing = mmToPt(1);
                const afterSpacingByIndex = {
                        0: extraSpacing,
                        1: extraSpacing,
                        2: extraSpacing,
                };

                const cumulativeOffsets = [];
                let offset = 0;
                for (let i = referenceLines.length - 1; i >= 0; i -= 1) {
                        cumulativeOffsets[i] = offset;
                        const spacing = afterSpacingByIndex[i] ?? extraSpacing;
                        offset += referenceLineHeight + spacing;
                }

                referenceLines.forEach((line, index) => {
                        const y = baseY + cumulativeOffsets[index];
                        const value = (line.value || "").trim();
                        if (!value) return;
                        page.drawText(line.label, {
                                x: refEmisionRect.x,
                                y,
                                size: referenceFontSize,
                                font: labelFont,
                                color: PDFLib.rgb(0, 0, 0),
                        });
                        const labelWidth = labelFont.widthOfTextAtSize(line.label, referenceFontSize);
                        page.drawText(value, {
                                x: refEmisionRect.x + labelWidth + mmToPt(1.2),
                                y,
                                size: referenceFontSize,
                                font: valueFont,
                                color: line.color || PDFLib.rgb(0, 0, 0),
                        });
                });
        }

        try {
                const vehicleField = resolveAnyRect(form, "objeto_cobertura", "objeto_cobertura_text");
                if (vehicleField) {
                        const { rect, name } = vehicleField;
                        const labelFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                        const valueFont = interRegular || robotoMono;
                        const label = "Vehículo objeto de la cobertura: ";
                        const value = `${matricula ? `${matricula} - ` : ""}${[marca, modelo]
                                .filter(Boolean)
                                .join(" ")}`.trim();
                        const labelWidth = labelFont.widthOfTextAtSize(label, 10);
                        const available = rect.width - mmToPt(3);
                        const paddedX = rect.x + mmToPt(1.5);
                        const baselineY = rect.y + rect.height / 2 - 5;
                        page.drawText(label, {
                                x: paddedX,
                                y: baselineY,
                                size: 10,
                                font: labelFont,
                                color: PDFLib.rgb(0, 0, 0),
                        });
                        const remainingWidth = Math.max(0, available - labelWidth);
                        const trimmedValue = valueFont.widthOfTextAtSize(value, 10) > remainingWidth
                                ? valueFont.splitTextIntoLines(value, { width: remainingWidth, size: 10 })[0]
                                : value;
                        page.drawText(trimmedValue, {
                                x: paddedX + labelWidth,
                                y: baselineY,
                                size: 10,
                                font: valueFont,
                                color: PDFLib.rgb(0, 0, 0),
                        });
                        form.removeField(name);
                }
        } catch (err) {
                console.warn("[DOC] coverage vehicle draw failed", err);
        }

        try {
                const coveragePeriodField = resolveRect(form, "periodo_cobertura");
                if (coveragePeriodField) {
                        const boldFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                        const regularFont = interRegular || robotoMono;
                        const baseY = coveragePeriodField.y + (coveragePeriodField.height - 10) / 2;
                        const label = "Periodo de servicio: ";
                        const value = coverageStartLabel && coverageEndLabel
                                ? `${coverageStartLabel} - ${coverageEndLabel}`
                                : "";
                        const labelWidth = boldFont.widthOfTextAtSize(label, 10);
                        const startX = coveragePeriodField.x + mmToPt(1.5);
                        page.drawText(label, { x: startX, y: baseY, size: 10, font: boldFont, color: PDFLib.rgb(0, 0, 0) });
                        if (value) {
                                page.drawText(value, {
                                        x: startX + labelWidth,
                                        y: baseY,
                                        size: 10,
                                        font: regularFont,
                                        color: PDFLib.rgb(0, 0, 0),
                                });
                        }
                        form.removeField("periodo_cobertura");
                }
        } catch (err) {
                console.warn("[DOC] coverage period draw failed", err);
        }

        try {
                form.removeField("concepto_1");
        } catch (err) {}
        try {
                form.removeField("importe_1");
        } catch (err) {}
        try {
                form.removeField("ref_emision");
        } catch (err) {}
        try {
                form.removeField("objeto_cobertura");
        } catch (err) {}
        try {
                form.removeField("periodo_cobertura");
        } catch (err) {}
        form.flatten();

        let totalHighlightImg = null;
        let totalHighlightImgTargetWidth = 0;
        if (highlightTotal) {
                try {
                        const totalHighlightImgUrl = new URL("../../images/highlight.png", import.meta.url);
                        const totalHighlightImgBytes = await loadStaticPdf(totalHighlightImgUrl.href, {
                                cache: true,
                                cacheKey: "img:total-highlight",
                        });
                        if (totalHighlightImgBytes) {
                                totalHighlightImg = await pdfDoc.embedPng(
                                        totalHighlightImgBytes instanceof Uint8Array
                                                ? totalHighlightImgBytes
                                                : new Uint8Array(totalHighlightImgBytes)
                                );
                                const baseDims = totalHighlightImg.scale(1);
                                totalHighlightImgTargetWidth =
                                        baseDims && baseDims.height > 0
                                                ? (rowHeight / baseDims.height) * baseDims.width
                                                : 0;
                        }
                } catch (err) {
                        console.warn("[DOC] Missing total highlight asset", err);
                }
        }

        const preparedItems = (Array.isArray(items) ? items : []).filter((item = {}) => {
                const concepto = (item.concepto || "").trim();
                const valor = item.valor;
                return concepto || valor === 0 || Number.isFinite(Number(valor));
        });

        const hasBaseImponible = preparedItems.some(
                (item = {}) => (item.concepto || "").trim().toLowerCase() === "base imponible"
        );
        if (!hasBaseImponible && preparedItems.length >= 2) {
                const ivaIndex = preparedItems.length - 2;
                const totalIndex = preparedItems.length - 1;
                const ivaValor = preparedItems[ivaIndex]?.valor;
                const totalValor = preparedItems[totalIndex]?.valor;
                if (Number.isFinite(ivaValor) && Number.isFinite(totalValor)) {
                        preparedItems.splice(ivaIndex, 0, {
                                concepto: "Base imponible",
                                valor: totalValor - ivaValor,
                                destacado: false,
                        });
                }
        }

        const conceptoPadding = mmToPt(5);
        const importePadding = mmToPt(5);
        const conceptoCellLeft = conceptoRect?.x ?? 40;
        const conceptoX = conceptoCellLeft + conceptoPadding;
        const conceptoRight = conceptoRect
                ? conceptoRect.x + conceptoRect.width
                : importeRect
                ? importeRect.x - 12
                : conceptoX + 200;
        const startY = conceptoRect?.y ?? importeRect?.y ?? page.getHeight() - 120;
        const importeRight = importeRect ? importeRect.x + importeRect.width : page.getWidth() - 60;
        let currentY = startY;
        const minY = 20;

        const lastIndex = preparedItems.length - 1;
        const penultimateIndex = Math.max(0, lastIndex - 1);

        const baseImponibleIndex = preparedItems.findIndex(
                (item = {}) => (item.concepto || "").trim().toLowerCase() === "base imponible"
        );

        let rowsDrawn = 0;

        const conceptoMaxWidth = conceptoRight - conceptoCellLeft - conceptoPadding * 2;

        for (let index = 0; index < preparedItems.length; index += 1) {
                const item = preparedItems[index];
                if (currentY < minY) {
                        break;
                }
                const baseLineGap = 2;
                const coverageNudge = 1;
                const isLast = index === lastIndex;
                const textSize = isLast ? 12 : fontSize;

                let concepto = (item.concepto || "").trim();
                const rawValor = item.valor;
                const valor = rawValor === 0 ? 0 : Number(rawValor);
                const hasValor = rawValor === 0 || Number.isFinite(valor);
                const importe = hasValor ? `${numberFormatter.format(valor)} \u20ac` : "";
                const conceptoLower = concepto.toLowerCase();
                const isBaseImponible = conceptoLower === "base imponible";
                const isIvaRow = conceptoLower.startsWith("iva");
                const isOddRow = rowsDrawn % 2 === 1;
                const isPenultimate = index === penultimateIndex;
                const alignRight = isLast || isPenultimate || isBaseImponible;
                const conceptUseBold = isLast;
                const importeUseBold = isLast || isBaseImponible || isIvaRow;

                if (coverageLabel && index === 0) {
                        concepto = `${concepto} - ${coverageLabel}`.trim();
                }

                let conceptoText = concepto;
                if (conceptoMaxWidth > 0) {
                        const conceptFont = conceptUseBold
                                ? interExtraBold || interBold || interMedium || robotoMonoBold
                                : interRegular || robotoMono;
                        const maxWidth = conceptoMaxWidth;
                        const measured = conceptFont.widthOfTextAtSize(conceptoText, textSize);
                        if (measured > maxWidth) {
                                const ratio = maxWidth / measured;
                                const approxLength = Math.max(4, Math.floor(conceptoText.length * ratio) - 1);
                                conceptoText = `${conceptoText.slice(0, approxLength)}…`;
                        }
                }

                const conceptoFont = conceptUseBold
                        ? interExtraBold || interBold || interMedium || robotoMonoBold
                        : interRegular || robotoMono;
                const importeFont = importeUseBold
                        ? interExtraBoldDisplay || interExtraBold || interBold || robotoMonoBold
                        : interMedium || interRegular || robotoMono;

                const y = currentY;
                const conceptoY = alignRight ? y + coverageNudge : y;
                const importeY = y;
                const lineHeight = textSize + baseLineGap;

                if (conceptoRect) {
                        page.drawRectangle({
                                x: conceptoRect.x,
                                y,
                                width: conceptoRect.width,
                                height: rowHeight,
                                color: PDFLib.rgb(1, 1, 1),
                                borderWidth: borderThickness,
                                borderColor: PDFLib.rgb(0, 0, 0),
                        });
                }
                if (importeRect) {
                        page.drawRectangle({
                                x: importeRect.x,
                                y,
                                width: importeRect.width,
                                height: rowHeight,
                                color: PDFLib.rgb(1, 1, 1),
                                borderWidth: borderThickness,
                                borderColor: PDFLib.rgb(0, 0, 0),
                        });
                }

                if (isOddRow) {
                        page.drawRectangle({
                                x: conceptoRect?.x ?? conceptoX - conceptoPadding,
                                y,
                                width: (conceptoRect?.width ?? conceptoMaxWidth) + conceptoPadding * 2,
                                height: rowHeight,
                                color: PDFLib.rgb(0.98, 0.98, 0.98),
                        });
                }

                page.drawText(conceptoText, {
                        x: conceptoX,
                        y: conceptoY + (rowHeight - lineHeight) / 2,
                        size: textSize,
                        font: conceptoFont,
                        color: PDFLib.rgb(0, 0, 0),
                });

                if (hasValor) {
                        const importeWidth = importeFont.widthOfTextAtSize(importe, textSize);
                        const startX = importeRight - importePadding - importeWidth;
                        page.drawText(importe, {
                                x: alignRight ? startX : importeRect?.x + importePadding || conceptoX,
                                y: importeY + (rowHeight - lineHeight) / 2,
                                size: textSize,
                                font: importeFont,
                                color: PDFLib.rgb(0, 0, 0),
                        });
                        if (isLast && totalHighlightImg && totalHighlightImgTargetWidth > 0) {
                                const { width, height } = totalHighlightImg.scale(
                                        totalHighlightImgTargetWidth / totalHighlightImg.width
                                );
                                const highlightY = y + (rowHeight - height) / 2;
                                page.drawImage(totalHighlightImg, {
                                        x: Math.max(conceptoX, startX - mmToPt(2)),
                                        y: highlightY,
                                        width,
                                        height,
                                });
                        }
                }

                currentY -= rowHeight;
                rowsDrawn += 1;
        }

        const concepto2Rect = resolveRect(form, "concepto_2");
        const importe2Rect = resolveRect(form, "importe_2");
        if (concepto2Rect) {
                form.removeField("concepto_2");
        }
        if (importe2Rect) {
                form.removeField("importe_2");
        }

        try {
                const vendedorField = resolveAnyRect(form, "nombre_vendedor", "nombre_vendedor_text");
                if (vendedorField) {
                        const { rect, name } = vendedorField;
                        const vendorLines = [
                                normalizedVendorInfo.name,
                                normalizedVendorInfo.role,
                        ].filter(Boolean);
                        const vendorFont = interMedium || interRegular || robotoMono;
                        vendorLines.forEach((line, idx) => {
                                const y = rect.y + rect.height - (idx + 1) * 12;
                                page.drawText(line, {
                                        x: rect.x + mmToPt(1.5),
                                        y,
                                        size: 10,
                                        font: vendorFont,
                                        color: PDFLib.rgb(0, 0, 0),
                                });
                        });
                        form.removeField(name);
                }
        } catch (err) {
                console.warn("[DOC] vendor draw failed", err);
        }

        try {
                const direccionField = resolveAnyRect(form, "direccion_vendedor", "direccion_vendedor_text");
                if (direccionField) {
                        const { rect, name } = direccionField;
                        const locationParts = [
                                normalizedVendorInfo.addressLine,
                                [normalizedVendorInfo.postalCode, normalizedVendorInfo.city].filter(Boolean).join(" "),
                                normalizedVendorInfo.province,
                        ].filter(Boolean);
                        const font = interRegular || robotoMono;
                        locationParts.forEach((line, idx) => {
                                const y = rect.y + rect.height - (idx + 1) * 12;
                                page.drawText(line, {
                                        x: rect.x + mmToPt(1.5),
                                        y,
                                        size: 10,
                                        font,
                                        color: PDFLib.rgb(0, 0, 0),
                                });
                        });
                        form.removeField(name);
                }
        } catch (err) {
                console.warn("[DOC] vendor location draw failed", err);
        }

        try {
                const pagoField = resolveAnyRect(form, "forma_pago", "forma_pago_text");
                if (pagoField) {
                        const { rect, name } = pagoField;
                        const lineHeight = 12;
                        const boxHeight = rect.height;
                        const leftBoxWidth = rect.width / 2;
                        const rightBoxWidth = rect.width / 2;
                        const leftBoxPadding = mmToPt(4);
                        const rightBoxPadding = mmToPt(4);
                        const paymentLabel = (() => {
                                if (normalizedPaymentMethod.startsWith("domiciliacion")) {
                                        return "Domiciliación";
                                }
                                if (normalizedPaymentMethod.startsWith("transferencia")) {
                                        return "Transferencia";
                                }
                                return "Transferencia";
                        })();
                        const showIbanBox = normalizedTransferIban !== "";
                        const paymentBoxHeight = boxHeight;
                        const paymentBoxY = rect.y;
                        const conceptoCellLeft = rect.x;
                        page.drawRectangle({
                                x: conceptoCellLeft,
                                y: paymentBoxY,
                                width: leftBoxWidth,
                                height: paymentBoxHeight,
                                borderColor: PDFLib.rgb(0, 0, 0),
                                borderWidth: borderThickness,
                        });
                        page.drawRectangle({
                                x: conceptoCellLeft + leftBoxWidth,
                                y: paymentBoxY,
                                width: rightBoxWidth,
                                height: paymentBoxHeight,
                                borderColor: PDFLib.rgb(0, 0, 0),
                                borderWidth: borderThickness,
                        });

                        const textY = paymentBoxY + (paymentBoxHeight - lineHeight) / 2;
                        const boldFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                        const regularFont = interRegular || robotoMono;

                        const drawSegments = (segments, startX) => {
                                let cursorX = startX;
                                segments.forEach(({ text, font }) => {
                                        if (!text) return;
                                        page.drawText(text, {
                                                x: cursorX,
                                                y: textY,
                                                size: lineHeight,
                                                font: font || regularFont,
                                                color: PDFLib.rgb(0, 0, 0),
                                        });
                                        cursorX += (font || regularFont).widthOfTextAtSize(text, lineHeight);
                                });
                        };

                        drawSegments(
                                [
                                        { text: "Forma de pago: ", font: boldFont },
                                        { text: paymentLabel, font: regularFont },
                                ],
                                conceptoCellLeft + leftBoxPadding
                        );

                        if (showIbanBox) {
                                const ibanSegments = [
                                        { text: "IBAN: ", font: boldFont },
                                        { text: normalizedTransferIban, font: regularFont },
                                ];
                                const totalWidth = ibanSegments.reduce(
                                        (acc, seg) => acc + (seg.font || regularFont).widthOfTextAtSize(seg.text || "", lineHeight),
                                        0
                                );
                                const rightBoxX = conceptoCellLeft + leftBoxWidth;
                                const startX = rightBoxX + Math.max(rightBoxPadding, rightBoxWidth - totalWidth - rightBoxPadding);
                                drawSegments(ibanSegments, startX);
                        }
                }
        } catch (err) {
                console.warn("[DOC] payment box draw failed", err);
        }

        const pdfBytesFilled = await pdfDoc.save();
        const signaturePayload = {
                templateUrl,
                items,
                coverageLabel,
                matricula,
                marca,
                modelo,
                emissionDate,
                dueDate,
                vendorInfo,
                paymentMethod,
                transferIban,
                coverageStart,
                coverageEnd,
                referenceValue,
        };

        return { pdfBytes: pdfBytesFilled, signature: encodeSignaturePayload(signaturePayload) };
}

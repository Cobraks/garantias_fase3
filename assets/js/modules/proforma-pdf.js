const pdfCache = new Map();

export async function loadPdfAsset(url, options = {}) {
        const cacheKey = options.cacheKey || url;
        const shouldCache = options.cache === true;

        if (!url || typeof url !== "string") return null;

        if (shouldCache && cacheKey && pdfCache.has(cacheKey)) {
                return pdfCache.get(cacheKey) || null;
        }

        try {
                const res = await fetch(url, { mode: "cors" });
                if (!res.ok) return null;
                const arrayBuffer = await res.arrayBuffer();
                const bytes = new Uint8Array(arrayBuffer);
                if (shouldCache && cacheKey) {
                        pdfCache.set(cacheKey, bytes);
                }
                return bytes;
        } catch (err) {
                console.warn("[PDF] loadPdfAsset failed", url, err);
                return null;
        }
}

function normalizeText(value) {
        if (typeof value === "string") return value.trim();
        if (value === null || value === undefined) return "";
        return String(value).trim();
}

export function normalizeVendorInfo(raw = {}) {
        const normalized = {
                role: normalizeText(raw.role || raw.channel || ""),
                name: normalizeText(raw.name || ""),
                companyName: normalizeText(raw.companyName || ""),
                personalName: normalizeText(raw.personalName || ""),
                cif: normalizeText(raw.cif || ""),
                email: normalizeText(raw.email || ""),
                phone: normalizeText(raw.phone || ""),
                address: {
                        street: normalizeText(raw.address?.street || ""),
                        zip: normalizeText(raw.address?.zip || ""),
                        city: normalizeText(raw.address?.city || ""),
                        province: normalizeText(raw.address?.province || ""),
                },
        };

        if (!normalized.role) {
                normalized.role = "";
        }

        if (!normalized.name) {
                normalized.name = normalized.companyName || normalized.personalName || "";
        }

        if (normalized.address.city && normalized.address.province) {
                const samePlace =
                        normalized.address.city.localeCompare(normalized.address.province, undefined, {
                                sensitivity: "base",
                        }) === 0;
                if (samePlace) {
                        normalized.address.province = normalized.address.city;
                }
        }

        return normalized;
}

function addDays(date, days) {
        const d = new Date(date);
        if (Number.isNaN(d.getTime())) return d;
        d.setDate(d.getDate() + days);
        return d;
}

function toIsoDateString(input) {
        const date = input instanceof Date ? input : new Date(input);
        if (Number.isNaN(date.getTime())) return "";

        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, "0");
        const day = String(date.getDate()).padStart(2, "0");

        return `${year}-${month}-${day}`;
}

function formatShortEsDate(value) {
        const date = value instanceof Date ? value : new Date(value);
        if (Number.isNaN(date.getTime())) return "";
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

function normalizePaymentMethod(value) {
        if (typeof value !== "string") return "";
        return value.trim().toLowerCase();
}

function mmToPt(mm) {
        return (mm * 72) / 25.4;
}

export async function buildProformaPdf({
        templateUrl,
        items,
        coverageLabel: rawCoverageLabel,
        reference,
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
        proformaSettings = {},
        loadAsset = loadPdfAsset,
}) {
        if (!templateUrl || !Array.isArray(items) || items.length === 0) {
                return null;
        }

        const pdfBytes = await loadAsset(templateUrl, { cache: true });
        if (!pdfBytes) {
                return null;
        }

        const { PDFDocument, rgb } = globalThis.PDFLib || {};
        if (!PDFDocument || !rgb) return null;

        const pdfDoc = await PDFDocument.load(pdfBytes);
        await import("../fontkit.umd.min.js");
        pdfDoc.registerFontkit(globalThis.fontkit);
        const form = pdfDoc.getForm();
        const page = pdfDoc.getPages()[0];

        const fontUrl = new URL("../../fonts/RobotoMono-Regular.ttf", import.meta.url);
        const boldFontUrl = new URL("../../fonts/RobotoMono-Bold.ttf", import.meta.url);
        const robotoBytes = await loadAsset(fontUrl.href, {
                cache: true,
                cacheKey: "font:roboto-mono",
        });
        const robotoBoldBytes = await loadAsset(boldFontUrl.href, {
                cache: true,
                cacheKey: "font:roboto-mono-bold",
        });
        const robotoMono = await pdfDoc.embedFont(robotoBytes);
        const robotoMonoBold = await pdfDoc.embedFont(robotoBoldBytes);

        let interRegular = null;
        let interMedium = null;
        let interBold = null;
        let interExtraBold = null;
        let interExtraBoldDisplay = null;
        try {
                const interRegularUrl = new URL("../../fonts/Inter_18pt-Regular.ttf", import.meta.url);
                const interRegularBytes = await loadAsset(interRegularUrl.href, {
                        cache: true,
                        cacheKey: "font:inter-18pt-regular",
                });
                if (interRegularBytes) {
                        interRegular = await pdfDoc.embedFont(interRegularBytes);
                }
        } catch (err) {
                console.warn("[PROFORMA] Inter Regular load failed", err);
        }
        try {
                const interMediumUrl = new URL("../../fonts/Inter_18pt-Medium.ttf", import.meta.url);
                const interMediumBytes = await loadAsset(interMediumUrl.href, {
                        cache: true,
                        cacheKey: "font:inter-18pt-medium",
                });
                if (interMediumBytes) {
                        interMedium = await pdfDoc.embedFont(interMediumBytes);
                }
        } catch (err) {
                console.warn("[PROFORMA] Inter Medium load failed", err);
        }
        try {
                const interBoldUrl = new URL("../../fonts/Inter_18pt-Bold.ttf", import.meta.url);
                const interBoldBytes = await loadAsset(interBoldUrl.href, {
                        cache: true,
                        cacheKey: "font:inter-18pt-bold",
                });
                if (interBoldBytes) {
                        interBold = await pdfDoc.embedFont(interBoldBytes);
                }
        } catch (err) {
                console.warn("[PROFORMA] Inter Bold load failed", err);
        }
        try {
                const interExtraBoldUrl = new URL("../../fonts/Inter_18pt-ExtraBold.ttf", import.meta.url);
                const interExtraBoldBytes = await loadAsset(interExtraBoldUrl.href, {
                        cache: true,
                        cacheKey: "font:inter-18pt-extrabold",
                });
                if (interExtraBoldBytes) {
                        interExtraBold = await pdfDoc.embedFont(interExtraBoldBytes);
                }
        } catch (err) {
                console.warn("[PROFORMA] Inter ExtraBold load failed", err);
        }
        try {
                const interExtraBoldDisplayUrl = new URL("../../fonts/Inter_28pt-ExtraBold.ttf", import.meta.url);
                const interExtraBoldDisplayBytes = await loadAsset(interExtraBoldDisplayUrl.href, {
                        cache: true,
                        cacheKey: "font:inter-28pt-extrabold",
                });
                if (interExtraBoldDisplayBytes) {
                        interExtraBoldDisplay = await pdfDoc.embedFont(interExtraBoldDisplayBytes);
                }
        } catch (err) {
                console.warn("[PROFORMA] Inter 28pt ExtraBold load failed", err);
        }

        const numberFormatter = new Intl.NumberFormat("es-ES", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
        });

        const splitIntoLines = (text, maxWidth, font, size, maxLines = 2) => {
                if (!text) return [];
                const words = text.split(/\s+/).filter(Boolean);
                const lines = [];
                let current = "";

                for (const word of words) {
                        const tentative = current ? `${current} ${word}` : word;
                        if (font.widthOfTextAtSize(tentative, size) <= maxWidth) {
                                current = tentative;
                        } else if (!current) {
                                lines.push(tentative);
                                if (lines.length === maxLines) break;
                                current = "";
                        } else {
                                lines.push(current);
                                if (lines.length === maxLines) {
                                        current = "";
                                        break;
                                }
                                current = word;
                        }
                }

                if (current && lines.length < maxLines) {
                        lines.push(current);
                }

                return lines;
        };

        const resolveRect = (fieldName) => {
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
                        console.warn("[PROFORMA] Missing proforma anchor", fieldName, err);
                }
                return null;
        };

        const resolveAnyRect = (...names) => {
                for (const name of names) {
                        if (!name) continue;
                        const rect = resolveRect(name);
                        if (rect) return { name, rect };
                }
                return null;
        };

        const conceptoRect = resolveRect("concepto_1");
        const importeRect = resolveRect("importe_1");
        const coverageLabel = (rawCoverageLabel || "").trim();
        const defaultRowHeight = mmToPt(12.7);
        const rowHeight = (conceptoRect?.height || importeRect?.height || defaultRowHeight) * 1;
        const fontSize = 10;
        const borderThickness = 0.35;
        const normalizedVendorInfo = normalizeVendorInfo(vendorInfo || {});
        const normalizedPaymentMethod = normalizePaymentMethod(paymentMethod);
        const normalizedTransferIban = typeof transferIban === "string" ? transferIban.trim() : "";

        const parseDateValue = (value) => {
                if (!value) return null;
                const date = value instanceof Date ? value : new Date(value);
                return Number.isNaN(date.getTime()) ? null : date;
        };

        const formatShortDate = (value) => {
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
        };

        const resolvedEmissionLabel = formatShortDate(emissionDate) || formatShortDate(new Date());
        const resolvedDueLabel = formatShortDate(dueDate) || formatShortDate(addDays(new Date(), 2));

        const refEmisionRect = resolveRect("ref_emision");
        if (refEmisionRect) {
                const labelFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                const valueFont = interRegular || robotoMono;
                const referenceLines = [
                        {
                                label: "Ref.",
                                value: (reference || matricula || "").trim(),
                        },
                        {
                                label: "Emisión:",
                                value: resolvedEmissionLabel,
                        },
                        {
                                label: "Vencimiento:",
                                value: resolvedDueLabel,
                                color: rgb(0.8, 0, 0),
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
                        offset += referenceLineHeight + (afterSpacingByIndex[i] || 0);
                }

                referenceLines.forEach((line, index) => {
                        const y = baseY + cumulativeOffsets[index];
                        const color = line.color || rgb(0, 0, 0);
                        const labelText = line.label ? `${line.label} ` : "";
                        const labelWidth = labelFont.widthOfTextAtSize(labelText, referenceFontSize);
                        const valueText = (line.value || "").trim();
                        const valueWidth = valueFont.widthOfTextAtSize(valueText, referenceFontSize);
                        const totalWidth = labelWidth + valueWidth;
                        const x = refEmisionRect.x + refEmisionRect.width - totalWidth;

                        page.drawText(labelText, {
                                x,
                                y,
                                size: referenceFontSize,
                                font: labelFont,
                                color,
                        });

                        page.drawText(valueText, {
                                x: x + labelWidth,
                                y,
                                size: referenceFontSize,
                                font: valueFont,
                                color,
                        });
                });
        }

        const objetoCoberturaRect = resolveRect("objeto_cobertura");
        if (objetoCoberturaRect) {
                const baseFont = interRegular || robotoMono;
                const boldFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                const textSize = 10;
                const matriculaLabel = (matricula || "").trim();
                const marcaModeloLabel = [marca, modelo]
                        .map((value) => (value || "").trim())
                        .filter(Boolean)
                        .join(" ");
                const segments = [
                        { text: "Vehículo objeto de la cobertura: ", font: boldFont },
                ];

                if (matriculaLabel) {
                        segments.push({ text: matriculaLabel, font: baseFont });
                }

                if (matriculaLabel && marcaModeloLabel) {
                        segments.push({ text: " - ", font: baseFont });
                }

                if (marcaModeloLabel) {
                        segments.push({ text: marcaModeloLabel, font: baseFont });
                }

                let cursorX = objetoCoberturaRect.x;
                const cursorY = objetoCoberturaRect.y;
                segments.forEach(({ text, font }) => {
                        if (!text) return;
                        const width = font.widthOfTextAtSize(text, textSize);
                        page.drawText(text, {
                                x: cursorX,
                                y: cursorY,
                                size: textSize,
                                font,
                                color: rgb(0, 0, 0),
                        });
                        cursorX += width;
                });
        }

        const periodoCoberturaRect = resolveRect("periodo_cobertura");
        const coverageStartLabel = formatShortDate(coverageStart);
        const coverageEndLabel = formatShortDate(coverageEnd);
        if (periodoCoberturaRect && (coverageStartLabel || coverageEndLabel)) {
                const boldFont = interBold || interMedium || interRegular || robotoMonoBold || robotoMono;
                const regularFont = interRegular || robotoMono;
                const textSize = 10;
                const periodLabel = "Periodo de servicio";
                const periodValue = [coverageStartLabel, coverageEndLabel]
                        .filter(Boolean)
                        .join(" - ");
                const segments = [
                        { text: `${periodLabel}: `, font: boldFont },
                        { text: periodValue, font: regularFont },
                ];

                let cursorX = periodoCoberturaRect.x;
                const cursorY = periodoCoberturaRect.y;
                segments.forEach(({ text, font }) => {
                        if (!text) return;
                        const width = font.widthOfTextAtSize(text, textSize);
                        page.drawText(text, {
                                x: cursorX,
                                y: cursorY,
                                size: textSize,
                                font,
                                color: rgb(0, 0, 0),
                        });
                        cursorX += width;
                });
        }

        const conceptoPadding = mmToPt(5);
        const importePadding = mmToPt(5);
        const conceptoCellLeft = conceptoRect?.x ?? 40;
        const conceptoX = conceptoCellLeft + conceptoPadding;
        const conceptoRight =
                conceptoRect?.x && conceptoRect?.width
                        ? conceptoRect.x + conceptoRect.width
                        : importeRect
                        ? importeRect.x - 12
                        : conceptoX + 200;
        const startY = conceptoRect?.y ?? importeRect?.y ?? page.getHeight() - 120;
        const importeRight = importeRect?.x && importeRect?.width ? importeRect.x + importeRect.width : page.getWidth() - 60;
        let currentY = startY;
        const minY = 20;

        let totalHighlightImg = null;
        let totalHighlightImgTargetWidth = 0;
        if (proformaSettings.highlightTotal) {
                try {
                        const totalHighlightImgUrl = new URL("../../images/highlight.png", import.meta.url);
                        const totalHighlightImgBytes = await loadAsset(totalHighlightImgUrl.href, {
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
                        console.warn("[PROFORMA] Missing total highlight asset", err);
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

        const lastIndex = preparedItems.length - 1;
        const penultimateIndex = Math.max(0, lastIndex - 1);
        const baseImponibleIndex = preparedItems.findIndex(
                (item = {}) => (item.concepto || "").trim().toLowerCase() === "base imponible"
        );
        const conceptoMaxWidth = conceptoRight - conceptoCellLeft - conceptoPadding * 2;
        let rowsDrawn = 0;

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
                const useExtraBold = isLast;
                const conceptRegularFont = robotoMono || interRegular;
                const conceptBoldFont = robotoMonoBold || conceptRegularFont;
                const conceptExtraBoldFont = robotoMonoBold || conceptBoldFont;
                const importeRegularFont = robotoMono || interRegular || conceptRegularFont;
                const importeBoldFont = robotoMonoBold || importeRegularFont;
                const importeExtraBoldFont = interExtraBoldDisplay || interExtraBold || importeBoldFont;
                const conceptFont =
                        useExtraBold
                                ? conceptExtraBoldFont
                                : conceptUseBold
                                ? conceptBoldFont
                                : conceptRegularFont;
                const importeFont =
                        useExtraBold
                                ? importeExtraBoldFont
                                : importeUseBold
                                ? importeBoldFont
                                : importeRegularFont;
                const textColor = isLast ? rgb(0.8, 0, 0) : rgb(0, 0, 0);
                const isFirstRowWithCoverage = index === 0 && coverageLabel;
                if (isFirstRowWithCoverage) {
                        concepto = `${coverageLabel} · ${concepto}`.trim();
                }
                const conceptoLines = concepto
                        ? splitIntoLines(
                                  concepto,
                                  Math.max(10, conceptoMaxWidth),
                                  conceptFont,
                                  textSize
                          )
                        : [];
                const conceptBlockHeight =
                        conceptoLines.length > 0
                                ? conceptoLines.length * textSize + (conceptoLines.length - 1) * baseLineGap
                                : 0;
                const blockOffset = (rowHeight - conceptBlockHeight) / 2;
                const conceptoBaseY =
                        currentY + blockOffset + (conceptoLines.length > 0 ? conceptBlockHeight - textSize : 0);
                const conceptoYStart = conceptoBaseY + (isFirstRowWithCoverage ? coverageNudge : 0);
                const importeY = currentY + (rowHeight - textSize) / 2;

                if (isLast) {
                        concepto = "TOTAL";
                        conceptoLines.length = 0;
                        conceptoLines.push(concepto);
                }

                if (isOddRow) {
                        page.drawRectangle({
                                x: conceptoCellLeft,
                                y: currentY,
                                width: importeRight - conceptoCellLeft,
                                height: rowHeight,
                                color: rgb(0.97, 0.97, 0.97),
                        });
                }

                if (isLast) {
                        if (totalHighlightImg && totalHighlightImgTargetWidth > 0) {
                                const inset = 1;
                                const availableWidth = importeRight - conceptoCellLeft - inset;
                                const svgWidth = Math.min(availableWidth, totalHighlightImgTargetWidth);
                                if (svgWidth > 0) {
                                        const svgScale = svgWidth / totalHighlightImgTargetWidth;
                                        const svgHeight = rowHeight * svgScale;
                                        const svgX = importeRight - svgWidth - inset;
                                        const svgY = currentY;
                                        page.drawImage(totalHighlightImg, {
                                                x: svgX,
                                                y: svgY,
                                                width: svgWidth,
                                                height: svgHeight,
                                        });
                                }
                        }
                }

                if (conceptoLines.length > 0) {
                        conceptoLines.forEach((line, lineIndex) => {
                                const width = conceptFont.widthOfTextAtSize(line, textSize);
                                const x = alignRight ? conceptoRight - conceptoPadding - width : conceptoX;
                                const y = conceptoYStart - lineIndex * (textSize + baseLineGap);
                                page.drawText(line, {
                                        x,
                                        y,
                                        size: textSize,
                                        font: conceptFont,
                                        color: textColor,
                                });
                        });
                }

                if (importe) {
                        const width = importeFont.widthOfTextAtSize(importe, textSize);
                        const targetX = importeRight - importePadding - width;
                        page.drawText(importe, {
                                x: targetX,
                                y: importeY,
                                size: textSize,
                                font: importeFont,
                                color: textColor,
                        });
                }

                rowsDrawn += 1;
                currentY -= rowHeight;
        }

        if (rowsDrawn > 0) {
                const tableTopY = startY + rowHeight;
                const tableBottomY = currentY + rowHeight;
                const strokeColor = rgb(0, 0, 0);
                page.drawLine({
                        start: { x: conceptoCellLeft, y: tableBottomY },
                        end: { x: conceptoCellLeft, y: tableTopY },
                        thickness: borderThickness,
                        color: strokeColor,
                });
                page.drawLine({
                        start: { x: importeRight, y: tableBottomY },
                        end: { x: importeRight, y: tableTopY },
                        thickness: borderThickness,
                        color: strokeColor,
                });
                page.drawLine({
                        start: { x: conceptoCellLeft, y: tableBottomY },
                        end: { x: importeRight, y: tableBottomY },
                        thickness: borderThickness,
                        color: strokeColor,
                });
        }

        if (rowsDrawn > 0) {
                const isParticularRole = normalizedVendorInfo.role === "particular";
                const isDirectDebit =
                        !isParticularRole &&
                        (normalizedPaymentMethod === "domiciliacion" ||
                                normalizedPaymentMethod === "domiciliacion_bancaria");
                const paymentLabel = isDirectDebit ? "Domiciliación bancaria" : "Transferencia bancaria";
                const showIbanBox = !isDirectDebit;
                const paymentBoxY = currentY + mmToPt(1);
                const paymentBoxHeight = rowHeight;
                const tableWidth = importeRight - conceptoCellLeft;
                const leftBoxWidth = showIbanBox ? tableWidth / 2 : tableWidth;
                const rightBoxWidth = showIbanBox ? tableWidth - leftBoxWidth : 0;
                const leftBoxPadding = mmToPt(5);
                const rightBoxPadding = mmToPt(5);
                const lineHeight = 10;
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
                                        color: rgb(0, 0, 0),
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

        const vendorAnchor = resolveAnyRect("beneficiario", "beneficiario_particular", "beneficiario_profesional");
        if (vendorAnchor?.rect) {
                const { rect, name: vendorFieldName } = vendorAnchor;
                const isParticular = normalizedVendorInfo.role === "particular";
                const mediumFont = interMedium || interBold || interRegular || robotoMonoBold || robotoMono;
                const regularFont = interRegular || robotoMono;
                const defaultLineHeight = 12;
                const firstLineHeight = 16.8;
                const gapAfterFirst = mmToPt(2);
                const gapAfterLine = mmToPt(1);
                const lines = [];

                const nameLine = isParticular
                        ? normalizedVendorInfo.name || normalizedVendorInfo.personalName
                        : normalizedVendorInfo.companyName || normalizedVendorInfo.name;
                if (nameLine) {
                        lines.push({
                                text: nameLine,
                                font: mediumFont,
                                size: 14,
                                lineHeight: firstLineHeight,
                                gap: gapAfterFirst,
                        });
                }

                if (!isParticular && normalizedVendorInfo.cif) {
                        lines.push({
                                text: normalizedVendorInfo.cif,
                                font: regularFont,
                                size: 10,
                                lineHeight: defaultLineHeight,
                                gap: gapAfterLine,
                        });
                }

                const contactParts = [normalizedVendorInfo.email, normalizedVendorInfo.phone].filter(Boolean);
                if (contactParts.length) {
                        lines.push({
                                text: contactParts.join("    "),
                                font: regularFont,
                                size: 10,
                                lineHeight: defaultLineHeight,
                                gap: gapAfterLine,
                        });
                }

                const addressParts = [];
                if (normalizedVendorInfo.address?.street) {
                        addressParts.push(normalizedVendorInfo.address.street);
                }
                if (normalizedVendorInfo.address?.zip) {
                        addressParts.push(normalizedVendorInfo.address.zip);
                }
                const city = normalizedVendorInfo.address?.city;
                const province = normalizedVendorInfo.address?.province;
                if (city) {
                        addressParts.push(city);
                }
                if (province && (!city || province.localeCompare(city, undefined, { sensitivity: "base" }) !== 0)) {
                        addressParts.push(province);
                }

                if (addressParts.length) {
                        lines.push({
                                text: addressParts.join(", "),
                                font: regularFont,
                                size: 10,
                                lineHeight: defaultLineHeight,
                                gap: gapAfterLine,
                        });
                }

                if (lines.length) {
                        const baseY = rect.y;
                        const cumulativeOffsets = [];
                        let offset = 0;

                        for (let i = lines.length - 1; i >= 0; i -= 1) {
                                cumulativeOffsets[i] = offset;
                                const lineHeight = typeof lines[i].lineHeight === "number" ? lines[i].lineHeight : defaultLineHeight;
                                const spacing = lines[i].gap ?? gapAfterLine;
                                offset += lineHeight + spacing;
                        }

                        lines.forEach((line, index) => {
                                const y = baseY + cumulativeOffsets[index];
                                const x = rect.x;
                                page.drawText(line.text, {
                                        x,
                                        y,
                                        size: line.size,
                                        font: line.font,
                                        color: rgb(0, 0, 0),
                                });
                        });
                }

                if (vendorFieldName) {
                        try {
                                form.removeField(vendorFieldName);
                        } catch (err) {
                                console.warn("[PROFORMA] vendor field cleanup", vendorFieldName, err);
                        }
                }
        }

        try {
                form.removeField("concepto_1");
        } catch (err) {
                // ignore
        }
        try {
                form.removeField("importe_1");
        } catch (err) {
                // ignore
        }
        try {
                form.removeField("ref_emision");
        } catch (err) {
                // ignore
        }
        try {
                form.removeField("objeto_cobertura");
        } catch (err) {
                // ignore
        }
        try {
                form.removeField("periodo_cobertura");
        } catch (err) {
                // ignore
        }

        form.flatten();

        return pdfDoc.save();
}

import { Page, Card } from "@shopify/polaris";
import { Fragment, useEffect, useRef, useState } from "react";

function normalizeVariantDraft(variant) {
  const saved = variant?.saved_inputs || {};
  return {
    metal_type: saved.metal_type || "gold",
    gold_karat: saved.gold_karat || "10kt",
    metal_weight: Number(saved.metal_weight) || 0,
    diamond_quality_value: saved.diamond_quality_value || "",
    diamond_weight: Number(saved.diamond_weight) || 0,
    making_charge: Number(saved.making_charge) || 0,
    price: Number(saved.computed_total ?? variant?.price ?? 0) || 0,
  };
}

function hasDraftChanged(variant, draft) {
  const base = normalizeVariantDraft(variant);
  return (
    base.metal_type !== draft.metal_type ||
    base.gold_karat !== draft.gold_karat ||
    Number(base.metal_weight) !== Number(draft.metal_weight) ||
    (base.diamond_quality_value || "") !== (draft.diamond_quality_value || "") ||
    Number(base.diamond_weight) !== Number(draft.diamond_weight) ||
    Number(base.making_charge) !== Number(draft.making_charge) ||
    Number(base.price).toFixed(2) !== Number(draft.price).toFixed(2)
  );
}

export default function ProductPrice() {
  const [products, setProducts] = useState([]);
  const [openProductId, setOpenProductId] = useState(null);
  const [openVariantId, setOpenVariantId] = useState(null);
  const [drafts, setDrafts] = useState({});
  const [savingAll, setSavingAll] = useState(false);
  const [csvImporting, setCsvImporting] = useState(false);
  const [currencySymbol, setCurrencySymbol] = useState("Rs");
  const [priceSettings, setPriceSettings] = useState({
    gold_10kt: 0,
    gold_14kt: 0,
    gold_18kt: 0,
    gold_22kt: 0,
    silver_price: 0,
    platinum_price: 0,
    tax_percent: 0,
    diamonds: [],
  });
  const [message, setMessage] = useState("");
  const [messageType, setMessageType] = useState("success");

  const importFileRef = useRef(null);

  const fetchProducts = () => {
    fetch("/api/products")
      .then((res) => res.json())
      .then((data) => setProducts(data.products || []))
      .catch(() => {});
  };

  const fetchPriceSettings = () => {
    fetch(`/api/price-settings${window.location.search ?? ""}`, {
      headers: { Accept: "application/json" },
      credentials: "same-origin",
    })
      .then((res) => (res.ok ? res.json() : null))
      .then((data) => {
        if (!data) return;
        if (data.currency_symbol) setCurrencySymbol(data.currency_symbol);
        setPriceSettings({
          gold_10kt: Number(data.gold_10kt) || 0,
          gold_14kt: Number(data.gold_14kt) || 0,
          gold_18kt: Number(data.gold_18kt) || 0,
          gold_22kt: Number(data.gold_22kt) || 0,
          silver_price: Number(data.silver_price) || 0,
          platinum_price: Number(data.platinum_price) || 0,
          tax_percent: Number(data.tax_percent) || 0,
          diamonds: Array.isArray(data.diamonds)
            ? data.diamonds.map((row) => {
                const quality = row?.quality ?? "";
                const color = row?.color ?? "";
                const minCt = Number(row?.min_ct ?? 0);
                const maxCt = Number(row?.max_ct ?? 0);
                const rate = Number(row?.price) || 0;
                return {
                  value: `${quality}|${color}|${minCt}|${maxCt}|${rate}`,
                  label: `${quality || "-"} / ${color || "-"} (${minCt}-${maxCt} ct)`,
                  minCt,
                  maxCt,
                  rate,
                };
              })
            : [],
        });
      })
      .catch(() => {});
  };

  useEffect(() => {
    fetchProducts();
    fetchPriceSettings();
  }, []);

  const getDisplayPrice = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? `${currencySymbol}${parsed.toFixed(2)}` : "-";
  };

  const updateVariantPriceInState = (variantId, newPrice, savedInputs) => {
    setProducts((prevProducts) =>
      prevProducts.map((product) => ({
        ...product,
        variants: (product.variants || []).map((variant) =>
          Number(variant.id) === Number(variantId)
            ? {
                ...variant,
                price: Number(newPrice).toFixed(2),
                saved_inputs: savedInputs ?? variant.saved_inputs,
              }
            : variant,
        ),
      })),
    );
  };

  const getVariantById = (variantId) => {
    for (const product of products) {
      const match = (product.variants || []).find(
        (variant) => Number(variant.id) === Number(variantId),
      );
      if (match) return match;
    }
    return null;
  };

  const upsertDraft = (variantId, nextDraft) => {
    setDrafts((prev) => {
      const variant = getVariantById(variantId);
      if (!variant) return prev;
      const draftChanged = hasDraftChanged(variant, nextDraft);
      if (!draftChanged) {
        if (!(variantId in prev)) return prev;
        const clone = { ...prev };
        delete clone[variantId];
        return clone;
      }
      return { ...prev, [variantId]: nextDraft };
    });
  };

  const pendingEntries = Object.entries(drafts).filter(([variantId, draft]) => {
    const variant = getVariantById(variantId);
    return variant ? hasDraftChanged(variant, draft) : false;
  });

  const pendingCount = pendingEntries.length;

  const handleSaveAll = async () => {
    if (!pendingCount || savingAll) return;
    setSavingAll(true);
    setMessage("");
    let successCount = 0;
    let failureCount = 0;
    const nextDrafts = { ...drafts };

    for (const [variantId, draft] of pendingEntries) {
      try {
        const response = await fetch(`/api/product-variant-price${window.location.search ?? ""}`, {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
          },
          credentials: "same-origin",
          body: JSON.stringify({
            variant_id: Number(variantId),
            price: Number(draft.price) || 0,
            metal_type: draft.metal_type,
            gold_karat: draft.gold_karat,
            metal_weight: Number(draft.metal_weight) || 0,
            diamond_quality_value: draft.diamond_quality_value || "",
            diamond_weight: Number(draft.diamond_weight) || 0,
            making_charge: Number(draft.making_charge) || 0,
          }),
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
          throw new Error(payload.message || "Could not save variant price.");
        }

        updateVariantPriceInState(Number(variantId), Number(draft.price), payload.saved_inputs);
        delete nextDrafts[variantId];
        successCount += 1;
      } catch (error) {
        failureCount += 1;
      }
    }

    setDrafts(nextDrafts);
    if (failureCount > 0) {
      setMessageType("error");
      setMessage(
        successCount > 0
          ? `${successCount} variant(s) saved, ${failureCount} failed.`
          : "Failed to save changes. Please try again.",
      );
    } else {
      setMessageType("success");
      setMessage(`${successCount} variant(s) saved successfully.`);
    }
    setSavingAll(false);
  };

  const handleDiscardAll = () => {
    if (savingAll || pendingCount === 0) return;
    setDrafts({});
    setMessageType("success");
    setMessage("All pending changes discarded.");
  };

  const downloadTextFile = (filename, text, mimeType = "text/plain;charset=utf-8") => {
    const blob = new Blob([text], { type: mimeType });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
  };

  const escapeCsv = (value) => {
    const raw = String(value ?? "");
    if (/[",\n\r]/.test(raw)) {
      return `"${raw.replace(/"/g, '""')}"`;
    }
    return raw;
  };

  const buildExcelCsvHeader = () =>
    "productId,sku,productName,attribute,metalType,goldKarat,diamondQuality,diamondColor,metalWeight,diamondCaratWeight,makingCharge,price";

  const handleExportCsv = () => {
    if (!products.length) return;
    const lines = [buildExcelCsvHeader()];

    for (const product of products) {
      for (const variant of product.variants || []) {
        if (!variant?.id) continue;
        const saved = variant?.saved_inputs || {};
        const diamondValue = String(saved.diamond_quality_value || "");
        const [quality = "", color = ""] = diamondValue.split("|");

        const row = [
          `gid://shopify/ProductVariant/${variant.id}`,
          variant.sku || "",
          product.title || "",
          variant.title || "Default Variant",
          saved.metal_type || "gold",
          saved.gold_karat || "10kt",
          quality,
          color,
          saved.metal_weight ?? 0,
          saved.diamond_weight ?? 0,
          saved.making_charge ?? 0,
          variant.price ?? 0,
        ];

        lines.push(row.map(escapeCsv).join(","));
      }
    }
    const csvText = `${lines.join("\n")}\n`;
    downloadTextFile(`variant-prices-${Date.now()}.csv`, csvText, "text/csv;charset=utf-8");
  };

  const handleDownloadSampleCsv = () => {
    const lines = [buildExcelCsvHeader()];
    const sample = [
      "gid://shopify/ProductVariant/1234567890",
      "SKU-001",
      "Sample Product",
      "10 / Gold",
      "gold",
      "10",
      "VVS-VS",
      "EF",
      "12.3",
      "1.2",
      "1000",
      "0",
    ];
    lines.push(sample.map(escapeCsv).join(","));
    const csvText = `${lines.join("\n")}\n`;
    downloadTextFile("product-price-sample.csv", csvText, "text/csv;charset=utf-8");
  };

  const parseCsv = (csvText) => {
    const rows = [];
    let row = [];
    let field = "";
    let inQuotes = false;

    for (let i = 0; i < csvText.length; i++) {
      const ch = csvText[i];
      const next = csvText[i + 1];

      if (ch === '"' && inQuotes && next === '"') {
        // Escaped quote
        field += '"';
        i++;
        continue;
      }

      if (ch === '"') {
        inQuotes = !inQuotes;
        continue;
      }

      if (ch === "," && !inQuotes) {
        row.push(field.trim());
        field = "";
        continue;
      }

      if ((ch === "\n" || ch === "\r") && !inQuotes) {
        if (ch === "\r" && next === "\n") i++;
        if (field.length > 0 || row.length > 0) {
          row.push(field.trim());
          rows.push(row);
        }
        row = [];
        field = "";
        continue;
      }

      field += ch;
    }

    if (field.length > 0 || row.length > 0) {
      row.push(field.trim());
      rows.push(row);
    }

    return rows.filter((r) => r.length > 0);
  };

  const sanitizeVariantId = (value) => {
    const asStr = String(value ?? "").trim();
    const digits = asStr.replace(/[^\d]/g, "");
    const num = Number(digits);
    return Number.isFinite(num) ? num : null;
  };

  const sanitizePrice = (value) => {
    const asStr = String(value ?? "").trim();
    const cleaned = asStr.replace(/[^0-9.\-]/g, "");
    const num = Number(cleaned);
    return Number.isFinite(num) ? num : null;
  };

  const sanitizeNumber = (value, fallback = 0) => {
    const asStr = String(value ?? "").trim();
    if (!asStr) return fallback;
    const cleaned = asStr.replace(/[^0-9.\-]/g, "");
    const num = Number(cleaned);
    return Number.isFinite(num) ? num : fallback;
  };

  const normalizeKaratForPayload = (value) => {
    const txt = String(value ?? "")
      .trim()
      .toLowerCase();
    if (!txt) return "10kt";
    if (txt.includes("10")) return "10kt";
    if (txt.includes("14")) return "14kt";
    if (txt.includes("18")) return "18kt";
    if (txt.includes("22")) return "22kt";
    return txt.endsWith("kt") ? txt : `${txt}kt`;
  };

  const resolveDiamondSelection = (quality, color, diamondWeight) => {
    const q = String(quality ?? "")
      .trim()
      .toLowerCase();
    const c = String(color ?? "")
      .trim()
      .toLowerCase();
    const weightCt = Number(diamondWeight) || 0;

    const options = priceSettings?.diamonds || [];
    if (!options.length) {
      return { value: "", rate: 0 };
    }

    const match = options.find((option) => {
      const [optQuality = "", optColor = "", minCtRaw = "0", maxCtRaw = "0", rateRaw = "0"] = String(
        option.value || "",
      ).split("|");

      if (q && optQuality.trim().toLowerCase() !== q) return false;
      if (c && optColor.trim().toLowerCase() !== c) return false;

      const minCt = Number(minCtRaw) || 0;
      const maxCtNum = Number(maxCtRaw);
      const maxCt = Number.isFinite(maxCtNum) && maxCtNum > 0 ? maxCtNum : Number.MAX_VALUE;
      const withinRange = weightCt >= minCt && weightCt <= maxCt;
      if (!withinRange) return false;

      const rate = Number(rateRaw) || Number(option.rate) || 0;
      return Number.isFinite(rate);
    });

    if (!match) return { value: "", rate: 0 };
    return {
      value: String(match.value || ""),
      rate: Number(match.rate) || Number(String(match.value || "").split("|")[4]) || 0,
    };
  };

  const handleImportCsv = async (file) => {
    if (!file) return;
    setCsvImporting(true);
    setMessage("");

    try {
      const text = await file.text();
      const rows = parseCsv(text);
      if (rows.length < 2) {
        throw new Error("CSV should have header + at least 1 row.");
      }

      const header = (rows[0] || []).map((h) =>
        String(h ?? "")
          .trim()
          .toLowerCase(),
      );
      const headerIndex = (name) => header.findIndex((h) => h === name);
      const variantIdIndex =
        headerIndex("variant_id") !== -1
          ? headerIndex("variant_id")
          : headerIndex("variantid") !== -1
            ? headerIndex("variantid")
            : headerIndex("id") !== -1
              ? headerIndex("id")
              : headerIndex("productid");
      const priceIndex =
        headerIndex("price") !== -1
          ? headerIndex("price")
          : headerIndex("computed_total") !== -1
            ? headerIndex("computed_total")
            : headerIndex("total");
      const skuIndex = headerIndex("sku");
      const productNameIndex = headerIndex("productname");
      const attributeIndex = headerIndex("attribute");
      const metalTypeIndex = headerIndex("metaltype");
      const goldKaratIndex = headerIndex("goldkarat");
      const diamondQualityIndex = headerIndex("diamondquality");
      const diamondColorIndex = headerIndex("diamondcolor");
      const metalWeightIndex = headerIndex("metalweight");
      const diamondCaratWeightIndex = headerIndex("diamondcaratweight");
      const makingChargeIndex = headerIndex("makingcharge");

      const hasBasicHeaderColumns =
        variantIdIndex !== undefined &&
        variantIdIndex !== -1;

      const hasDetailedFields =
        metalTypeIndex !== -1 ||
        goldKaratIndex !== -1 ||
        metalWeightIndex !== -1 ||
        diamondQualityIndex !== -1 ||
        diamondColorIndex !== -1 ||
        diamondCaratWeightIndex !== -1 ||
        makingChargeIndex !== -1;

      let importRows = [];
      if (hasBasicHeaderColumns) {
        importRows = rows.slice(1).map((r) => ({
          variant_id: sanitizeVariantId(r[variantIdIndex]),
          price: priceIndex !== -1 ? sanitizePrice(r[priceIndex]) : null,
          sku: skuIndex !== -1 ? String(r[skuIndex] ?? "").trim() : "",
          productName: productNameIndex !== -1 ? String(r[productNameIndex] ?? "").trim() : "",
          attribute: attributeIndex !== -1 ? String(r[attributeIndex] ?? "").trim() : "",
          metal_type: metalTypeIndex !== -1 ? String(r[metalTypeIndex] ?? "").trim().toLowerCase() : "gold",
          gold_karat:
            goldKaratIndex !== -1 ? normalizeKaratForPayload(r[goldKaratIndex]) : "10kt",
          diamond_quality:
            diamondQualityIndex !== -1 ? String(r[diamondQualityIndex] ?? "").trim() : "",
          diamond_color: diamondColorIndex !== -1 ? String(r[diamondColorIndex] ?? "").trim() : "",
          metal_weight: metalWeightIndex !== -1 ? sanitizeNumber(r[metalWeightIndex], 0) : 0,
          diamond_weight:
            diamondCaratWeightIndex !== -1 ? sanitizeNumber(r[diamondCaratWeightIndex], 0) : 0,
          making_charge: makingChargeIndex !== -1 ? sanitizeNumber(r[makingChargeIndex], 0) : 0,
        }));
      } else {
        // No header: treat first 2 columns as [variant_id, price]
        importRows = rows.slice(0).map((r) => ({
          variant_id: sanitizeVariantId(r[0]),
          price: sanitizePrice(r[1]),
          metal_type: "gold",
          gold_karat: "10kt",
          diamond_quality: "",
          diamond_color: "",
          metal_weight: 0,
          diamond_weight: 0,
          making_charge: 0,
        }));
      }

      importRows = importRows
        .map((row) => {
          if (row.variant_id === null) return null;

          if (!hasDetailedFields) {
            return row.price !== null ? row : null;
          }

          const metalType = ["gold", "silver", "platinum"].includes(row.metal_type)
            ? row.metal_type
            : "gold";

          const karat = normalizeKaratForPayload(row.gold_karat);
          const metalRate =
            metalType === "gold"
              ? Number(priceSettings?.[`gold_${karat}`] || 0)
              : metalType === "silver"
                ? Number(priceSettings?.silver_price || 0)
                : Number(priceSettings?.platinum_price || 0);

          const diamondSelection = resolveDiamondSelection(
            row.diamond_quality,
            row.diamond_color,
            row.diamond_weight,
          );

          const subtotal =
            row.metal_weight * metalRate +
            row.diamond_weight * Number(diamondSelection.rate || 0) +
            row.making_charge;
          const taxPercent = Number(priceSettings?.tax_percent || 0);
          const total = subtotal + (subtotal * taxPercent) / 100;

          return {
            ...row,
            metal_type: metalType,
            gold_karat: karat,
            diamond_quality_value: diamondSelection.value || "",
            price: Number(total.toFixed(2)),
          };
        })
        .filter((r) => r !== null && r.variant_id !== null && r.price !== null);

      if (!importRows.length) {
        throw new Error(
          "No valid rows found. Use `variant_id,price` or Excel format with `productId,...` columns.",
        );
      }

      let successCount = 0;
      let failureCount = 0;
      const failedRows = [];

      for (const row of importRows) {
        try {
          const endpoint = hasDetailedFields
            ? `/api/product-variant-price${window.location.search ?? ""}`
            : `/api/product-variant-price-csv${window.location.search ?? ""}`;

          const payload = hasDetailedFields
            ? {
                variant_id: row.variant_id,
                price: row.price,
                metal_type: row.metal_type || "gold",
                gold_karat: row.gold_karat || "10kt",
                metal_weight: Number(row.metal_weight) || 0,
                diamond_quality_value: row.diamond_quality_value || "",
                diamond_weight: Number(row.diamond_weight) || 0,
                making_charge: Number(row.making_charge) || 0,
              }
            : {
                variant_id: row.variant_id,
                price: row.price,
              };

          const response = await fetch(endpoint, {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
              Accept: "application/json",
              "X-Requested-With": "XMLHttpRequest",
            },
            credentials: "same-origin",
            body: JSON.stringify(payload),
          });

          const responsePayload = await response.json().catch(() => ({}));
          if (!response.ok) {
            throw new Error(responsePayload?.message || "Save failed");
          }
          successCount += 1;
        } catch (e) {
          failureCount += 1;
          failedRows.push({
            ...row,
            error: e?.message || "Save failed",
          });
        }
      }

      setDrafts({});
      fetchProducts();

      if (failureCount > 0) {
        setMessageType("error");
        setMessage(
          successCount > 0
            ? `${successCount} row(s) updated, ${failureCount} failed.`
            : "Failed to import CSV.",
        );

        if (failedRows.length > 0) {
          const failedLines = [
            `${buildExcelCsvHeader()},error`,
            ...failedRows.map((row) =>
              [
                `gid://shopify/ProductVariant/${row.variant_id}`,
                row.sku || "",
                row.productName || "",
                row.attribute || "",
                row.metal_type || "",
                row.gold_karat || "",
                row.diamond_quality || "",
                row.diamond_color || "",
                row.metal_weight ?? "",
                row.diamond_weight ?? "",
                row.making_charge ?? "",
                row.price ?? "",
                row.error || "",
              ]
                .map(escapeCsv)
                .join(","),
            ),
          ];
          downloadTextFile(
            `product-price-import-errors-${Date.now()}.csv`,
            `${failedLines.join("\n")}\n`,
            "text/csv;charset=utf-8",
          );
        }
      } else {
        setMessageType("success");
        setMessage(`${successCount} row(s) updated successfully.`);
      }
    } catch (error) {
      setMessageType("error");
      setMessage(error?.message || "CSV import failed.");
    } finally {
      setCsvImporting(false);
      if (importFileRef.current) importFileRef.current.value = "";
    }
  };

  return (
    <div className="dashboard-page">
      <div className="set-price-hero">
        <div className="set-price-hero__icon" aria-hidden="true">
          PB
        </div>
        <div>
          <h1 className="set-price-hero__title">Product Price</h1>
          <p className="set-price-hero__subtitle">Manage product and variant pricing</p>
        </div>
      </div>
      {pendingCount > 0 ? (
        <div className="unsaved-changes-bar">
          <div className="unsaved-changes-bar__text">Unsaved changes ({pendingCount})</div>
          <div className="unsaved-changes-bar__actions">
            <button
              type="button"
              className="app-btn app-btn--ghost app-btn--lg unsaved-changes-bar__discard"
              disabled={savingAll}
              onClick={handleDiscardAll}
            >
              Discard
            </button>
            <button
              type="button"
              className="app-btn app-btn--primary app-btn--lg unsaved-changes-bar__save"
              disabled={savingAll}
              onClick={handleSaveAll}
            >
              {savingAll ? "Saving..." : "Save"}
            </button>
          </div>
        </div>
      ) : null}
      {pendingCount > 0 ? <div className="unsaved-changes-bar-spacer" /> : null}
      <div className="product-price-page">
        <Page>
          {message ? <div className={`app-alert app-alert--${messageType}`}>{message}</div> : null}
          {products.length === 0 ? (
            <Card>
              <div className="product-empty-state">
                <p>No products found yet.</p>
              </div>
            </Card>
          ) : null}

          {products.length > 0 ? (
            <Card>
              <div className="csv-actions">
                <button
                  type="button"
                  className="app-btn app-btn--primary"
                  onClick={handleDownloadSampleCsv}
                >
                  Download Sample CSV
                </button>

                <button
                  type="button"
                  className="app-btn app-btn--primary"
                  disabled={!products.length}
                  onClick={handleExportCsv}
                >
                  Export CSV
                </button>

                <input
                  ref={importFileRef}
                  type="file"
                  accept=".csv,text/csv"
                  style={{ display: "none" }}
                  onChange={(e) => handleImportCsv(e.target.files?.[0] || null)}
                />

                <button
                  type="button"
                  className="app-btn app-btn--primary"
                  disabled={csvImporting}
                  onClick={() => importFileRef.current?.click()}
                >
                  {csvImporting ? "Importing..." : "Import CSV"}
                </button>
                
              </div>
              <div className="product-list-table-wrap">
                <table className="product-list-table">
                  <thead>
                    <tr>
                      <th>S.No.</th>
                      <th>Product Name</th>
                      <th>Product Type</th>
                      <th>Variants</th>
                      <th className="product-list-table__action">Action</th>
                    </tr>
                  </thead>
                  <tbody>
                    {products.map((product, index) => (
                      <Fragment key={product.id}>
                        <tr className="product-row">
                          <td>{index + 1}</td>
                          <td>{product.title}</td>
                          <td>{product.product_type || "-"}</td>
                          <td>{Array.isArray(product.variants) ? product.variants.length : 0}</td>
                          <td className="product-list-table__action">
                            <button
                              type="button"
                              className="app-btn app-btn--primary"
                              onClick={() => {
                                const willOpen = openProductId !== product.id;
                                setOpenProductId(willOpen ? product.id : null);
                                setOpenVariantId(null);
                              }}
                            >
                              {openProductId === product.id ? "Close" : "View Variants"}
                            </button>
                          </td>
                        </tr>
                        {openProductId === product.id ? (
                          <tr className="variant-table-row">
                            <td colSpan={5}>
                              <div className="variant-table-wrap">
                                <table className="variant-list-table">
                                  <thead>
                                    <tr>
                                      <th>Variant Name</th>
                                      <th>SKU</th>
                                      <th>Price</th>
                                      <th className="product-list-table__action">Edit</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    {(product.variants || []).map((variant) => (
                                      <Fragment key={variant.id}>
                                        <tr>
                                          <td>{variant.title || "Default Variant"}</td>
                                          <td>{variant.sku || "-"}</td>
                                          <td>
                                            {getDisplayPrice(
                                              drafts[variant.id]?.price ?? variant.price,
                                            )}
                                          </td>
                                          <td className="product-list-table__action">
                                            <button
                                              type="button"
                                              className="app-btn app-btn--primary"
                                              onClick={() =>
                                                setOpenVariantId(openVariantId === variant.id ? null : variant.id)
                                              }
                                            >
                                              {openVariantId === variant.id ? "Close" : "Edit"}
                                            </button>
                                          </td>
                                        </tr>
                                        {openVariantId === variant.id ? (
                                          <tr>
                                            <td colSpan={4}>
                                              <EditForm
                                                product={product}
                                                variant={variant}
                                                currencySymbol={currencySymbol}
                                                priceSettings={priceSettings}
                                                draft={drafts[variant.id] || normalizeVariantDraft(variant)}
                                                onDraftChange={(nextDraft) =>
                                                  upsertDraft(variant.id, nextDraft)
                                                }
                                                disabled={savingAll}
                                              />
                                            </td>
                                          </tr>
                                        ) : null}
                                      </Fragment>
                                    ))}
                                  </tbody>
                                </table>
                              </div>
                            </td>
                          </tr>
                        ) : null}
                      </Fragment>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          ) : null}
        </Page>
      </div>
    </div>
  );
}

function EditForm({
  product,
  variant,
  currencySymbol,
  priceSettings,
  draft,
  onDraftChange,
  disabled,
}) {
  const metalType = draft.metal_type || "gold";
  const goldKarat = draft.gold_karat || "10kt";
  const diamondQuality = draft.diamond_quality_value || "";
  const parsedWeight = Number(draft.metal_weight) || 0;
  const parsedMaking = Number(draft.making_charge) || 0;
  const parsedDiamond = Number(draft.diamond_weight) || 0;
  const variantPrice = Number(variant?.price) || 0;

  const metalRate =
    metalType === "gold"
      ? Number(priceSettings?.[`gold_${goldKarat}`] || 0)
      : metalType === "silver"
        ? Number(priceSettings?.silver_price || 0)
        : Number(priceSettings?.platinum_price || 0);

  const selectedDiamond = (priceSettings?.diamonds || []).find(
    (diamondOption) => diamondOption.value === diamondQuality,
  );
  const diamondRate = Number(selectedDiamond?.rate || 0);

  useEffect(() => {
    if (diamondQuality) return; // If already set (e.g. from saved data), don't override.
    const options = priceSettings?.diamonds || [];
    if (!options.length) return;

    const weightCt = Number(parsedDiamond);
    if (!Number.isFinite(weightCt) || weightCt <= 0) return;

    const matched = options.find((option) => {
      const min = Number.isFinite(option.minCt) ? option.minCt : 0;
      const max = Number.isFinite(option.maxCt) && option.maxCt > 0 ? option.maxCt : Number.MAX_VALUE;
      return weightCt >= min && weightCt <= max;
    });

    if (matched && matched.value !== diamondQuality) {
      onDraftChange?.({
        ...draft,
        diamond_quality_value: matched.value,
      });
    }
  }, [draft, diamondQuality, onDraftChange, parsedDiamond, priceSettings]);

  const metalPrice = parsedWeight * metalRate;
  const diamondPrice = parsedDiamond * diamondRate;
  const subtotal = metalPrice + parsedMaking + diamondPrice;
  const taxPercent = Number(priceSettings?.tax_percent || 0);
  const tax = (subtotal * taxPercent) / 100;
  const total = subtotal + tax;

  const formatMoney = (value) => value.toFixed(2);

  useEffect(() => {
    if (Number(draft.price).toFixed(2) === Number(total).toFixed(2)) return;
    onDraftChange?.({
      ...draft,
      price: total,
    });
  }, [draft, onDraftChange, total]);

  return (
    <div className="product-edit-panel">
      <h4 className="product-edit-title">
        {product.title} - {variant.title || "Default Variant"}
      </h4>
      <div className="product-form-grid">
        <div className="app-field">
          <label htmlFor={`metalType-${variant.id}`}>Metal Type</label>
          <select
            id={`metalType-${variant.id}`}
            className="app-control"
            value={metalType}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                metal_type: event.target.value,
              })
            }
            disabled={disabled}
          >
            <option value="gold">Gold</option>
            <option value="silver">Silver</option>
            <option value="platinum">Platinum</option>
          </select>
        </div>
        <div className="app-field">
          <label htmlFor={`goldKarat-${variant.id}`}>Gold Karat</label>
          <select
            id={`goldKarat-${variant.id}`}
            className="app-control"
            value={goldKarat}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                gold_karat: event.target.value,
              })
            }
            disabled={disabled}
          >
            <option value="10kt">10kt</option>
            <option value="14kt">14kt</option>
            <option value="18kt">18kt</option>
            <option value="22kt">22kt</option>
          </select>
        </div>
        <div className="app-field">
          <label htmlFor={`weight-${variant.id}`}>Metal Weight (g)</label>
          <input
            id={`weight-${variant.id}`}
            className="app-control"
            type="number"
            value={draft.metal_weight}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                metal_weight: event.target.value,
              })
            }
            autoComplete="off"
            disabled={disabled}
          />
        </div>
        <div className="app-field">
          <label htmlFor={`quality-${variant.id}`}>Diamond quality-color</label>
          <select
            id={`quality-${variant.id}`}
            className="app-control"
            value={diamondQuality}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                diamond_quality_value: event.target.value,
              })
            }
            disabled={disabled}
          >
            <option value="">Select quality-color</option>
            {(priceSettings?.diamonds || []).map((diamondOption, index) => (
              <option key={`${diamondOption.value}-${index}`} value={diamondOption.value}>
                {diamondOption.label}
              </option>
            ))}
          </select>
        </div>
        <div className="app-field">
          <label htmlFor={`diamond-${variant.id}`}>Diamond Weight</label>
          <input
            id={`diamond-${variant.id}`}
            className="app-control"
            type="number"
            value={draft.diamond_weight}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                diamond_weight: event.target.value,
              })
            }
            autoComplete="off"
            disabled={disabled}
          />
        </div>
        <div className="app-field">
          <label htmlFor={`making-${variant.id}`}>Making Charge</label>
          <input
            id={`making-${variant.id}`}
            className="app-control"
            type="number"
            value={draft.making_charge}
            onChange={(event) =>
              onDraftChange?.({
                ...draft,
                making_charge: event.target.value,
              })
            }
            autoComplete="off"
            disabled={disabled}
          />
        </div>
      </div>

      <div className="price-breakup-box">
        <p>
          Metal Price: <strong>{currencySymbol}{formatMoney(metalPrice)}</strong>
        </p>
        <p>
          Metal Calculation: {parsedWeight} x {currencySymbol}
          {formatMoney(metalRate)} = <strong>{currencySymbol}{formatMoney(metalPrice)}</strong>
        </p>
        <p>
          Diamond Price: <strong>{currencySymbol}{formatMoney(diamondPrice)}</strong>
        </p>
        <p>
          Diamond Calculation: {parsedDiamond} x {currencySymbol}
          {formatMoney(diamondRate)} = <strong>{currencySymbol}{formatMoney(diamondPrice)}</strong>
        </p>
        <p>
          Making: <strong>{currencySymbol}{formatMoney(parsedMaking)}</strong>
        </p>
        <p>
          Tax ({taxPercent}%): <strong>{currencySymbol}{formatMoney(tax)}</strong>
        </p>
        <p>
          Subtotal: <strong>{currencySymbol}{formatMoney(subtotal)}</strong>
        </p>
        <p className="price-breakup-total">
          Calculated Total: <strong>{currencySymbol}{formatMoney(total)}</strong>
        </p>
        <p>
          Current Shopify Price: <strong>{currencySymbol}{formatMoney(variantPrice)}</strong>
        </p>
        <div className="price-breakup-actions">
          <small>Save using the top “Unsaved changes” bar.</small>
        </div>
      </div>
    </div>
  );
}
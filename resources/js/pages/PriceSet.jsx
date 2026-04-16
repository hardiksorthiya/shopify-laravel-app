import React, { useEffect, useState } from "react";

const getCsrfToken = () =>
  document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "";

const createEmptyDiamond = () => ({
  quality: "",
  color: "",
  min_ct: "",
  max_ct: "",
  price: "",
});

const createInitialPriceForm = () => ({
  gold_10kt: "0",
  gold_14kt: "0",
  gold_18kt: "0",
  gold_22kt: "0",
  silver_price: "0",
  platinum_price: "0",
  tax_percent: "0",
  diamonds: [createEmptyDiamond()],
});

export default function PriceSet({ apiEndpoint }) {
  const [form, setForm] = useState(createInitialPriceForm());
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [success, setSuccess] = useState("");
  const [error, setError] = useState("");
  const [currencySymbol, setCurrencySymbol] = useState("$");
  const endpointWithQuery = `${apiEndpoint || "/api/price-settings"}${window.location.search ?? ""}`;

  useEffect(() => {
    const controller = new AbortController();

    const loadData = async () => {
      try {
        const timeoutId = window.setTimeout(() => controller.abort(), 15000);
        const response = await fetch(endpointWithQuery, {
          headers: {
            Accept: "application/json",
          },
          credentials: "same-origin",
          signal: controller.signal,
        });
        window.clearTimeout(timeoutId);

        if (!response.ok) {
          throw new Error(`Failed to load price settings (${response.status}).`);
        }

        const data = await response.json().catch(() => null);
        if (!data) {
          throw new Error("Invalid server response for price settings.");
        }

        const normalizedDiamonds =
          data.diamonds && data.diamonds.length > 0 ? data.diamonds : [createEmptyDiamond()];

        setForm({
          gold_10kt: data.gold_10kt ?? "0",
          gold_14kt: data.gold_14kt ?? "0",
          gold_18kt: data.gold_18kt ?? "0",
          gold_22kt: data.gold_22kt ?? "0",
          silver_price: data.silver_price ?? "0",
          platinum_price: data.platinum_price ?? "0",
          tax_percent: data.tax_percent ?? "0",
          diamonds: normalizedDiamonds,
        });
        setCurrencySymbol(data.currency_symbol ?? "$");
      } catch (loadError) {
        const isAbort = loadError?.name === "AbortError";
        setError(
          isAbort
            ? "Loading timed out. Please refresh and try again."
            : (loadError?.message ?? "Failed to load price settings."),
        );
      } finally {
        setLoading(false);
      }
    };

    loadData();
    return () => controller.abort();
  }, [endpointWithQuery]);

  const updateField = (key, value) => {
    setForm((prev) => ({ ...prev, [key]: value }));
  };

  const updateDiamondField = (index, key, value) => {
    setForm((prev) => {
      const diamonds = [...prev.diamonds];
      diamonds[index] = { ...diamonds[index], [key]: value };
      return { ...prev, diamonds };
    });
  };

  const addDiamondRow = () => {
    setForm((prev) => ({ ...prev, diamonds: [...prev.diamonds, createEmptyDiamond()] }));
  };

  const removeDiamondRow = (index) => {
    setForm((prev) => {
      const diamonds = prev.diamonds.filter((_, i) => i !== index);
      return { ...prev, diamonds: diamonds.length ? diamonds : [createEmptyDiamond()] };
    });
  };

  const submitForm = async (event) => {
    event.preventDefault();
    setSaving(true);
    setSuccess("");
    setError("");

    try {
      const response = await fetch(endpointWithQuery, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Accept: "application/json",
          "X-Requested-With": "XMLHttpRequest",
          "X-CSRF-TOKEN": getCsrfToken(),
        },
        credentials: "same-origin",
        body: JSON.stringify({
          ...form,
          _token: getCsrfToken(),
        }),
      });

      if (!response.ok) {
        if (response.status === 422) {
          const errorPayload = await response.json();
          const message = Object.values(errorPayload.errors ?? {})
            .flat()
            .join(" ");
          throw new Error(message || "Validation failed.");
        }

        if (response.status === 419) {
          throw new Error("Session expired. Please refresh and try again.");
        }

        throw new Error("Failed to save price settings.");
      }

      const payload = await response.json();
      setSuccess(payload.message ?? "Price settings updated successfully.");
    } catch (saveError) {
      setError(saveError.message);
    } finally {
      setSaving(false);
    }
  };

  if (loading) {
    return <div className="set-price-loading">Loading price settings...</div>;
  }

  return (
    <div className="set-price-page">
      <div className="set-price-hero">
        <div className="set-price-hero__icon" aria-hidden="true">
          PB
        </div>
        <div>
          <h1 className="set-price-hero__title">Price Breakup Pro</h1>
          <p className="set-price-hero__subtitle">Price distribution</p>
        </div>
      </div>

      <div className="set-price-header">
        <p className="set-price-subtitle">
          Set your metal prices per unit (e.g. per gram). Gold uses karat tiers; silver and
          platinum use a single rate.
        </p>
      </div>

      <div className="set-price-content">
        {success ? <div className="app-alert app-alert--success">{success}</div> : null}
        {error ? <div className="app-alert app-alert--error">{error}</div> : null}

        <form onSubmit={submitForm} className="set-price-form">
          <div className="set-price-section set-panel">
            <h2 className="section-title">Metals</h2>

            <div className="metal-card metal-card--gold">
              <h3 className="section-subtitle">Gold</h3>
              <div className="product-form-grid">
                <Field
                  label={`10kt (${currencySymbol})`}
                  value={form.gold_10kt}
                  onChange={(value) => updateField("gold_10kt", value)}
                />
                <Field
                  label={`14kt (${currencySymbol})`}
                  value={form.gold_14kt}
                  onChange={(value) => updateField("gold_14kt", value)}
                />
                <Field
                  label={`18kt (${currencySymbol})`}
                  value={form.gold_18kt}
                  onChange={(value) => updateField("gold_18kt", value)}
                />
                <Field
                  label={`22kt (${currencySymbol})`}
                  value={form.gold_22kt}
                  onChange={(value) => updateField("gold_22kt", value)}
                />
              </div>
            </div>

            <div className="product-form-grid">
              <div className="metal-card metal-card--silver">
                <h3 className="section-subtitle">Silver</h3>
                <Field
                  label={`Silver price (${currencySymbol})`}
                  value={form.silver_price}
                  onChange={(value) => updateField("silver_price", value)}
                />
              </div>
              <div className="metal-card metal-card--platinum">
                <h3 className="section-subtitle">Platinum</h3>
                <Field
                  label={`Platinum price (${currencySymbol})`}
                  value={form.platinum_price}
                  onChange={(value) => updateField("platinum_price", value)}
                />
              </div>
            </div>
          </div>

          <div className="set-price-section set-panel">
            <div className="panel-header">
              <h2 className="section-title">Diamond Price</h2>
              <button type="button" className="app-btn app-btn--primary" onClick={addDiamondRow}>
                Add Diamond Row
              </button>
            </div>

            <div className="diamond-grid">
              {form.diamonds.map((diamond, index) => (
                <div className="diamond-row-card" key={`diamond-${index}`}>
                  <div className="product-form-grid">
                    <Field
                      label="Quality"
                      type="text"
                      value={diamond.quality ?? ""}
                      onChange={(value) => updateDiamondField(index, "quality", value)}
                    />
                    <Field
                      label="Color"
                      type="text"
                      value={diamond.color ?? ""}
                      onChange={(value) => updateDiamondField(index, "color", value)}
                    />
                    <Field
                      label="Min Range (ct)"
                      value={diamond.min_ct ?? ""}
                      onChange={(value) => updateDiamondField(index, "min_ct", value)}
                    />
                    <Field
                      label="Max Range (ct)"
                      value={diamond.max_ct ?? ""}
                      onChange={(value) => updateDiamondField(index, "max_ct", value)}
                    />
                    <Field
                      label={`Price (${currencySymbol})`}
                      value={diamond.price ?? ""}
                      onChange={(value) => updateDiamondField(index, "price", value)}
                    />
                  </div>
                  <div className="diamond-row-actions">
                    <button
                      type="button"
                      className="app-btn app-btn--danger"
                      onClick={() => removeDiamondRow(index)}
                    >
                      Remove
                    </button>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="set-price-section set-panel">
            <h2 className="section-title">Tax</h2>
            <div className="tax-field-wrap">
              <Field
                label="Tax Percent (%)"
                value={form.tax_percent}
                onChange={(value) => updateField("tax_percent", value)}
              />
            </div>
          </div>

          <div className="save-price-btn">
            <button type="submit" className="app-btn app-btn--primary app-btn--lg" disabled={saving}>
              {saving ? "Saving..." : "Save Price Set"}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

function Field({ label, value, onChange, type = "number" }) {
  return (
    <div className="app-field">
      <label>{label}</label>
      <input
        className="app-control"
        type={type}
        step={type === "number" ? "0.01" : undefined}
        autoComplete="off"
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </div>
  );
}

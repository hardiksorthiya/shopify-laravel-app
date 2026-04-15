import React, { useEffect, useState } from "react";
import {
  Banner,
  Box,
  Card,
  Button,
  Divider,
  InlineGrid,
  InlineStack,
  Page,
  Text,
  TextField,
} from "@shopify/polaris";

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
  const endpointWithQuery = `${apiEndpoint}${window.location.search ?? ""}`;

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

      <Page>
        <div className="set-price-content">
          {success ? (
            <Box paddingBlockEnd="400">
              <Banner tone="success">{success}</Banner>
            </Box>
          ) : null}
          {error ? (
            <Box paddingBlockEnd="400">
              <Banner tone="critical">{error}</Banner>
            </Box>
          ) : null}

          <form onSubmit={submitForm} className="set-price-form">
            <div className="set-price-section">
              <Card>
                <Box padding="400">
                  <Text as="h2" variant="headingMd">
                    Metals
                  </Text>

                  <div className="metal-card metal-card--gold">
                    <Text as="h3" variant="headingMd">
                      Gold
                    </Text>
                    <Text as="p" variant="bodySm" tone="subdued">
                      Price per unit for each karat
                    </Text>
                    <Box paddingBlockStart="300">
                      <InlineGrid gap="300" columns={{ xs: 1, md: 4 }}>
                        <TextField
                          label="10kt"
                          prefix={currencySymbol}
                          type="number"
                          step={0.01}
                          autoComplete="off"
                          value={form.gold_10kt}
                          onChange={(value) => updateField("gold_10kt", value)}
                        />
                        <TextField
                          label="14kt"
                          prefix={currencySymbol}
                          type="number"
                          step={0.01}
                          autoComplete="off"
                          value={form.gold_14kt}
                          onChange={(value) => updateField("gold_14kt", value)}
                        />
                        <TextField
                          label="18kt"
                          prefix={currencySymbol}
                          type="number"
                          step={0.01}
                          autoComplete="off"
                          value={form.gold_18kt}
                          onChange={(value) => updateField("gold_18kt", value)}
                        />
                        <TextField
                          label="22kt"
                          prefix={currencySymbol}
                          type="number"
                          step={0.01}
                          autoComplete="off"
                          value={form.gold_22kt}
                          onChange={(value) => updateField("gold_22kt", value)}
                        />
                      </InlineGrid>
                    </Box>
                  </div>

                  <div className="metal-card metal-card--silver">
                    <Text as="h3" variant="headingMd">
                      Silver
                    </Text>
                    <Text as="p" variant="bodySm" tone="subdued">
                      Price per unit
                    </Text>
                    <Box paddingBlockStart="300">
                      <TextField
                        label="Silver price"
                        labelHidden
                        prefix={currencySymbol}
                        type="number"
                        step={0.01}
                        autoComplete="off"
                        value={form.silver_price}
                        onChange={(value) => updateField("silver_price", value)}
                      />
                    </Box>
                  </div>

                  <div className="metal-card metal-card--platinum">
                    <Text as="h3" variant="headingMd">
                      Platinum
                    </Text>
                    <Text as="p" variant="bodySm" tone="subdued">
                      Price per unit
                    </Text>
                    <Box paddingBlockStart="300">
                      <TextField
                        label="Platinum price"
                        labelHidden
                        prefix={currencySymbol}
                        type="number"
                        step={0.01}
                        autoComplete="off"
                        value={form.platinum_price}
                        onChange={(value) => updateField("platinum_price", value)}
                      />
                    </Box>
                  </div>
                </Box>
              </Card>
            </div>

            <Box paddingBlockStart="400">
              <div className="set-price-section">
                <Card>
                  <Box padding="400">
                    <InlineStack align="space-between" blockAlign="center">
                      <Text as="h2" variant="headingMd">
                        Diamond Price
                      </Text>
                      <Button onClick={addDiamondRow}>Add Diamond Row</Button>
                    </InlineStack>

                    <Box paddingBlockStart="300">
                      {form.diamonds.map((diamond, index) => (
                        <Box key={`diamond-${index}`} paddingBlockEnd="300">
                          <div className="diamond-row-card">
                            <InlineGrid gap="300" columns={{ xs: 1, md: 5 }}>
                              <TextField
                                label="Quality"
                                autoComplete="off"
                                value={diamond.quality ?? ""}
                                onChange={(value) => updateDiamondField(index, "quality", value)}
                              />
                              <TextField
                                label="Color"
                                autoComplete="off"
                                value={diamond.color ?? ""}
                                onChange={(value) => updateDiamondField(index, "color", value)}
                              />
                              <TextField
                                label="Min Range (ct)"
                                type="number"
                                step={0.01}
                                autoComplete="off"
                                value={diamond.min_ct ?? ""}
                                onChange={(value) => updateDiamondField(index, "min_ct", value)}
                              />
                              <TextField
                                label="Max Range (ct)"
                                type="number"
                                step={0.01}
                                autoComplete="off"
                                value={diamond.max_ct ?? ""}
                                onChange={(value) => updateDiamondField(index, "max_ct", value)}
                              />
                              <TextField
                                label="Price"
                                prefix={currencySymbol}
                                type="number"
                                step={0.01}
                                autoComplete="off"
                                value={diamond.price ?? ""}
                                onChange={(value) => updateDiamondField(index, "price", value)}
                              />
                            </InlineGrid>
                            <Box paddingBlockStart="200">
                              <Button tone="critical" onClick={() => removeDiamondRow(index)}>
                                Remove
                              </Button>
                            </Box>
                          </div>
                          {index !== form.diamonds.length - 1 ? (
                            <Box paddingBlockStart="300">
                              <Divider />
                            </Box>
                          ) : null}
                        </Box>
                      ))}
                    </Box>
                  </Box>
                </Card>
              </div>
            </Box>

            <Box paddingBlockStart="400">
              <div className="set-price-section">
                <Card>
                  <Box padding="400">
                    <Text as="h2" variant="headingMd">
                      Tax
                    </Text>
                    <Box paddingBlockStart="300">
                      <TextField
                        label="Tax Percent (%)"
                        suffix="%"
                        type="number"
                        step={0.01}
                        autoComplete="off"
                        value={form.tax_percent}
                        onChange={(value) => updateField("tax_percent", value)}
                      />
                    </Box>
                  </Box>
                </Card>
              </div>
            </Box>

            <Box paddingBlockStart="400" paddingBlockEnd="400">
              <div className="save-price-btn">
                <Button variant="primary" submit loading={saving}>
                  Save Price Set
                </Button>
              </div>
            </Box>
          </form>
        </div>
      </Page>
    </div>
  );
}

import { useEffect, useMemo, useState } from "react";

const formatLimit = (limit) => (limit === null ? "Unlimited" : String(limit));

export default function DashboardPage() {
  const [summary, setSummary] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const query = useMemo(() => window.location.search || "", []);

  useEffect(() => {
    const loadSummary = async () => {
      try {
        const response = await fetch(`/api/dashboard-summary${query}`, {
          headers: { Accept: "application/json" },
          credentials: "same-origin",
        });
        if (!response.ok) throw new Error("Unable to load dashboard data.");
        const payload = await response.json();
        setSummary(payload);
      } catch (loadError) {
        setError(loadError?.message || "Unable to load dashboard data.");
      } finally {
        setLoading(false);
      }
    };

    loadSummary();
  }, [query]);

  const activePlanName = summary?.plan_details?.name || String(summary?.plan || "free").toUpperCase();
  const productCount = Number(summary?.product_count || 0);
  const productLimit = summary?.product_limit ?? null;
  const usagePercent = Number(summary?.usage_percent || 0);

  return (
    <div className="dashboard-page">
      <div className="set-price-hero">
        <div className="set-price-hero__icon" aria-hidden="true">
          MB
        </div>
        <div>
          <h1 className="set-price-hero__title">MetalBreak Dashboard</h1>
          <p className="set-price-hero__subtitle">Billing, plan usage, and theme setup in one place</p>
        </div>
      </div>

      <div className="dashboard-page-content">
        {error ? <div className="app-alert app-alert--error">{error}</div> : null}
        {loading ? <div className="set-price-loading">Loading dashboard...</div> : null}

        {!loading && summary ? (
          <div className="dashboard-grid">
            <div className="dashboard-card">
              <h3>Active Plane</h3>
              <p className="dashboard-card__value">{activePlanName}</p>
              <p className="dashboard-card__meta">
                Status: {summary.is_active || summary.plan === "free" ? "Active" : "Inactive"}
              </p>
              <a className="app-btn app-btn--primary dashboard-card__btn" href={`/plane${query}`}>
                Manage Plane
              </a>
            </div>

            <div className="dashboard-card">
              <h3>Products Used</h3>
              <p className="dashboard-card__value">
                {productCount} / {formatLimit(productLimit)}
              </p>
              {productLimit !== null ? (
                <>
                  <div className="usage-track">
                    <div className="usage-track__bar" style={{ width: `${usagePercent}%` }} />
                  </div>
                  <p className="dashboard-card__meta">{usagePercent}% of plan limit used</p>
                </>
              ) : (
                <p className="dashboard-card__meta">Unlimited products on this plan.</p>
              )}
            </div>

            <div className="dashboard-card dashboard-card--wide">
              <h3>Embedded App Customization</h3>
              <p className="dashboard-card__meta">
                Enable the app block in your theme so storefront shoppers can see price breakup details.
              </p>
              <ol className="dashboard-steps">
                <li>Go to Shopify Admin → Online Store → Themes → Customize.</li>
                <li>Open the Product template and add the MetalBreak app block.</li>
                <li>Save the theme and preview a product page.</li>
              </ol>
              <div className="dashboard-actions">
                <a className="app-btn app-btn--primary" href={`/products${query}`}>
                  Open Product Price
                </a>
                <a className="app-btn app-btn--ghost" href={`/price${query}`}>
                  Open Set Price
                </a>
              </div>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { AppProvider, Page, Card, Text } from "@shopify/polaris";
import enTranslations from "@shopify/polaris/locales/en.json";

import PriceSet from "./pages/PriceSet";
import ProductPrice from "./pages/ProductPrice";

function App() {
  const pathname = useMemo(
    () => window.location.pathname.replace(/\/+$/, "") || "/",
    [],
  );

  return (
    <AppProvider i18n={enTranslations}>
      {pathname === "/price" ? (
        <PriceSet />
      ) : pathname === "/products" ? (
        <ProductPrice />
      ) : (
        <div className="dashboard-page">
          <div className="set-price-hero">
            <div className="set-price-hero__icon" aria-hidden="true">
              MB
            </div>
            <div>
              <h1 className="set-price-hero__title">MetalBreak Dashboard</h1>
              <p className="set-price-hero__subtitle">Manage prices and products from one place</p>
            </div>
          </div>
          <div className="dashboard-page-content">
            <Page>
              <Card>
                <div className="dashboard-welcome-card">
                  <Text as="h2" variant="headingMd">
                    Welcome to MetalBreak
                  </Text>
                  <Text as="p" variant="bodyMd" tone="subdued">
                    Use the navigation menu to configure price sets and update product prices.
                  </Text>
                </div>
              </Card>
            </Page>
          </div>
        </div>
      )}
    </AppProvider>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App />);
import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { AppProvider, Box, Card, Page, Text } from "@shopify/polaris";
import enTranslations from "@shopify/polaris/locales/en.json";
import "@shopify/polaris/build/esm/styles.css";
import "../css/shopify-app.css";
import PriceSet from "./pages/PriceSet";

const APP_ROOT = "/";
const ROUTES = {
  dashboard: `${APP_ROOT}app`,
  price: `${APP_ROOT}price`,
};
const API_ENDPOINTS = {
  priceSettings: `${APP_ROOT}api/price-settings`,
};

function App() {
  const pathname = useMemo(() => window.location.pathname, []);

  return (
    <AppProvider i18n={enTranslations}>
      {pathname === ROUTES.price ? (
        <PriceSet apiEndpoint={API_ENDPOINTS.priceSettings} />
      ) : (
        <div className="dashboard-page">
          <Page title="Dashboard">
            <div className="set-price-section">
              <Card>
                <Box padding="400">
                  <Text as="p" variant="bodyMd">
                    Welcome to MetalBreak. Use the Shopify sidebar to open Set Price.
                  </Text>
                </Box>
              </Card>
            </div>
          </Page>
        </div>
      )}
    </AppProvider>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App />);

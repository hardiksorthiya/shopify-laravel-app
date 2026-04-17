import React, { useMemo } from "react";
import ReactDOM from "react-dom/client";
import { AppProvider } from "@shopify/polaris";
import enTranslations from "@shopify/polaris/locales/en.json";

import PriceSet from "./pages/PriceSet";
import ProductPrice from "./pages/ProductPrice";
import PricingPage from "./pages/PricingPage";
import DashboardPage from "./pages/DashboardPage";

function App() {
  const pathname = useMemo(() => window.location.pathname.replace(/\/+$/, "") || "/", []);
  const normalizedPath = useMemo(() => pathname.toLowerCase(), [pathname]);
  const isPricePage = normalizedPath === "/price" || normalizedPath.endsWith("/price");
  const isProductsPage = normalizedPath === "/products" || normalizedPath.endsWith("/products");
  const isPlanePage =
    normalizedPath === "/pricing" ||
    normalizedPath.endsWith("/pricing") ||
    normalizedPath === "/plane" ||
    normalizedPath.endsWith("/plane");

  return (
    <AppProvider i18n={enTranslations}>
      {isPricePage ? (
        <PriceSet />
      ) : isProductsPage ? (
        <ProductPrice />
      ) : isPlanePage ? (
        <PricingPage />
      ) : (
        <DashboardPage />
      )}
    </AppProvider>
  );
}

ReactDOM.createRoot(document.getElementById("app")).render(<App />);
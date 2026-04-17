import { useEffect, useMemo, useState } from "react";

const getCsrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content") ?? "";
const getCreateChargeUrl = () =>
    document
        .querySelector('meta[name="billing-create-charge-url"]')
        ?.getAttribute("content") ?? "/billing/create-charge";

const planOrder = ["free", "starter", "growth", "pro"];
const fallbackPlans = {
    free: {
        name: "Free",
        price: 0,
        product_limit: 10,
        dev_only: true,
        features: [],
    },
    starter: { name: "Starter", price: 9, product_limit: 50, features: [] },
    growth: { name: "Growth", price: 30, product_limit: 200, features: [] },
    pro: { name: "Pro", price: 50, product_limit: null, features: [] },
};

export default function PricingPage() {
    const [plans, setPlans] = useState({});
    const [currentPlan, setCurrentPlan] = useState("free");
    const [active, setActive] = useState(false);
    const [submittingPlan, setSubmittingPlan] = useState("");
    const [error, setError] = useState("");
    const params = useMemo(
        () => new URLSearchParams(window.location.search || ""),
        [],
    );
    const shop = params.get("shop") || "";

    useEffect(() => {
        const loadPlans = async () => {
            try {
                const response = await fetch(
                    `/api/billing/plans${window.location.search || ""}`,
                    {
                        headers: { Accept: "application/json" },
                        credentials: "same-origin",
                    },
                );
                if (!response.ok) throw new Error("Unable to load plans.");
                const payload = await response.json();
                const incomingPlans =
                    payload?.plans && typeof payload.plans === "object"
                        ? payload.plans
                        : {};
                setPlans(
                    Object.keys(incomingPlans).length
                        ? incomingPlans
                        : fallbackPlans,
                );
                setCurrentPlan(payload.current_plan || "free");
                setActive(Boolean(payload.is_active));
            } catch (loadError) {
                setError(loadError?.message || "Unable to load plans.");
            }
        };

        loadPlans();
    }, []);

    const resolvedPlans = Object.keys(plans || {}).length
        ? plans
        : fallbackPlans;
    const visiblePlans = planOrder
        .filter((key) => resolvedPlans[key])
        .map((key) => ({ key, ...resolvedPlans[key] }));

    const submitPlan = (plan) => {
        setSubmittingPlan(plan);
        setError("");
        const form = document.createElement("form");
        form.method = "POST";
        form.action = getCreateChargeUrl();

        const fields = {
            _token: getCsrfToken(),
            plan,
            shop,
            host: params.get("host") || "",
        };

        Object.entries(fields).forEach(([name, value]) => {
            const input = document.createElement("input");
            input.type = "hidden";
            input.name = name;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

    return (
        <div className="dashboard-page">
            <div className="set-price-hero">
                <div className="set-price-hero__icon" aria-hidden="true">
                    MB
                </div>
                <div>
                    <h1 className="set-price-hero__title">Choose your Plane</h1>
                    <p className="set-price-hero__subtitle">
                        Simple billing with 7-day trial on paid plans
                    </p>
                </div>
            </div>
            <div className="set-price-content">
                {error ? (
                    <div className="app-alert app-alert--error">{error}</div>
                ) : null}
                
                <div className="pricing-grid">
                    {visiblePlans.map((plan) => {
                        const isCurrent = currentPlan === plan.key;
                        const isFree = plan.key === "free";
                        const limitText =
                            plan.product_limit === null
                                ? "Unlimited products"
                                : `${plan.product_limit} products`;
                        const planFeatures = Array.isArray(plan.features)
                            ? plan.features
                            : [];

                        return (
                            <div
                                key={plan.key}
                                className={`pricing-card ${isCurrent ? "pricing-card--active" : ""}`}
                            >
                                <h3>{plan.name || plan.key}</h3>
                                <p className="pricing-price">
                                    ${Number(plan.price || 0).toFixed(0)}
                                    <span>/month</span>
                                </p>
                                <p className="pricing-meta">{limitText}</p>
                                {isFree ? (
                                    <p className="pricing-meta">
                                        Development stores only
                                    </p>
                                ) : (
                                    <p className="pricing-meta">
                                        7-day free trial included
                                    </p>
                                )}
                                <ul className="plan-features">
                                    {planFeatures.map((feature, i) => (
                                        <li key={i}>✔ {feature}</li>
                                    ))}
                                </ul>
                                <button
                                    type="button"
                                    className="app-btn app-btn--primary"
                                    onClick={() => submitPlan(plan.key)}
                                    disabled={
                                        submittingPlan === plan.key ||
                                        (isCurrent && (isFree || active))
                                    }
                                >
                                    {submittingPlan === plan.key
                                        ? "Processing..."
                                        : isCurrent && (isFree || active)
                                          ? "Current plan"
                                          : `Choose ${plan.name || plan.key}`}
                                </button>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}

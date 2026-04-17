<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - MetalBreak</title>
    <style>
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
            line-height: 1.6;
        }
        .container {
            max-width: 920px;
            margin: 32px auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 28px;
        }
        h1, h2 {
            margin-top: 0;
            color: #1e293b;
        }
        h2 {
            margin-top: 24px;
            font-size: 20px;
        }
        p, li {
            font-size: 15px;
            color: #334155;
        }
        a {
            color: #7f5012;
        }
        .muted {
            color: #64748b;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <main class="container">
        <h1>Privacy Policy</h1>
        <p class="muted">Last updated: {{ now()->format('F d, Y') }}</p>

        <p>
            This Privacy Policy describes how MetalBreak collects, uses, and protects merchant data when you install and use the app.
        </p>

        <h2>Information We Collect</h2>
        <ul>
            <li>Store domain and app access token from Shopify during app installation.</li>
            <li>Plan and billing metadata required to manage subscriptions.</li>
            <li>Product-related pricing inputs entered by the merchant in the app.</li>
        </ul>

        <h2>How We Use Information</h2>
        <ul>
            <li>To provide app functionality such as pricing management and storefront price breakup display.</li>
            <li>To authenticate Shopify API requests and process recurring billing charges.</li>
            <li>To improve app reliability, security, and support response.</li>
        </ul>

        <h2>Data Sharing</h2>
        <p>
            We do not sell your data. We only share data with Shopify as required to operate the app and billing flow.
        </p>

        <h2>Data Retention</h2>
        <p>
            We retain data only as long as necessary to provide the service or comply with legal obligations. You may request data removal after uninstalling the app.
        </p>

        <h2>Security</h2>
        <p>
            We apply reasonable administrative and technical safeguards to protect your information.
        </p>

        <h2>Contact</h2>
        <p>
            For privacy inquiries, contact us at <a href="mailto:info@sorathwebsolution.com">info@sorathwebsolution.com</a>.
        </p>
    </main>
</body>
</html>

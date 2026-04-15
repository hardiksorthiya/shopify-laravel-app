<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <meta name="shopify-api-key" content="{{ env('SHOPIFY_API_KEY') }}">
    <meta name="shopify-host" content="{{ request()->query('host') }}">
    <title>MetalBreak App</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>

    @viteReactRefresh
    @vite('resources/js/app.jsx')
</head>
<body>
    <ui-nav-menu>
        <a href="/app" rel="home">Dashboard</a>
        <a href="/price">Set Price</a>
    </ui-nav-menu>
    <div id="app"></div>
</body>
</html>

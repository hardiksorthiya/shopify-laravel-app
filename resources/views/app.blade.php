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
    <meta name="billing-create-charge-url" content="{{ route('billing.create-charge') }}">
    <meta name="app-home-url" content="{{ route('app.home') }}">
    <title>MetalBreak App</title>
    <script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>
    <link rel="stylesheet" href="{{ asset('css/set-price.css') }}?v={{ @filemtime(public_path('css/set-price.css')) }}">

    @viteReactRefresh
    @vite('resources/js/app.jsx')
</head>

<body>
    <ui-nav-menu>
        <a href="{{ route('billing.plane', request()->query()) }}">Plane</a>
        <a href="{{ route('price.index', request()->query()) }}">Set Price</a>
        <a href="{{ route('product.price', request()->query()) }}">Product Price</a>
    </ui-nav-menu>
    <div id="app"></div>
</body>

</html>

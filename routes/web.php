<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PriceController;
use App\Http\Controllers\WebhookController;
use App\Models\VariantPriceEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| STEP 1: Entry (Install Flow)
|--------------------------------------------------------------------------
*/
Route::get('/', function (Request $request) {
    $shop = $request->get('shop');

    if (! $shop) {
        return 'No shop provided';
    }

    return redirect()->route('auth', array_filter([
        'shop' => $shop,
        'host' => $request->query('host'),
        'embedded' => $request->query('embedded'),
    ], fn ($value) => $value !== null && $value !== ''));
});

/*
|--------------------------------------------------------------------------
| STEP 2: OAuth Start
|--------------------------------------------------------------------------
*/
Route::get('/auth', function (Request $request) {

    $shop = $request->get('shop');

    if (! $shop) {
        return 'No shop provided';
    }

    $apiKey = config('services.shopify.api_key');
    $redirectUri = config('services.shopify.redirect_uri');
    $scopes = config('services.shopify.scopes');

    if (! $apiKey || ! $redirectUri || ! $scopes) {
        return response()->json([
            'error' => 'Shopify app config is missing',
            'missing' => [
                'SHOPIFY_API_KEY' => ! $apiKey,
                'SHOPIFY_REDIRECT_URI' => ! $redirectUri,
                'SHOPIFY_SCOPES' => ! $scopes,
            ],
        ], 500);
    }
    $authUrl = "https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}";

    // Shopify OAuth must happen in the top-level window, not inside the embedded iframe.
    // Use App Bridge redirect to avoid browser cross-origin navigation restrictions.
    if ($request->query('embedded') === '1') {
        $host = $request->query('host');

        if (! $host) {
            return redirect($authUrl);
        }

        $html = '<!doctype html><html><head><meta charset="utf-8"><script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script></head><body>'
            . '<script>'
            . 'const AppBridge=window["app-bridge"];'
            . 'const createApp=AppBridge.default;'
            . 'const Redirect=AppBridge.actions.Redirect;'
            . 'const app=createApp({apiKey:'.json_encode($apiKey).',host:'.json_encode($host).'});'
            . 'Redirect.create(app).dispatch(Redirect.Action.REMOTE,'.json_encode($authUrl).');'
            . '</script></body></html>';

        return response($html);
    }

    return redirect($authUrl);
})->name('auth');

/*
|--------------------------------------------------------------------------
| STEP 3: OAuth Callback
|--------------------------------------------------------------------------
*/
Route::get('/auth/callback', function (Request $request) {

    $code = $request->get('code');
    $shop = $request->get('shop');

    if (! $code || ! $shop) {
        return 'Missing code or shop';
    }

    $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
        'client_id' => config('services.shopify.api_key'),
        'client_secret' => config('services.shopify.api_secret'),
        'code' => $code,
    ]);

    $data = $response->json();

    if (! isset($data['access_token'])) {
        return response()->json($data);
    }

    DB::table('shops')->updateOrInsert(
        ['shop' => $shop],
        ['access_token' => $data['access_token']]
    );

    return redirect()->route('app.home', [
        'shop' => $shop,
        'host' => $request->get('host'),
    ]);
});

/*
|--------------------------------------------------------------------------
| API: Shopify Products
|--------------------------------------------------------------------------
*/
Route::get('/api/products', function (Request $request) {

    $shopDomain = $request->query('shop');
    $shop = DB::table('shops')
        ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
        ->first();

    if (! $shop) {
        return response()->json(['error' => 'No shop']);
    }

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $shop->access_token,
    ])->get("https://{$shop->shop}/admin/api/2024-01/products.json");

    $data = $response->json();

    $products = $data['products'] ?? [];
    $shopDomain = $shop->shop;

    $variantIds = [];
    foreach ($products as $product) {
        foreach (($product['variants'] ?? []) as $variant) {
            if (isset($variant['id'])) {
                $variantIds[] = (int) $variant['id'];
            }
        }
    }

    $variantIds = array_values(array_unique($variantIds));

    $entries = collect();
    if (! empty($variantIds)) {
        $entries = VariantPriceEntry::query()
            ->where('shop', $shopDomain)
            ->whereIn('variant_id', $variantIds)
            ->get()
            ->keyBy(fn ($entry) => (string) $entry->variant_id);
    }

    foreach ($products as $pIndex => $product) {
        foreach (($product['variants'] ?? []) as $vIndex => $variant) {
            $idKey = isset($variant['id']) ? (string) $variant['id'] : null;
            if (! $idKey) {
                continue;
            }

            if ($entries->has($idKey)) {
                $entry = $entries->get($idKey);
                $products[$pIndex]['variants'][$vIndex]['saved_inputs'] = [
                    'metal_type' => $entry->metal_type,
                    'gold_karat' => $entry->gold_karat,
                    'metal_weight' => $entry->metal_weight,
                    'diamond_quality_value' => $entry->diamond_quality_value,
                    'diamond_weight' => $entry->diamond_weight,
                    'making_charge' => $entry->making_charge,
                    'computed_total' => $entry->computed_total,
                ];
            }
        }
    }

    $data['products'] = $products;

    return response()->json($data);
})->middleware('billing.active');

/*
|--------------------------------------------------------------------------
| PRICE SET PAGES
|--------------------------------------------------------------------------
*/
Route::get('/price', [PriceController::class, 'index'])->middleware('billing.active')->name('price.index');
Route::get('/api/price-settings', [PriceController::class, 'data'])->middleware('billing.active')->name('price.data');
Route::post('/api/price-settings', [PriceController::class, 'store'])->middleware('billing.active')->name('price.store');
Route::post('/api/product-variant-price', [PriceController::class, 'saveVariantPrice'])->middleware('billing.active')->name('product.variant.price.save');
Route::post('/api/product-variant-price-csv', [PriceController::class, 'saveVariantPriceFromCsv'])->middleware('billing.active')->name('product.variant.price.csv.save');
Route::match(['get', 'options'], '/api/storefront/variant-breakup', [PriceController::class, 'storefrontVariantBreakup'])
    ->name('storefront.variant.breakup');
Route::match(['get', 'options'], '/apps/metalbreak/variant-breakup', [PriceController::class, 'storefrontVariantBreakup']);

/*
|--------------------------------------------------------------------------
| PRODUCT PRICE PAGE
|--------------------------------------------------------------------------
*/
Route::get('/products', [PriceController::class, 'productPrice'])->middleware('billing.active')->name('product.price');

Route::get('/app', fn () => view('app'))->name('app.home');
Route::view('/privacy-policy', 'privacy-policy')->name('privacy.policy');
Route::get('/pricing', [BillingController::class, 'pricing'])->name('billing.pricing');
Route::get('/plane', [BillingController::class, 'pricing'])->name('billing.plane');
Route::get('/api/billing/plans', [BillingController::class, 'plans'])->name('billing.plans');
Route::get('/api/dashboard-summary', [BillingController::class, 'dashboardSummary'])->name('dashboard.summary');
Route::post('/billing/create-charge', [BillingController::class, 'createCharge'])->name('billing.create-charge');
Route::get('/billing/callback', [BillingController::class, 'callback'])->name('billing.callback');

Route::post('/webhooks/customers/data_request', [WebhookController::class, 'customersDataRequest'])
    ->name('webhooks.customers.data_request');
Route::post('/webhooks/customers/redact', [WebhookController::class, 'customersRedact'])
    ->name('webhooks.customers.redact');
Route::post('/webhooks/shop/redact', [WebhookController::class, 'shopRedact'])
    ->name('webhooks.shop.redact');
Route::get('/webhooks/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Webhook service is live. Use POST for compliance webhooks.',
    ]);
})->name('webhooks.health');

<?php

use App\Http\Controllers\BillingController;
use App\Http\Controllers\PriceController;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use App\Models\VariantPriceEntry;

/*
|--------------------------------------------------------------------------
| STEP 1: Entry (Install Flow)
|--------------------------------------------------------------------------
*/
Route::get('/', function (Request $request) {
    $shop = $request->get('shop');

    if (!$shop) {
        return 'No shop provided';
    }

    return redirect()->route('auth', ['shop' => $shop]);
});


/*
|--------------------------------------------------------------------------
| STEP 2: OAuth Start
|--------------------------------------------------------------------------
*/
Route::get('/auth', function (Request $request) {

    $shop = $request->get('shop');

    if (!$shop) {
        return 'No shop provided';
    }

    $apiKey = config('services.shopify.api_key');
    $redirectUri = config('services.shopify.redirect_uri');
    $scopes = config('services.shopify.scopes');

    if (!$apiKey || !$redirectUri || !$scopes) {
        return response()->json([
            'error' => 'Missing Shopify configuration',
            'details' => 'Set SHOPIFY_API_KEY, SHOPIFY_SCOPES, and SHOPIFY_REDIRECT_URI in production .env',
        ], 500);
    }

    $query = http_build_query([
        'client_id' => $apiKey,
        'scope' => $scopes,
        'redirect_uri' => $redirectUri,
    ]);
    $authUrl = "https://{$shop}/admin/oauth/authorize?{$query}";

    // Shopify OAuth must happen in top-level window, not inside iframe.
    if ($request->boolean('embedded') || $request->filled('host')) {
        return response(
            '<!DOCTYPE html><html><head><meta charset="UTF-8"><script>window.top.location.href='
            . json_encode($authUrl)
            . ';</script></head><body>Redirecting...</body></html>'
        );
    }

    return redirect()->away($authUrl);
})->name('auth');


/*
|--------------------------------------------------------------------------
| STEP 3: OAuth Callback
|--------------------------------------------------------------------------
*/
Route::get('/auth/callback', function (Request $request) {

    $code = $request->get('code');
    $shop = $request->get('shop');

    if (!$code || !$shop) {
        return 'Missing code or shop';
    }

    $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
        'client_id' => config('services.shopify.api_key'),
        'client_secret' => config('services.shopify.api_secret'),
        'code' => $code,
    ]);

    $data = $response->json();

    if (!isset($data['access_token'])) {
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

    if (!$shop) {
        return response()->json(['error' => 'No shop']);
    }

    $response = Http::withHeaders([
        'X-Shopify-Access-Token' => $shop->access_token
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
    if (!empty($variantIds)) {
        $entries = VariantPriceEntry::query()
            ->where('shop', $shopDomain)
            ->whereIn('variant_id', $variantIds)
            ->get()
            ->keyBy(fn ($entry) => (string) $entry->variant_id);
    }

    foreach ($products as $pIndex => $product) {
        foreach (($product['variants'] ?? []) as $vIndex => $variant) {
            $idKey = isset($variant['id']) ? (string) $variant['id'] : null;
            if (!$idKey) {
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
<?php

use App\Http\Controllers\PriceController;
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

    $apiKey = env('SHOPIFY_API_KEY');
    $redirectUri = env('SHOPIFY_REDIRECT_URI');
    $scopes = env('SHOPIFY_SCOPES');

    return redirect("https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}");
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
        'client_id' => env('SHOPIFY_API_KEY'),
        'client_secret' => env('SHOPIFY_API_SECRET'),
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

    // 👉 redirect to main page (Set Price)
    return redirect()->route('price.index', [
        'shop' => $shop,
        'host' => $request->get('host'),
    ]);
});


/*
|--------------------------------------------------------------------------
| API: Shopify Products
|--------------------------------------------------------------------------
*/
Route::get('/api/products', function () {

    $shop = DB::table('shops')->first();

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
});


/*
|--------------------------------------------------------------------------
| PRICE SET PAGES
|--------------------------------------------------------------------------
*/
Route::get('/price', [PriceController::class, 'index'])->name('price.index');
Route::get('/api/price-settings', [PriceController::class, 'data'])->name('price.data');
Route::post('/api/price-settings', [PriceController::class, 'store'])->name('price.store');
Route::post('/api/product-variant-price', [PriceController::class, 'saveVariantPrice'])->name('product.variant.price.save');
Route::post('/api/product-variant-price-csv', [PriceController::class, 'saveVariantPriceFromCsv'])->name('product.variant.price.csv.save');


/*
|--------------------------------------------------------------------------
| PRODUCT PRICE PAGE
|--------------------------------------------------------------------------
*/
Route::get('/products', [PriceController::class, 'productPrice'])->name('product.price');
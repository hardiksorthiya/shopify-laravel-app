<?php

use App\Http\Controllers\PriceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;


/**
 * STEP 1: Entry point
 * Shopify will hit this with ?shop=
 */
/**
 * STEP 1: Entry (Install flow)
 */
Route::get('/', function (Request $request) {
    $shop = $request->get('shop');

    if (!$shop) {
        return "No shop provided";
    }

    return redirect()->route('auth', ['shop' => $shop]);
});


/**
 * STEP 2: App UI (React loads here)
 */
Route::get('/app', function () {
    return view('app'); // this loads React Polaris
})->name('app');

/**
 * STEP 2: OAuth Start
 */
Route::get('/auth', function (Request $request) {

    $shop = $request->get('shop');

    if (!$shop) {
        return "No shop provided";
    }

    $apiKey = env('SHOPIFY_API_KEY');
    $redirectUri = env('SHOPIFY_REDIRECT_URI');
    $scopes = env('SHOPIFY_SCOPES');

    return redirect("https://{$shop}/admin/oauth/authorize?client_id={$apiKey}&scope={$scopes}&redirect_uri={$redirectUri}");
})->name('auth');


/**
 * STEP 3: OAuth Callback
 */
Route::get('/auth/callback', function (Request $request) {

    $code = $request->get('code');
    $shop = $request->get('shop');

    if (!$code || !$shop) {
        return "Missing code or shop";
    }

    $response = Http::asForm()->post("https://{$shop}/admin/oauth/access_token", [
        'client_id' => env('SHOPIFY_API_KEY'),
        'client_secret' => env('SHOPIFY_API_SECRET'),
        'code' => $code,
    ]);

    $data = $response->json();

    if (!isset($data['access_token'])) {
        return response()->json($data); // debug
    }

    DB::table('shops')->updateOrInsert(
        ['shop' => $shop],
        ['access_token' => $data['access_token']]
    );

    return redirect()->route('app', [
        'shop' => $shop,
        'host' => $request->get('host'),
    ]);
});

Route::get('/price', [PriceController::class, 'index'])->name('price.index');
Route::get('/api/price-settings', [PriceController::class, 'data'])->name('price.data');
Route::post('/api/price-settings', [PriceController::class, 'store'])->name('price.store');

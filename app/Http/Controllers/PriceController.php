<?php

namespace App\Http\Controllers;

use App\Models\DiamondPrice;
use App\Models\PriceSetting;
use App\Models\VariantPriceEntry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PriceController extends Controller
{
    public function index()
    {
        return view('app');
    }

    public function data(Request $request)
    {
        $priceSetting = PriceSetting::first();
        $diamonds = DiamondPrice::orderBy('id')->get()->map(function (DiamondPrice $diamond) {
            return [
                'quality' => $diamond->quality ?? '',
                'color' => $diamond->color ?? '',
                'min_ct' => (string) ($diamond->min_ct ?? ''),
                'max_ct' => (string) ($diamond->max_ct ?? ''),
                'price' => (string) ($diamond->price ?? ''),
            ];
        })->values();
        $currency = $this->resolveStoreCurrency($request);

        return response()->json([
            'gold_10kt' => (string) ($priceSetting->gold_10kt ?? 0),
            'gold_14kt' => (string) ($priceSetting->gold_14kt ?? 0),
            'gold_18kt' => (string) ($priceSetting->gold_18kt ?? 0),
            'gold_22kt' => (string) ($priceSetting->gold_22kt ?? 0),
            'silver_price' => (string) ($priceSetting->silver_price ?? 0),
            'platinum_price' => (string) ($priceSetting->platinum_price ?? 0),
            'tax_percent' => (string) ($priceSetting->tax_percent ?? 0),
            'diamonds' => $diamonds,
            'currency_code' => $currency['code'],
            'currency_symbol' => $currency['symbol'],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'gold_10kt' => ['required', 'numeric', 'min:0'],
            'gold_14kt' => ['required', 'numeric', 'min:0'],
            'gold_18kt' => ['required', 'numeric', 'min:0'],
            'gold_22kt' => ['required', 'numeric', 'min:0'],
            'silver_price' => ['required', 'numeric', 'min:0'],
            'platinum_price' => ['required', 'numeric', 'min:0'],
            'tax_percent' => ['required', 'numeric', 'min:0'],
            'diamonds' => ['nullable', 'array'],
            'diamonds.*.quality' => ['nullable', 'string', 'max:255'],
            'diamonds.*.color' => ['nullable', 'string', 'max:255'],
            'diamonds.*.min_ct' => ['nullable', 'numeric', 'min:0'],
            'diamonds.*.max_ct' => ['nullable', 'numeric', 'min:0'],
            'diamonds.*.price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $this->persistPriceSettings($validated);

        if ($request->expectsJson() || $request->wantsJson() || $request->isJson()) {
            return response()->json([
                'message' => 'Price settings updated successfully.',
            ]);
        }

        return redirect()->route('price.index')->with('success', 'Price settings updated successfully.');
    }

    public function saveVariantPrice(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
            'metal_type' => ['required', 'string', 'max:50'],
            'gold_karat' => ['required', 'string', 'max:50'],
            'metal_weight' => ['required', 'numeric', 'min:0'],
            'diamond_quality_value' => ['nullable', 'string'],
            'diamond_weight' => ['required', 'numeric', 'min:0'],
            'making_charge' => ['required', 'numeric', 'min:0'],
        ]);

        $shopDomain = $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        $accessToken = DB::table('shops')
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if (!$shopDomain || !$accessToken) {
            return response()->json([
                'message' => 'Shop connection is missing. Please reinstall or reconnect the app.',
            ], 422);
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->put("https://{$shopDomain}/admin/api/2024-01/variants/{$validated['variant_id']}.json", [
            'variant' => [
                'id' => $validated['variant_id'],
                'price' => number_format((float) $validated['price'], 2, '.', ''),
            ],
        ]);

        if (!$response->ok()) {
            return response()->json([
                'message' => data_get($response->json(), 'errors')
                    ? json_encode(data_get($response->json(), 'errors'))
                    : 'Failed to save variant price to Shopify.',
            ], $response->status() ?: 500);
        }

        $entry = VariantPriceEntry::updateOrCreate(
            [
                'shop' => $shopDomain,
                'variant_id' => $validated['variant_id'],
            ],
            [
                'metal_type' => $validated['metal_type'],
                'gold_karat' => $validated['gold_karat'],
                'metal_weight' => (float) $validated['metal_weight'],
                'diamond_quality_value' => $validated['diamond_quality_value'] ?? null,
                'diamond_weight' => (float) $validated['diamond_weight'],
                'making_charge' => (float) $validated['making_charge'],
                'computed_total' => (float) $validated['price'],
            ],
        );

        return response()->json([
            'message' => 'Variant price saved successfully.',
            'variant' => $response->json('variant'),
            'saved_inputs' => [
                'metal_type' => $entry->metal_type,
                'gold_karat' => $entry->gold_karat,
                'metal_weight' => $entry->metal_weight,
                'diamond_quality_value' => $entry->diamond_quality_value,
                'diamond_weight' => $entry->diamond_weight,
                'making_charge' => $entry->making_charge,
                'computed_total' => $entry->computed_total,
            ],
        ]);
    }

    public function saveVariantPriceFromCsv(Request $request)
    {
        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'price' => ['required', 'numeric', 'min:0'],
        ]);

        $shopDomain = $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        $accessToken = DB::table('shops')
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if (!$shopDomain || !$accessToken) {
            return response()->json([
                'message' => 'Shop connection is missing. Please reinstall or reconnect the app.',
            ], 422);
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->put("https://{$shopDomain}/admin/api/2024-01/variants/{$validated['variant_id']}.json", [
            'variant' => [
                'id' => $validated['variant_id'],
                'price' => number_format((float) $validated['price'], 2, '.', ''),
            ],
        ]);

        if (!$response->ok()) {
            return response()->json([
                'message' => data_get($response->json(), 'errors')
                    ? json_encode(data_get($response->json(), 'errors'))
                    : 'Failed to save variant price to Shopify.',
            ], $response->status() ?: 500);
        }

        $existing = VariantPriceEntry::query()
            ->where('shop', $shopDomain)
            ->where('variant_id', $validated['variant_id'])
            ->first();

        $entry = VariantPriceEntry::updateOrCreate(
            [
                'shop' => $shopDomain,
                'variant_id' => $validated['variant_id'],
            ],
            [
                'metal_type' => $existing?->metal_type ?? 'gold',
                'gold_karat' => $existing?->gold_karat ?? '10kt',
                'metal_weight' => (float) ($existing?->metal_weight ?? 0),
                'diamond_quality_value' => $existing?->diamond_quality_value,
                'diamond_weight' => (float) ($existing?->diamond_weight ?? 0),
                'making_charge' => (float) ($existing?->making_charge ?? 0),
                'computed_total' => (float) $validated['price'],
            ],
        );

        return response()->json([
            'message' => 'Variant price saved successfully.',
            'variant' => $response->json('variant'),
            'computed_total' => $entry->computed_total,
        ]);
    }

    private function persistPriceSettings(array $validated): void
    {
        PriceSetting::updateOrCreate(
            ['id' => 1],
            [
                'gold_10kt' => $validated['gold_10kt'],
                'gold_14kt' => $validated['gold_14kt'],
                'gold_18kt' => $validated['gold_18kt'],
                'gold_22kt' => $validated['gold_22kt'],
                'silver_price' => $validated['silver_price'],
                'platinum_price' => $validated['platinum_price'],
                'tax_percent' => $validated['tax_percent'],
            ]
        );

        DiamondPrice::query()->delete();

        $diamondRows = $validated['diamonds'] ?? [];
        foreach ($diamondRows as $diamondRow) {
            $hasData = filled($diamondRow['quality'] ?? null)
                || filled($diamondRow['color'] ?? null)
                || isset($diamondRow['min_ct'])
                || isset($diamondRow['max_ct'])
                || isset($diamondRow['price']);

            if (!$hasData) {
                continue;
            }

            DiamondPrice::create([
                'quality' => $diamondRow['quality'] ?? '',
                'color' => $diamondRow['color'] ?? '',
                'min_ct' => $diamondRow['min_ct'] ?? 0,
                'max_ct' => $diamondRow['max_ct'] ?? 0,
                'price' => $diamondRow['price'] ?? 0,
            ]);
        }
    }

    private function resolveStoreCurrency(Request $request): array
    {
        $default = ['code' => 'USD', 'symbol' => '$'];
        $shop = $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        if (!$shop) {
            return $default;
        }

        $accessToken = DB::table('shops')
            ->where('shop', $shop)
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if (!$accessToken) {
            return $default;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
        ])->get("https://{$shop}/admin/api/2024-01/shop.json");

        if (!$response->ok()) {
            return $default;
        }

        $currencyCode = strtoupper((string) data_get($response->json(), 'shop.currency', 'USD'));

        return [
            'code' => $currencyCode,
            'symbol' => $this->currencySymbolForCode($currencyCode),
        ];
    }

    private function currencySymbolForCode(string $currencyCode): string
    {
        return match ($currencyCode) {
            'USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'INR' => 'Rs',
            'JPY', 'CNY' => '¥',
            'KRW' => '₩',
            'AED' => 'AED',
            'SAR' => 'SAR',
            'QAR' => 'QAR',
            default => $currencyCode,
        };
    }

    public function productPrice()
    {
        return view('app');
    }


}

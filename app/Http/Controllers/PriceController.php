<?php

namespace App\Http\Controllers;

use App\Models\DiamondPrice;
use App\Models\PriceSetting;
use App\Models\Shop;
use App\Models\VariantPriceEntry;
use App\Services\PlanLimitService;
use App\Services\ShopifyProductBreakupMetafields;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PriceController extends Controller
{
    public function __construct(private readonly PlanLimitService $planLimitService) {}

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

        $shopModel = $this->resolveCurrentShop($request);
        $shopDomain = $shopModel?->shop
            ?: $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        $accessToken = DB::table('shops')
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if (!$shopDomain || !$accessToken || ! $shopModel) {
            return response()->json([
                'message' => 'Shop connection is missing. Please reinstall or reconnect the app.',
            ], 422);
        }

        try {
            $this->planLimitService->enforceProductLimit($shopModel);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
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

        $this->syncProductBreakupMetafields($request, $shopDomain, $accessToken, (int) $validated['variant_id'], $entry);

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

        $shopModel = $this->resolveCurrentShop($request);
        $shopDomain = $shopModel?->shop
            ?: $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        $accessToken = DB::table('shops')
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if (!$shopDomain || !$accessToken || ! $shopModel) {
            return response()->json([
                'message' => 'Shop connection is missing. Please reinstall or reconnect the app.',
            ], 422);
        }

        try {
            $this->planLimitService->enforceProductLimit($shopModel);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
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

        $this->syncProductBreakupMetafields($request, $shopDomain, $accessToken, (int) $validated['variant_id'], $entry);

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

    private function resolveCurrentShop(Request $request): ?Shop
    {
        $shopDomain = $request->query('shop') ?: $request->input('shop');

        return Shop::query()
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->first();
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

    public function storefrontVariantBreakup(Request $request)
    {
        if ($request->isMethod('OPTIONS')) {
            return $this->storefrontBreakupCors(response('', 204));
        }

        $validated = $request->validate([
            'variant_id' => ['required', 'integer'],
            'shop' => ['nullable', 'string'],
        ]);

        $shopDomain = $validated['shop']
            ?? $request->query('shop')
            ?: DB::table('shops')->orderBy('id')->value('shop');

        if (! $shopDomain) {
            return $this->storefrontBreakupCors(response()->json([
                'message' => 'Shop is missing.',
            ], 422));
        }

        $variantId = (int) $validated['variant_id'];

        $entry = VariantPriceEntry::query()
            ->where('shop', $shopDomain)
            ->where('variant_id', $variantId)
            ->first();

        if (! $entry) {
            $entry = VariantPriceEntry::query()
                ->where('variant_id', $variantId)
                ->orderByDesc('id')
                ->first();
        }

        if (! $entry) {
            return $this->storefrontBreakupCors(response()->json([
                'message' => 'No breakup found for this variant.',
            ], 404));
        }

        $payload = $this->buildBreakupPayloadFromEntry($entry, $request, $variantId, $shopDomain);

        return $this->storefrontBreakupCors(response()->json($payload));
    }

    /**
     * Storefront JSON + variant metafield JSON (same shape).
     *
     * @return array<string, mixed>
     */
    private function buildBreakupPayloadFromEntry(VariantPriceEntry $entry, Request $request, int $variantId, string $shopDomain): array
    {
        $core = $this->calculateVariantBreakupCore($entry);
        $metalValue = $core['metal_value'];
        $diamondValue = $core['diamond_value'];
        $metalRate = $core['metal_rate'];
        $diamondRate = $core['diamond_rate'];
        $metalWeight = $core['metal_weight'];
        $diamondWeight = $core['diamond_weight'];
        $makingCharge = $core['making_charge'];
        $subtotal = $core['subtotal'];
        $taxPercent = $core['tax_percent'];
        $taxValue = $core['tax_value'];
        $grandTotal = $core['grand_total'];

        $diamondParts = array_map('trim', explode('|', (string) ($entry->diamond_quality_value ?? '')));
        $diamondQuality = $diamondParts[0] ?? '';
        $diamondColor = $diamondParts[1] ?? '';

        $compareAtPrice = null;
        $accessToken = DB::table('shops')
            ->where('shop', $shopDomain)
            ->value('access_token')
            ?: DB::table('shops')->orderBy('id')->value('access_token');

        if ($accessToken) {
            $variantResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->get("https://{$shopDomain}/admin/api/2024-01/variants/{$variantId}.json");

            if ($variantResponse->ok()) {
                $compareAt = data_get($variantResponse->json(), 'variant.compare_at_price');
                $compareAtNumeric = is_numeric($compareAt) ? (float) $compareAt : null;
                if ($compareAtNumeric && $compareAtNumeric > 0) {
                    $compareAtPrice = round($compareAtNumeric, 2);
                }
            }
        }

        $currency = $this->resolveStoreCurrency($request);
        $karatLabel = strtoupper(str_replace('kt', 'KT', (string) ($entry->gold_karat ?? '')));
        $metalLabel = Str::headline((string) ($entry->metal_type ?? 'gold'));
        $metalTitle = trim("{$karatLabel} {$metalLabel}");
        $diamondTitle = trim($diamondColor && $diamondQuality ? "{$diamondColor} / {$diamondQuality}" : 'Diamond');

        return [
            'variant_id' => $variantId,
            'currency_symbol' => $currency['symbol'],
            'currency_code' => $currency['code'],
            'metal' => [
                'section_title' => 'Metal',
                'title' => $metalTitle ?: 'Metal',
                'rate' => round($metalRate, 2),
                'weight' => round($metalWeight, 3),
                'weight_unit' => 'g',
                'value' => round($metalValue, 2),
            ],
            'diamond' => [
                'section_title' => 'Diamond',
                'title' => $diamondTitle,
                'rate' => round($diamondRate, 2),
                'weight' => round($diamondWeight, 3),
                'weight_unit' => 'ct',
                'value' => round($diamondValue, 2),
            ],
            'making_charge' => round($makingCharge, 2),
            'subtotal' => round($subtotal, 2),
            'tax_percent' => round($taxPercent, 2),
            'tax_value' => round($taxValue, 2),
            'grand_total' => $grandTotal,
            'compare_at_price' => $compareAtPrice,
        ];
    }

    private function storefrontBreakupCors($response)
    {
        return $response
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept, ngrok-skip-browser-warning');
    }

    /**
     * Shared breakup math for storefront JSON, variant JSON metafield, and product summary metafields.
     *
     * @return array{
     *     metal_value: float,
     *     diamond_value: float,
     *     metal_rate: float,
     *     diamond_rate: float,
     *     metal_weight: float,
     *     diamond_weight: float,
     *     making_charge: float,
     *     subtotal: float,
     *     tax_percent: float,
     *     tax_value: float,
     *     grand_total: float
     * }
     */
    private function calculateVariantBreakupCore(VariantPriceEntry $entry): array
    {
        $settings = PriceSetting::first();
        $taxPercent = (float) ($settings->tax_percent ?? 0);

        $diamondParts = array_map('trim', explode('|', (string) ($entry->diamond_quality_value ?? '')));
        $diamondQuality = $diamondParts[0] ?? '';
        $diamondColor = $diamondParts[1] ?? '';
        $diamondRateFromValue = (float) ($diamondParts[4] ?? 0);

        $diamondRate = $diamondRateFromValue;
        if ($diamondRate <= 0 && $diamondQuality !== '' && $diamondColor !== '') {
            $match = DiamondPrice::query()
                ->where('quality', $diamondQuality)
                ->where('color', $diamondColor)
                ->first();
            $diamondRate = (float) ($match->price ?? 0);
        }

        $metalRate = match ($entry->metal_type) {
            'gold' => (float) ($settings?->{$entry->gold_karat ? "gold_{$entry->gold_karat}" : 'gold_10kt'} ?? 0),
            'silver' => (float) ($settings->silver_price ?? 0),
            'platinum' => (float) ($settings->platinum_price ?? 0),
            default => 0.0,
        };

        $metalWeight = (float) $entry->metal_weight;
        $diamondWeight = (float) $entry->diamond_weight;
        $makingCharge = (float) $entry->making_charge;

        $metalValue = $metalWeight * $metalRate;
        $diamondValue = $diamondWeight * $diamondRate;
        $subtotal = $metalValue + $diamondValue + $makingCharge;
        $taxValue = ($subtotal * $taxPercent) / 100;
        $grandTotal = round((float) $entry->computed_total, 2) > 0
            ? round((float) $entry->computed_total, 2)
            : round($subtotal + $taxValue, 2);

        return [
            'metal_value' => round($metalValue, 2),
            'diamond_value' => round($diamondValue, 2),
            'metal_rate' => round($metalRate, 2),
            'diamond_rate' => round($diamondRate, 2),
            'metal_weight' => round($metalWeight, 3),
            'diamond_weight' => round($diamondWeight, 3),
            'making_charge' => round($makingCharge, 2),
            'subtotal' => round($subtotal, 2),
            'tax_percent' => round($taxPercent, 2),
            'tax_value' => round($taxValue, 2),
            'grand_total' => $grandTotal,
        ];
    }

    private function syncProductBreakupMetafields(
        Request $request,
        string $shopDomain,
        string $accessToken,
        int $variantId,
        VariantPriceEntry $entry
    ): void {
        try {
            $variantResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Accept' => 'application/json',
            ])->get("https://{$shopDomain}/admin/api/2024-01/variants/{$variantId}.json");

            if (! $variantResponse->ok()) {
                return;
            }

            $productId = (int) data_get($variantResponse->json(), 'variant.product_id');
            if ($productId <= 0) {
                return;
            }

            $core = $this->calculateVariantBreakupCore($entry);
            $materialTotal = $core['metal_value'] + $core['diamond_value'];

            $metafields = app(ShopifyProductBreakupMetafields::class);
            $metafields->syncProductMetafields($shopDomain, $accessToken, $productId, [
                'gold_price' => $materialTotal,
                'making_charges' => $core['making_charge'],
                'gst' => $core['tax_value'],
                'total_price' => $core['grand_total'],
            ]);

            $payload = $this->buildBreakupPayloadFromEntry($entry, $request, $variantId, $shopDomain);
            $metafields->syncVariantPriceBreakupJson($shopDomain, $accessToken, $variantId, $payload);
        } catch (\Throwable $e) {
            Log::warning('Product breakup metafield sync failed', [
                'shop' => $shopDomain,
                'variant_id' => $variantId,
                'message' => $e->getMessage(),
            ]);
        }
    }


}

<?php

namespace App\Http\Controllers;

use App\Models\DiamondPrice;
use App\Models\PriceSetting;
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
        $shop = $request->query('shop');

        if (!$shop) {
            return $default;
        }

        $accessToken = DB::table('shops')
            ->where('shop', $shop)
            ->value('access_token');

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


}

<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Services\ShopifyBillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(private readonly ShopifyBillingService $billingService) {}

    public function pricing()
    {
        return view('app');
    }

    public function plans(Request $request)
    {
        $shopDomain = $request->query('shop');
        $shop = Shop::query()
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->first();

        return response()->json([
            'plans' => config('plans', []),
            'current_plan' => $shop?->plan ?? 'free',
            'is_active' => (bool) ($shop?->is_active ?? false),
            'shop' => $shop?->shop ?? $shopDomain,
        ]);
    }

    public function dashboardSummary(Request $request)
    {
        $shopDomain = (string) ($request->query('shop') ?: '');
        $shop = Shop::query()
            ->when($shopDomain !== '', fn ($query) => $query->where('shop', $shopDomain))
            ->first();

        if (! $shop) {
            return response()->json([
                'shop' => $shopDomain,
                'plan' => 'free',
                'plan_details' => config('plans.free', []),
                'is_active' => false,
                'product_count' => 0,
                'product_limit' => (int) config('plans.free.product_limit', 10),
                'usage_percent' => 0,
            ]);
        }

        $plan = $shop->plan ?: 'free';
        $planDetails = config("plans.{$plan}", config('plans.free', []));
        $productCount = 0;

        if (filled($shop->access_token)) {
            $countResponse = Http::withHeaders([
                'X-Shopify-Access-Token' => $shop->access_token,
                'Accept' => 'application/json',
            ])->get("https://{$shop->shop}/admin/api/2024-01/products/count.json");

            if ($countResponse->ok()) {
                $productCount = (int) data_get($countResponse->json(), 'count', 0);
            }
        }

        $productLimit = data_get($planDetails, 'product_limit');
        $usagePercent = $productLimit ? min(100, (int) round(($productCount / max(1, (int) $productLimit)) * 100)) : 0;

        return response()->json([
            'shop' => $shop->shop,
            'plan' => $plan,
            'plan_details' => $planDetails,
            'is_active' => (bool) $shop->is_active,
            'product_count' => $productCount,
            'product_limit' => $productLimit,
            'usage_percent' => $usagePercent,
        ]);
    }

    public function createCharge(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop' => ['required', 'string'],
            'plan' => ['required', 'string', Rule::in(array_keys(config('plans', [])))],
            'host' => ['nullable', 'string'],
        ]);

        $shop = Shop::query()
            ->where('shop', $validated['shop'])
            ->firstOrFail();

        $plan = $validated['plan'];
        Log::info('Billing createCharge request', [
            'shop' => $validated['shop'],
            'plan' => $plan,
        ]);

        if ($plan === 'free') {
            if (! $this->billingService->isDevelopmentStore($shop)) {
                return redirect()->route('billing.plane', [
                    'shop' => $validated['shop'],
                    'host' => $validated['host'] ?? $request->query('host'),
                ])
                    ->with('error', 'Free plan is only available for development stores.');
            }

            $shop->update([
                'plan' => 'free',
                'charge_id' => null,
                'is_active' => true,
            ]);

            return redirect()->route('app.home', [
                'shop' => $validated['shop'],
                'host' => $validated['host'] ?? $request->query('host'),
            ])
                ->with('success', 'Free plan activated.');
        }

        if (empty($shop->access_token)) {
            return redirect()->route('billing.plane', [
                'shop' => $validated['shop'],
                'host' => $validated['host'] ?? $request->query('host'),
            ])
                ->with('error', 'Missing store access token. Please reconnect the app.');
        }

        $charge = $this->billingService->createCharge(
            $shop->shop,
            $shop->access_token,
            $plan,
            $validated['host'] ?? $request->query('host')
        );
        $shop->update([
            'plan' => $plan,
            'charge_id' => (string) $charge['charge_id'],
            'is_active' => false,
        ]);

        return redirect()->away($charge['confirmation_url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'shop' => ['required', 'string'],
            'charge_id' => ['required', 'string'],
            'plan' => ['required', 'string', Rule::in(array_keys(config('plans', [])))],
            'host' => ['nullable', 'string'],
        ]);

        $shop = Shop::query()
            ->where('shop', $validated['shop'])
            ->firstOrFail();

        Log::info('Billing callback request', [
            'shop' => $validated['shop'],
            'plan' => $validated['plan'],
            'charge_id' => $validated['charge_id'],
        ]);

        $this->billingService->activateCharge($shop->shop, $shop->access_token, $validated['charge_id']);

        $shop->update([
            'plan' => $validated['plan'],
            'charge_id' => (string) $validated['charge_id'],
            'is_active' => true,
        ]);

        return redirect()->route('app.home', [
            'shop' => $validated['shop'],
            'host' => $validated['host'] ?? $request->query('host'),
        ])
            ->with('success', 'Billing activated successfully.');
    }
}

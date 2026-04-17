<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopBillingIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $shopDomain = $request->query('shop') ?: $request->input('shop');
        $shop = Shop::query()
            ->when($shopDomain, fn ($query) => $query->where('shop', $shopDomain))
            ->first();

        if (! $shop) {
            return redirect()->route('billing.pricing', $request->query())
                ->with('error', 'Please connect your store first.');
        }

        if ($shop->plan === 'free' || $shop->is_active) {
            return $next($request);
        }

        return redirect()->route('billing.pricing', $request->query())
            ->with('error', 'Please activate a plan to continue.');
    }
}

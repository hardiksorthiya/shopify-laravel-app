<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PlanLimitService
{
    private const API_VERSION = '2024-01';

    public function enforceProductLimit(Shop $shop): void
    {
        $plan = $shop->plan ?: 'free';
        $limit = config("plans.{$plan}.product_limit");

        if ($limit === null) {
            return;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Accept' => 'application/json',
        ])->get("https://{$shop->shop}/admin/api/".self::API_VERSION."/products/count.json");

        if (! $response->ok()) {
            throw new RuntimeException('Unable to check product limit right now.');
        }

        $count = (int) data_get($response->json(), 'count', 0);
        if ($count > (int) $limit) {
            throw new RuntimeException("Your {$plan} plan allows up to {$limit} products. Current store has {$count} products.");
        }
    }
}

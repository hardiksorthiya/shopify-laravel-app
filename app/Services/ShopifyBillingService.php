<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ShopifyBillingService
{
    private const API_VERSION = '2024-01';

    /**
     * @return array{confirmation_url: string, charge_id: string}
     */
    public function createCharge(string $shopDomain, string $accessToken, string $plan, ?string $host = null): array
    {
        $planConfig = config("plans.{$plan}");
        if (! is_array($planConfig)) {
            throw new RuntimeException('Invalid plan selected.');
        }

        $returnUrlParams = [
            'shop' => $shopDomain,
            'plan' => $plan,
        ];

        if (filled($host)) {
            $returnUrlParams['host'] = $host;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/".self::API_VERSION."/recurring_application_charges.json", [
            'recurring_application_charge' => [
                'name' => Arr::get($planConfig, 'name', ucfirst($plan)),
                'price' => (float) Arr::get($planConfig, 'price', 0),
                'return_url' => route('billing.callback', $returnUrlParams),
                'trial_days' => 7,
                'test' => true,
            ],
        ]);

        if (! $response->ok()) {
            throw new RuntimeException('Shopify charge creation failed: '.$response->body());
        }

        $charge = $response->json('recurring_application_charge');
        $confirmationUrl = (string) data_get($charge, 'confirmation_url', '');
        $chargeId = (string) data_get($charge, 'id', '');

        if ($confirmationUrl === '' || $chargeId === '') {
            throw new RuntimeException('Shopify did not return a valid charge payload.');
        }

        return [
            'confirmation_url' => $confirmationUrl,
            'charge_id' => $chargeId,
        ];
    }

    public function activateCharge(string $shopDomain, string $accessToken, string|int $chargeId): array
    {
        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/".self::API_VERSION."/recurring_application_charges/{$chargeId}/activate.json");

        if (! $response->ok()) {
            throw new RuntimeException('Failed to activate charge: '.$response->body());
        }

        return $response->json('recurring_application_charge', []);
    }

    public function isDevelopmentStore(Shop $shop): bool
    {
        if (! filled($shop->access_token)) {
            return false;
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $shop->access_token,
            'Accept' => 'application/json',
        ])->get("https://{$shop->shop}/admin/api/".self::API_VERSION."/shop.json");

        if (! $response->ok()) {
            return false;
        }

        $planName = strtolower((string) data_get($response->json(), 'shop.plan_name', ''));
        $planDisplayName = strtolower((string) data_get($response->json(), 'shop.plan_display_name', ''));
        $isPartnerTest = (bool) data_get($response->json(), 'shop.partner_test', false);

        return $isPartnerTest
            || str_contains($planName, 'development')
            || str_contains($planName, 'partner_test')
            || str_contains($planDisplayName, 'development')
            || str_contains($planDisplayName, 'partner test');
    }
}

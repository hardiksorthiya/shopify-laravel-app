<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function compliance(Request $request): Response
    {
        $rawBody = $request->getContent();
        $hmacHeader = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, (string) env('SHOPIFY_API_SECRET'), true));

        if (! hash_equals($hmacHeader, $calculatedHmac)) {
            Log::warning('Invalid Shopify webhook signature', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);

            return response('Invalid signature', 401);
        }

        $topic = (string) $request->header('X-Shopify-Topic', '');
        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $payload = $request->json()->all();

        // Compliance webhooks must return quickly with 200 after processing.
        match ($topic) {
            'shop/redact' => $this->handleShopRedact($shopDomain),
            'customers/redact', 'customers/data_request' => $this->logCustomerCompliance($topic, $shopDomain, $payload),
            default => Log::info('Unhandled webhook topic', ['topic' => $topic, 'shop' => $shopDomain]),
        };

        return response('OK', 200);
    }

    private function handleShopRedact(string $shopDomain): void
    {
        if ($shopDomain !== '') {
            Shop::query()->where('shop', $shopDomain)->delete();
        }

        Log::info('Processed shop/redact webhook', ['shop' => $shopDomain]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function logCustomerCompliance(string $topic, string $shopDomain, array $payload): void
    {
        Log::info('Processed customer compliance webhook', [
            'topic' => $topic,
            'shop' => $shopDomain,
            'shop_id' => data_get($payload, 'shop_id'),
            'customer_id' => data_get($payload, 'customer.id') ?? data_get($payload, 'customer_id'),
            'orders_requested' => data_get($payload, 'orders_requested'),
        ]);
    }
}

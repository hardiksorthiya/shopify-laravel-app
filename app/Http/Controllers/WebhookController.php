<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookController extends Controller
{
    public function customersDataRequest(Request $request): Response
    {
        if (! $this->isValidWebhookRequest($request)) {
            return response('Invalid signature', 401);
        }

        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $payload = $request->json()->all();

        Log::info('Processed customers/data_request webhook', [
            'shop' => $shopDomain,
            'shop_id' => data_get($payload, 'shop_id'),
            'customer_id' => data_get($payload, 'customer.id') ?? data_get($payload, 'customer_id'),
            'orders_requested' => data_get($payload, 'orders_requested'),
        ]);

        return response('OK', 200);
    }

    public function customersRedact(Request $request): Response
    {
        if (! $this->isValidWebhookRequest($request)) {
            return response('Invalid signature', 401);
        }

        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');
        $payload = $request->json()->all();

        Log::info('Processed customers/redact webhook', [
            'shop' => $shopDomain,
            'shop_id' => data_get($payload, 'shop_id'),
            'customer_id' => data_get($payload, 'customer.id') ?? data_get($payload, 'customer_id'),
        ]);

        return response('OK', 200);
    }

    public function shopRedact(Request $request): Response
    {
        if (! $this->isValidWebhookRequest($request)) {
            return response('Invalid signature', 401);
        }

        $shopDomain = (string) $request->header('X-Shopify-Shop-Domain', '');

        if ($shopDomain !== '') {
            Shop::query()->where('shop', $shopDomain)->delete();
        }

        Log::info('Processed shop/redact webhook', ['shop' => $shopDomain]);

        return response('OK', 200);
    }

    /**
     * Verify Shopify webhook HMAC signature.
     */
    private function isValidWebhookRequest(Request $request): bool
    {
        $secret = (string) env('SHOPIFY_API_SECRET', '');
        if ($secret === '') {
            Log::error('SHOPIFY_API_SECRET is missing for webhook verification.');

            return false;
        }

        $rawBody = $request->getContent();
        $hmacHeader = (string) $request->header('X-Shopify-Hmac-Sha256', '');
        $calculatedHmac = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

        $isValid = hash_equals($hmacHeader, $calculatedHmac);
        if (! $isValid) {
            Log::warning('Invalid Shopify webhook signature', [
                'topic' => $request->header('X-Shopify-Topic'),
                'shop' => $request->header('X-Shopify-Shop-Domain'),
            ]);
        }

        return $isValid;
    }
}

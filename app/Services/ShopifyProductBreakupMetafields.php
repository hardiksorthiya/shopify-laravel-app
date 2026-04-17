<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Creates metafield definitions and syncs product summary + full variant JSON via Admin GraphQL.
 */
class ShopifyProductBreakupMetafields
{
    public const NAMESPACE = 'custom';

    public const VARIANT_JSON_KEY = 'app_price_breakup';

    /** @var array<string, string> key => admin label */
    private const PRODUCT_DEFINITIONS = [
        'gold_price' => 'Price breakup — Metal & stone value',
        'making_charges' => 'Price breakup — Making charges',
        'gst' => 'Price breakup — GST',
        'total_price' => 'Price breakup — Total',
    ];

    public function ensureProductDefinitions(string $shopDomain, string $accessToken): void
    {
        foreach (self::PRODUCT_DEFINITIONS as $key => $name) {
            $this->createDefinitionIfMissing(
                $shopDomain,
                $accessToken,
                $key,
                $name,
                'PRODUCT',
                'number_decimal'
            );
        }
    }

    /**
     * @param  array{gold_price: float|int, making_charges: float|int, gst: float|int, total_price: float|int}  $amounts
     */
    public function syncProductMetafields(string $shopDomain, string $accessToken, int $productId, array $amounts): void
    {
        $this->ensureProductDefinitions($shopDomain, $accessToken);

        $ownerId = 'gid://shopify/Product/'.$productId;
        $inputs = [];

        foreach (['gold_price', 'making_charges', 'gst', 'total_price'] as $k) {
            if (! array_key_exists($k, $amounts)) {
                continue;
            }
            $inputs[] = [
                'ownerId' => $ownerId,
                'namespace' => self::NAMESPACE,
                'key' => $k,
                'type' => 'number_decimal',
                'value' => (string) round((float) $amounts[$k], 2),
            ];
        }

        if ($inputs === []) {
            return;
        }

        $this->runMetafieldsSet($shopDomain, $accessToken, $inputs);
    }

    /**
     * Full breakup payload (same JSON as storefront API) for the variant — used by theme / integrations.
     *
     * @param  array<string, mixed>  $payload
     */
    public function syncVariantPriceBreakupJson(string $shopDomain, string $accessToken, int $variantId, array $payload): void
    {
        $this->createDefinitionIfMissing(
            $shopDomain,
            $accessToken,
            self::VARIANT_JSON_KEY,
            'App price breakup (JSON, auto-managed)',
            'PRODUCT_VARIANT',
            'json'
        );

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            Log::warning('Failed to encode variant price breakup JSON');

            return;
        }

        $ownerId = 'gid://shopify/ProductVariant/'.$variantId;
        $this->runMetafieldsSet($shopDomain, $accessToken, [[
            'ownerId' => $ownerId,
            'namespace' => self::NAMESPACE,
            'key' => self::VARIANT_JSON_KEY,
            'type' => 'json',
            'value' => $json,
        ]]);
    }

    /**
     * @param  list<array<string, string>>  $inputs
     */
    private function runMetafieldsSet(string $shopDomain, string $accessToken, array $inputs): void
    {
        $mutation = <<<'GQL'
mutation metafieldsSet($metafields: [MetafieldsSetInput!]!) {
  metafieldsSet(metafields: $metafields) {
    metafields { id }
    userErrors { field message code }
  }
}
GQL;

        $response = $this->graphql($shopDomain, $accessToken, $mutation, [
            'metafields' => $inputs,
        ]);

        if (! empty($response['errors'])) {
            Log::warning('Shopify metafieldsSet GraphQL errors', ['errors' => $response['errors']]);

            return;
        }

        $userErrors = data_get($response, 'data.metafieldsSet.userErrors', []);
        if ($userErrors !== []) {
            Log::warning('Shopify metafieldsSet userErrors', ['userErrors' => $userErrors]);
        }
    }

    private function createDefinitionIfMissing(
        string $shopDomain,
        string $accessToken,
        string $key,
        string $name,
        string $ownerType,
        string $type
    ): void {
        $mutation = <<<'GQL'
mutation metafieldDefinitionCreate($definition: MetafieldDefinitionInput!) {
  metafieldDefinitionCreate(definition: $definition) {
    createdDefinition { id }
    userErrors { field message code }
  }
}
GQL;

        $variables = [
            'definition' => [
                'name' => $name,
                'namespace' => self::NAMESPACE,
                'key' => $key,
                'description' => 'Managed automatically by the app when variant prices are saved.',
                'type' => $type,
                'ownerType' => $ownerType,
            ],
        ];

        $response = $this->graphql($shopDomain, $accessToken, $mutation, $variables);

        $userErrors = data_get($response, 'data.metafieldDefinitionCreate.userErrors', []);
        foreach ($userErrors as $err) {
            $code = strtoupper((string) ($err['code'] ?? ''));
            $message = strtolower((string) ($err['message'] ?? ''));
            if ($code === 'TAKEN' || str_contains($message, 'taken') || str_contains($message, 'already been taken')) {
                return;
            }
        }

        if (! empty($response['errors'])) {
            Log::warning('metafieldDefinitionCreate GraphQL errors', ['key' => $key, 'response' => $response]);
        }
    }

    private function graphql(string $shopDomain, string $accessToken, string $query, array $variables = []): array
    {
        $res = Http::withHeaders([
            'X-Shopify-Access-Token' => $accessToken,
            'Content-Type' => 'application/json',
        ])->post("https://{$shopDomain}/admin/api/2024-01/graphql.json", [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (! $res->ok()) {
            Log::warning('Shopify GraphQL HTTP error', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);

            return [];
        }

        return $res->json() ?? [];
    }
}

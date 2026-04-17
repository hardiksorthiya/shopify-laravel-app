<?php

return [
    'free' => [
        'name' => 'Free',
        'price' => 0,
        'product_limit' => 10,
        'dev_only' => true,
        'features' => [
            "Price Breakup Display",
            "Variant Pricing",
            "Metal & Diamond Calculation",
            "Basic Support",
            "Impoer and Export Pricing",
            "10 Products Limit",
            "Development Store Only"
          ],
    ],
    'starter' => [
        'name' => 'Starter',
        'price' => 9,
        'product_limit' => 50,
        'features' => [
            "Price Breakup Display",
            "Variant Pricing",
            "Metal & Diamond Calculation",
            "Impoer and Export Pricing",
            "Basic Support",
            "50 Products Limit",
          ],
    ],
    'growth' => [
        'name' => 'Growth',
        'price' => 30,
        'product_limit' => 200,
        'features' => [
            "Price Breakup Display",
            "Variant Pricing",
            "Metal & Diamond Calculation",
            "Impoer and Export Pricing",
            "Basic Support",
            "200 Products Limit",
          ],
    ],
    'pro' => [
        'name' => 'Pro',
        'price' => 50,
        'product_limit' => null,
        'features' => [
            "Price Breakup Display",
            "Variant Pricing",
            "Metal & Diamond Calculation",
            "Impoer and Export Pricing",
            "Basic Support",
            "Unlimited Products Limit",
          ],
    ],
];

<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ShopifyIframe
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Remove iframe blocking header
        $response->headers->remove('X-Frame-Options');

        // Allow Shopify iframe
        $response->headers->set(
            'Content-Security-Policy',
            "frame-ancestors https://admin.shopify.com https://*.myshopify.com"
        );

        return $response;
    }
}
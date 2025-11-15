<?php

if (!function_exists('shopify_hmac_valid')) {
    function shopify_hmac_valid(array $query): bool {
        $hmac = $query['hmac'] ?? '';
        unset($query['hmac'], $query['signature']); ksort($query);
        $data = urldecode(http_build_query($query));
        $calc = hash_hmac('sha256', $data, config('services.shopify.secret'));
        return hash_equals($hmac, $calc);
    }
}

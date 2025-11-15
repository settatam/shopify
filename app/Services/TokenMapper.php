<?php

namespace App\Services;
use Illuminate\Support\Arr;

class TokenMapper
{
    /** Resolve tokens inside mapping strings, e.g. "{product.title}", supports filters: |fallback:'x' and |limit:8 */
    public static function resolve(string|array $tpl, array $ctx): string|array
    {
        if (is_array($tpl)) {
            return array_map(fn($v) => is_string($v) ? self::resolveString($v, $ctx) : (is_array($v) ? self::resolve($v, $ctx) : $v), $tpl);
        }
        return self::resolveString((string)$tpl, $ctx);
    }

    protected static function resolveString(string $tpl, array $ctx): string
    {
        return preg_replace_callback('/\\{([^}]+)\\}/', function ($m) use ($ctx) {
            $expr = $m[1];
            $parts = explode('|', $expr);
            $path = array_shift($parts);
            $val = Arr::get($ctx, $path);
            foreach ($parts as $p) {
                if (str_starts_with($p, 'fallback:')) {
                    $fallback = trim(substr($p, 9), "'\"");
                    $val = $val !== null && $val !== '' ? $val : $fallback;
                }
                if (str_starts_with($p, 'limit:')) {
                    $n = (int) substr($p, 6);
                    if (is_array($val)) $val = array_slice($val, 0, $n);
                }
            }
            return is_array($val) ? json_encode($val) : (string)($val ?? '');
        }, $tpl);
    }
}

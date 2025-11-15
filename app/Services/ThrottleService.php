<?php

namespace App\Services;

use Illuminate\Support\Facades\RateLimiter;
use Closure;

class ThrottleService
{
    public static function with(string $key, int $maxPerMinute, Closure $cb)
    {
        return RateLimiter::attempt($key, $maxPerMinute, fn() => $cb(), 60);
    }
}

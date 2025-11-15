<?php

namespace App\Support\Http;

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;


class HttpFactory
{
    public static function make(array $opts = []): Client
    {
        $stack = HandlerStack::create();
        $stack->push(self::retryMiddleware());
        return new Client(array_replace_recursive(['handler' => $stack, 'timeout' => 30], $opts));
    }


    protected static function retryMiddleware(): callable
    {
        return Middleware::retry(function ($retries, RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $exception = null) {
            if ($retries >= 5) return false;
            if ($exception) return true; // network errors
            if ($response) {
                $status = $response->getStatusCode();
                if (in_array($status, [429, 500, 502, 503, 504])) return true;
            }
            return false;
        }, function ($retries, RequestInterface $request, ?ResponseInterface $response = null) {
// exp backoff + jitter (ms)
            $base = 500 * (2 ** ($retries - 1));
            return (int) min(5000, $base + random_int(0, 250));
        });
    }
}

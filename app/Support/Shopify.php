<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;

class Shopify
{
    public static function rest(string $shop, string $token, string $path, string $method='GET', array $payload=[]): array
    {
        $url = "https://{$shop}/admin/api/2024-10/".ltrim($path,'/');
        $req = Http::withHeaders(['X-Shopify-Access-Token'=>$token, 'Content-Type'=>'application/json']);
        $res = $req->{$method}($url, $method==='GET' ? $payload : [])->when($method!=='GET', fn($r)=>$req->{$method}($url, $payload));
        return $res->json();
    }


    public static function graphql(string $shop, string $token, string $query, array $variables=[]): array
    {
        $url = "https://{$shop}/admin/api/2024-10/graphql.json";
        return Http::withHeaders(['X-Shopify-Access-Token'=>$token,'Content-Type'=>'application/json'])
            ->post($url, ['query'=>$query,'variables'=>$variables])->json();
    }
}

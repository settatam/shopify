<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use App\Models\Shop;
use App\Models\Channel;
use Inertia\Response;

class AuthController extends Controller
{
    //
    protected function oauthUrl(string $shopDomain, string $state): string
    {
        $key = config('services.shopify.key');
        $scopes = urlencode(config('services.shopify.scopes'));
        $redirect = urlencode(config('services.shopify.app_url') . '/shopify/auth/callback');
        return "https://{$shopDomain}/admin/oauth/authorize?client_id={$key}&scope={$scopes}&redirect_uri={$redirect}&state={$state}&grant_options[]=per-user";
    }


    public function install(Request $r)
    {
        $shop = $r->query('shop');
        if (!$shop) return Inertia::render('Shopify/Install');
        $state = bin2hex(random_bytes(16));
        session(['shopify_oauth_state' => $state, 'shopify_domain' => $shop]);
        return redirect()->away($this->oauthUrl($shop, $state));
    }

    public function callback(Request $r)
    {
        $shop = $r->query('shop');
        $code = $r->query('code');
        $state = $r->query('state');
        abort_unless($state && $state === session('shopify_oauth_state'), 401, 'Invalid state');
// HMAC verify
        $params = $r->query();
        unset($params['signature']);
        $hmac = $params['hmac'] ?? '';
        unset($params['hmac']);
        ksort($params);
        $data = urldecode(http_build_query($params));
        $calc = hash_hmac('sha256', $data, config('services.shopify.secret'));
        abort_unless(hash_equals($hmac, $calc), 401, 'Invalid HMAC');


// Exchange code for token
        $resp = Http::asJson()->post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => config('services.shopify.key'),
            'client_secret' => config('services.shopify.secret'),
            'code' => $code,
        ])->throw()->json();

        $token = $resp['access_token'];
        $scope = $resp['scope'] ?? '';
        $email = null;
// Optionally pull shop info
        $shopInfo = Http::withHeaders(['X-Shopify-Access-Token' => $token])->get("https://{$shop}/admin/api/2024-10/shop.json")->json();
        $email = $shopInfo['shop']['email'] ?? null;


        $s = Shop::updateOrCreate(['shopify_domain' => $shop], ['access_token' => $token, 'scope' => $scope, 'email' => $email]);
        session(['shopify_domain' => $shop]);


// Register webhooks
        $this->registerWebhooks($shop, $token);

// Create a default channel stubs (optional)
// Channel::firstOrCreate(['shop_id'=>$s->id,'type'=>'amazon'],['name'=>'Amazon US','status'=>'disconnected','auth_json'=>[]]);


// (Optional) Billing gate
        if (config('services.shopify.billing_enabled')) {
            $url = $this->ensureSubscription($shop, $token);
            if ($url) return redirect()->away($url);
        }

        // Redirect to app home after successful installation
        return redirect()->route('app.home', ['shop' => $shop]);
    }

    protected function registerWebhooks(string $shop, string $token): void
    {
        $endpoint = config('services.shopify.app_url').'/shopify/webhooks';
        $topics = [
            'products/update',
            'inventory_levels/update',
            'app/uninstalled'
        ];
        foreach ($topics as $topic) {
            Http::withHeaders(['X-Shopify-Access-Token'=>$token])
                ->post("https://{$shop}/admin/api/2024-10/webhooks.json", [
                    'webhook' => [
                        'topic' => $topic,
                        'address' => $endpoint,
                        'format' => 'json'
                    ]
                ]);
        }
    }

    // Minimal Billing: returns confirmation URL or null
    protected function ensureSubscription(string $shop, string $token): ?string
    {
        $q = <<<'GQL'
mutation appSubscriptionCreate($name: String!, $returnUrl: URL!, $test: Boolean, $lineItems: [AppSubscriptionLineItemInput!]!) {
appSubscriptionCreate(name: $name, returnUrl: $returnUrl, test: $test, lineItems: $lineItems) {
confirmationUrl
userErrors { field message }
}
}
GQL;
        $returnUrl = config('services.shopify.app_url').'/app?shop='.$shop;
        $vars = [
            'name' => 'Shopmata Multichannel',
            'returnUrl' => $returnUrl,
            'test' => true,
            'lineItems' => [[ 'plan' => [ 'appRecurringPricingDetails' => [ 'price' => [ 'amount' => 29.0, 'currencyCode' => 'USD' ] ] ] ]]
        ];
        $res = Http::withHeaders([
            'X-Shopify-Access-Token'=>$token,
            'Content-Type'=>'application/json',
        ])->post("https://{$shop}/admin/api/2024-10/graphql.json", ['query'=>$q,'variables'=>$vars])->json();
        return $res['data']['appSubscriptionCreate']['confirmationUrl'] ?? null;
    }

    public function appHome(Request $r): Response
    {
        $shop = $r->query('shop');
        $s = Shop::where('shopify_domain', $shop)->firstOrFail();
        return Inertia::render('Shopify/AppHome', [
            'shop' => [ 'id'=>$s->id, 'domain'=>$s->shopify_domain, 'email'=>$s->email ],
            'apiKey' => config('services.shopify.key'),
        ]);
    }
}

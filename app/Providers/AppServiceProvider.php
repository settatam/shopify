<?php

namespace App\Providers;

use App\Models\ChannelOrder;
use App\Models\Product;
use App\Models\Order;
use App\Models\Channel;
use App\Models\Inventory;
use App\Models\Shop;
use App\Models\User;
use App\Models\TeamInvitation;
use App\Models\NotificationTemplate;
use App\Models\NotificationLog;
use App\Observers\ChannelOrderObserver;
use App\Policies\ProductPolicy;
use App\Policies\OrderPolicy;
use App\Policies\ChannelPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\ShopPolicy;
use App\Policies\UserPolicy;
use App\Policies\TeamInvitationPolicy;
use App\Policies\NotificationTemplatePolicy;
use App\Policies\NotificationLogPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        // Register model observers
        ChannelOrder::observe(ChannelOrderObserver::class);

        // Register authorization policies
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Channel::class, ChannelPolicy::class);
        Gate::policy(Inventory::class, InventoryPolicy::class);
        Gate::policy(Shop::class, ShopPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(TeamInvitation::class, TeamInvitationPolicy::class);
        Gate::policy(NotificationTemplate::class, NotificationTemplatePolicy::class);
        Gate::policy(NotificationLog::class, NotificationLogPolicy::class);
    }
}

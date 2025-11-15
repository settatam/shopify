<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class NotificationEventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $events = [
            // Orders Category
            [
                'event_type' => 'order.placed',
                'name' => 'Order Placed',
                'description' => 'Triggered when a new order is created',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'customer_email' => 'Customer email address',
                    'order_number' => 'Order reference number',
                    'order_total' => 'Total order amount',
                    'order_date' => 'Order date',
                    'product_name' => 'Product name',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'order.paid',
                'name' => 'Order Paid',
                'description' => 'Triggered when payment is confirmed',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'order_number' => 'Order reference number',
                    'order_total' => 'Total order amount',
                    'payment_method' => 'Payment method used',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'order.shipped',
                'name' => 'Order Shipped',
                'description' => 'Triggered when order is shipped',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'order_number' => 'Order reference number',
                    'tracking_number' => 'Shipping tracking number',
                    'tracking_url' => 'Tracking URL',
                    'carrier' => 'Shipping carrier',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'order.delivered',
                'name' => 'Order Delivered',
                'description' => 'Triggered when order is delivered',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'order_number' => 'Order reference number',
                    'delivery_date' => 'Delivery date',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'order.cancelled',
                'name' => 'Order Cancelled',
                'description' => 'Triggered when order is cancelled',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'order_number' => 'Order reference number',
                    'cancellation_reason' => 'Reason for cancellation',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'order.refunded',
                'name' => 'Order Refunded',
                'description' => 'Triggered when order is refunded',
                'category' => 'orders',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'order_number' => 'Order reference number',
                    'refund_amount' => 'Refund amount',
                ]),
                'enabled_by_default' => true,
            ],

            // Inventory Category
            [
                'event_type' => 'inventory.low_stock',
                'name' => 'Low Stock Alert',
                'description' => 'Triggered when inventory falls below threshold',
                'category' => 'inventory',
                'required_permission' => 'manage_inventory',
                'available_variables' => json_encode([
                    'product_name' => 'Product name',
                    'product_sku' => 'Product SKU',
                    'quantity' => 'Current quantity',
                    'threshold' => 'Low stock threshold',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'inventory.out_of_stock',
                'name' => 'Out of Stock',
                'description' => 'Triggered when product is out of stock',
                'category' => 'inventory',
                'required_permission' => 'manage_inventory',
                'available_variables' => json_encode([
                    'product_name' => 'Product name',
                    'product_sku' => 'Product SKU',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'inventory.restocked',
                'name' => 'Product Restocked',
                'description' => 'Triggered when product is restocked',
                'category' => 'inventory',
                'required_permission' => 'manage_inventory',
                'available_variables' => json_encode([
                    'product_name' => 'Product name',
                    'product_sku' => 'Product SKU',
                    'new_quantity' => 'New quantity',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'inventory.sync_failed',
                'name' => 'Inventory Sync Failed',
                'description' => 'Triggered when inventory sync fails',
                'category' => 'inventory',
                'required_permission' => 'manage_inventory',
                'available_variables' => json_encode([
                    'channel_name' => 'Channel name',
                    'error_message' => 'Error message',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],

            // Returns Category
            [
                'event_type' => 'return.created',
                'name' => 'Return Request Created',
                'description' => 'Triggered when return request is submitted',
                'category' => 'returns',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'rma_number' => 'RMA number',
                    'order_number' => 'Order number',
                    'return_reason' => 'Reason for return',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'return.approved',
                'name' => 'Return Request Approved',
                'description' => 'Triggered when return request is approved',
                'category' => 'returns',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'rma_number' => 'RMA number',
                    'return_label_url' => 'Return shipping label URL',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'return.rejected',
                'name' => 'Return Request Rejected',
                'description' => 'Triggered when return request is rejected',
                'category' => 'returns',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'rma_number' => 'RMA number',
                    'rejection_reason' => 'Reason for rejection',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'return.received',
                'name' => 'Return Item Received',
                'description' => 'Triggered when return item is received',
                'category' => 'returns',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'rma_number' => 'RMA number',
                    'received_date' => 'Date received',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'return.refund_processed',
                'name' => 'Return Refund Processed',
                'description' => 'Triggered when refund is processed',
                'category' => 'returns',
                'required_permission' => 'manage_orders',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer full name',
                    'rma_number' => 'RMA number',
                    'refund_amount' => 'Refund amount',
                    'refund_method' => 'Refund method',
                ]),
                'enabled_by_default' => true,
            ],

            // Channels Category
            [
                'event_type' => 'channel.connected',
                'name' => 'Channel Connected',
                'description' => 'Triggered when channel is successfully connected',
                'category' => 'channels',
                'required_permission' => 'manage_channels',
                'available_variables' => json_encode([
                    'channel_name' => 'Channel name',
                    'channel_type' => 'Channel type',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'channel.disconnected',
                'name' => 'Channel Disconnected',
                'description' => 'Triggered when channel is disconnected',
                'category' => 'channels',
                'required_permission' => 'manage_channels',
                'available_variables' => json_encode([
                    'channel_name' => 'Channel name',
                    'channel_type' => 'Channel type',
                    'reason' => 'Disconnection reason',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'channel.sync_error',
                'name' => 'Channel Sync Error',
                'description' => 'Triggered when channel sync fails',
                'category' => 'channels',
                'required_permission' => 'manage_channels',
                'available_variables' => json_encode([
                    'channel_name' => 'Channel name',
                    'error_message' => 'Error message',
                    'products_affected' => 'Number of products affected',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'channel.listing_created',
                'name' => 'Listing Created',
                'description' => 'Triggered when new listing is published',
                'category' => 'channels',
                'required_permission' => 'manage_products',
                'available_variables' => json_encode([
                    'product_name' => 'Product name',
                    'channel_name' => 'Channel name',
                    'listing_url' => 'Listing URL',
                ]),
                'enabled_by_default' => true,
            ],

            // System Category
            [
                'event_type' => 'system.welcome',
                'name' => 'Welcome Email',
                'description' => 'Triggered when new user signs up',
                'category' => 'system',
                'required_permission' => null, // Everyone gets this
                'available_variables' => json_encode([
                    'user_name' => 'User name',
                    'shop_name' => 'Shop name',
                    'shop_url' => 'Shop URL',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'system.password_reset',
                'name' => 'Password Reset',
                'description' => 'Triggered when password reset is requested',
                'category' => 'system',
                'required_permission' => null, // Everyone gets this
                'available_variables' => json_encode([
                    'user_name' => 'User name',
                    'reset_link' => 'Password reset link',
                    'expiry_time' => 'Link expiry time',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'system.invoice',
                'name' => 'Invoice Generated',
                'description' => 'Triggered when monthly invoice is generated',
                'category' => 'system',
                'required_permission' => 'manage_billing',
                'available_variables' => json_encode([
                    'shop_name' => 'Shop name',
                    'invoice_number' => 'Invoice number',
                    'amount_due' => 'Amount due',
                    'due_date' => 'Due date',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'system.trial_ending',
                'name' => 'Trial Ending',
                'description' => 'Triggered when trial period is ending soon',
                'category' => 'system',
                'required_permission' => 'manage_billing',
                'available_variables' => json_encode([
                    'shop_name' => 'Shop name',
                    'days_remaining' => 'Days remaining',
                    'upgrade_url' => 'Upgrade URL',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'system.subscription_renewed',
                'name' => 'Subscription Renewed',
                'description' => 'Triggered when subscription is renewed',
                'category' => 'system',
                'required_permission' => 'manage_billing',
                'available_variables' => json_encode([
                    'shop_name' => 'Shop name',
                    'plan_name' => 'Plan name',
                    'next_billing_date' => 'Next billing date',
                ]),
                'enabled_by_default' => true,
            ],
            [
                'event_type' => 'system.team_member_joined',
                'name' => 'Team Member Joined',
                'description' => 'Triggered when team member accepts invitation',
                'category' => 'system',
                'required_permission' => 'manage_team',
                'available_variables' => json_encode([
                    'member_name' => 'Team member name',
                    'member_email' => 'Team member email',
                    'member_role' => 'Assigned role',
                    'shop_name' => 'Shop name',
                ]),
                'enabled_by_default' => true,
            ],

            // Marketing Category
            [
                'event_type' => 'marketing.abandoned_cart',
                'name' => 'Abandoned Cart',
                'description' => 'Triggered when cart is abandoned',
                'category' => 'marketing',
                'required_permission' => 'view_reports',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer name',
                    'cart_total' => 'Cart total',
                    'cart_url' => 'Cart recovery URL',
                    'products' => 'Products in cart',
                ]),
                'enabled_by_default' => false,
            ],
            [
                'event_type' => 'marketing.product_recommendation',
                'name' => 'Product Recommendation',
                'description' => 'Triggered for product recommendations',
                'category' => 'marketing',
                'required_permission' => 'manage_products',
                'available_variables' => json_encode([
                    'customer_name' => 'Customer name',
                    'recommended_products' => 'Recommended products',
                ]),
                'enabled_by_default' => false,
            ],
            [
                'event_type' => 'marketing.sale_announcement',
                'name' => 'Sale Announcement',
                'description' => 'Triggered for sale or promotion announcements',
                'category' => 'marketing',
                'required_permission' => 'manage_products',
                'available_variables' => json_encode([
                    'shop_name' => 'Shop name',
                    'sale_title' => 'Sale title',
                    'discount_amount' => 'Discount amount',
                    'sale_url' => 'Sale URL',
                ]),
                'enabled_by_default' => false,
            ],
        ];

        foreach ($events as $event) {
            DB::table('notification_events')->updateOrInsert(
                ['event_type' => $event['event_type']],
                array_merge($event, [
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'name',
        'description',
        'category',
        'required_permission',
        'available_variables',
        'is_system',
        'enabled_by_default',
        'settings',
    ];

    protected $casts = [
        'available_variables' => 'array',
        'settings' => 'array',
        'is_system' => 'boolean',
        'enabled_by_default' => 'boolean',
    ];

    /**
     * Get template for this event type.
     */
    public function getTemplateFor(Shop $shop): ?NotificationTemplate
    {
        return NotificationTemplate::where('shop_id', $shop->id)
            ->where('event_type', $this->event_type)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if this event is enabled for a user.
     */
    public function isEnabledFor(User $user, string $channel = 'email'): bool
    {
        $preference = NotificationPreference::forUser($user)
            ->forEvent($this->event_type)
            ->first();

        if (!$preference) {
            return $this->enabled_by_default;
        }

        return $preference->isEnabledFor($channel);
    }

    /**
     * Get variable description with type.
     */
    public function getVariableInfo(): array
    {
        $info = [];

        foreach ($this->available_variables as $variable => $description) {
            $info[] = [
                'name' => $variable,
                'description' => $description,
                'example' => $this->getVariableExample($variable),
            ];
        }

        return $info;
    }

    /**
     * Get example value for a variable.
     */
    protected function getVariableExample(string $variable): string
    {
        return match ($variable) {
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
            'order_number' => 'ORD-12345',
            'order_total' => '$99.99',
            'product_name' => 'Sample Product',
            'product_sku' => 'SKU-123',
            'quantity' => '5',
            'tracking_number' => '1Z999AA10123456784',
            'rma_number' => 'RMA-12345',
            'refund_amount' => '$50.00',
            'shop_name' => 'My Store',
            'support_email' => 'support@mystore.com',
            default => 'Example Value',
        };
    }

    /**
     * Scope: Filter by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: System events only.
     */
    public function scopeSystemOnly($query)
    {
        return $query->where('is_system', true);
    }

    /**
     * Scope: Non-system (custom) events.
     */
    public function scopeCustomOnly($query)
    {
        return $query->where('is_system', false);
    }
}

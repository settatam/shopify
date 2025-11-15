<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'cash_register_id',
        'location_id',
        'transaction_number',
        'payment_method',
        'subtotal',
        'tax',
        'discount',
        'total',
        'amount_tendered',
        'change_given',
        'check_number',
        'customer_name',
        'customer_email',
        'customer_phone',
        'notes',
        'processed_by_user_id',
        'line_items',
        'completed_at',
        'status',
        'voided_at',
        'voided_by_user_id',
        'void_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_tendered' => 'decimal:2',
        'change_given' => 'decimal:2',
        'line_items' => 'array',
        'completed_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            if (!$transaction->transaction_number) {
                $transaction->transaction_number = static::generateTransactionNumber();
            }

            if (!$transaction->completed_at) {
                $transaction->completed_at = now();
            }
        });
    }

    /**
     * Generate a unique transaction number
     */
    public static function generateTransactionNumber(): string
    {
        $prefix = 'POS-';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));

        return $prefix . $timestamp . '-' . $random;
    }

    /**
     * Get the shop that owns the transaction
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the cash register associated with the transaction
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * Get the location associated with the transaction
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who processed the transaction
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by_user_id');
    }

    /**
     * Get the user who voided the transaction
     */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by_user_id');
    }

    /**
     * Check if transaction is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if transaction is voided
     */
    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    /**
     * Void the transaction
     */
    public function void(User $user, string $reason): void
    {
        $this->update([
            'status' => 'voided',
            'voided_at' => now(),
            'voided_by_user_id' => $user->id,
            'void_reason' => $reason,
        ]);

        // Restore inventory
        foreach ($this->line_items as $item) {
            if (isset($item['product_variant_id'])) {
                $variant = ProductVariant::find($item['product_variant_id']);
                if ($variant) {
                    $stockItem = $variant->stockItems()->where('location_id', $this->location_id)->first();
                    if ($stockItem) {
                        $stockItem->increment('quantity', $item['quantity']);
                    }
                }
            }
        }

        // Adjust cash register if it was a cash sale
        if ($this->cashRegister && $this->payment_method === 'cash') {
            $balanceBefore = $this->cashRegister->current_balance;
            $balanceAfter = $balanceBefore - $this->total + ($this->change_given ?? 0);

            $this->cashRegister->update([
                'current_balance' => $balanceAfter,
                'expected_balance' => $this->cashRegister->expected_balance - $this->total + ($this->change_given ?? 0),
            ]);

            $this->cashRegister->drawerActivities()->create([
                'shop_id' => $this->shop_id,
                'type' => 'adjustment',
                'amount' => -$this->total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_method' => 'cash',
                'pos_transaction_id' => $this->id,
                'user_id' => $user->id,
                'notes' => "Voided transaction: {$reason}",
            ]);
        }
    }

    /**
     * Get formatted receipt data
     */
    public function getReceiptData(): array
    {
        return [
            'transaction_number' => $this->transaction_number,
            'date' => $this->completed_at?->format('Y-m-d H:i:s'),
            'cashier' => $this->processedBy?->name ?? 'Unknown',
            'customer_name' => $this->customer_name,
            'payment_method' => ucfirst($this->payment_method),
            'check_number' => $this->check_number,
            'items' => $this->line_items,
            'subtotal' => number_format($this->subtotal, 2),
            'tax' => number_format($this->tax, 2),
            'discount' => number_format($this->discount, 2),
            'total' => number_format($this->total, 2),
            'amount_tendered' => $this->amount_tendered ? number_format($this->amount_tendered, 2) : null,
            'change_given' => $this->change_given ? number_format($this->change_given, 2) : null,
            'notes' => $this->notes,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDrawerActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'cash_register_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'payment_method',
        'pos_transaction_id',
        'user_id',
        'notes',
        'reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    /**
     * Get the shop that owns the activity
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the cash register associated with the activity
     */
    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    /**
     * Get the POS transaction associated with the activity
     */
    public function posTransaction(): BelongsTo
    {
        return $this->belongsTo(PosTransaction::class);
    }

    /**
     * Get the user who performed the activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get formatted activity type
     */
    public function getFormattedType(): string
    {
        return match ($this->type) {
            'sale' => 'Sale',
            'cash_in' => 'Cash In',
            'cash_out' => 'Cash Out',
            'opening' => 'Opening Balance',
            'closing' => 'Closing Balance',
            'adjustment' => 'Adjustment',
            default => ucfirst($this->type),
        };
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'location_id',
        'name',
        'status',
        'opening_balance',
        'current_balance',
        'expected_balance',
        'opened_at',
        'closed_at',
        'opened_by_user_id',
        'closed_by_user_id',
        'opening_notes',
        'closing_notes',
        'settings',
    ];

    protected $casts = [
        'opening_balance' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'expected_balance' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Get the shop that owns the cash register
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * Get the location associated with the cash register
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the user who opened the register
     */
    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    /**
     * Get the user who closed the register
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    /**
     * Get all POS transactions for this register
     */
    public function posTransactions(): HasMany
    {
        return $this->hasMany(PosTransaction::class);
    }

    /**
     * Get all cash drawer activities for this register
     */
    public function drawerActivities(): HasMany
    {
        return $this->hasMany(CashDrawerActivity::class);
    }

    /**
     * Check if the register is open
     */
    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Check if the register is closed
     */
    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /**
     * Open the cash register
     */
    public function open(float $openingBalance, User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'open',
            'opening_balance' => $openingBalance,
            'current_balance' => $openingBalance,
            'expected_balance' => $openingBalance,
            'opened_at' => now(),
            'opened_by_user_id' => $user->id,
            'opening_notes' => $notes,
            'closed_at' => null,
            'closed_by_user_id' => null,
            'closing_notes' => null,
        ]);

        // Log opening activity
        $this->drawerActivities()->create([
            'shop_id' => $this->shop_id,
            'type' => 'opening',
            'amount' => $openingBalance,
            'balance_before' => 0,
            'balance_after' => $openingBalance,
            'user_id' => $user->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Close the cash register
     */
    public function close(float $actualBalance, User $user, ?string $notes = null): void
    {
        $this->update([
            'status' => 'closed',
            'current_balance' => $actualBalance,
            'closed_at' => now(),
            'closed_by_user_id' => $user->id,
            'closing_notes' => $notes,
        ]);

        // Log closing activity
        $this->drawerActivities()->create([
            'shop_id' => $this->shop_id,
            'type' => 'closing',
            'amount' => $actualBalance,
            'balance_before' => $this->expected_balance,
            'balance_after' => $actualBalance,
            'user_id' => $user->id,
            'notes' => $notes,
        ]);
    }

    /**
     * Record a cash in transaction
     */
    public function cashIn(float $amount, User $user, ?string $notes = null, ?string $reference = null): void
    {
        $balanceBefore = $this->current_balance;
        $balanceAfter = $balanceBefore + $amount;

        $this->update([
            'current_balance' => $balanceAfter,
            'expected_balance' => $this->expected_balance + $amount,
        ]);

        $this->drawerActivities()->create([
            'shop_id' => $this->shop_id,
            'type' => 'cash_in',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'user_id' => $user->id,
            'notes' => $notes,
            'reference' => $reference,
        ]);
    }

    /**
     * Record a cash out transaction
     */
    public function cashOut(float $amount, User $user, ?string $notes = null, ?string $reference = null): void
    {
        $balanceBefore = $this->current_balance;
        $balanceAfter = $balanceBefore - $amount;

        $this->update([
            'current_balance' => $balanceAfter,
            'expected_balance' => $this->expected_balance - $amount,
        ]);

        $this->drawerActivities()->create([
            'shop_id' => $this->shop_id,
            'type' => 'cash_out',
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'user_id' => $user->id,
            'notes' => $notes,
            'reference' => $reference,
        ]);
    }

    /**
     * Record a sale transaction
     */
    public function recordSale(PosTransaction $transaction): void
    {
        if ($transaction->payment_method === 'cash') {
            $balanceBefore = $this->current_balance;
            $balanceAfter = $balanceBefore + $transaction->total - ($transaction->change_given ?? 0);

            $this->update([
                'current_balance' => $balanceAfter,
                'expected_balance' => $this->expected_balance + $transaction->total - ($transaction->change_given ?? 0),
            ]);

            $this->drawerActivities()->create([
                'shop_id' => $this->shop_id,
                'type' => 'sale',
                'amount' => $transaction->total,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'payment_method' => 'cash',
                'pos_transaction_id' => $transaction->id,
                'user_id' => $transaction->processed_by_user_id,
            ]);
        }
    }

    /**
     * Get cash discrepancy (difference between expected and actual)
     */
    public function getDiscrepancy(): float
    {
        return $this->current_balance - $this->expected_balance;
    }
}

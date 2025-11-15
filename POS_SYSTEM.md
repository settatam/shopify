# Point of Sale (POS) System

This document provides comprehensive information about the Point of Sale (POS) system for in-person cash and check sales.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Cash Register Management](#cash-register-management)
6. [POS Transactions](#pos-transactions)
7. [Cash Drawer Management](#cash-drawer-management)
8. [Inventory Integration](#inventory-integration)
9. [Reporting](#reporting)
10. [API Reference](#api-reference)
11. [Database Schema](#database-schema)
12. [Best Practices](#best-practices)

## Overview

The POS system enables businesses to sell inventory items in-person by accepting cash or check payments. It includes complete cash register management, automatic inventory deduction, transaction tracking, and cash drawer reconciliation.

### Key Benefits

- **In-Person Sales**: Accept cash and check payments for walk-in customers
- **Cash Register Management**: Open/close registers with proper accountability
- **Automatic Inventory**: Inventory automatically decreases on each sale
- **Cash Drawer Tracking**: Track all cash movements (sales, cash in/out, opening/closing)
- **Transaction Voiding**: Void transactions with automatic inventory restoration
- **Multiple Registers**: Support for multiple cash registers per location
- **Accountability**: Track which user performed each operation
- **Reporting**: Sales summaries and cash reconciliation reports

## Features

### Implemented Features

1. **Cash Register Management**
   - Create and manage multiple cash registers
   - Associate registers with specific locations
   - Open/close registers with opening and closing balances
   - Track who opened and closed each register
   - Calculate cash discrepancies automatically

2. **POS Transactions**
   - Quick product lookup by SKU or barcode
   - Support for cash and check payments
   - Calculate subtotal, tax, and discounts
   - Handle change calculation for cash payments
   - Record check numbers
   - Optional customer information
   - Transaction voiding with inventory restoration

3. **Cash Drawer Activities**
   - Track all cash movements
   - Sale transactions
   - Cash in (add money to drawer)
   - Cash out (remove money from drawer)
   - Opening balance
   - Closing balance
   - Adjustments (e.g., from voids)

4. **Inventory Integration**
   - Automatic inventory deduction on sale
   - Real-time availability checking
   - Location-specific inventory
   - Inventory restoration on void

5. **Reporting**
   - Sales summaries by date range
   - Cash vs. check breakdown
   - Transaction history
   - Cash drawer activity log
   - Discrepancy tracking

## Architecture

### Components

```
app/
├── Models/
│   ├── CashRegister.php            # Cash register model
│   ├── PosTransaction.php          # POS sale transaction
│   └── CashDrawerActivity.php      # Cash movement tracking
├── Http/Controllers/POS/
│   ├── CashRegisterController.php  # Register management
│   └── POSController.php            # Transaction processing
└── database/migrations/
    └── create_cash_registers_table.php  # Database schema
```

### Data Flow

```
Product Search → Add to Cart → Process Payment → Create Transaction
                                                        ↓
                                          Deduct Inventory + Update Cash Register
                                                        ↓
                                              Log Cash Drawer Activity
```

## Setup

### Database Migration

Run the migration to create the necessary tables:

```bash
php artisan migrate
```

This creates three tables:
- `cash_registers` - Cash register information
- `pos_transactions` - POS sale transactions
- `cash_drawer_activities` - Cash movement log

### Creating Cash Registers

Create cash registers for your locations:

```php
POST /api/pos/registers

{
  "name": "Main Register",
  "location_id": 1
}
```

## Cash Register Management

### Register Lifecycle

1. **Create Register** → 2. **Open Register** → 3. **Process Sales** → 4. **Close Register**

### Opening a Cash Register

Before processing sales, open the register with a starting cash amount:

```php
POST /api/pos/registers/{id}/open

{
  "opening_balance": 100.00,
  "notes": "Starting with $100 in small bills"
}
```

**What Happens:**
- Register status changes to "open"
- Opening balance is recorded
- Current and expected balances are set
- Opening activity is logged
- User who opened is recorded

### Closing a Cash Register

At end of day, close the register with the actual cash count:

```php
POST /api/pos/registers/{id}/close

{
  "actual_balance": 450.75,
  "notes": "End of day count"
}
```

**What Happens:**
- Register status changes to "closed"
- Actual balance is recorded
- Discrepancy is calculated (actual vs. expected)
- Closing activity is logged
- User who closed is recorded

### Cash Discrepancy

The system automatically calculates discrepancies:

```
Discrepancy = Actual Balance - Expected Balance

Examples:
Actual: $450.75, Expected: $450.00 → Discrepancy: +$0.75 (overage)
Actual: $448.50, Expected: $450.00 → Discrepancy: -$1.50 (shortage)
```

### Cash In/Out

Record cash added to or removed from the drawer:

**Cash In** (adding money):
```php
POST /api/pos/registers/{id}/cash-in

{
  "amount": 50.00,
  "notes": "Bank deposit prepared",
  "reference": "DEP-20251115-001"
}
```

**Cash Out** (removing money):
```php
POST /api/pos/registers/{id}/cash-out

{
  "amount": 200.00,
  "notes": "Bank deposit",
  "reference": "DEP-20251115-001"
}
```

## POS Transactions

### Creating a Sale

```php
POST /api/pos/transactions

{
  "cash_register_id": 1,
  "location_id": 1,
  "payment_method": "cash",
  "items": [
    {
      "variant_id": 123,
      "quantity": 2,
      "price": 29.99
    },
    {
      "variant_id": 456,
      "quantity": 1,
      "price": 15.00
    }
  ],
  "tax": 6.75,
  "discount": 0,
  "amount_tendered": 100.00,
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "notes": "Customer requested gift receipt"
}
```

**Transaction Processing:**
1. Validates cash register is open
2. Checks inventory availability for each item
3. Calculates subtotal, tax, discount, and total
4. Calculates change (if cash payment)
5. Creates POS transaction record
6. **Decreases inventory for each item**
7. Updates cash register balance (if cash)
8. Logs cash drawer activity
9. Returns transaction number and receipt data

### Product Lookup

**Search by SKU or Barcode:**
```php
POST /api/pos/search-product

{
  "code": "SKU-12345",
  "location_id": 1
}
```

**Browse Products:**
```php
GET /api/pos/products?location_id=1&search=shirt&exclude_out_of_stock=true
```

### Payment Methods

#### Cash Payment

- Requires `amount_tendered`
- System calculates change
- Updates cash register balance
- Logs cash drawer activity

Example:
```json
{
  "payment_method": "cash",
  "total": 75.99,
  "amount_tendered": 100.00,
  "change_given": 24.01
}
```

#### Check Payment

- Requires `check_number`
- Does not affect cash register balance
- Tracks check for bank deposit

Example:
```json
{
  "payment_method": "check",
  "total": 75.99,
  "check_number": "1234"
}
```

### Voiding Transactions

Void a transaction to reverse it:

```php
POST /api/pos/transactions/{id}/void

{
  "reason": "Customer returned immediately - wrong size"
}
```

**What Happens:**
- Transaction status changes to "voided"
- **Inventory is restored** for all items
- Cash register balance is adjusted (if cash sale)
- Adjustment activity is logged
- Void reason is recorded
- User who voided is recorded

## Cash Drawer Management

### Activity Types

1. **Opening** - Register opened with starting balance
2. **Sale** - Cash sale transaction
3. **Cash In** - Money added to drawer
4. **Cash Out** - Money removed from drawer
5. **Closing** - Register closed with final count
6. **Adjustment** - Balance adjustment (e.g., from void)

### Activity Log

Each activity records:
- Type of activity
- Amount involved
- Balance before and after
- Associated transaction (if applicable)
- User who performed it
- Notes and reference number
- Timestamp

### Viewing Activities

```php
GET /api/pos/registers/{id}/activities
```

Returns paginated list of all cash drawer activities for a register.

## Inventory Integration

### Automatic Inventory Deduction

When a POS sale is completed:

1. System finds the product variant
2. Locates stock item for the location
3. Checks if sufficient quantity is available
4. **Decreases stock quantity** by amount sold
5. Transaction fails if insufficient inventory

Example:
```
Product: Blue T-Shirt (Large)
Location: Main Store
Before Sale: 15 units
Sale Quantity: 2 units
After Sale: 13 units
```

### Inventory Restoration on Void

When a transaction is voided:

1. System retrieves original line items
2. For each item sold:
   - Finds the product variant
   - Locates stock item for the location
   - **Increases stock quantity** by amount originally sold

Example:
```
Product: Blue T-Shirt (Large)
Location: Main Store
Before Void: 13 units
Voided Quantity: 2 units
After Void: 15 units (restored)
```

### Stock Validation

Before completing a sale, the system validates:
- Product variant exists
- Stock item exists for the location
- Available quantity >= requested quantity

If validation fails, the entire transaction is rejected with an error message.

## Reporting

### Sales Summary

Get sales summary for a date range:

```php
GET /api/pos/sales-summary?start_date=2025-11-01&end_date=2025-11-30&cash_register_id=1
```

Returns:
- Total sales amount
- Number of transactions
- Cash sales total
- Check sales total
- Total tax collected
- Total discounts given
- Average sale amount

### Register Summary

Get current session summary for an open register:

```php
GET /api/pos/registers/{id}/summary
```

Returns:
- Register information
- Opening details (time, balance, who opened)
- Current balance
- Expected balance
- Transaction count
- Sales breakdown (cash vs. check)

### Transaction History

```php
GET /api/pos/transactions?start_date=2025-11-01&end_date=2025-11-30&status=completed
```

Filter options:
- Date range
- Cash register
- Status (completed, voided)
- Payment method

## API Reference

### Cash Register Endpoints

```
GET    /api/pos/registers              # List all registers
POST   /api/pos/registers              # Create register
GET    /api/pos/registers/{id}         # Get register details
PUT    /api/pos/registers/{id}         # Update register
DELETE /api/pos/registers/{id}         # Delete register (must be closed)
POST   /api/pos/registers/{id}/open    # Open register
POST   /api/pos/registers/{id}/close   # Close register
POST   /api/pos/registers/{id}/cash-in # Record cash in
POST   /api/pos/registers/{id}/cash-out # Record cash out
GET    /api/pos/registers/{id}/activities # Get activity log
GET    /api/pos/registers/{id}/summary # Get register summary
```

### Transaction Endpoints

```
GET  /api/pos/products                 # Get products for POS
POST /api/pos/search-product           # Search by SKU/barcode
POST /api/pos/transactions             # Create transaction
GET  /api/pos/transactions             # Get transaction history
GET  /api/pos/transactions/{id}        # Get transaction details
POST /api/pos/transactions/{id}/void   # Void transaction
GET  /api/pos/sales-summary            # Get sales summary
```

## Database Schema

### cash_registers

```sql
id                  - Primary key
shop_id             - Foreign key to shops
location_id         - Foreign key to locations (nullable)
name                - Register name
status              - 'open' or 'closed'
opening_balance     - Starting cash amount
current_balance     - Current cash in drawer
expected_balance    - Expected cash based on transactions
opened_at           - When register was opened
closed_at           - When register was closed
opened_by_user_id   - User who opened
closed_by_user_id   - User who closed
opening_notes       - Notes when opening
closing_notes       - Notes when closing
settings            - JSON settings
```

### pos_transactions

```sql
id                   - Primary key
shop_id              - Foreign key to shops
cash_register_id     - Foreign key to cash_registers (nullable)
location_id          - Foreign key to locations (nullable)
transaction_number   - Unique transaction ID (auto-generated)
payment_method       - 'cash' or 'check'
subtotal             - Sum of line items before tax/discount
tax                  - Tax amount
discount             - Discount amount
total                - Final total
amount_tendered      - Amount customer gave (cash only)
change_given         - Change returned (cash only)
check_number         - Check number (check only)
customer_name        - Optional customer name
customer_email       - Optional customer email
customer_phone       - Optional customer phone
notes                - Transaction notes
processed_by_user_id - User who processed sale
line_items           - JSON array of items sold
completed_at         - When transaction was completed
status               - 'completed', 'voided', or 'refunded'
voided_at            - When voided
voided_by_user_id    - User who voided
void_reason          - Why it was voided
```

### cash_drawer_activities

```sql
id                  - Primary key
shop_id             - Foreign key to shops
cash_register_id    - Foreign key to cash_registers
type                - Activity type (sale, cash_in, cash_out, etc.)
amount              - Amount of money involved
balance_before      - Cash balance before activity
balance_after       - Cash balance after activity
payment_method      - Payment method (for sales)
pos_transaction_id  - Related transaction (nullable)
user_id             - User who performed activity
notes               - Activity notes
reference           - External reference number
```

## Best Practices

### Opening a Register

1. **Count starting cash carefully**
2. **Include breakdown** (bills and coins) in notes
3. **Use consistent amounts** (e.g., always $100)
4. **Record opening time** automatically captured
5. **One person** should count and open

### Processing Sales

1. **Always verify inventory** before completing sale
2. **Double-check totals** especially with manual discounts
3. **Count cash carefully** and announce amount tendered
4. **Verify check information** including account and routing numbers
5. **Print/email receipts** for all transactions
6. **Get customer info** for returns and marketing

### Managing Cash Drawer

1. **Regular deposits** - Don't let cash build up
2. **Cash out frequently** - Remove excess cash to safe
3. **Document all movements** - Always include notes
4. **Use reference numbers** - For cash in/out tracking
5. **Limit access** - Only authorized users
6. **Spot checks** - Random cash counts during the day

### Closing a Register

1. **Count all cash** thoroughly
2. **Reconcile checks** - List check numbers and amounts
3. **Investigate discrepancies** - Even small ones
4. **Document reasons** - For overages/shortages
5. **Secure cash** - Deposit or safe storage
6. **Review transactions** - Check for voids or issues

### Handling Discrepancies

**Small Discrepancies** (< $5):
- Document in closing notes
- Monitor for patterns
- Retrain if recurring

**Large Discrepancies** (> $5):
- Investigate immediately
- Review all transactions
- Check for voids or returns
- Consider security review

### Transaction Voids

1. **Require authorization** - Manager approval
2. **Document reason** - Clear explanation required
3. **Time limit** - Void within same business day
4. **Customer presence** - Customer should be present
5. **Inventory check** - Verify items returned

### Security

1. **User authentication** - Required for all operations
2. **Audit trail** - All actions logged with user ID
3. **Cash limits** - Set maximum drawer amount
4. **Dual control** - Two people for large cash movements
5. **Video surveillance** - Monitor cash register area
6. **Regular audits** - Review logs and transactions

## Troubleshooting

### Common Issues

**1. Cannot Open Register**
- Check if already open
- Verify user has permission
- Ensure location is set (if required)

**2. Insufficient Inventory Error**
- Check stock levels at location
- Verify product is assigned to location
- Consider transferring stock

**3. Cannot Close Register**
- Verify all transactions are complete
- Check for pending operations
- Ensure no one else is using register

**4. Cash Discrepancy**
- Recount cash carefully
- Check for missing transactions
- Review cash in/out activities
- Verify voided transactions

**5. Transaction Won't Complete**
- Check inventory availability
- Verify cash register is open
- Ensure payment amount is sufficient
- Check network connection

## Receipt Data

Each transaction includes receipt data:

```json
{
  "transaction_number": "POS-20251115143052-A4B2",
  "date": "2025-11-15 14:30:52",
  "cashier": "Jane Smith",
  "customer_name": "John Doe",
  "payment_method": "Cash",
  "items": [
    {
      "title": "Blue T-Shirt",
      "variant_title": "Large",
      "quantity": 2,
      "price": 29.99,
      "line_total": 59.98
    }
  ],
  "subtotal": "59.98",
  "tax": "5.40",
  "discount": "0.00",
  "total": "65.38",
  "amount_tendered": "70.00",
  "change_given": "4.62"
}
```

This data can be used to:
- Print paper receipts
- Send email receipts
- Display on-screen confirmations
- Generate reports

---

**Last Updated**: November 15, 2025
**POS System Version**: 1.0.0

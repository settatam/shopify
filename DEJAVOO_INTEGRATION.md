# Dejavoo Payment Integration

This document provides comprehensive information about the Dejavoo payment terminal integration for accepting credit and debit card payments.

## Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Setup](#setup)
5. [Configuration](#configuration)
6. [Payment Processing](#payment-processing)
7. [POS Integration](#pos-integration)
8. [Transaction Management](#transaction-management)
9. [Batch Settlement](#batch-settlement)
10. [API Reference](#api-reference)
11. [Error Handling](#error-handling)
12. [Best Practices](#best-practices)

## Overview

The Dejavoo integration enables businesses to accept credit and debit card payments through Dejavoo payment terminals, both via cloud API and local terminal connections. This integration provides secure, PCI-compliant card payment processing with full POS system integration.

### Key Benefits

- **Card Payment Acceptance**: Process Visa, MasterCard, Discover, Amex, and more
- **EMV Chip Support**: Secure chip card processing
- **Contactless Payments**: Support for Apple Pay, Google Pay, and tap-to-pay cards
- **POS Integration**: Seamlessly integrates with the POS system
- **Multiple Transaction Types**: Sales, voids, refunds, auth/capture
- **Tip Management**: Add tips or adjust tips after transaction
- **Batch Settlement**: Automatic or manual batch closing
- **Real-time Processing**: Immediate transaction approval/decline

## Features

### Implemented Features

1. **Payment Processing**
   - Sale transactions with immediate capture
   - Authorization-only transactions (auth without capture)
   - Capture previously authorized amounts
   - Void transactions (same-day reversal)
   - Refund transactions (return money to customer)
   - Tip adjustments (add or modify tips)

2. **POS Integration**
   - Link card payments to POS transactions
   - Automatic inventory deduction
   - Receipt generation with card details
   - Support for card payments alongside cash/check

3. **Terminal Management**
   - Cloud-based API connection
   - Terminal status checking
   - Transaction cancellation
   - Batch closing (settlement)

4. **Transaction Tracking**
   - Store card payment details (last 4 digits, card type)
   - Transaction ID and authorization code storage
   - Entry mode tracking (chip, swipe, contactless)
   - Response code and message logging

5. **Security**
   - PCI-compliant processing (no card data stored)
   - Encrypted communication
   - Authentication via API key
   - Secure terminal registration

## Architecture

### Components

```
app/
├── Services/Dejavoo/
│   └── DejavooClient.php           # Dejavoo API client
├── Http/Controllers/Dejavoo/
│   ├── SettingsController.php      # Settings management
│   └── PaymentController.php       # Payment processing
└── Models/
    └── PosTransaction.php          # Enhanced with payment_data
```

### Data Flow

```
User Initiates Payment → Dejavoo Terminal → Card Reader → Processor
                                                              ↓
                                                          Approval
                                                              ↓
Response → DejavooClient → PaymentController → POS Transaction
                                                              ↓
                                                  Inventory Deduction
```

## Setup

### Prerequisites

1. Active Dejavoo merchant account
2. Dejavoo payment terminal (hardware or cloud)
3. Register ID and Auth Key from Dejavoo

### Getting Dejavoo Credentials

1. **Contact Dejavoo**
   - Sign up for a merchant account
   - Purchase or lease a payment terminal

2. **Obtain Credentials**
   - Register ID: Unique identifier for your terminal
   - Auth Key: Authentication key for API access
   - API URL: Cloud API endpoint (usually `https://app3.dejavoo.com`)

3. **Terminal Activation**
   - Activate terminal with Dejavoo
   - Ensure terminal is connected to internet
   - Test connectivity

### Configuration

Dejavoo credentials are stored per-shop in `shop.settings.dejavoo`:

```json
{
  "dejavoo": {
    "register_id": "YOUR_REGISTER_ID",
    "auth_key": "YOUR_AUTH_KEY",
    "api_url": "https://app3.dejavoo.com",
    "terminal_name": "Main Terminal",
    "auto_settle": false,
    "allow_tips": true,
    "allow_cashback": false
  }
}
```

### Initial Setup via API

```php
POST /api/dejavoo/credentials

{
  "register_id": "YOUR_REGISTER_ID",
  "auth_key": "YOUR_AUTH_KEY",
  "api_url": "https://app3.dejavoo.com"
}
```

### Test Connection

```php
POST /api/dejavoo/test-connection
```

Returns terminal status and connectivity information.

## Configuration

### Settings

**terminal_name** (string)
- Friendly name for the terminal
- Used for identification in reports

**auto_settle** (boolean)
- Automatically close batch at end of day
- Default: `false` (manual settlement)

**allow_tips** (boolean)
- Enable tip entry on terminal
- Default: `true`

**allow_cashback** (boolean)
- Enable cashback for debit cards
- Default: `false`

### Updating Settings

```php
POST /api/dejavoo/settings

{
  "terminal_name": "Front Counter Terminal",
  "auto_settle": true,
  "allow_tips": true,
  "allow_cashback": false
}
```

## Payment Processing

### Sale Transaction

Process a standard sale (authorization + capture):

```php
POST /api/dejavoo/process-payment

{
  "amount": 50.00,
  "reference_number": "ORDER-12345",
  "invoice_number": "INV-001",
  "tip_amount": 5.00
}
```

**Response (Approved):**
```json
{
  "success": true,
  "approved": true,
  "message": "Payment approved",
  "transaction_id": "TXN123456789",
  "auth_code": "123456",
  "card_type": "Visa",
  "last_four": "1234",
  "amount": 50.00,
  "tip_amount": 5.00,
  "total_amount": 55.00,
  "entry_mode": "chip"
}
```

**Response (Declined):**
```json
{
  "success": false,
  "declined": true,
  "message": "Insufficient funds",
  "response_code": "51"
}
```

### Authorization Only

Authorize payment without capture (useful for pre-orders):

```php
// Dejavoo API doesn't expose this publicly in the simple API
// Use sale with subsequent void if needed
```

### Void Transaction

Void a transaction (same-day only):

```php
POST /api/dejavoo/void-payment

{
  "transaction_id": "TXN123456789",
  "pos_transaction_id": 123  // Optional: also void POS transaction
}
```

### Refund Transaction

Refund money to customer:

```php
POST /api/dejavoo/refund-payment

{
  "amount": 50.00,
  "transaction_id": "TXN123456789"  // Optional: link to original
}
```

### Tip Adjustment

Add or adjust tip after transaction:

```php
POST /api/dejavoo/tip-adjustment

{
  "transaction_id": "TXN123456789",
  "tip_amount": 10.00
}
```

### Cancel Transaction

Cancel transaction currently in progress on terminal:

```php
POST /api/dejavoo/cancel
```

## POS Integration

### Processing Card Payment with POS

When processing a POS sale with card payment:

1. **Create POS Transaction First** (without completing payment)
2. **Process Card Payment** through Dejavoo
3. **Link Payment to Transaction**

Example flow:

```php
// Step 1: Create items in cart
$items = [
  ['variant_id' => 123, 'quantity' => 2, 'price' => 29.99],
  ['variant_id' => 456, 'quantity' => 1, 'price' => 15.00]
];

// Step 2: Process payment through Dejavoo
POST /api/dejavoo/process-payment
{
  "amount": 75.98,  // Total from cart
  "reference_number": "POS-20251115-ABCD"
}

// Step 3: If approved, create POS transaction
POST /api/pos/transactions
{
  "cash_register_id": 1,
  "location_id": 1,
  "payment_method": "card",
  "items": [...],
  "total": 75.98
}
```

### Payment Data Storage

Card payment details are stored in `pos_transactions.payment_data`:

```json
{
  "payment_processor": "dejavoo",
  "transaction_id": "TXN123456789",
  "auth_code": "123456",
  "card_type": "Visa",
  "last_four": "1234",
  "amount": 50.00,
  "tip_amount": 5.00,
  "total_amount": 55.00,
  "entry_mode": "chip",
  "approved": true,
  "response_code": "00",
  "message": "Approved",
  "timestamp": "2025-11-15T14:30:52Z",
  "raw_response": {...}
}
```

### Receipt Information

Card payment receipts include:
- Last 4 digits of card
- Card type (Visa, MasterCard, etc.)
- Entry mode (Chip, Swipe, Contactless)
- Authorization code
- Transaction ID
- Approval message

## Transaction Management

### Transaction Types

**Sale**
- Immediate authorization and capture
- Funds settled during batch close
- Cannot be voided after settlement

**Void**
- Reverse a transaction before settlement
- Same-day only
- Full amount must be voided

**Refund**
- Return money to customer
- Can be done anytime (even after settlement)
- Can be partial or full amount
- May require original transaction ID

**Tip Adjustment**
- Add or change tip amount
- Must be done before settlement
- Common in restaurant scenarios

### Response Codes

Common Dejavoo response codes:

- `00` / `000` / `0` - Approved
- `01` - Call for authorization
- `05` - Do not honor
- `12` - Invalid transaction
- `13` - Invalid amount
- `14` - Invalid card number
- `51` - Insufficient funds
- `54` - Expired card
- `55` - Incorrect PIN
- `57` - Transaction not permitted
- `61` - Exceeds withdrawal limit
- `65` - Activity limit exceeded

### Entry Modes

- `chip` - EMV chip card
- `swipe` - Magnetic stripe
- `contactless` - NFC/tap (Apple Pay, Google Pay)
- `manual` - Manual key entry
- `fallback` - Chip fallback to swipe

## Batch Settlement

### What is Batch Settlement?

Batch settlement (or batch close) is the process of sending captured transactions to the processor for funding. Transactions are not actually deposited into your account until the batch is closed.

### Manual Batch Close

```php
POST /api/dejavoo/batch-close
```

**When to Close Batch:**
- End of business day
- Before generating financial reports
- As required by processor (usually daily)

**What Happens:**
- All authorized transactions are submitted for settlement
- Funds are scheduled for deposit (usually 1-2 business days)
- Transaction batch is closed
- New batch automatically begins

### Automatic Settlement

Enable auto-settle to automatically close batch daily:

```json
{
  "auto_settle": true
}
```

The system can trigger batch close at a scheduled time (requires cron job setup).

## API Reference

### Settings Endpoints

```
GET  /api/dejavoo/settings          # Get current settings
POST /api/dejavoo/credentials       # Update credentials
POST /api/dejavoo/settings          # Update settings
POST /api/dejavoo/test-connection   # Test terminal connection
POST /api/dejavoo/batch-close       # Close batch (settlement)
POST /api/dejavoo/disconnect        # Disconnect Dejavoo
```

### Payment Endpoints

```
POST /api/dejavoo/process-payment   # Process sale
POST /api/dejavoo/void-payment      # Void transaction
POST /api/dejavoo/refund-payment    # Refund transaction
POST /api/dejavoo/tip-adjustment    # Adjust tip
POST /api/dejavoo/get-status        # Get transaction status
POST /api/dejavoo/cancel            # Cancel in-progress transaction
```

### DejavooClient Methods

```php
// Connection
testConnection(): array

// Sale Processing
sale(float $amount, string $refNumber, array $options): array

// Transaction Management
void(string $transactionId): array
refund(float $amount, string $transactionId): array
tipAdjustment(string $transactionId, float $tipAmount): array

// Authorization & Capture
auth(float $amount, string $refNumber): array
capture(string $authCode, float $amount): array

// Status & Control
getStatus(string $refNumber): array
cancel(): array
batchClose(): array

// Response Parsing
parseResponse(array $response): array
isApproved(array $response): bool
buildPaymentData(array $response): array
```

## Error Handling

### Common Errors

**Connection Refused**
```
Error: Cannot connect to terminal
```
**Solutions:**
- Check terminal is powered on and connected
- Verify API URL is correct
- Test network connectivity
- Ensure Register ID and Auth Key are correct

**Transaction Declined**
```
{
  "success": false,
  "declined": true,
  "response_code": "51",
  "message": "Insufficient funds"
}
```
**Solutions:**
- Customer has insufficient funds
- Try different payment method
- Verify amount is correct

**Void Failed - Already Settled**
```
Error: Transaction already settled
```
**Solutions:**
- Use refund instead of void
- Voids only work same-day before settlement

**Terminal Busy**
```
Error: Terminal is processing another transaction
```
**Solutions:**
- Wait for current transaction to complete
- Use cancel endpoint to abort transaction
- Check terminal display

### Error Logging

All Dejavoo errors are logged:

```php
Log::error('Dejavoo payment failed: ' . $e->getMessage(), [
    'user_id' => $user->id,
    'shop_id' => $shop->id,
    'amount' => $amount,
]);
```

Check `storage/logs/laravel.log` for details.

## Best Practices

### Payment Processing

1. **Always Verify Amount**
   - Show amount to customer before processing
   - Confirm total includes tax and tip
   - Double-check decimal placement

2. **Handle Declines Gracefully**
   - Don't retry declined cards automatically
   - Offer alternative payment methods
   - Never store decline reasons in customer records

3. **Void vs Refund**
   - Use void for same-day mistakes (no processing fee)
   - Use refund for returns after settlement
   - Keep transaction IDs for reference

4. **Tip Handling**
   - Allow tip entry on terminal for better security
   - Adjust tips before batch close
   - Include tip in receipt

5. **Receipt Printing**
   - Always provide customer receipt
   - Store merchant copy
   - Include all required information (last 4, auth code, etc.)

### Security

1. **Credentials**
   - Never log Auth Key
   - Store credentials encrypted
   - Rotate keys periodically
   - Limit API access to authorized users

2. **Card Data**
   - Never store full card numbers
   - Never store CVV
   - Never store magnetic stripe data
   - Only store last 4 digits and card type

3. **PCI Compliance**
   - Use Dejavoo terminals for all card entry
   - Never manually enter cards in application
   - Keep terminals updated
   - Follow PCI DSS requirements

### Terminal Management

1. **Daily Settlement**
   - Close batch every business day
   - Reconcile transactions
   - Verify settlement reports
   - Maintain settlement logs

2. **Terminal Maintenance**
   - Keep firmware updated
   - Clean card reader regularly
   - Test terminal daily
   - Keep backup power supply

3. **Connectivity**
   - Ensure stable internet connection
   - Use wired connection when possible
   - Monitor connection status
   - Have backup payment method

### Transaction Tracking

1. **Reference Numbers**
   - Use unique reference for each transaction
   - Link to POS transaction numbers
   - Include in reports
   - Useful for disputes

2. **Record Keeping**
   - Store all transaction details
   - Keep for at least 18 months
   - Include raw response data
   - Document voids and refunds

3. **Reconciliation**
   - Compare batch totals with sales
   - Investigate discrepancies
   - Match deposits to batches
   - Review monthly statements

## Troubleshooting

### Terminal Not Responding

1. Check terminal power and connectivity
2. Verify API URL is correct
3. Test with /status command
4. Restart terminal if needed

### Payments Failing

1. Check Register ID and Auth Key
2. Verify terminal is activated
3. Test with small amount
4. Check processor status

### Batch Won't Close

1. Ensure all transactions are complete
2. Check for pending authorizations
3. Verify terminal connectivity
4. Contact Dejavoo support if persistent

### Incorrect Amounts

1. Verify decimal handling (use `number_format`)
2. Check currency is set correctly
3. Ensure tax calculation is accurate
4. Test with known amounts

## Integration Testing

### Test Transactions

Dejavoo provides test mode for development:

1. Use test credentials from Dejavoo
2. Process small test amounts ($1.00)
3. Test approval and decline scenarios
4. Verify void and refund functionality

### Test Card Numbers

Check with Dejavoo for test card numbers and scenarios.

---

**Last Updated**: November 15, 2025
**Integration Version**: 1.0.0
**Dejavoo API**: Cloud API v3

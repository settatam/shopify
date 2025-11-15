# Returns & Refunds Management System 🔄💰

Comprehensive return merchandise authorization (RMA), refund processing, and return shipping management for multi-channel e-commerce.

## Overview

The Returns & Refunds Management System provides end-to-end management of product returns, from customer request through inspection, restocking, and refund processing. It integrates with shipping providers for return labels, payment gateways for refunds, and marketplace channels for compliance.

## Key Features

### 🎯 Return Request Management (RMA)
- **Customer-Initiated Returns**: Self-service return requests with reason tracking
- **Multi-Channel Support**: eBay, Amazon, Shopify, Etsy, Walmart, and custom channels
- **Approval Workflows**: Optional manual approval before accepting returns
- **RMA Number Generation**: Unique tracking numbers for all returns
- **Deadline Management**: Configurable return windows with automatic expiration
- **Image Upload**: Customers can upload photos of defective/damaged items

### 📦 Return Shipping
- **Automatic Label Generation**: Integration with ShipStation for return shipping labels
- **Multiple Carriers**: USPS, UPS, FedEx, DHL, Canada Post, Royal Mail
- **Real-Time Tracking**: Monitor return shipments in transit
- **Cost Management**: Customer-paid or merchant-paid return shipping
- **Label Voiding**: Cancel unused labels for refunds

### 🔍 Inspection & Quality Control
- **Item-by-Item Inspection**: Assess condition of each returned item
- **Condition Tracking**: New unopened, new opened, used, damaged, defective
- **Disposition Management**: Restock, refurbish, dispose, return to vendor, quarantine
- **Inspection Notes**: Detailed documentation for quality assurance
- **Partial Approvals**: Accept some items while rejecting others

### 💰 Refund Processing
- **Multiple Refund Methods**:
  - Original payment method (credit card, PayPal, etc.)
  - Store credit with expiration dates
  - Cash (for POS returns)
  - Check
  - Bank transfer
  - Manual processing
- **Payment Gateway Integration**: Stripe, Square, PayPal
- **Partial Refunds**: Flexible refund amounts based on inspection
- **Restocking Fees**: Configurable fees for certain return reasons
- **Return Shipping Deduction**: Deduct return shipping costs from refund
- **Automatic Channel Sync**: Sync refunds to eBay, Amazon, Shopify, etc.

### 📊 Inventory Management
- **Automatic Restocking**: Add returned items back to inventory
- **Location Management**: Choose which warehouse receives restocked items
- **Condition-Based Restocking**: Mark items as new, open-box, or refurbished
- **Quarantine System**: Hold items for additional review
- **Vendor Returns**: Track items being returned to suppliers

### 📧 Customer Communication
- **Automated Notifications**: Status updates at every stage
- **Custom Email Templates**: Branded communication
- **Notification History**: Track all customer communications
- **Return Instructions**: Automatic shipping label and packing slip delivery

### 📈 Analytics & Reporting
- **Return Rate Tracking**: Monitor return rates by product, category, channel
- **Return Reason Analysis**: Identify common issues (defective, wrong item, etc.)
- **Refund Amount Tracking**: Financial impact of returns
- **Timeline Metrics**: Average processing times
- **Overdue Tracking**: Identify stuck returns

## Database Schema

### return_requests

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop reference |
| channel_order_id | bigint | Original order |
| rma_number | string | Unique RMA number (RMA-XXXXXXXX) |
| order_number | string | Original order number |
| channel_id | bigint | Sales channel |
| customer_name | string | Customer name |
| customer_email | string | Customer email |
| customer_phone | string | Customer phone |
| return_reason | enum | Reason for return (11 types) |
| return_reason_details | text | Additional details |
| images | json | Customer-uploaded images |
| status | enum | requested, pending_approval, approved, rejected, label_generated, in_transit, received, inspecting, completed, cancelled |
| return_type | enum | refund, exchange, store_credit |
| items_subtotal | decimal | Items total |
| shipping_paid | decimal | Original shipping cost |
| tax_paid | decimal | Original tax |
| total_paid | decimal | Original order total |
| refund_amount | decimal | Calculated refund |
| restocking_fee | decimal | Fee charged |
| refund_shipping | boolean | Refund shipping cost |
| requires_approval | boolean | Manual approval required |
| approved_by_id | bigint | Approving user |
| approved_at | timestamp | Approval time |
| rejected_by_id | bigint | Rejecting user |
| rejected_at | timestamp | Rejection time |
| rejection_reason | text | Why rejected |
| return_address | json | Where to send items |
| return_tracking_number | string | Return shipment tracking |
| inspection_result | enum | approved, partial_approval, rejected |
| return_by_date | timestamp | Deadline for customer |
| received_at | timestamp | When package arrived |

### return_items

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| return_request_id | bigint | Parent return |
| product_id | bigint | Product reference |
| variant_id | bigint | Variant reference |
| sku | string | Product SKU |
| quantity_returned | integer | Quantity being returned |
| unit_price | decimal | Price per item |
| return_reason | enum | Item-specific reason |
| condition_received | enum | Actual condition upon receipt |
| disposition | enum | restock, refurbish, dispose, return_to_vendor, quarantine, pending |
| restocked | boolean | Has been restocked |
| restocked_at | timestamp | When restocked |
| location_id | bigint | Warehouse location |
| refundable | boolean | Eligible for refund |
| refund_amount | decimal | Refund for this item |
| restocking_fee | decimal | Fee for this item |
| exchange_variant_id | bigint | Exchange product (if applicable) |

### refunds

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| shop_id | bigint | Shop reference |
| return_request_id | bigint | Associated return (nullable) |
| refund_number | string | Unique refund number (REF-XXXXXXXX) |
| order_number | string | Original order |
| refund_type | enum | full, partial, shipping_only, tax_only, custom |
| items_refund | decimal | Items refunded |
| shipping_refund | decimal | Shipping refunded |
| tax_refund | decimal | Tax refunded |
| restocking_fee | decimal | Fee charged |
| total_refund | decimal | Total amount |
| refund_method | enum | original_payment, store_credit, cash, check, bank_transfer, paypal, manual |
| status | enum | pending, processing, completed, failed, cancelled, on_hold |
| gateway_refund_id | string | Payment gateway refund ID |
| payment_gateway | string | stripe, square, paypal |
| processed_at | timestamp | When completed |
| processed_by_id | bigint | Processing user |
| retry_count | integer | Failed attempts |
| store_credit_code | string | Credit code (if applicable) |
| channel_id | bigint | Sales channel |
| channel_refund_id | string | Channel's refund ID |
| synced_to_channel | boolean | Synced to marketplace |

### return_shipping

| Field | Type | Description |
|-------|------|-------------|
| id | bigint | Primary key |
| return_request_id | bigint | Parent return |
| provider | enum | shipstation, ups, usps, fedex, dhl |
| tracking_number | string | Tracking number |
| carrier_code | string | Carrier identifier |
| label_url | string | Label download URL |
| label_cost | decimal | Label cost |
| customer_pays | boolean | Who pays shipping |
| weight_oz | decimal | Package weight |
| from_address | json | Customer address |
| to_address | json | Return warehouse |
| status | enum | label_created, in_transit, delivered, cancelled |
| tracking_events | json | Shipment updates |
| voided | boolean | Label cancelled |

## API Endpoints

### Return Management

```javascript
// List all returns
GET /api/returns
  ?status=pending_approval
  ?return_type=refund
  ?customer_email=customer@example.com
  ?per_page=15

// Get pending approvals
GET /api/returns/pending-approvals

// Get return details
GET /api/returns/{returnId}

// Create return request
POST /api/returns
{
  "order_number": "ORDER-12345",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "return_reason": "defective",
  "return_reason_details": "Product stopped working after 2 days",
  "return_type": "refund",
  "items_subtotal": 99.99,
  "shipping_paid": 7.99,
  "tax_paid": 8.50,
  "total_paid": 116.48,
  "refund_shipping": false,
  "requires_approval": true,
  "items": [
    {
      "product_id": 123,
      "variant_id": 456,
      "product_name": "Widget Pro",
      "quantity_ordered": 2,
      "quantity_returned": 2,
      "unit_price": 49.99,
      "total_price": 99.98,
      "tax_amount": 8.50
    }
  ]
}

// Update return
PUT /api/returns/{returnId}
{
  "return_reason_details": "Updated details",
  "restocking_fee": 10.00,
  "internal_notes": "Customer is VIP"
}

// Approve return
POST /api/returns/{returnId}/approve
{
  "restocking_fee": 0,
  "refund_shipping": true
}

// Reject return
POST /api/returns/{returnId}/reject
{
  "reason": "Outside of return window"
}

// Mark as received
POST /api/returns/{returnId}/received

// Complete inspection
POST /api/returns/{returnId}/inspection
{
  "result": "approved", // approved, partial_approval, rejected
  "notes": "All items in good condition",
  "items": [
    {
      "item_id": 789,
      "condition": "new_opened",
      "disposition": "restock",
      "refundable": true,
      "refund_amount": 99.98,
      "notes": "Packaging slightly damaged but product perfect"
    }
  ]
}

// Restock items
POST /api/returns/{returnId}/restock
{
  "items": [
    {
      "item_id": 789,
      "location_id": 1 // Warehouse ID
    }
  ]
}

// Complete return
POST /api/returns/{returnId}/complete

// Delete return
DELETE /api/returns/{returnId}
```

### Refund Management

```javascript
// Get refund details
GET /api/returns/refunds/{refundId}

// Create standalone refund (without return)
POST /api/returns/refunds
{
  "order_number": "ORDER-12345",
  "refund_type": "partial",
  "items_refund": 50.00,
  "shipping_refund": 0,
  "tax_refund": 4.25,
  "restocking_fee": 10.00,
  "refund_method": "original_payment",
  "payment_gateway": "stripe",
  "original_transaction_id": "ch_ABC123",
  "customer_email": "john@example.com",
  "customer_name": "John Doe",
  "refund_reason": "Customer requested partial refund"
}

// Process refund
POST /api/returns/refunds/{refundId}/process

// Retry failed refund
POST /api/returns/refunds/{refundId}/retry
```

### Statistics

```javascript
// Get comprehensive statistics
GET /api/returns/statistics
  ?start_date=2025-01-01
  ?end_date=2025-01-31

Response:
{
  "returns": {
    "total_returns": 127,
    "pending_approval": 8,
    "completed": 95,
    "total_refunded": 12543.50,
    "return_rate": 3.2,
    "reasons_breakdown": {
      "defective": 45,
      "size_fit_issue": 32,
      "changed_mind": 28,
      "wrong_item": 12,
      "damaged_in_shipping": 10
    }
  },
  "refunds": {
    "total_refunds": 118,
    "total_amount": 11890.25,
    "pending": 3,
    "failed": 1
  }
}
```

## Workflow Examples

### 1. Standard Return Flow

**Step 1: Customer Initiates Return**
```javascript
POST /api/returns
{
  "order_number": "ORD-789",
  "customer_name": "Jane Smith",
  "customer_email": "jane@example.com",
  "return_reason": "size_fit_issue",
  "return_type": "refund",
  // ... items and amounts
}

Response: { rma_number: "RMA-AB12CD34" }
```

**Step 2: Merchant Reviews (if approval required)**
```javascript
GET /api/returns/pending-approvals
// Merchant reviews the request

POST /api/returns/123/approve
{
  "restocking_fee": 0,
  "refund_shipping": false
}
```

**Step 3: System Generates Return Label**
- GenerateReturnLabelJob automatically creates shipping label
- Customer receives email with label and tracking number
- Status: `label_generated`

**Step 4: Customer Ships Package**
- Customer prints label and ships package
- Tracking updates automatically via carrier webhook/polling
- Status: `in_transit`

**Step 5: Package Received**
```javascript
POST /api/returns/123/received
```
- Status: `received` → `inspecting`

**Step 6: Inspection**
```javascript
POST /api/returns/123/inspection
{
  "result": "approved",
  "items": [
    {
      "item_id": 456,
      "condition": "new_opened",
      "disposition": "restock",
      "refundable": true
    }
  ]
}
```

**Step 7: Automatic Refund Processing**
- ProcessRefundJob creates and processes refund
- RefundService integrates with payment gateway
- Customer receives refund confirmation
- Status: Refund `completed`

**Step 8: Restock Items**
```javascript
POST /api/returns/123/restock
{
  "items": [{ "item_id": 456, "location_id": 1 }]
}
```

**Step 9: Complete Return**
```javascript
POST /api/returns/123/complete
```
- Status: `completed`
- Customer receives final confirmation

### 2. Defective Product Return

```javascript
// Customer uploads images of defect
POST /api/returns
{
  "return_reason": "defective",
  "return_reason_details": "Screen has dead pixels",
  "images": ["https://...image1.jpg", "https://...image2.jpg"],
  // ...
}

// After inspection, mark as defective
POST /api/returns/123/inspection
{
  "result": "approved",
  "items": [{
    "condition": "defective",
    "disposition": "return_to_vendor",
    "refundable": true,
    "notes": "Confirmed dead pixels - RTV"
  }]
}

// Full refund including shipping
// restocking_fee: 0 for defective items
```

### 3. Exchange Processing

```javascript
POST /api/returns
{
  "return_type": "exchange",
  "items": [
    {
      "product_name": "T-Shirt Size M",
      "quantity_returned": 1,
      "exchange_variant_id": 789, // Size L variant
      "exchange_quantity": 1
    }
  ]
}

// After inspection, fulfill exchange
// Create new order for exchange item
// No refund processed, just exchange shipment
```

### 4. Standalone Refund (No Physical Return)

```javascript
// For digital products, damaged items customer keeps, goodwill, etc.
POST /api/returns/refunds
{
  "order_number": "ORD-456",
  "refund_type": "partial",
  "items_refund": 25.00,
  "refund_method": "store_credit",
  "refund_reason": "Goodwill gesture for delayed shipping"
}

POST /api/returns/refunds/789/process
// Store credit code generated and emailed
```

## Return Reasons

The system tracks 11 different return reasons:

| Reason | Description | Typical Restocking Fee | Inspection Required |
|--------|-------------|------------------------|---------------------|
| defective | Product is defective/broken | No | Yes |
| wrong_item | Wrong product sent | No | Yes |
| not_as_described | Doesn't match description | No | Yes |
| damaged_in_shipping | Damaged during transit | No | Yes |
| changed_mind | Buyer's remorse | Maybe | No |
| size_fit_issue | Doesn't fit properly | No | No |
| quality_issue | Poor quality | No | Yes |
| missing_parts | Incomplete product | No | Yes |
| arrived_late | Delivery too late | No | No |
| duplicate_order | Ordered by mistake | No | No |
| other | Other reason | Maybe | Maybe |

## Return Statuses

The return request progresses through these statuses:

1. **requested** - Customer initiated
2. **pending_approval** - Awaiting merchant review
3. **approved** - Merchant approved
4. **rejected** - Merchant rejected
5. **label_generated** - Return label created
6. **in_transit** - Package in transit
7. **received** - Package arrived
8. **inspecting** - Items being inspected
9. **completed** - Return fully processed
10. **cancelled** - Return cancelled

## Background Jobs

### GenerateReturnLabelJob

**Purpose**: Creates return shipping label via ShipStation

**Trigger**: After return approval

**Queue**: `returns`

**Timeout**: 5 minutes

**Process**:
1. Verify return is approved
2. Get shop's ShipStation credentials
3. Calculate package dimensions/weight
4. Create shipping label via API
5. Store label URL and tracking
6. Update return request status
7. Email label to customer

### ProcessRefundJob

**Purpose**: Processes refund after inspection approval

**Trigger**: After inspection approval

**Queue**: `refunds`

**Timeout**: 10 minutes

**Process**:
1. Create refund record from return
2. Determine refund method
3. Process via payment gateway (Stripe, Square, PayPal)
4. Handle store credit issuance
5. Mark refund as completed
6. Schedule channel sync
7. Notify customer

### NotifyReturnStatusJob

**Purpose**: Send customer email notifications

**Trigger**: Status changes

**Queue**: `notifications`

**Events**:
- `created` - Return request received
- `approved` - Return approved
- `rejected` - Return rejected
- `label_generated` - Label ready
- `inspection_completed` - Inspection done
- `refund_processed` - Refund sent
- `completed` - Return finished

### SyncRefundToChannelJob

**Purpose**: Sync refunds to marketplaces

**Trigger**: After refund completion

**Queue**: `channel-sync`

**Channels Supported**:
- eBay (Returns API)
- Amazon (MWS/SP-API)
- Shopify (Admin API)
- Etsy (Open API)
- Walmart (Marketplace API)

## Refund Methods

### Original Payment Method
- **Supported Gateways**: Stripe, Square, PayPal
- **Processing Time**: 3-5 business days
- **API Integration**: Automatic via gateway API
- **Customer Experience**: Money returned to original card/account

### Store Credit
- **Credit Code**: Auto-generated (SC-XXXXXXXXXX)
- **Expiration**: Configurable (default 1 year)
- **Usage**: Can be applied to future orders
- **Customer Experience**: Email with credit code

### Cash (POS)
- **Use Case**: In-store returns
- **Integration**: Cash register system
- **Tracking**: Recorded in cash drawer activities
- **Customer Experience**: Immediate cash refund

### Manual Methods
- **Check**: Mailed to customer
- **Bank Transfer**: Direct deposit
- **Other**: Custom arrangements

## ShipStation Integration

### Return Label Creation

```php
// Automatic via ReturnShippingService
$shippingService->generateReturnLabel($returnRequest, $user);
```

**API Call**:
```
POST https://ssapi.shipstation.com/shipments/createlabel
Authorization: Basic {base64(apiKey:apiSecret)}

{
  "carrierCode": "usps",
  "serviceCode": "usps_priority_mail",
  "packageCode": "package",
  "shipFrom": { /* customer address */ },
  "shipTo": { /* return warehouse */ },
  "weight": { "value": 16, "units": "ounces" },
  "dimensions": { "length": 12, "width": 9, "height": 6, "units": "inches" }
}
```

**Response**:
```json
{
  "shipmentId": 123456789,
  "trackingNumber": "9400111899223456789012",
  "labelData": "base64EncodedPDF...",
  "shipmentCost": 7.50
}
```

### Tracking Updates

Tracking can be updated via:
1. **Webhooks**: ShipStation sends updates
2. **Polling**: Periodic API calls to check status
3. **Manual**: Staff marks as received

## Payment Gateway Integration

### Stripe Refunds

```php
\Stripe\Refund::create([
    'charge' => $refund->original_transaction_id,
    'amount' => $refund->total_refund * 100, // cents
    'reason' => 'requested_by_customer'
]);
```

### Square Refunds

```php
$client->getRefundsApi()->refundPayment([
    'payment_id' => $refund->original_transaction_id,
    'amount_money' => [
        'amount' => $refund->total_refund * 100,
        'currency' => 'USD'
    ],
    'reason' => 'Return processed'
]);
```

### PayPal Refunds

```php
$paypal->refund([
    'transaction_id' => $refund->original_transaction_id,
    'amount' => $refund->total_refund,
    'note' => 'Return RMA-' . $returnRequest->rma_number
]);
```

## Best Practices

### Return Policies
- **Clear Communication**: Display return policy prominently
- **Reasonable Windows**: 30-60 days standard
- **Condition Requirements**: Specify acceptable conditions
- **Restocking Fees**: Be transparent about fees
- **Exception Handling**: Different rules for defects vs. buyer's remorse

### Approval Workflows
- **Auto-Approve Low-Risk**: Changed mind, size issues under $50
- **Manual Review Required**: High-value items, defective claims, frequent returners
- **Time Limits**: Review requests within 24-48 hours
- **Consistent Criteria**: Use same standards for all customers

### Inspection Process
- **Checklist**: Standard evaluation for each item type
- **Photo Documentation**: Take pictures of received items
- **Detailed Notes**: Record condition, completeness, functionality
- **Fair Assessment**: Don't penalize normal wear
- **Quick Turnaround**: Complete within 2-3 business days

### Refund Processing
- **Prompt Processing**: Issue refunds within 48 hours of approval
- **Original Method First**: Use original payment method when possible
- **Clear Communication**: Tell customer when to expect money
- **Fee Transparency**: Explain any deductions
- **Exception Handling**: Handle gateway failures gracefully

### Inventory Management
- **Quick Restocking**: Add items back to inventory immediately after approval
- **Condition Marking**: Differentiate new, open-box, refurbished
- **Quality Quarantine**: Hold questionable items for additional review
- **Vendor Returns**: Track RTV authorization numbers

### Customer Experience
- **Self-Service Portal**: Let customers initiate online
- **Status Updates**: Email at every stage
- **Easy Labels**: One-click label printing
- **Flexible Options**: Offer refund, exchange, or store credit
- **Follow-Up**: Survey after completion

## Troubleshooting

### Return Request Issues

**Problem**: Customer can't create return request
- Check order number exists
- Verify within return window
- Ensure items are eligible
- Check for duplicate requests

**Problem**: Return stuck in pending approval
- Review approval queue regularly
- Set auto-approval rules
- Send reminders to staff

### Shipping Label Issues

**Problem**: Label generation fails
- Verify ShipStation credentials
- Check address validation
- Ensure package dimensions set
- Review ShipStation account status

**Problem**: Tracking not updating
- Check carrier API status
- Verify tracking number format
- Wait 24 hours for first scan
- Contact carrier if needed

### Refund Processing Issues

**Problem**: Refund fails to process
- Verify payment gateway credentials
- Check original transaction exists
- Ensure sufficient time hasn't passed
- Verify refund amount ≤ original charge
- Check for already-refunded transactions

**Problem**: Refund not syncing to channel
- Verify channel credentials
- Check channel API status
- Review channel refund policies
- Manually sync if needed

### Inventory Issues

**Problem**: Items not restocking
- Verify disposition is set to "restock"
- Check location exists
- Ensure variant still exists
- Review inventory service logs

## Security & Compliance

### Data Protection
- Customer PII encrypted at rest
- Secure payment gateway tokens
- Audit logs for all refund actions
- Role-based access control

### Fraud Prevention
- Return rate monitoring per customer
- Serial number tracking
- Weight verification on received packages
- Photo documentation requirements

### Channel Compliance
- **eBay**: Follow eBay Money Back Guarantee
- **Amazon**: Adhere to Amazon's return policies
- **Shopify**: Support Shopify's standard refund flow
- **Walmart**: Meet Walmart Marketplace requirements

### Financial Reporting
- Return reserves for accounting
- Refund liability tracking
- Revenue recognition adjustments
- Tax implications for refunds

## Future Enhancements

- **AI-Powered Fraud Detection**: Machine learning to identify suspicious patterns
- **Automated Image Recognition**: Verify returned items match photos
- **Return Prediction**: Forecast return likelihood at time of sale
- **Cross-Channel Analytics**: Compare return rates across marketplaces
- **Customer Return Profiles**: Track customer return history
- **Barcode Scanning**: Mobile app for warehouse receiving
- **Video Unboxing**: Record package opening for disputes
- **Return Shipping Optimizer**: Choose cheapest carrier automatically
- **Exchange Automation**: Automatically create exchange orders
- **Warranty Integration**: Connect with manufacturer warranties

## Conclusion

The Returns & Refunds Management System provides enterprise-grade capabilities for handling product returns efficiently while maintaining excellent customer experience. By automating shipping labels, refund processing, and channel synchronization, it reduces manual work and ensures consistent, compliant handling of all returns.

Key benefits:
- **Reduced Processing Time**: Automated workflows save hours
- **Improved Accuracy**: Systematic tracking prevents errors
- **Better Customer Experience**: Fast, transparent process
- **Cost Savings**: Optimize shipping, reduce fraud
- **Compliance**: Meet marketplace requirements
- **Data-Driven**: Analytics inform product and policy decisions

Start with basic approval workflows and gradually enable automation as you gain confidence in the system's performance.

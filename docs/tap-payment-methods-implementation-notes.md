# Tap Payment Methods - Implementation Notes

## Overview
This document tracks the implementation status of Tap payment methods and notes for future complex integrations.

---

## ✅ Implemented - Standard Redirect Flow

All these methods use the standard Tap redirect flow and are ready for production:

### Cards (International)
- **Visa** (`tap_visa`) - Multi-currency, 2.5% fee
- **Mastercard** (`tap_mastercard`) - Multi-currency, 2.5% fee  
- **American Express** (`tap_amex`) - Multi-currency, 3.0% fee

### Kuwait (KWD)
- **KNET** (`tap_knet`) - Source: `src_kw.knet`, Fixed 0.150 KWD fee
- **Deema BNPL** (`tap_deema`) - Source: `src_deema`, 2% fee, Min: 10 KWD
  - Shariah-compliant Buy Now Pay Later
  - Does NOT support authorize transactions
  - Must contact Tap support to enable in production

### Saudi Arabia (SAR)
- **MADA** (`tap_mada`) - Source: `src_sa.mada`, 1.5% fee

### Bahrain (BHD)
- **BENEFIT** (`tap_benefit`) - Source: `src_bh.benefit`, Fixed 0.100 BHD fee

### Qatar (QAR)
- **QPay (NAPS)** (`tap_qpay`) - Source: `src_qa.qpay`, Fixed 0.50 QAR fee
  - Qatar domestic debit cards
  - Must contact Tap support to enable in production
  - Full page redirection required (no iframe)

### Egypt (EGP)
- **Fawry** (`tap_fawry`) - Source: `src_eg.fawry`, 2% fee
  - Disabled by default until configured
  - Egypt's largest payment network

### Digital Wallets
- **Apple Pay** (`tap_apple_pay`) - Source: `src_apple_pay`, 2.5% fee
- **Samsung Pay** (`tap_samsung_pay`) - Source: `src_samsung_pay`, 2.5% fee
  - Disabled by default

---

## ⚠️ Deferred - Complex Implementation Required

### 1. STC Pay (Saudi Arabia) - HIGH COMPLEXITY

**Status:** Not implemented - Requires custom OTP flow

**Details:**
- **Source ID:** `src_sa.stcpay`
- **Currency:** SAR only
- **Flow Type:** TWO-STEP (unique among Tap methods)

**Implementation Requirements:**

#### Step 1: Initial Charge Creation
```json
{
  "amount": 1,
  "currency": "SAR",
  "customer_initiated": true,
  "threeDSecure": true,
  "save_card": false,
  "source": {
    "id": "src_sa.stcpay",
    "phone": {
      "country_code": "966",
      "number": "500000001"
    }
  },
  "redirect": {
    "url": "https://example.com/redirect"
  }
}
```

#### Step 2: Customer Receives OTP
- Customer gets SMS with OTP (Test OTP: 123456)
- Transaction expires in 3 minutes (vs standard 30 minutes)

#### Step 3: Complete Payment via PUT Request
```json
PUT /v2/charges/{charge_id}
{
  "gateway_response": {
    "response": {
      "reference": {
        "otp": "123456"
      }
    }
  }
}
```

**UI/UX Needs:**
1. Phone number input field (country code + number)
2. OTP input field
3. Countdown timer (3 minutes)
4. Resend OTP functionality
5. Real-time charge status updates

**Technical Needs:**
1. New controller methods:
   - `createStcPayCharge(phone, amount)`
   - `submitStcPayOTP(chargeId, otp)`
2. Frontend component for OTP flow
3. Websocket/polling for status updates
4. Error handling for expired OTP

**Limitations:**
- Does NOT support `authorize` transactions
- Does NOT support `recurring` payments
- SAR currency only
- 3-minute expiry (short window)

**Recommendation:** Implement after core payment flow is stable and tested.

---

### 2. Tabby BNPL - MEDIUM COMPLEXITY

**Status:** Not implemented - Requires Tabby-specific integration

**Details:**
- **Source ID:** `src_tabby`
- **Type:** Buy Now Pay Later (4 installments)
- **Flow:** Customer credit check + approval

**Implementation Requirements:**

#### Integration with Tabby API
- Separate API integration beyond standard Tap flow
- Customer eligibility check
- Credit approval process
- Installment scheduling (4 equal payments)

**UI/UX Needs:**
1. Tabby branding and disclosure
2. Payment schedule display
3. Customer agreement checkbox
4. Credit check flow

**Technical Needs:**
1. Tabby API client integration
2. Customer eligibility verification
3. Installment tracking system
4. Payment schedule management
5. Compliance with Tabby's terms

**Recommendation:** Evaluate business need first - May require separate agreement with Tabby.

---

## Standard Implementation Pattern

For reference, all implemented methods follow this pattern:

```php
// Create charge via Tap API
POST /v2/charges
{
  "amount": 10.50,
  "currency": "KWD",
  "source": {
    "id": "src_kw.knet"  // Or other source IDs
  },
  "redirect": {
    "url": "https://example.com/callback"
  }
}

// Customer redirected to payment page
// Payment completed
// Customer redirected back to callback URL

// Verify charge status
GET /v2/charges/{charge_id}
```

**Key Characteristics:**
- Single API call to create charge
- Automatic redirect to payment page
- No additional steps required
- 30-minute expiry window
- Standard callback handling

---

## Seeder Information

**File:** `database/seeders/TapPaymentMethodSeeder.php`

**Usage:**
```bash
php artisan db:seed --class=TapPaymentMethodSeeder
```

**What it does:**
- Seeds 11 standard payment methods for all companies
- Sets appropriate fees and currencies
- Marks Fawry and Samsung Pay as inactive by default
- Displays implementation notes for deferred methods

**Requirements:**
- Tap gateway must exist in `charges` table
- Companies must exist before seeding
- ChargeSeeder should run first

---

## Testing Notes

### Sandbox Testing

**KNET Test Cards:**
- Success: 0000000001 / 0001 / 1234
- Insufficient Funds: 0000000003 / 0001 / 1234

**MADA Test Cards:**
- Success: 5043000000000000 / 12/25 / 123

**Deema Test Amount:**
- Sandbox requires 100-200 KWD minimum (production: 10 KWD)

**STC Pay Test OTP:**
- Always use: 123456

### Production Activation

Some methods require Tap support to enable:
1. QPay - Contact Tap support
2. Deema - Contact Tap support
3. Fawry - May require separate configuration

---

## Documentation References

- [Tap API Documentation](https://developers.tap.company/docs)
- [KNET Integration](https://developers.tap.company/docs/knet)
- [MADA Integration](https://developers.tap.company/docs/mada)
- [QPay Integration](https://developers.tap.company/docs/qpay)
- [Deema Integration](https://developers.tap.company/docs/deema)
- [STC Pay Integration](https://developers.tap.company/docs/stcpay)

---

## Change Log

**2025-11-11** - Initial documentation
- Documented 11 standard payment methods
- Noted STC Pay deferred (OTP flow)
- Noted Tabby deferred (BNPL complexity)
- Created TapPaymentMethodSeeder

---

## Next Steps

1. **Immediate:** Run TapPaymentMethodSeeder for all companies
2. **Short-term:** Test standard methods in sandbox
3. **Medium-term:** Contact Tap support for QPay/Deema production access
4. **Long-term:** Evaluate need for STC Pay and Tabby
5. **Long-term:** Implement STC Pay OTP flow if required
6. **Long-term:** Implement Tabby integration if required

# Charge API Implementation Guide

## Overview
This document outlines where and how to implement the `hasApiImplementation()` and `canGeneratePaymentLink()` methods throughout the codebase.

## Key Concepts

### 1. `hasApiImplementation()` - Technical Check
**Purpose**: Verify if code implementation exists for the gateway
**Returns**: `true` if gateway has API integration code, `false` otherwise
**Location**: `app/Models/Charge.php`

```php
public function hasApiImplementation(): bool
{
    $implementedGateways = ['Tap', 'MyFatoorah', 'Hesabe', 'UPayment'];
    return in_array($this->name, $implementedGateways, true);
}
```

### 2. `canGeneratePaymentLink()` - Combined Check
**Purpose**: Verify both technical capability AND business permission
**Returns**: `true` if API exists AND link generation is enabled
**Location**: `app/Models/Charge.php`

```php
public function canGeneratePaymentLink(): bool
{
    return $this->hasApiImplementation() && $this->can_generate_link;
}
```

---

## Implementation Locations

### 1. InvoiceController

#### A. `show()` Method (Line ~1926-1972)
**Current Issue**: Directly attempts to generate links without checking if API exists

**Current Code**:
```php
$canGenerateLink = false;
foreach ($invoice->invoicePartials as $partial) {
    if ($partial->charge_id) {
        $canGenerateLink = $partial->charge ? $partial->charge->can_generate_link : false;
        break;
    }
}
```

**Recommended Implementation**:
```php
$canGenerateLink = false;
foreach ($invoice->invoicePartials as $partial) {
    if ($partial->charge_id && $partial->charge) {
        // Check both API implementation and business permission
        $canGenerateLink = $partial->charge->canGeneratePaymentLink();
        break;
    }
}

// If link cannot be generated, provide clear reason
if (!$canGenerateLink) {
    $linkGenerationMessage = null;
    if ($partial->charge && !$partial->charge->hasApiImplementation()) {
        $linkGenerationMessage = "Gateway {$partial->charge->name} does not have API implementation.";
    } elseif ($partial->charge && !$partial->charge->can_generate_link) {
        $linkGenerationMessage = "Link generation is disabled for {$partial->charge->name}.";
    }
}
```

#### B. `split()` Method (Line ~2146-2175)
**Current Issue**: Calls ChargeService methods without validating gateway support

**Current Code**:
```php
if (strtolower($paymentGateway) === 'myfatoorah' && $paymentMethod) {
    $gatewayFee = ChargeService::FatoorahCharge($invoicePartial->amount, $paymentMethod, $companyId);
} else if (strtolower($paymentGateway) === 'tap') {
    $gatewayFee = ChargeService::TapCharge([...], $paymentGateway);
}
```

**Recommended Implementation**:
```php
$charge = $invoicePartial->charge;

// Validate API implementation before calling ChargeService
if (!$charge || !$charge->hasApiImplementation()) {
    Log::warning('Gateway has no API implementation', [
        'gateway' => $paymentGateway,
        'invoice_number' => $invoiceNumber,
    ]);
    $gatewayFee = ['fee' => 0, 'paid_by' => 'Company'];
} else {
    try {
        // Use unified getFee method
        $gatewayFee = ChargeService::getFee(
            gatewayName: $paymentGateway,
            amount: $invoicePartial->amount,
            methodCode: $paymentMethod,
            companyId: $companyId,
            currency: $invoice->currency
        );
    } catch (\Exception $e) {
        Log::error('ChargeService exception', [
            'message' => $e->getMessage(),
            'gateway' => $paymentGateway,
        ]);
        $gatewayFee = ['fee' => 0, 'paid_by' => 'Company'];
    }
}
```

#### C. `savePartial()` Method (Line ~950-993)
**Current Issue**: Gateway validation happens after attempting to calculate fees

**Recommended Implementation**:
```php
$charge = Charge::where('name', $gateway)->first();

if (!$charge) {
    return response()->json([
        'status' => 'error',
        'message' => "Gateway '{$gateway}' not found."
    ], 404);
}

// Check API implementation
if (!$charge->hasApiImplementation()) {
    return response()->json([
        'status' => 'error',
        'message' => "Gateway '{$gateway}' does not have API implementation."
    ], 400);
}

// Check if link generation is enabled
if (!$charge->can_generate_link) {
    return response()->json([
        'status' => 'error',
        'message' => "Link generation is disabled for '{$gateway}'."
    ], 400);
}

// Now safe to proceed with ChargeService
$gatewayFee = ChargeService::getFee(
    gatewayName: $gateway,
    amount: $amount,
    methodCode: $method,
    companyId: $companyId
);
```

---

### 2. PaymentController

#### A. `paymentShowLink()` Method (Line ~1771-1794)
**Current Issue**: Recalculates fees without checking gateway support

**Current Code**:
```php
if ($payment->status !== 'completed') {
    if (strtolower($payment->payment_gateway) === 'myfatoorah') {
        $chargeResult = ChargeService::FatoorahCharge(...);
    } else if (strtolower($payment->payment_gateway) === 'tap') {
        $chargeResult = ChargeService::TapCharge(...);
    }
}
```

**Recommended Implementation**:
```php
if ($payment->status !== 'completed') {
    $charge = Charge::where('name', $payment->payment_gateway)
        ->where('company_id', $companyId)
        ->first();
    
    if ($charge && $charge->hasApiImplementation()) {
        try {
            $chargeResult = ChargeService::getFee(
                gatewayName: $payment->payment_gateway,
                amount: $payment->amount,
                methodCode: $payment->payment_method_id,
                companyId: $companyId
            );
            
            $gatewayFee = $chargeResult['fee'] ?? 0;
            $finalAmount = $chargeResult['finalAmount'] ?? $payment->amount;
        } catch (\Exception $e) {
            Log::error('Failed to calculate gateway fee', [
                'error' => $e->getMessage(),
                'payment_id' => $payment->id,
            ]);
            $gatewayFee = 0;
            $finalAmount = $payment->amount;
        }
    } else {
        Log::warning('Gateway has no API implementation', [
            'gateway' => $payment->payment_gateway,
            'payment_id' => $payment->id,
        ]);
        $gatewayFee = 0;
        $finalAmount = $payment->amount;
    }
}
```

#### B. `paymentStoreLinkProcess()` Method (Line ~1617-1630)
**Current Issue**: Direct gateway string comparison without validation

**Current Code**:
```php
if (strtolower($request->payment_gateway) === 'myfatoorah') {
    $chargeResult = ChargeService::FatoorahCharge($request->amount, $paymentMethodId, $companyId);
} else if (strtolower($request->payment_gateway) === 'tap') {
    $chargeResult = ChargeService::TapCharge([...]);
}
```

**Recommended Implementation**:
```php
$gateway = $request->payment_gateway;
$charge = Charge::where('name', $gateway)
    ->where('company_id', $companyId)
    ->first();

if (!$charge) {
    return [
        'status' => 'error',
        'message' => "Gateway '{$gateway}' not found for this company.",
    ];
}

if (!$charge->hasApiImplementation()) {
    return [
        'status' => 'error',
        'message' => "Gateway '{$gateway}' does not support API integration.",
    ];
}

if (!$charge->can_generate_link) {
    return [
        'status' => 'error',
        'message' => "Link generation is disabled for '{$gateway}'.",
    ];
}

// Use unified getFee method
$chargeResult = ChargeService::getFee(
    gatewayName: $gateway,
    amount: $request->amount,
    methodCode: $request->payment_method,
    companyId: $companyId
);
```

#### C. `paymentLinkInitiate()` Method (Line ~1829-1860)
**Current Issue**: Gateway-specific logic without validation

**Recommended Implementation**:
```php
$charge = Charge::where('name', $paymentGateway)
    ->where('company_id', $payment->agent->branch->company_id)
    ->first();

if (!$charge || !$charge->hasApiImplementation()) {
    return redirect()->back()->withErrors([
        'error' => "Gateway '{$paymentGateway}' is not supported or not properly configured."
    ]);
}

// Proceed with gateway-specific implementation
if (strtolower($paymentGateway) === 'tap') {
    // Tap implementation
} else if (strtolower($paymentGateway) === 'myfatoorah') {
    // MyFatoorah implementation
}
```

---

### 3. Blade Views

#### A. `charges/index.blade.php`
**Purpose**: Conditionally show API settings button

**Recommended Implementation**:
```blade
@foreach($charges as $charge)
<tr>
    <td>{{ $charge->name }}</td>
    
    <!-- API Settings Column -->
    <td>
        @if($charge->hasApiImplementation())
            <button @click.stop="editCredsModal = {{ $charge->id }}" 
                    class="text-blue-600 hover:text-blue-800"
                    title="Edit API Settings">
                <svg><!-- Settings Icon --></svg>
            </button>
        @else
            <span class="text-gray-400 text-xs" title="No API implementation">
                No API
            </span>
        @endif
    </td>
</tr>
@endforeach
```

#### B. `invoice/edit.blade.php` & `invoice/show.blade.php`
**Purpose**: Show/hide payment link generation button

**Recommended Implementation**:
```blade
@if($canGenerateLink)
    <button @click="generatePaymentLink()">
        Generate Payment Link
    </button>
@else
    @if($charge && !$charge->hasApiImplementation())
        <span class="text-red-500 text-sm">
            Gateway not implemented
        </span>
    @elseif($charge && !$charge->can_generate_link)
        <span class="text-yellow-500 text-sm">
            Link generation disabled
        </span>
    @endif
@endif
```

#### C. `payment/link/create.blade.php`
**Purpose**: Disable gateway selection if not implemented

**Recommended Implementation**:
```blade
<select name="payment_gateway" id="gateway-select">
    @foreach($paymentGateways as $gateway)
        <option 
            value="{{ $gateway->name }}"
            @if(!$gateway->hasApiImplementation()) disabled @endif
            data-has-api="{{ $gateway->hasApiImplementation() ? 'true' : 'false' }}"
        >
            {{ $gateway->name }}
            @if(!$gateway->hasApiImplementation())
                (Not Supported)
            @endif
        </option>
    @endforeach
</select>

<small class="text-gray-500 mt-1" id="gateway-help-text"></small>

<script>
document.getElementById('gateway-select').addEventListener('change', function() {
    const option = this.options[this.selectedIndex];
    const helpText = document.getElementById('gateway-help-text');
    
    if (option.dataset.hasApi === 'false') {
        helpText.textContent = 'This gateway does not have API implementation yet.';
        helpText.className = 'text-red-500 mt-1';
    } else {
        helpText.textContent = '';
    }
});
</script>
```

---

### 4. API/AJAX Responses

#### Global Error Handler Pattern
**Purpose**: Consistent error messages for frontend

```php
// In any controller method that uses ChargeService
try {
    $charge = Charge::findOrFail($chargeId);
    
    if (!$charge->hasApiImplementation()) {
        return response()->json([
            'success' => false,
            'error' => 'api_not_implemented',
            'message' => "Gateway '{$charge->name}' does not have API implementation.",
            'action' => 'contact_support'
        ], 400);
    }
    
    if (!$charge->can_generate_link) {
        return response()->json([
            'success' => false,
            'error' => 'link_generation_disabled',
            'message' => "Link generation is disabled for '{$charge->name}'.",
            'action' => 'contact_admin'
        ], 400);
    }
    
    // Proceed with operation
    
} catch (\Exception $e) {
    Log::error('Gateway operation failed', [
        'error' => $e->getMessage(),
        'charge_id' => $chargeId,
    ]);
    
    return response()->json([
        'success' => false,
        'error' => 'operation_failed',
        'message' => 'An error occurred while processing the gateway operation.',
    ], 500);
}
```

---

## Testing Checklist

Run these tests after implementation:

```bash
# Run all charge CRUD tests
php artisan test --filter=ChargeTest

# Test specific scenarios
php artisan test --filter=ChargeTest::admin_can_create_system_gateway
php artisan test --filter=ChargeTest::company_cannot_delete_system_gateway
php artisan test --filter=ChargeTest::has_api_implementation_returns_true_for_implemented_gateways
php artisan test --filter=ChargeTest::can_generate_payment_link_requires_both_api_implementation_and_permission

# Test full integration
php artisan test
```

---

## Migration Path

### Phase 1: Add Helper Methods (COMPLETED ✅)
- Added `hasApiImplementation()` to Charge model
- Added `canGeneratePaymentLink()` to Charge model
- Added documentation comments

### Phase 2: Update Controllers (NOT STARTED ⚠️)
- [ ] InvoiceController@show
- [ ] InvoiceController@split
- [ ] InvoiceController@savePartial
- [ ] PaymentController@paymentShowLink
- [ ] PaymentController@paymentStoreLinkProcess
- [ ] PaymentController@paymentLinkInitiate

### Phase 3: Update Views (NOT STARTED ⚠️)
- [ ] charges/index.blade.php
- [ ] invoice/edit.blade.php
- [ ] invoice/show.blade.php
- [ ] payment/link/create.blade.php
- [ ] payment/link/show.blade.php

### Phase 4: Testing (NOT STARTED ⚠️)
- [ ] Run ChargeTest suite
- [ ] Manual testing of payment link generation
- [ ] Manual testing of charge CRUD operations
- [ ] Verify error messages are user-friendly

### Phase 5: Documentation (NOT STARTED ⚠️)
- [ ] Update README with new gateway addition process
- [ ] Document how to add new gateway implementation
- [ ] Create troubleshooting guide

---

## Adding New Gateway Support

When adding a new gateway (e.g., "Deema"):

1. **Create Gateway Class**: `app/Support/PaymentGateway/Deema.php`
2. **Add ChargeService Method**: `ChargeService::DeemaCharge()`
3. **Update hasApiImplementation()**: Add 'Deema' to array
4. **Create Migration**: Mark as system gateway if needed
5. **Test Implementation**: Add tests for new gateway
6. **Update Documentation**: Document API requirements

---

## Best Practices

1. **Always check `hasApiImplementation()` before calling ChargeService**
2. **Use `canGeneratePaymentLink()` for UI visibility**
3. **Provide clear error messages** distinguishing between:
   - No API implementation (technical issue)
   - Link generation disabled (business rule)
4. **Log warnings** when unsupported gateways are attempted
5. **Handle exceptions gracefully** with fallback values
6. **Test both positive and negative scenarios**

---

## FAQ

**Q: Why not just use `can_generate_link` from database?**
A: `can_generate_link` is a business permission. `hasApiImplementation()` is a technical constraint. Both are needed for proper validation.

**Q: What happens if I try to use a gateway without API implementation?**
A: The code should gracefully handle it, log a warning, and return zero fees or an error message.

**Q: Can admins enable `can_generate_link` for custom gateways?**
A: Yes, but it won't work unless `hasApiImplementation()` returns true. The UI should warn them.

**Q: How do I add support for a new payment gateway?**
A: Follow the "Adding New Gateway Support" section above.

---

## Related Files

- `app/Models/Charge.php` - Model with helper methods
- `app/Services/ChargeService.php` - Unified fee calculation
- `app/Policies/ChargePolicy.php` - Authorization rules
- `tests/Feature/ChargeTest.php` - Comprehensive tests
- `database/factories/ChargeFactory.php` - Test data generation
- `database/seeders/ChargeSeeder.php` - System gateway seeding

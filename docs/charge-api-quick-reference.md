# Quick Reference: hasApiImplementation() Usage

## When to Use

### ✅ Use `hasApiImplementation()` when:
1. Validating before calling ChargeService methods
2. Determining if API settings button should show
3. Checking if gateway code exists before processing
4. Logging warnings about unsupported gateways

### ✅ Use `canGeneratePaymentLink()` when:
1. Showing/hiding "Generate Link" button in UI
2. Determining if payment link creation should proceed
3. Combined validation (both technical + business)

### ✅ Use `can_generate_link` (database field) when:
1. Admin wants to enable/disable link generation
2. Business rule to temporarily disable a gateway
3. Feature flag for specific gateway

---

## Code Examples

### Example 1: Before Calling ChargeService
```php
$charge = Charge::where('name', $gatewayName)->first();

if (!$charge->hasApiImplementation()) {
    Log::warning("Gateway {$gatewayName} has no API implementation");
    return ['fee' => 0, 'paid_by' => 'Company'];
}

// Safe to call ChargeService
$result = ChargeService::getFee($gatewayName, $amount, $methodCode, $companyId);
```

### Example 2: UI Button Visibility
```blade
@if($charge->canGeneratePaymentLink())
    <button>Generate Payment Link</button>
@else
    @if(!$charge->hasApiImplementation())
        <span class="text-red-500">Not Implemented</span>
    @else
        <span class="text-yellow-500">Link Generation Disabled</span>
    @endif
@endif
```

### Example 3: API Settings Button
```blade
@if($charge->hasApiImplementation())
    <button @click="editCredsModal = {{ $charge->id }}">
        API Settings
    </button>
@else
    <span class="text-gray-400">No API</span>
@endif
```

### Example 4: Validation in Controller
```php
public function createPaymentLink(Request $request)
{
    $charge = Charge::findOrFail($request->charge_id);
    
    // Technical check
    if (!$charge->hasApiImplementation()) {
        return back()->withErrors([
            'error' => "Gateway '{$charge->name}' is not supported yet."
        ]);
    }
    
    // Business check
    if (!$charge->can_generate_link) {
        return back()->withErrors([
            'error' => "Link generation is disabled for '{$charge->name}'."
        ]);
    }
    
    // OR use combined check
    if (!$charge->canGeneratePaymentLink()) {
        return back()->withErrors([
            'error' => "Cannot generate payment link for '{$charge->name}'."
        ]);
    }
    
    // Proceed with link generation
}
```

### Example 5: Dropdown Selection
```blade
<select name="gateway">
    @foreach($gateways as $gateway)
        <option 
            value="{{ $gateway->name }}"
            @if(!$gateway->hasApiImplementation()) disabled @endif
        >
            {{ $gateway->name }}
            @if(!$gateway->hasApiImplementation()) (Not Supported) @endif
        </option>
    @endforeach
</select>
```

---

## Test Examples

### Test if Gateway Has API
```php
/** @test */
public function gateway_has_api_implementation()
{
    $tap = Charge::factory()->create(['name' => 'Tap']);
    $this->assertTrue($tap->hasApiImplementation());
    
    $custom = Charge::factory()->create(['name' => 'CustomGateway']);
    $this->assertFalse($custom->hasApiImplementation());
}
```

### Test Combined Check
```php
/** @test */
public function can_generate_link_requires_both_checks()
{
    // Has API + Enabled
    $tap = Charge::factory()->create([
        'name' => 'Tap',
        'can_generate_link' => true
    ]);
    $this->assertTrue($tap->canGeneratePaymentLink());
    
    // Has API but Disabled
    $disabled = Charge::factory()->create([
        'name' => 'MyFatoorah',
        'can_generate_link' => false
    ]);
    $this->assertFalse($disabled->canGeneratePaymentLink());
    
    // No API but Enabled
    $custom = Charge::factory()->create([
        'name' => 'CustomGateway',
        'can_generate_link' => true
    ]);
    $this->assertFalse($custom->canGeneratePaymentLink());
}
```

---

## Common Patterns

### Pattern 1: Try-Catch with Validation
```php
try {
    $charge = Charge::where('name', $gatewayName)->firstOrFail();
    
    if (!$charge->hasApiImplementation()) {
        throw new \Exception("Gateway not implemented");
    }
    
    $result = ChargeService::getFee(...);
    
} catch (\Exception $e) {
    Log::error('Payment processing failed', [
        'error' => $e->getMessage(),
        'gateway' => $gatewayName,
    ]);
    
    return response()->json([
        'error' => 'Payment processing failed',
        'message' => $e->getMessage()
    ], 400);
}
```

### Pattern 2: Early Return
```php
public function processPayment($gatewayName, $amount)
{
    $charge = Charge::where('name', $gatewayName)->first();
    
    if (!$charge) {
        return ['error' => 'Gateway not found'];
    }
    
    if (!$charge->hasApiImplementation()) {
        return ['error' => 'Gateway not supported'];
    }
    
    if (!$charge->can_generate_link) {
        return ['error' => 'Link generation disabled'];
    }
    
    // Happy path
    return ChargeService::getFee(...);
}
```

### Pattern 3: Detailed Error Messages
```php
$charge = Charge::find($chargeId);

if (!$charge->canGeneratePaymentLink()) {
    $reason = !$charge->hasApiImplementation() 
        ? 'The gateway does not have API implementation yet. Please contact support.'
        : 'Link generation is currently disabled for this gateway. Please contact your administrator.';
    
    return response()->json([
        'error' => 'Cannot generate payment link',
        'reason' => $reason,
        'action' => !$charge->hasApiImplementation() ? 'contact_support' : 'contact_admin'
    ], 400);
}
```

---

## Checklist for Implementation

When implementing in a new location:

- [ ] Check `hasApiImplementation()` before calling ChargeService
- [ ] Use `canGeneratePaymentLink()` for UI visibility
- [ ] Provide clear error messages for each failure case
- [ ] Log warnings when unsupported gateways are attempted
- [ ] Handle exceptions gracefully
- [ ] Test both positive and negative scenarios
- [ ] Update blade views to show appropriate messages

---

## Common Mistakes to Avoid

### ❌ DON'T: Only check database field
```php
// WRONG - might break if no API implementation
if ($charge->can_generate_link) {
    ChargeService::getFee(...); // May crash!
}
```

### ✅ DO: Check API implementation first
```php
// CORRECT
if ($charge->canGeneratePaymentLink()) {
    ChargeService::getFee(...); // Safe
}
```

### ❌ DON'T: Hardcode gateway names
```php
// WRONG - not maintainable
if (in_array($gateway, ['Tap', 'MyFatoorah'])) {
    // process
}
```

### ✅ DO: Use the method
```php
// CORRECT
if ($charge->hasApiImplementation()) {
    // process
}
```

### ❌ DON'T: Forget error messages
```php
// WRONG - user doesn't know why it failed
if (!$charge->canGeneratePaymentLink()) {
    return back()->withErrors(['error' => 'Failed']);
}
```

### ✅ DO: Provide context
```php
// CORRECT
if (!$charge->canGeneratePaymentLink()) {
    $message = !$charge->hasApiImplementation()
        ? "Gateway '{$charge->name}' is not supported"
        : "Link generation is disabled for '{$charge->name}'";
    
    return back()->withErrors(['error' => $message]);
}
```

---

## Summary

**Remember**: 
- `hasApiImplementation()` = "Does code exist?" (Technical)
- `can_generate_link` = "Is it enabled?" (Business)
- `canGeneratePaymentLink()` = Both checks combined (Recommended)

**Use**: `canGeneratePaymentLink()` for most cases, use individual checks when you need to provide specific error messages.

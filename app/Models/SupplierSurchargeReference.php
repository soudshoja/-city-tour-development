<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierSurchargeReference extends Model
{
    protected $fillable = [
        'supplier_surcharge_id',
        'reference',
        'is_charged',
    ];

    protected $casts = [
        'is_charged' => 'boolean',
    ];

    public function supplierSurcharge()
    {
        return $this->belongsTo(SupplierSurcharge::class);
    }

    public function markAsCharged(): void
    {
        $this->update(['is_charged' => true]);
    }

    public function canBeCharged(string $behavior = 'single'): bool
    {
        if ($this->is_charged) {
            return false;
        }

        if ($behavior === 'repetitive') {
            return true;
        }

        return !$this->is_charged;
    }


   public static function createSurchargeRecord($task, $surcharge)
{
    // Check if this reference already exists
    $existing = self::where('supplier_surcharge_id', $surcharge->id)
        ->where('reference', $task->reference)
        ->first();

    if ($existing) {
        return $existing; // don't recreate or reset
    }

    return self::create([
        'supplier_surcharge_id' => $surcharge->id,
        'reference' => $task->reference,
        'is_charged' => false,
    ]);
}

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * BulkUploadRow Model
 *
 * Represents a single row from a bulk invoice upload with validation status and matched entities.
 */
class BulkUploadRow extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'bulk_upload_id',
        'row_number',
        'status',
        'task_id',
        'client_id',
        'supplier_id',
        'payment_id',
        'raw_data',
        'errors',
        'flag_reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'raw_data' => 'array',
        'errors' => 'array',
        'status' => 'string',
        'row_number' => 'integer',
    ];

    /**
     * Get the bulk upload that owns the row.
     */
    public function bulkUpload()
    {
        return $this->belongsTo(BulkUpload::class);
    }

    /**
     * Get the task associated with this row.
     */
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the client associated with this row.
     */
    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the supplier associated with this row.
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the payment associated with this row.
     */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}

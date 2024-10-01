<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceDetails extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'invoice_number',
        'task_id',
        'task_description',
        'task_remark',
        'task_price',
    ];

}

<?php

namespace App\Enums;

enum InvoiceReceiptStatus : string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}

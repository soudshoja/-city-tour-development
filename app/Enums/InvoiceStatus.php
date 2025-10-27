<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID_BY_REFUND = 'paid by refund';
    case REFUNDED = 'refunded';
}

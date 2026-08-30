<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case PAID = 'paid';
    case UNPAID = 'unpaid';
    case PARTIAL = 'partial';
    case PAID_BY_REFUND = 'paid by refund';
    case REFUNDED = 'refunded';
    case PARTIAL_REFUND = 'partial refund';

    /** W6.V (w6-brief.md "Model"): all of an invoice's tasks have been voided. */
    case CANCELLED = 'cancelled';
}

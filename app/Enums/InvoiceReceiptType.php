<?php

namespace App\Enums;

enum InvoiceReceiptType : string
{
    case INVOICE = 'invoice';
    case CREDIT = 'credit';
    case ACCOUNT = 'account';
    case IMPORT = 'import';
}

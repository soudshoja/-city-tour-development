<?php

namespace App\Enums;

enum TaskSupplierStatus : string
{
    case MAGIC_CANCEL = 'XX';    
    case MAGIC_CONFIRM = 'OK';
    case MAGIC_CONFIRM_PENDING = 'RQ';
    case MAGIC_AMEND = 'AM';
}

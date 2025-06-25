<?php

namespace App\Enums;

enum SupplierAuthType: string
{
    case BASIC = 'basic';
    case OAUTH = 'oauth';
}

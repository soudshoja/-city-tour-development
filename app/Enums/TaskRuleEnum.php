<?php

namespace App\Enums;

enum TaskRuleEnum : string
{
    case DEFAULT = 'default';
    case MINUS_EXISTING = 'minus_existing';
    case TAX_CALCULATED = 'tax_calculated';
}

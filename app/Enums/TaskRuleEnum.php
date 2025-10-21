<?php

namespace App\Enums;

enum TaskRuleEnum : string
{
    case DEFAULT = 'default';
    case MINUS_EXISTING = 'minus_existing';
}

<?php

namespace App\Enums;

enum PaymentMailTypeEnum: string
{
    case PAYMENT_LINK = 'payment_link';
    case PAYMENT_SUCCESS = 'payment_success';
    case PAYMENT_FAILURE = 'payment_failure';
}


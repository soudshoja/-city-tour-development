<?php

namespace App\Enums;

enum NotificationEmailTypeEnum: string
{
    case AUTOBILL = 'autobill';
    case PAYMENT = 'payment';
}
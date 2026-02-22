<?php

namespace App\Enums;

enum InvoiceType: string
{
    case SALES = 'sales';
    case SERVICE = 'service';
    case PURCHASE = 'purchase';
}

<?php

namespace App\Enums;

enum JournalType: string
{
    case GENERAL = 'general';
    case SALES = 'sales';
    case PURCHASE = 'purchase';
    case CASH_RECEIPTS = 'cash_receipts';
    case CASH_DISBURSEMENTS = 'cash_disbursements';
}

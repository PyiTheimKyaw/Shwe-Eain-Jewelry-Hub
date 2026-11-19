<?php

namespace App\Enums;

enum PaymentChannel: string
{
    case KPay = 'kpay';

    case KBZ = 'kbz';

    case CB = 'cb';

    case Bank = 'bank';

    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::KPay => 'KPay',
            self::KBZ => 'KBZ Pay',
            self::CB => 'CB Bank',
            self::Bank => 'Bank transfer',
            self::Other => 'Other',
        };
    }
}

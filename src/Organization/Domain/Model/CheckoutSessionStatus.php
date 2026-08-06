<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

enum CheckoutSessionStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
}

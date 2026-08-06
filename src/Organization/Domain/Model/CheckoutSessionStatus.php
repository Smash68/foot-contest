<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

enum CheckoutSessionStatus
{
    case Pending;
    case Completed;
    case Failed;
}

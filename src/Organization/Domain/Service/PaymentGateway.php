<?php

declare(strict_types=1);

namespace App\Organization\Domain\Service;

use App\Organization\Domain\Model\CheckoutReference;

interface PaymentGateway
{
    public function initiateCheckout(): CheckoutReference;
}

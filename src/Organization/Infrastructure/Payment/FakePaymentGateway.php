<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Payment;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Service\PaymentGateway;
use Symfony\Component\Uid\Uuid;

final class FakePaymentGateway implements PaymentGateway
{
    public function initiateCheckout(): CheckoutReference
    {
        return new CheckoutReference('cs_test_'.Uuid::v7()->toRfc4122());
    }
}

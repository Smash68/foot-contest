<?php

declare(strict_types=1);

namespace App\Organization\Domain\Repository;

use App\Organization\Domain\Model\CheckoutReference;
use App\Organization\Domain\Model\CheckoutSession;
use App\Organization\Domain\Model\CheckoutSessionId;

interface CheckoutSessionRepository
{
    public function nextIdentity(): CheckoutSessionId;

    public function save(CheckoutSession $session): void;

    public function ofCheckoutReference(CheckoutReference $reference): ?CheckoutSession;
}

<?php

declare(strict_types=1);

namespace App\Organization\Application\ConfirmOrganizationCheckout;

final readonly class ConfirmOrganizationCheckoutCommand
{
    public function __construct(
        public string $checkoutReference,
        public bool $succeeded,
    ) {
    }
}

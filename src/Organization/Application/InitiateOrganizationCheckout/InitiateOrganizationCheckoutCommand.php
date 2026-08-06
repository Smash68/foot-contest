<?php

declare(strict_types=1);

namespace App\Organization\Application\InitiateOrganizationCheckout;

final readonly class InitiateOrganizationCheckoutCommand
{
    public function __construct(
        public string $organizationName,
        public string $ownerId,
    ) {
    }
}

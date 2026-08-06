<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

final readonly class InitiateOrganizationCheckoutRequest
{
    public function __construct(
        public string $organizationName,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Organization\Infrastructure\Http;

final readonly class ConfirmOrganizationCheckoutRequest
{
    public function __construct(
        public string $checkoutReference,
        public bool $succeeded,
    ) {
    }
}

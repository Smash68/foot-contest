<?php

declare(strict_types=1);

namespace App\Competition\Application\CloseRegistration;

final readonly class CloseRegistrationCommand
{
    public function __construct(
        public string $competitionId,
    ) {
    }
}

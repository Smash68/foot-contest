<?php

declare(strict_types=1);

namespace App\Competition\Application\Withdraw;

final readonly class WithdrawCommand
{
    public function __construct(
        public string $competitionId,
        public string $teamId,
    ) {
    }
}
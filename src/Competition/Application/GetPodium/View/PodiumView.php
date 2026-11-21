<?php

declare(strict_types=1);

namespace App\Competition\Application\GetPodium\View;

final readonly class PodiumView
{
    public function __construct(
        public string $champion,
        public string $runnerUp,
        public ?string $third,
    ) {
    }
}

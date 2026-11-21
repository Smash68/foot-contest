<?php

declare(strict_types=1);

namespace App\Competition\Application\GetPodium\View;

final readonly class PodiumView implements \JsonSerializable
{
    public function __construct(
        public string $champion,
        public string $runnerUp,
        public ?string $third,
    ) {
    }

    /** @return array{champion: string, runnerUp: string, third: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'champion' => $this->champion,
            'runnerUp' => $this->runnerUp,
            'third' => $this->third,
        ];
    }
}

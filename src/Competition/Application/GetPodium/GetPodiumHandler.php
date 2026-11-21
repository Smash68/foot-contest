<?php

declare(strict_types=1);

namespace App\Competition\Application\GetPodium;

use App\Competition\Application\GetPodium\View\PodiumView;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;

final readonly class GetPodiumHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
    ) {
    }

    public function __invoke(GetPodiumQuery $query): ?PodiumView
    {
        $competition = $this->competitions->ofId(new CompetitionId($query->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$query->competitionId}' does not exist.");
        }

        if (!$competition->isFinished()) {
            return null;
        }

        $podium = $competition->getPodium();

        return new PodiumView($podium->champion->value, $podium->runnerUp->value, $podium->third?->value);
    }
}

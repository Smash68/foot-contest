<?php

declare(strict_types=1);

namespace App\Competition\Application\GenerateBracket;

use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\BracketGeneratorFactory;

final readonly class GenerateBracketHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private BracketGeneratorFactory $factory,
    ) {
    }

    public function __invoke(GenerateBracketCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        $competition->generateBracket($this->factory);

        $this->competitions->save($competition);
    }
}

<?php

declare(strict_types=1);

namespace App\Competition\Application\GenerateBracket;

use App\Competition\Domain\Exception\OrganizerNotAuthorizedForOrganizationException;
use App\Competition\Domain\Model\CompetitionId;
use App\Competition\Domain\Repository\CompetitionRepository;
use App\Competition\Domain\Service\BracketGeneratorFactory;
use App\Competition\Domain\Service\OrganizerOrganizationAuthorization;

final readonly class GenerateBracketHandler
{
    public function __construct(
        private CompetitionRepository $competitions,
        private BracketGeneratorFactory $factory,
        private OrganizerOrganizationAuthorization $authorization,
    ) {
    }

    public function __invoke(GenerateBracketCommand $command): void
    {
        $competition = $this->competitions->ofId(new CompetitionId($command->competitionId));

        if ($competition === null) {
            throw new \InvalidArgumentException("Competition '{$command->competitionId}' does not exist.");
        }

        if (!$this->authorization->authorizes($command->organizerId, $competition->getOrganizationId())) {
            throw new OrganizerNotAuthorizedForOrganizationException($command->organizerId, $competition->getOrganizationId()->value);
        }

        $competition->generateBracket($this->factory);

        $this->competitions->save($competition);
    }
}

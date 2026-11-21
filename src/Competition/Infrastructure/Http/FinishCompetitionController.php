<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\FinishCompetition\FinishCompetitionCommand;
use App\Organization\Infrastructure\Security\SecurityOrganizer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class FinishCompetitionController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/finish', methods: ['POST'])]
    public function __invoke(string $competitionId, #[CurrentUser] SecurityOrganizer $organizer): Response
    {
        $this->bus->dispatch(new FinishCompetitionCommand($competitionId, $organizer->getUserIdentifier()));

        return new Response(status: 204);
    }
}

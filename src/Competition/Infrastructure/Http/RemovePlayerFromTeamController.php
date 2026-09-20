<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RemovePlayerFromTeam\RemovePlayerFromTeamCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RemovePlayerFromTeamController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/teams/{teamId}/players/{playerId}', methods: ['DELETE'])]
    public function __invoke(string $competitionId, string $teamId, string $playerId, #[CurrentUser] UserInterface $actor): Response
    {
        $this->bus->dispatch(new RemovePlayerFromTeamCommand($competitionId, $teamId, $playerId, $actor->getUserIdentifier()));

        return new Response(status: 204);
    }
}

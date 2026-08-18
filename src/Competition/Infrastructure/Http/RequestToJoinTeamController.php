<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RequestToJoinTeam\RequestToJoinTeamCommand;
use App\Competition\Infrastructure\Security\SecurityPlayer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RequestToJoinTeamController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/teams/{teamId}/join-requests', methods: ['POST'])]
    public function __invoke(string $competitionId, string $teamId, #[CurrentUser] SecurityPlayer $player): Response
    {
        $this->bus->dispatch(new RequestToJoinTeamCommand($competitionId, $teamId, $player->getUserIdentifier()));

        return new Response(status: 204);
    }
}

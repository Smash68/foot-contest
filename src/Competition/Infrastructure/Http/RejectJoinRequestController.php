<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RejectJoinRequest\RejectJoinRequestCommand;
use App\Competition\Infrastructure\Security\SecurityPlayer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RejectJoinRequestController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/teams/{teamId}/join-requests/{playerId}/reject', methods: ['POST'])]
    public function __invoke(string $competitionId, string $teamId, string $playerId, #[CurrentUser] SecurityPlayer $captain): Response
    {
        $this->bus->dispatch(new RejectJoinRequestCommand($competitionId, $teamId, $playerId, $captain->getUserIdentifier()));

        return new Response(status: 204);
    }
}

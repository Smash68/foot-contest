<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\Withdraw\WithdrawCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class WithdrawController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/teams/{teamId}', methods: ['DELETE'])]
    public function __invoke(string $competitionId, string $teamId): Response
    {
        $this->bus->dispatch(new WithdrawCommand($competitionId, $teamId));

        return new Response(status: 204);
    }
}

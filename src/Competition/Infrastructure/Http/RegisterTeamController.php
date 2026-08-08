<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RegisterTeam\RegisterTeamCommand;
use App\Competition\Domain\Model\TeamId;
use App\Competition\Infrastructure\Security\SecurityPlayer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RegisterTeamController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/teams', methods: ['POST'])]
    public function __invoke(
        string $competitionId,
        #[MapRequestPayload] RegisterTeamRequest $request,
        #[CurrentUser] SecurityPlayer $player,
    ): JsonResponse {
        $envelope = $this->bus->dispatch(new RegisterTeamCommand(
            $competitionId,
            $request->name,
            $player->getUserIdentifier(),
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $id = $handledStamp->getResult();
        assert($id instanceof TeamId);

        return new JsonResponse(['id' => $id->value], 201);
    }
}

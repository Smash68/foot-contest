<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Domain\Model\CompetitionId;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class CreateCompetitionController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions', methods: ['POST'])]
    public function __invoke(#[MapRequestPayload] CreateCompetitionRequest $request): JsonResponse
    {
        $envelope = $this->bus->dispatch(new CreateCompetitionCommand(
            $request->name,
            $request->minTeams,
            $request->maxTeams,
        ));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $id = $handledStamp->getResult();
        assert($id instanceof CompetitionId);

        return new JsonResponse(['id' => $id->value], 201);
    }
}

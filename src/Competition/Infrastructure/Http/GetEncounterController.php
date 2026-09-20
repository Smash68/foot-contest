<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\GetEncounter\GetEncounterQuery;
use App\Competition\Application\GetEncounter\View\EncounterDetailView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class GetEncounterController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/encounters/{encounterId}', methods: ['GET'])]
    public function __invoke(string $competitionId, string $encounterId): JsonResponse
    {
        $envelope = $this->bus->dispatch(new GetEncounterQuery($competitionId, $encounterId));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $view = $handledStamp->getResult();
        assert($view === null || $view instanceof EncounterDetailView);

        if ($view === null) {
            return new JsonResponse(['error' => "Encounter '{$encounterId}' not found for competition '{$competitionId}'."], 404);
        }

        return new JsonResponse($view);
    }
}

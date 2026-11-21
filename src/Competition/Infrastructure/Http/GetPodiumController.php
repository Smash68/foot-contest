<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\GetPodium\GetPodiumQuery;
use App\Competition\Application\GetPodium\View\PodiumView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class GetPodiumController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/podium', methods: ['GET'])]
    public function __invoke(string $competitionId): JsonResponse
    {
        $envelope = $this->bus->dispatch(new GetPodiumQuery($competitionId));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $view = $handledStamp->getResult();
        assert($view === null || $view instanceof PodiumView);

        if ($view === null) {
            return new JsonResponse(['error' => "Competition '{$competitionId}' is not finished yet."], 404);
        }

        return new JsonResponse($view);
    }
}

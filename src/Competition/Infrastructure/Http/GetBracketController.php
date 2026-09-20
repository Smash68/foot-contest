<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\GetBracket\GetBracketQuery;
use App\Competition\Application\GetBracket\View\BracketView;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

final class GetBracketController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/bracket', methods: ['GET'])]
    public function __invoke(string $competitionId): JsonResponse
    {
        $envelope = $this->bus->dispatch(new GetBracketQuery($competitionId));

        $handledStamp = $envelope->last(HandledStamp::class);
        assert($handledStamp instanceof HandledStamp);

        $view = $handledStamp->getResult();
        assert($view === null || $view instanceof BracketView);

        if ($view === null) {
            return new JsonResponse(['error' => "Bracket for competition '{$competitionId}' has not been generated yet."], 404);
        }

        return new JsonResponse($view);
    }
}

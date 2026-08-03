<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\GenerateBracket\GenerateBracketCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class GenerateBracketController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/generate-bracket', methods: ['POST'])]
    public function __invoke(string $competitionId): Response
    {
        $this->bus->dispatch(new GenerateBracketCommand($competitionId));

        return new Response(status: 204);
    }
}

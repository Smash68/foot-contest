<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\CloseRegistration\CloseRegistrationCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

final class CloseRegistrationController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/close-registration', methods: ['POST'])]
    public function __invoke(string $competitionId): Response
    {
        $this->bus->dispatch(new CloseRegistrationCommand($competitionId));

        return new Response(status: 204);
    }
}
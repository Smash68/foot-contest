<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Http;

use App\Competition\Application\RecordEncounterResult\RecordEncounterResultCommand;
use App\Competition\Application\RecordEncounterResult\ScoreInput;
use App\Organization\Infrastructure\Security\SecurityOrganizer;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class RecordEncounterResultController
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    #[Route('/competitions/{competitionId}/encounters/{encounterId}/result', methods: ['POST'])]
    public function __invoke(
        string $competitionId,
        string $encounterId,
        #[MapRequestPayload] RecordEncounterResultRequest $request,
        #[CurrentUser] SecurityOrganizer $organizer,
    ): Response {
        $this->bus->dispatch(new RecordEncounterResultCommand(
            competitionId: $competitionId,
            encounterId: $encounterId,
            organizerId: $organizer->getUserIdentifier(),
            regularTime: new ScoreInput($request->regularTime->home, $request->regularTime->away),
        ));

        return new Response(status: 204);
    }
}

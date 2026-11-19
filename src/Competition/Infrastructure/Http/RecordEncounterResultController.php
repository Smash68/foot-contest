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
        #[MapRequestPayload] EncounterResultRequest $request,
        #[CurrentUser] SecurityOrganizer $organizer,
    ): Response {
        $this->bus->dispatch(new RecordEncounterResultCommand(
            competitionId: $competitionId,
            encounterId: $encounterId,
            organizerId: $organizer->getUserIdentifier(),
            regularTime: self::scoreInput($request->regularTime),
            extraTime: $request->extraTime !== null ? self::scoreInput($request->extraTime) : null,
            penalties: $request->penalties !== null ? self::scoreInput($request->penalties) : null,
        ));

        return new Response(status: 204);
    }

    private static function scoreInput(ScoreRequest $score): ScoreInput
    {
        return new ScoreInput($score->home, $score->away);
    }
}

<?php

declare(strict_types=1);

namespace App\Competition\Application\GetEncounter\View;

final readonly class EncounterDetailView implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public ParticipantDetailView $home,
        public ParticipantDetailView $away,
        public ?EncounterResultView $result = null,
    ) {
    }

    /** @return array{id: string, home: ParticipantDetailView, away: ParticipantDetailView, result: ?EncounterResultView} */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'home' => $this->home,
            'away' => $this->away,
            'result' => $this->result,
        ];
    }
}

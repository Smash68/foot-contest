<?php

declare(strict_types=1);

namespace App\Competition\Application\GetBracket\View;

final readonly class EncounterView implements \JsonSerializable
{
    public function __construct(
        public string $id,
        public ParticipantView $home,
        public ParticipantView $away,
        public ?EncounterResultView $result = null,
    ) {
    }

    /** @return array{id: string, home: ParticipantView, away: ParticipantView, result: ?EncounterResultView} */
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

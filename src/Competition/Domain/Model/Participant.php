<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final readonly class Participant
{
    private function __construct(
        private ?TeamId $teamId,
        private bool $isBye,
        private ?EncounterId $pendingWinnerOfEncounterId,
    ) {
    }

    public static function forTeam(TeamId $teamId): self
    {
        return new self($teamId, false, null);
    }

    public static function bye(): self
    {
        return new self(null, true, null);
    }

    public static function pendingWinnerOf(EncounterId $encounterId): self
    {
        return new self(null, false, $encounterId);
    }

    public function isTeam(): bool
    {
        return $this->teamId !== null;
    }

    public function isBye(): bool
    {
        return $this->isBye;
    }

    public function isPending(): bool
    {
        return $this->pendingWinnerOfEncounterId !== null;
    }

    public function getTeamId(): TeamId
    {
        if ($this->teamId === null) {
            throw new \LogicException('Participant does not hold a team.');
        }

        return $this->teamId;
    }

    public function getPendingWinnerOfEncounterId(): EncounterId
    {
        if ($this->pendingWinnerOfEncounterId === null) {
            throw new \LogicException('Participant is not pending.');
        }

        return $this->pendingWinnerOfEncounterId;
    }
}

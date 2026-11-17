<?php

declare(strict_types=1);

namespace App\Competition\Domain\Format\SingleElimination;

use App\Competition\Domain\Model\EncounterId;

final readonly class ThirdPlaceFixture
{
    public function __construct(
        public EncounterId $id,
        public EncounterId $semiFinalOneId,
        public EncounterId $semiFinalTwoId,
    ) {
    }

    public function isSemiFinal(EncounterId $encounterId): bool
    {
        return $encounterId->equals($this->semiFinalOneId) || $encounterId->equals($this->semiFinalTwoId);
    }
}

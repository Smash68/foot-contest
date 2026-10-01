<?php

declare(strict_types=1);

namespace App\Tests\Support\Assertion;

use App\Competition\Domain\Model\Encounter;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Assert;

/**
 * Reads an Encounter's participants: who holds home/away, and whether it is a known team or still pending.
 */
final class EncounterAssert
{
    private function __construct(private readonly Encounter $encounter)
    {
    }

    public static function assertThat(Encounter $encounter): self
    {
        return new self($encounter);
    }

    public function homeIsForTeam(TeamId $teamId): self
    {
        Assert::assertTrue($this->encounter->getHome()->isTeam());
        Assert::assertSame($teamId, $this->encounter->getHome()->getTeamId());

        return $this;
    }

    public function awayIsPending(): self
    {
        Assert::assertTrue($this->encounter->getAway()->isPending());

        return $this;
    }
}

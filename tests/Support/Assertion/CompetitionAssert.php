<?php

declare(strict_types=1);

namespace App\Tests\Support\Assertion;

use App\Competition\Domain\Model\Competition;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Assert;

/**
 * Reads a Competition's registration state: how many teams, and who is on each team's roster or waiting list.
 */
final class CompetitionAssert
{
    private function __construct(private readonly Competition $competition)
    {
    }

    public static function assertThat(Competition $competition): self
    {
        return new self($competition);
    }

    public function hasRegisteredTeamsCount(int $count): self
    {
        Assert::assertSame($count, $this->competition->countRegistrations());

        return $this;
    }

    public function hasPendingRequestsCount(string $teamId, int $count): self
    {
        Assert::assertCount($count, $this->competition->getTeamPendingRequests(new TeamId($teamId)));

        return $this;
    }
}

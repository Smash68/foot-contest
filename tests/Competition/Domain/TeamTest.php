<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Model\Team;
use App\Competition\Domain\Model\TeamId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeamTest extends TestCase
{
    #[Test]
    public function it_exposes_the_captain_id(): void
    {
        $captainId = new PlayerId('captain@example.com');

        $team = Team::create(new TeamId('a'), 'Team A', $captainId);

        self::assertTrue($captainId->equals($team->getCaptainId()));
    }

    #[Test]
    public function it_initializes_the_roster_with_the_captain(): void
    {
        $captainId = new PlayerId('captain@example.com');

        $team = Team::create(new TeamId('a'), 'Team A', $captainId);

        self::assertCount(1, $team->getRoster());
        self::assertTrue($captainId->equals($team->getRoster()[0]));
    }
}

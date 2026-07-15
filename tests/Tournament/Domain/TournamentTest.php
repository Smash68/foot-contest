<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\TeamCapacity;
use App\Tournament\Domain\Model\Tournament;
use App\Tournament\Domain\Model\TournamentId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TournamentTest extends TestCase
{
    #[Test]
    public function it_starts_open_for_registration_with_no_teams(): void
    {
        $tournament = Tournament::create(new TournamentId('t1'), 'Summer Cup', TeamCapacity::of(2, 4));

        self::assertTrue($tournament->isOpenForRegistration());
        self::assertSame(0, $tournament->countRegistrations());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\EncounterResult;
use App\Tournament\Domain\Model\Side;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncounterResultTest extends TestCase
{
    #[Test]
    public function it_declares_home_as_winner_when_home_scores_more(): void
    {
        $result = EncounterResult::regularTime(2, 0);

        self::assertSame(Side::Home, $result->winner());
    }

    #[Test]
    public function it_declares_away_as_winner_when_away_scores_more(): void
    {
        $result = EncounterResult::regularTime(1, 3);

        self::assertSame(Side::Away, $result->winner());
    }

    #[Test]
    public function it_rejects_a_draw_in_regular_time(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::regularTime(1, 1);
    }

    #[Test]
    public function it_rejects_negative_scores(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::regularTime(-1, 2);
    }
}

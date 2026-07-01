<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\EncounterResult;
use App\Tournament\Domain\Model\Score;
use App\Tournament\Domain\Model\Side;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncounterResultTest extends TestCase
{
    // --- regularTime ---

    #[Test]
    public function it_declares_home_as_winner_when_home_scores_more(): void
    {
        self::assertSame(Side::Home, EncounterResult::regularTime(Score::of(2, 0))->winner());
    }

    #[Test]
    public function it_declares_away_as_winner_when_away_scores_more(): void
    {
        self::assertSame(Side::Away, EncounterResult::regularTime(Score::of(1, 3))->winner());
    }

    #[Test]
    public function it_throws_when_getting_winner_from_a_draw(): void
    {
        $this->expectException(\LogicException::class);

        EncounterResult::regularTime(Score::of(1, 1))->winner();
    }

    // --- afterExtraTime ---

    #[Test]
    public function it_declares_home_winner_after_extra_time(): void
    {
        // 1-1 à la fin du temps réglementaire, 2-1 après prolongations
        $result = EncounterResult::afterExtraTime(Score::of(1, 1), Score::of(2, 1));

        self::assertSame(Side::Home, $result->winner());
    }

    #[Test]
    public function it_declares_away_winner_after_extra_time(): void
    {
        $result = EncounterResult::afterExtraTime(Score::of(0, 0), Score::of(0, 1));

        self::assertSame(Side::Away, $result->winner());
    }

    #[Test]
    public function it_rejects_extra_time_when_regular_time_was_not_a_draw(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterExtraTime(Score::of(2, 1), Score::of(3, 1));
    }

    #[Test]
    public function it_rejects_extra_time_when_still_a_draw_after_et(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterExtraTime(Score::of(1, 1), Score::of(2, 2));
    }

    #[Test]
    public function it_rejects_extra_time_goals_lower_than_regular_time_goals(): void
    {
        // Le score ET est cumulatif — impossible de "désmarquer" des buts
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterExtraTime(Score::of(1, 1), Score::of(0, 2));
    }

    // --- afterPenalties ---

    #[Test]
    public function it_declares_home_winner_on_penalties(): void
    {
        $result = EncounterResult::afterPenalties(Score::of(1, 1), Score::of(2, 2), Score::of(4, 3));

        self::assertSame(Side::Home, $result->winner());
    }

    #[Test]
    public function it_declares_away_winner_on_penalties(): void
    {
        $result = EncounterResult::afterPenalties(Score::of(0, 0), Score::of(1, 1), Score::of(3, 5));

        self::assertSame(Side::Away, $result->winner());
    }

    #[Test]
    public function it_rejects_penalties_when_regular_time_was_not_a_draw(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterPenalties(Score::of(2, 1), Score::of(2, 2), Score::of(4, 3));
    }

    #[Test]
    public function it_rejects_penalties_when_extra_time_was_not_a_draw(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterPenalties(Score::of(1, 1), Score::of(3, 2), Score::of(4, 3));
    }

    #[Test]
    public function it_rejects_a_draw_in_penalties(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncounterResult::afterPenalties(Score::of(1, 1), Score::of(2, 2), Score::of(3, 3));
    }
}
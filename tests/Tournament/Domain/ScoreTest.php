<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\Score;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScoreTest extends TestCase
{
    #[Test]
    public function it_rejects_negative_home_goals(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Score::of(-1, 0);
    }

    #[Test]
    public function it_rejects_negative_away_goals(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Score::of(0, -1);
    }

    #[Test]
    public function it_detects_a_draw(): void
    {
        self::assertTrue(Score::of(1, 1)->isDraw());
    }

    #[Test]
    public function it_detects_no_draw(): void
    {
        self::assertFalse(Score::of(2, 1)->isDraw());
    }
}
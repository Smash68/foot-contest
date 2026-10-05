<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\MinimumRosterSize;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MinimumRosterSizeTest extends TestCase
{
    #[Test]
    public function it_rejects_a_minimum_below_one_player(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        MinimumRosterSize::of(0);
    }
}

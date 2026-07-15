<?php

declare(strict_types=1);

namespace App\Tests\Tournament\Domain;

use App\Tournament\Domain\Model\TeamCapacity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TeamCapacityTest extends TestCase
{
    #[Test]
    public function it_rejects_a_minimum_greater_than_the_maximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TeamCapacity::of(8, 4);
    }

    #[Test]
    public function it_rejects_a_minimum_below_two_teams(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        TeamCapacity::of(1, 4);
    }
}
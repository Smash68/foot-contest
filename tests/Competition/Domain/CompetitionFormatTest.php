<?php

declare(strict_types=1);

namespace App\Tests\Competition\Domain;

use App\Competition\Domain\Model\CompetitionFormat;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompetitionFormatTest extends TestCase
{
    #[Test]
    public function it_rejects_an_unknown_value(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CompetitionFormat::fromValue('not_a_real_format');
    }
}

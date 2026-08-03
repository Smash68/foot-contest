<?php

declare(strict_types=1);

namespace App\Tests\Competition\Application\CreateCompetition;

use App\Competition\Application\CreateCompetition\CreateCompetitionCommand;
use App\Competition\Domain\Model\CompetitionFormat;
use App\Competition\Domain\Repository\CompetitionRepository;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

final class CreateCompetitionThroughMessageBusTest extends KernelTestCase
{
    #[Test]
    public function it_persists_a_competition_when_the_command_is_dispatched_through_the_message_bus(): void
    {
        $container = self::getContainer();

        $envelope = $container->get(MessageBusInterface::class)->dispatch(
            new CreateCompetitionCommand('Summer Cup', 2, 4, CompetitionFormat::SingleElimination->value, false),
        );

        $id = $envelope->last(HandledStamp::class)->getResult();

        $competition = $container->get(CompetitionRepository::class)->ofId($id);

        self::assertNotNull($competition);
        self::assertTrue($competition->isOpenForRegistration());
    }
}

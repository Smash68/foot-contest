<?php

declare(strict_types=1);

namespace App\Competition\Infrastructure\Persistence\Doctrine;

use App\Competition\Domain\Model\Player;
use App\Competition\Domain\Model\PlayerId;
use App\Competition\Domain\Repository\PlayerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePlayerRepository implements PlayerRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function nextIdentity(): PlayerId
    {
        return new PlayerId(Uuid::v7()->toRfc4122());
    }

    public function save(Player $player): void
    {
        $this->entityManager->persist($player);
        $this->entityManager->flush();
    }

    public function ofId(PlayerId $id): ?Player
    {
        return $this->entityManager->find(Player::class, $id);
    }

    public function ofEmail(string $email): ?Player
    {
        return $this->entityManager->getRepository(Player::class)->findOneBy(['email' => $email]);
    }
}

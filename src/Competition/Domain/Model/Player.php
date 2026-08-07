<?php

declare(strict_types=1);

namespace App\Competition\Domain\Model;

final class Player
{
    private function __construct(
        private readonly PlayerId $id,
        private readonly string $name,
        private readonly string $email,
        private readonly string $hashedPassword,
    ) {
    }

    public static function register(PlayerId $id, string $name, string $email, string $hashedPassword): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Player email must be a valid email address.');
        }

        return new self($id, $name, $email, $hashedPassword);
    }

    public function getId(): PlayerId
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getHashedPassword(): string
    {
        return $this->hashedPassword;
    }
}

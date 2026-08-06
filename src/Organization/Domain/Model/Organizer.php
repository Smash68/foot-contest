<?php

declare(strict_types=1);

namespace App\Organization\Domain\Model;

final class Organizer
{
    private function __construct(
        private readonly OrganizerId $id,
        private readonly string $email,
        private readonly string $hashedPassword,
    ) {
    }

    public static function register(OrganizerId $id, string $email, string $hashedPassword): self
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Organizer email must be a valid email address.');
        }

        return new self($id, $email, $hashedPassword);
    }

    public function getId(): OrganizerId
    {
        return $this->id;
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

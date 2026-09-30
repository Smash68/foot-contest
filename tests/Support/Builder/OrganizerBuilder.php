<?php

declare(strict_types=1);

namespace App\Tests\Support\Builder;

use App\Organization\Domain\Model\Organizer;
use App\Organization\Domain\Model\OrganizerId;

final class OrganizerBuilder
{
    private string $id = 'organizer-1';
    private ?string $email = null;
    private string $hashedPassword = 'hashed-password';

    public static function anOrganizer(): self
    {
        return new self();
    }

    /** The email defaults to "{id}@example.com"; pass one when the test looks the organizer up by email. */
    public function withEmail(string $email): self
    {
        $clone = clone $this;
        $clone->email = $email;

        return $clone;
    }

    public function build(): Organizer
    {
        return Organizer::register(new OrganizerId($this->id), $this->email ?? "{$this->id}@example.com", $this->hashedPassword);
    }
}
<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261005161948 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Effectif minimum par équipe ; 1 (aucune contrainte) pour les compétitions existantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competition ADD minimum_roster_size INT NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE competition ALTER minimum_roster_size DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competition DROP minimum_roster_size');
    }
}

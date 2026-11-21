<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261121100635 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fin du tournoi ; non terminé pour les compétitions existantes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competition ADD finished BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE competition ALTER finished DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE competition DROP finished');
    }
}

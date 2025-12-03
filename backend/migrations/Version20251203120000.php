<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251203120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add index for slot_hold.expires_at to speed up cleanups';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE INDEX slot_hold_expires_idx ON slot_hold (expires_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX slot_hold_expires_idx ON slot_hold');
    }
}


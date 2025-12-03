<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251203121500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prevent concurrent holds on the same provider/start time';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT uniq_slot_hold_provider_start UNIQUE (provider_id, start_date_time)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE slot_hold DROP INDEX uniq_slot_hold_provider_start');
    }
}


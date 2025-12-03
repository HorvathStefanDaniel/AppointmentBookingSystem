<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251202190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add slot_hold table for temporary booking reservations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE slot_hold (id INT AUTO_INCREMENT NOT NULL, provider_id INT NOT NULL, service_id INT NOT NULL, user_id INT NOT NULL, start_date_time DATETIME NOT NULL, end_date_time DATETIME NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, INDEX slot_hold_provider_time_idx (provider_id, start_date_time, end_date_time), INDEX IDX_40D2431EA53A8AA (provider_id), INDEX IDX_40D2431EED5CA9E6 (service_id), INDEX IDX_40D2431EA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT FK_40D2431EA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id)');
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT FK_40D2431EED5CA9E6 FOREIGN KEY (service_id) REFERENCES service (id)');
        $this->addSql('ALTER TABLE slot_hold ADD CONSTRAINT FK_40D2431EA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE slot_hold');
    }
}


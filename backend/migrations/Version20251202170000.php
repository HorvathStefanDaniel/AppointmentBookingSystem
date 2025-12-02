<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251202170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Decouple services from providers and link bookings directly to providers.';
    }

    public function up(Schema $schema): void
    {
        // Add provider reference to bookings.
        $this->addSql('ALTER TABLE booking ADD provider_id INT DEFAULT NULL');
        $this->addSql('UPDATE booking b SET provider_id = (SELECT s.provider_id FROM service s WHERE s.id = b.service_id)');
        $this->addSql('ALTER TABLE booking MODIFY provider_id INT NOT NULL');
        $this->addSql('ALTER TABLE booking ADD CONSTRAINT FK_E00CEDDEA53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id)');
        $this->addSql('CREATE INDEX IDX_E00CEDDEA53A8AA ON booking (provider_id)');
        $this->addSql('CREATE INDEX booking_provider_start_idx ON booking (provider_id, start_date_time)');

        // Drop provider FK from services.
        $this->addSql('ALTER TABLE service DROP FOREIGN KEY FK_E19D9AD2A53A8AA');
        $this->addSql('DROP INDEX IDX_E19D9AD2A53A8AA ON service');
        $this->addSql('ALTER TABLE service DROP provider_id');
    }

    public function down(Schema $schema): void
    {
        // Reintroduce provider reference on services.
        $this->addSql('ALTER TABLE service ADD provider_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_E19D9AD2A53A8AA ON service (provider_id)');
        $this->addSql('ALTER TABLE service ADD CONSTRAINT FK_E19D9AD2A53A8AA FOREIGN KEY (provider_id) REFERENCES provider (id)');

        // Remove provider reference from bookings.
        $this->addSql('ALTER TABLE booking DROP FOREIGN KEY FK_E00CEDDEA53A8AA');
        $this->addSql('DROP INDEX booking_provider_start_idx ON booking');
        $this->addSql('DROP INDEX IDX_E00CEDDEA53A8AA ON booking');
        $this->addSql('ALTER TABLE booking DROP provider_id');
    }
}


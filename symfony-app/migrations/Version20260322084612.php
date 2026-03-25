<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322084612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE events ADD created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE events ADD created_by_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE events ADD CONSTRAINT FK_5387574AB03A8386 FOREIGN KEY (created_by_id) REFERENCES "admin" (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_5387574AB03A8386 ON events (created_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events DROP CONSTRAINT FK_5387574AB03A8386');
        $this->addSql('DROP INDEX IDX_5387574AB03A8386');
        $this->addSql('ALTER TABLE events DROP updated_at');
        $this->addSql('ALTER TABLE events DROP created_at');
        $this->addSql('ALTER TABLE events DROP created_by_id');
    }
}

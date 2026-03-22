<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322085811 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT fk_4da23971f7e88b');
        $this->addSql('ALTER TABLE reservations ADD status VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE reservations ADD user_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT FK_4DA23971F7E88B FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT FK_4DA239A76ED395 FOREIGN KEY (user_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_4DA239A76ED395 ON reservations (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT FK_4DA23971F7E88B');
        $this->addSql('ALTER TABLE reservations DROP CONSTRAINT FK_4DA239A76ED395');
        $this->addSql('DROP INDEX IDX_4DA239A76ED395');
        $this->addSql('ALTER TABLE reservations DROP status');
        $this->addSql('ALTER TABLE reservations DROP user_id');
        $this->addSql('ALTER TABLE reservations ADD CONSTRAINT fk_4da23971f7e88b FOREIGN KEY (event_id) REFERENCES events (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}

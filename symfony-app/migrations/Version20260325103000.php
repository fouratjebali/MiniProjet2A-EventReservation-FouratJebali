<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260325103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add email verification flag to user table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" ADD is_verified BOOLEAN DEFAULT FALSE NOT NULL");
        $this->addSql("UPDATE \"user\" SET is_verified = TRUE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE \"user\" DROP is_verified");
    }
}

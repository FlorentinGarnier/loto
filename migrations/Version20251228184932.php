<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228184932 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD is_blocked BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE card ADD blocked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD blocked_reason VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE game ADD is_frozen BOOLEAN DEFAULT false NOT NULL');
        $this->addSql('ALTER TABLE game ADD freeze_order_index INT DEFAULT NULL');
        $this->addSql('ALTER TABLE winner ADD winning_order_index INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card DROP is_blocked');
        $this->addSql('ALTER TABLE card DROP blocked_at');
        $this->addSql('ALTER TABLE card DROP blocked_reason');
        $this->addSql('ALTER TABLE game DROP is_frozen');
        $this->addSql('ALTER TABLE game DROP freeze_order_index');
        $this->addSql('ALTER TABLE winner DROP winning_order_index');
    }
}

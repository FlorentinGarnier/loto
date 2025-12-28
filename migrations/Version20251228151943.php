<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251228151943 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card DROP CONSTRAINT fk_161498d371f7e88b');
        $this->addSql('DROP INDEX idx_161498d371f7e88b');
        $this->addSql('ALTER TABLE card DROP event_id');
        $this->addSql('ALTER TABLE player ADD event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A6571F7E88B FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_98197A6571F7E88B ON player (event_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card ADD event_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT fk_161498d371f7e88b FOREIGN KEY (event_id) REFERENCES event (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('CREATE INDEX idx_161498d371f7e88b ON card (event_id)');
        $this->addSql('ALTER TABLE player DROP CONSTRAINT FK_98197A6571F7E88B');
        $this->addSql('DROP INDEX IDX_98197A6571F7E88B');
        $this->addSql('ALTER TABLE player DROP event_id');
    }
}

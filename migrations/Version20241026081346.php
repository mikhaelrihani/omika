<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241026081346 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_info (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', user_read_info_count INT NOT NULL, shared_with_count INT NOT NULL, is_fully_read TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event ADD info_id INT DEFAULT NULL, DROP user_read_info_count, DROP shared_with_count, DROP is_fully_read');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA75D8BC1F8 FOREIGN KEY (info_id) REFERENCES event_info (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_3BAE0AA75D8BC1F8 ON event (info_id)');
        $this->addSql('ALTER TABLE event_user_share DROP FOREIGN KEY FK_552ABF2D71F7E88B');
        $this->addSql('DROP INDEX IDX_552ABF2D71F7E88B ON event_user_share');
        $this->addSql('DROP INDEX `primary` ON event_user_share');
        $this->addSql('ALTER TABLE event_user_share CHANGE event_id event_info_id INT NOT NULL');
        $this->addSql('ALTER TABLE event_user_share ADD CONSTRAINT FK_552ABF2DD8DC6857 FOREIGN KEY (event_info_id) REFERENCES event_info (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_552ABF2DD8DC6857 ON event_user_share (event_info_id)');
        $this->addSql('ALTER TABLE event_user_share ADD PRIMARY KEY (event_info_id, user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA75D8BC1F8');
        $this->addSql('ALTER TABLE event_user_share DROP FOREIGN KEY FK_552ABF2DD8DC6857');
        $this->addSql('DROP TABLE event_info');
        $this->addSql('DROP INDEX UNIQ_3BAE0AA75D8BC1F8 ON event');
        $this->addSql('ALTER TABLE event ADD user_read_info_count INT NOT NULL, ADD shared_with_count INT NOT NULL, ADD is_fully_read TINYINT(1) NOT NULL, DROP info_id');
        $this->addSql('DROP INDEX IDX_552ABF2DD8DC6857 ON event_user_share');
        $this->addSql('DROP INDEX `PRIMARY` ON event_user_share');
        $this->addSql('ALTER TABLE event_user_share CHANGE event_info_id event_id INT NOT NULL');
        $this->addSql('ALTER TABLE event_user_share ADD CONSTRAINT FK_552ABF2D71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_552ABF2D71F7E88B ON event_user_share (event_id)');
        $this->addSql('ALTER TABLE event_user_share ADD PRIMARY KEY (event_id, user_id)');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240907123909 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE refresh_tokens ADD id INT AUTO_INCREMENT NOT NULL, CHANGE username username VARCHAR(255) NOT NULL, DROP PRIMARY KEY, ADD PRIMARY KEY (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens (refresh_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE refresh_tokens MODIFY id INT NOT NULL');
        $this->addSql('DROP INDEX UNIQ_9BACE7E1C74F2195 ON refresh_tokens');
        $this->addSql('DROP INDEX `PRIMARY` ON refresh_tokens');
        $this->addSql('ALTER TABLE refresh_tokens DROP id, CHANGE username username VARCHAR(180) NOT NULL');
        $this->addSql('ALTER TABLE refresh_tokens ADD PRIMARY KEY (refresh_token)');
    }
}

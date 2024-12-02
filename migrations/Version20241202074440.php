<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241202074440 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact ADD avatar_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63886383B10 FOREIGN KEY (avatar_id) REFERENCES picture (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4C62E63886383B10 ON contact (avatar_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63886383B10');
        $this->addSql('DROP INDEX UNIQ_4C62E63886383B10 ON contact');
        $this->addSql('ALTER TABLE contact DROP avatar_id');
    }
}

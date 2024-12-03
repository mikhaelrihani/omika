<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241203164419 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63886383B10');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63886383B10 FOREIGN KEY (avatar_id) REFERENCES picture (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact DROP FOREIGN KEY FK_4C62E63886383B10');
        $this->addSql('ALTER TABLE contact ADD CONSTRAINT FK_4C62E63886383B10 FOREIGN KEY (avatar_id) REFERENCES picture (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240905075525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue DROP INDEX UNIQ_12AD233ED5AFAE6D, ADD INDEX IDX_12AD233ED5AFAE6D (technician_contacted_id)');
        $this->addSql('ALTER TABLE issue DROP INDEX UNIQ_12AD233E5E1744C5, ADD INDEX IDX_12AD233E5E1744C5 (technician_coming_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue DROP INDEX IDX_12AD233ED5AFAE6D, ADD UNIQUE INDEX UNIQ_12AD233ED5AFAE6D (technician_contacted_id)');
        $this->addSql('ALTER TABLE issue DROP INDEX IDX_12AD233E5E1744C5, ADD UNIQUE INDEX UNIQ_12AD233E5E1744C5 (technician_coming_id)');
    }
}

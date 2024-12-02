<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241202082759 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233E5E1744C5');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233ED5AFAE6D');
        $this->addSql('ALTER TABLE issue CHANGE technician_contacted_id technician_contacted_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233E5E1744C5 FOREIGN KEY (technician_coming_id) REFERENCES contact (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233ED5AFAE6D FOREIGN KEY (technician_contacted_id) REFERENCES contact (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233ED5AFAE6D');
        $this->addSql('ALTER TABLE issue DROP FOREIGN KEY FK_12AD233E5E1744C5');
        $this->addSql('ALTER TABLE issue CHANGE technician_contacted_id technician_contacted_id INT NOT NULL');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233ED5AFAE6D FOREIGN KEY (technician_contacted_id) REFERENCES contact (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE issue ADD CONSTRAINT FK_12AD233E5E1744C5 FOREIGN KEY (technician_coming_id) REFERENCES contact (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}

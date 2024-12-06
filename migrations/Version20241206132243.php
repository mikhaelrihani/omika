<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241206132243 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE category (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE category_supplier (category_id INT NOT NULL, supplier_id INT NOT NULL, INDEX IDX_2C50E80512469DE2 (category_id), INDEX IDX_2C50E8052ADD6D8C (supplier_id), PRIMARY KEY(category_id, supplier_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE category_supplier ADD CONSTRAINT FK_2C50E80512469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE category_supplier ADD CONSTRAINT FK_2C50E8052ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE category_supplier DROP FOREIGN KEY FK_2C50E80512469DE2');
        $this->addSql('ALTER TABLE category_supplier DROP FOREIGN KEY FK_2C50E8052ADD6D8C');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE category_supplier');
    }
}

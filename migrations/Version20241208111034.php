<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241208111034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB4584665A');
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB9EEA759');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB4584665A');
        $this->addSql('ALTER TABLE product_inventory DROP FOREIGN KEY FK_DF8DFCBB9EEA759');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE product_inventory ADD CONSTRAINT FK_DF8DFCBB9EEA759 FOREIGN KEY (inventory_id) REFERENCES inventory (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}

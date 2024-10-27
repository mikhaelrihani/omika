<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241027085859 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE delivery_day (id INT AUTO_INCREMENT NOT NULL, day INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE order_day (id INT AUTO_INCREMENT NOT NULL, day INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supplier_order_day (supplier_id INT NOT NULL, order_day_id INT NOT NULL, INDEX IDX_3CC95C2E2ADD6D8C (supplier_id), INDEX IDX_3CC95C2ECA4D51F3 (order_day_id), PRIMARY KEY(supplier_id, order_day_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE supplier_delivery_day (supplier_id INT NOT NULL, delivery_day_id INT NOT NULL, INDEX IDX_A866D56D2ADD6D8C (supplier_id), INDEX IDX_A866D56D17D3B8A8 (delivery_day_id), PRIMARY KEY(supplier_id, delivery_day_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supplier_order_day ADD CONSTRAINT FK_3CC95C2E2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_order_day ADD CONSTRAINT FK_3CC95C2ECA4D51F3 FOREIGN KEY (order_day_id) REFERENCES order_day (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_delivery_day ADD CONSTRAINT FK_A866D56D2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_delivery_day ADD CONSTRAINT FK_A866D56D17D3B8A8 FOREIGN KEY (delivery_day_id) REFERENCES delivery_day (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier DROP order_days, DROP delivery_days');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE supplier_order_day DROP FOREIGN KEY FK_3CC95C2E2ADD6D8C');
        $this->addSql('ALTER TABLE supplier_order_day DROP FOREIGN KEY FK_3CC95C2ECA4D51F3');
        $this->addSql('ALTER TABLE supplier_delivery_day DROP FOREIGN KEY FK_A866D56D2ADD6D8C');
        $this->addSql('ALTER TABLE supplier_delivery_day DROP FOREIGN KEY FK_A866D56D17D3B8A8');
        $this->addSql('DROP TABLE delivery_day');
        $this->addSql('DROP TABLE order_day');
        $this->addSql('DROP TABLE supplier_order_day');
        $this->addSql('DROP TABLE supplier_delivery_day');
        $this->addSql('ALTER TABLE supplier ADD order_days JSON NOT NULL, ADD delivery_days JSON NOT NULL');
    }
}

<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241208110745 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredient DROP FOREIGN KEY FK_6BAF78704584665A');
        $this->addSql('DROP INDEX IDX_6BAF78704584665A ON ingredient');
        $this->addSql('ALTER TABLE ingredient ADD name VARCHAR(255) NOT NULL, DROP product_id');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D4584665A');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D54177093');
        $this->addSql('ALTER TABLE room_product CHANGE room_id room_id INT NOT NULL');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D54177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE ingredient ADD product_id INT NOT NULL, DROP name');
        $this->addSql('ALTER TABLE ingredient ADD CONSTRAINT FK_6BAF78704584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('CREATE INDEX IDX_6BAF78704584665A ON ingredient (product_id)');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D54177093');
        $this->addSql('ALTER TABLE room_product DROP FOREIGN KEY FK_3F68B84D4584665A');
        $this->addSql('ALTER TABLE room_product CHANGE room_id room_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D54177093 FOREIGN KEY (room_id) REFERENCES room (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
        $this->addSql('ALTER TABLE room_product ADD CONSTRAINT FK_3F68B84D4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON UPDATE NO ACTION ON DELETE NO ACTION');
    }
}

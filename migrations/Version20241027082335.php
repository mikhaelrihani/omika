<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241027082335 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE supplier_recurringEventChildren (supplier_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_858C1B2C2ADD6D8C (supplier_id), INDEX IDX_858C1B2C71F7E88B (event_id), PRIMARY KEY(supplier_id, event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE supplier_recurringEventChildren ADD CONSTRAINT FK_858C1B2C2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_recurringEventChildren ADD CONSTRAINT FK_858C1B2C71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_event DROP FOREIGN KEY FK_E2B5088D2ADD6D8C');
        $this->addSql('ALTER TABLE supplier_event DROP FOREIGN KEY FK_E2B5088D71F7E88B');
        $this->addSql('DROP TABLE supplier_event');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE supplier_event (supplier_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_E2B5088D2ADD6D8C (supplier_id), INDEX IDX_E2B5088D71F7E88B (event_id), PRIMARY KEY(supplier_id, event_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE supplier_event ADD CONSTRAINT FK_E2B5088D2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES supplier (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_event ADD CONSTRAINT FK_E2B5088D71F7E88B FOREIGN KEY (event_id) REFERENCES event (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE supplier_recurringEventChildren DROP FOREIGN KEY FK_858C1B2C2ADD6D8C');
        $this->addSql('ALTER TABLE supplier_recurringEventChildren DROP FOREIGN KEY FK_858C1B2C71F7E88B');
        $this->addSql('DROP TABLE supplier_recurringEventChildren');
    }
}

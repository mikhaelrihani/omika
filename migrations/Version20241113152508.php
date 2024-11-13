<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241113152508 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE event_recurring_user (event_recurring_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_FD3ACD2B5C5AC3AD (event_recurring_id), INDEX IDX_FD3ACD2BA76ED395 (user_id), PRIMARY KEY(event_recurring_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE event_recurring_user ADD CONSTRAINT FK_FD3ACD2B5C5AC3AD FOREIGN KEY (event_recurring_id) REFERENCES event_recurring (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE event_recurring_user ADD CONSTRAINT FK_FD3ACD2BA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE event_recurring_user DROP FOREIGN KEY FK_FD3ACD2B5C5AC3AD');
        $this->addSql('ALTER TABLE event_recurring_user DROP FOREIGN KEY FK_FD3ACD2BA76ED395');
        $this->addSql('DROP TABLE event_recurring_user');
    }
}

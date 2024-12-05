<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20241205105231 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE note_user (note_id INT NOT NULL, user_id INT NOT NULL, INDEX IDX_2DE9C71126ED0855 (note_id), INDEX IDX_2DE9C711A76ED395 (user_id), PRIMARY KEY(note_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE note_user ADD CONSTRAINT FK_2DE9C71126ED0855 FOREIGN KEY (note_id) REFERENCES note (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE note_user ADD CONSTRAINT FK_2DE9C711A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX absence_search_idx ON absence (status, user_id, contact_id, start_date, end_date)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE note_user DROP FOREIGN KEY FK_2DE9C71126ED0855');
        $this->addSql('ALTER TABLE note_user DROP FOREIGN KEY FK_2DE9C711A76ED395');
        $this->addSql('DROP TABLE note_user');
        $this->addSql('DROP INDEX absence_search_idx ON absence');
    }
}
